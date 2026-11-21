<?php
/**
 * Golden tests: the PHP port of divi5.py must reproduce the Python output byte for byte.
 *
 * tests/golden/cases.json lists helper calls; tests/golden/build_fixtures.py ran them
 * through the Python library and wrote tests/golden/fixtures/. This test runs the same
 * calls through WPDM\Divi\D5 with a tiny interpreter and compares the output.
 *
 * @package WPDM
 */

use WPDM\Divi\D5;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class GoldenTest extends TestCase {

	const BUILDER_VERSION = '5.19.1';

	/**
	 * Parameter names that collect the leftover keyword arguments.
	 */
	const OPTS_PARAMS = array( 'opts', 'more', 'groups' );

	public function set_up() {
		parent::set_up();
		D5::set_builder_version( self::BUILDER_VERSION );
	}

	public function tear_down() {
		D5::set_builder_version( self::BUILDER_VERSION );
		parent::tear_down();
	}

	/**
	 * @return array<string, array{0: object}>
	 */
	public function cases_provider() {
		$json  = file_get_contents( dirname( __DIR__, 2 ) . '/golden/cases.json' );
		$cases = json_decode( $json );
		if ( ! is_array( $cases ) ) {
			throw new RuntimeException( 'tests/golden/cases.json is not a JSON list' );
		}
		$out = array();
		foreach ( $cases as $case ) {
			$out[ $case->name ] = array( $case );
		}
		return $out;
	}

	/**
	 * @dataProvider cases_provider
	 *
	 * @param object $case Decoded case (objects kept as stdClass).
	 */
	public function test_case_matches_python( $case ) {
		$output = isset( $case->output ) ? $case->output : 'serialize';
		$ext    = 'json' === $output ? '.json' : '.html';
		$file   = dirname( __DIR__, 2 ) . '/golden/fixtures/' . $case->name . $ext;
		$this->assertFileExists( $file );
		$expected = file_get_contents( $file );

		$value = $this->run_case( $case );

		if ( 'serialize' === $output ) {
			$this->assertSame( $expected, D5::serialize( $value ) );
		} elseif ( 'string' === $output ) {
			$this->assertIsString( $value );
			$this->assertSame( $expected, $value );
		} else {
			// JSON outputs are compared structurally (decoded on both sides).
			$this->assertSame(
				json_decode( $expected, true ),
				json_decode( (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), true )
			);
		}
	}

	/**
	 * Every golden case must still be listed in cases.json (no orphan fixture).
	 */
	public function test_every_fixture_has_a_case() {
		$names = array_keys( $this->cases_provider() );
		foreach ( glob( dirname( __DIR__, 2 ) . '/golden/fixtures/*' ) as $file ) {
			$this->assertContains( pathinfo( $file, PATHINFO_FILENAME ), $names, $file );
		}
	}

	/**
	 * Interpret one case.
	 *
	 * @param object $case Case.
	 * @return mixed
	 */
	private function run_case( $case ) {
		$results = array();
		foreach ( $case->calls as $call ) {
			$args   = isset( $call->args ) ? $this->resolve( $call->args, $results ) : array();
			$kwargs = array();
			if ( isset( $call->kwargs ) ) {
				foreach ( get_object_vars( $call->kwargs ) as $key => $value ) {
					// Python's trailing underscore (radius_, open_, type_) is dropped in PHP.
					$kwargs[ rtrim( (string) $key, '_' ) ] = $this->resolve( $value, $results );
				}
			}
			$results[] = $this->call( $call->fn, $args, $kwargs );
		}
		$picked = isset( $case->result ) ? $case->result : count( $results ) - 1;
		if ( is_array( $picked ) ) {
			$value = array();
			foreach ( $picked as $index ) {
				$value[] = $results[ $index ];
			}
			return $value;
		}
		return $results[ $picked ];
	}

	/**
	 * Call D5::$fn mapping Python args/kwargs onto the PHP signature:
	 * kwargs that name a positional parameter fill that slot, the rest go into the
	 * trailing options array (or, for variadic helpers, a trailing array argument).
	 *
	 * @param string $fn     Helper name.
	 * @param array  $args   Positional args.
	 * @param array  $kwargs Keyword args.
	 * @return mixed
	 */
	private function call( $fn, array $args, array $kwargs ) {
		$this->assertTrue( method_exists( D5::class, $fn ), "D5::{$fn}() is missing" );
		$method = new ReflectionMethod( D5::class, $fn );
		$params = $method->getParameters();
		$last   = $params ? $params[ count( $params ) - 1 ] : null;

		if ( $last && $last->isVariadic() ) {
			$call = $args;
			if ( $kwargs ) {
				$call[] = $kwargs;
			}
			return call_user_func_array( array( D5::class, $fn ), $call );
		}

		$opts_param = ( $last && in_array( $last->getName(), self::OPTS_PARAMS, true ) ) ? $last : null;
		$positional = $opts_param ? array_slice( $params, 0, -1 ) : $params;
		$this->assertLessThanOrEqual( count( $positional ), count( $args ), "Too many positional args for {$fn}()" );

		$call = array();
		foreach ( $positional as $i => $param ) {
			$name = $param->getName();
			if ( array_key_exists( $i, $args ) ) {
				$call[] = $args[ $i ];
			} elseif ( array_key_exists( $name, $kwargs ) ) {
				$call[] = $kwargs[ $name ];
				unset( $kwargs[ $name ] );
			} elseif ( $param->isDefaultValueAvailable() ) {
				$call[] = $param->getDefaultValue();
			} else {
				$this->fail( "Missing argument {$name} for {$fn}()" );
			}
		}
		if ( $opts_param ) {
			$call[] = $kwargs;
		} else {
			$this->assertSame( array(), $kwargs, "Unexpected kwargs for {$fn}()" );
		}
		return call_user_func_array( array( D5::class, $fn ), $call );
	}

	/**
	 * Resolve {"$ref": n} and convert decoded JSON to the D5 conventions:
	 * non-empty objects become associative arrays, empty objects stay stdClass,
	 * and objects whose keys would collapse into a PHP list stay stdClass.
	 *
	 * @param mixed $value   Decoded JSON value.
	 * @param array $results Earlier call results.
	 * @return mixed
	 */
	private function resolve( $value, array $results ) {
		if ( $value instanceof stdClass ) {
			$vars = get_object_vars( $value );
			if ( array( '$ref' ) === array_keys( $vars ) ) {
				return $results[ $vars['$ref'] ];
			}
			if ( ! $vars ) {
				return new stdClass();
			}
			$out = array();
			foreach ( $vars as $key => $item ) {
				$out[ $key ] = $this->resolve( $item, $results );
			}
			return array_keys( $out ) === range( 0, count( $out ) - 1 ) ? (object) $out : $out;
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				$out[] = $this->resolve( $item, $results );
			}
			return $out;
		}
		return $value;
	}
}
