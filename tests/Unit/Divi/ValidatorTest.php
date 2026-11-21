<?php
/**
 * Validator port: parity with the skill's validate.py.
 *
 * tests/golden/validator/<name>.json are layout exports; build_validator_fixtures.py
 * ran validate.py's validate_doc on each and wrote <name>.expected.json. The PHP
 * report must match it exactly: same messages, same classification, same order.
 * Both sides use the bundled catalog (knowledge/module-catalog.json, a byte copy of
 * the skill's), passed explicitly here; nocatalog_* fixtures run with no catalog.
 *
 * @package WPDM
 */

use WPDM\Divi\Catalog;
use WPDM\Divi\PyJson;
use WPDM\Divi\PyJsonError;
use WPDM\Divi\PyList;
use WPDM\Divi\Report;
use WPDM\Divi\Validator;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ValidatorTest extends TestCase {

	/**
	 * @return string
	 */
	private static function dir() {
		return dirname( __DIR__, 2 ) . '/golden/validator';
	}

	/**
	 * @return array
	 */
	private static function bundled() {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/knowledge/module-catalog.json' ), true );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function fixture_provider() {
		$out = array();
		foreach ( glob( self::dir() . '/*.json' ) as $file ) {
			if ( '.expected.json' === substr( $file, -14 ) ) {
				continue;
			}
			$name         = basename( $file, '.json' );
			$out[ $name ] = array( $name );
		}
		$this->assertNotEmpty( $out );
		return $out;
	}

	/**
	 * @param string $name Fixture name.
	 * @return array{0: array, 1: array}
	 */
	private function expected( $name ) {
		$file = self::dir() . '/' . $name . '.expected.json';
		$this->assertFileExists( $file, 'Run python3 -I tests/golden/build_validator_fixtures.py' );
		$catalog = 0 === strpos( $name, 'nocatalog_' ) ? array( 'modules' => array() ) : self::bundled();
		return array( json_decode( (string) file_get_contents( $file ), true ), $catalog );
	}

	/**
	 * @param array  $expected Expected report.
	 * @param Report $report   Report.
	 */
	private function assert_report( array $expected, Report $report ) {
		$this->assertSame( $expected['errors'], $report->errors, 'errors' );
		$this->assertSame( $expected['warnings'], $report->warnings, 'warnings' );
		$this->assertSame( $expected['info'], $report->info, 'info' );
		$this->assertSame( $expected['counts'], $report->counts, 'counts' );
	}

	/**
	 * @dataProvider fixture_provider
	 *
	 * @param string $name Fixture name.
	 */
	public function test_parity_with_python_validator( $name ) {
		list( $expected, $catalog ) = $this->expected( $name );
		$doc                        = json_decode( (string) file_get_contents( self::dir() . '/' . $name . '.json' ) );
		$this->assert_report( $expected, Validator::validate_doc( $doc, $catalog, false ) );
	}

	/**
	 * The MCP layer hands over json_decode(..., true) arrays; the fixtures carry no
	 * empty {} / [] whose meaning that loses, so the reports must be the same.
	 *
	 * @dataProvider fixture_provider
	 *
	 * @param string $name Fixture name.
	 */
	public function test_parity_with_assoc_array_input( $name ) {
		list( $expected, $catalog ) = $this->expected( $name );
		$doc                        = json_decode( (string) file_get_contents( self::dir() . '/' . $name . '.json' ), true );
		$this->assert_report( $expected, Validator::validate_doc( $doc, $catalog, false ) );
	}

	public function test_validate_raw_markup() {
		$markup = '<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.19.1"} -->'
			. "\n" . '<!-- wp:divi/row {"builderVersion":"5.19.1"} --><!-- wp:divi/column {"builderVersion":"5.19.1"} -->'
			. "\n" . '<!-- wp:divi/fancy {"builderVersion":"5.19.1"} /-->'
			. "\n" . '<!-- wp:divi/button {"button":{"innerContent":{"desktop":{"value":{"text":"Go"}}}},"builderVersion":"5.19.1"} /-->'
			. "\n" . '<!-- /wp:divi/column --><!-- /wp:divi/row -->';

		$report = Validator::validate_content( $markup, self::bundled() );

		$this->assertFalse( $report->ok() );
		$this->assertSame(
			array(
				'content line 3 divi/fancy: unknown Divi block name (not in Divi 5.11.0 catalog)',
				'content: divi/section is never closed',
				'content: divi/placeholder is never closed',
			),
			$report->errors
		);
		$this->assertSame( array(), $report->warnings );
		$this->assertSame( array( 'content line 4 divi/button: button without explicit padding renders thin; prefer link() / patterns.pill_link' ), $report->info );
		$this->assertSame(
			array(
				'divi/placeholder' => 1,
				'divi/section'     => 1,
				'divi/row'         => 1,
				'divi/column'      => 1,
				'divi/fancy'       => 1,
				'divi/button'      => 1,
			),
			$report->counts
		);
		$this->assertSame(
			array( 'errors', 'warnings', 'info', 'counts', 'ok' ),
			array_keys( $report->to_array() )
		);
		$this->assertFalse( $report->to_array()['ok'] );
	}

	public function test_validate_raw_markup_ok_and_empty() {
		$ok = Validator::validate_content( '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->', self::bundled() );
		$this->assertTrue( $ok->ok() );
		$this->assertSame( array( 'divi/placeholder' => 1 ), $ok->counts );

		$empty = Validator::validate_content( "  \n", self::bundled() );
		$this->assertSame( array( 'content: empty post content' ), $empty->errors );
	}

	public function test_invalid_utf8_markup_does_not_break_scanning() {
		$report = Validator::validate_content( "<!-- wp:divi/placeholder -->\xff<!-- /wp:divi/placeholder -->", self::bundled() );
		$this->assertSame( array(), $report->errors );
		$this->assertSame( array( "content: stray text between blocks inside divi/placeholder: '\u{fffd}'" ), $report->warnings );
	}

	public function test_catalog_falls_back_to_bundled() {
		Catalog::reset();
		add_filter( 'wpdm_catalog_theme_dir', '__return_empty_string' );
		try {
			$catalog = Catalog::load();
		} finally {
			remove_filter( 'wpdm_catalog_theme_dir', '__return_empty_string' );
			Catalog::reset();
		}
		$this->assertSame( self::bundled(), $catalog );
		$this->assertSame( '5.11.0', $catalog['diviVersion'] );
		$this->assertArrayHasKey( 'divi/text', $catalog['modules'] );
	}

	public function test_validate_doc_without_catalog_argument_uses_catalog_load() {
		$doc    = json_decode( (string) file_get_contents( self::dir() . '/good_cta_dark.json' ), true );
		$report = Validator::validate_doc( $doc );
		$this->assertTrue( $report->ok() );
	}

	/**
	 * Task 10 carry: `{"fa": ...}` instead of `{"$fa": ...}` in a layout tree turns
	 * into a literal (invalid) icon value. Strict tree hints flag it.
	 */
	public function test_strict_tree_hints_flag_unprefixed_icon_helpers() {
		$markup = '<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.19.1"} -->'
			. '<!-- wp:divi/row {"builderVersion":"5.19.1"} --><!-- wp:divi/column {"builderVersion":"5.19.1"} -->'
			. '<!-- wp:divi/icon {"icon":{"innerContent":{"desktop":{"value":{"fa":"&#xf0e7;"}}}},"builderVersion":"5.19.1"} /-->'
			. '<!-- wp:divi/icon {"icon":{"innerContent":{"desktop":{"value":{"unicode":"&#xf0e7;","type":"fa","weight":"900"}}}},"builderVersion":"5.19.1"} /-->'
			. '<!-- wp:divi/link {"content":{"innerContent":{"desktop":{"value":{"text":"Go"}}}},"icon":{"innerContent":{"rv":"&#xe00b;"}},"builderVersion":"5.19.1"} /-->'
			. '<!-- wp:divi/blurb {"imageIcon":{"innerContent":{"desktop":{"value":{"useIcon":"on","icon":{"divi_icon":"&#xe00b;"}}}}},"builderVersion":"5.19.1"} /-->'
			. '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';

		$plain = Validator::validate_content( $markup, self::bundled() );
		$this->assertSame( array(), $plain->warnings );

		$strict = Validator::validate_content( $markup, self::bundled(), true );
		$this->assertSame(
			array(
				"content line 1 divi/icon: icon value 'icon.innerContent.desktop.value' has the key 'fa' — that is an unprefixed value helper, write {\"\$fa\": ...} in the layout tree",
				"content line 1 divi/link: icon value 'icon.innerContent' has the key 'rv' — that is an unprefixed value helper, write {\"\$rv\": ...} in the layout tree",
				"content line 1 divi/blurb: icon value 'imageIcon.innerContent.desktop.value.icon' has the key 'divi_icon' — that is an unprefixed value helper, write {\"\$divi_icon\": ...} in the layout tree",
			),
			$strict->warnings
		);
	}

	public function test_report_shape() {
		$report = new Report();
		$this->assertTrue( $report->ok() );
		$report->warn( 'w' );
		$report->note( 'i' );
		$this->assertTrue( $report->ok() );
		$report->err( 'e' );
		$this->assertFalse( $report->ok() );
		$this->assertSame(
			array(
				'errors'   => array( 'e' ),
				'warnings' => array( 'w' ),
				'info'     => array( 'i' ),
				'counts'   => array(),
				'ok'       => false,
			),
			$report->to_array()
		);
	}

	/**
	 * Messages and positions captured from Python 3.14's json.loads.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function json_error_provider() {
		return array(
			'open brace'         => array( '{', 'Expecting property name enclosed in double quotes: line 1 column 2 (char 1)' ),
			'no colon'           => array( '{"a"', "Expecting ':' delimiter: line 1 column 5 (char 4)" ),
			'no value'           => array( '{"a":', 'Expecting value: line 1 column 6 (char 5)' ),
			'no comma'           => array( '{"a":1 "b":2}', "Expecting ',' delimiter: line 1 column 8 (char 7)" ),
			'trailing comma obj' => array( '{"a":1,  }', 'Illegal trailing comma before end of object: line 1 column 7 (char 6)' ),
			'trailing comma arr' => array( '{"a":[1,]}', 'Illegal trailing comma before end of array: line 1 column 8 (char 7)' ),
			'double comma'       => array( '{"a":1,,}', 'Expecting property name enclosed in double quotes: line 1 column 8 (char 7)' ),
			'unterminated'       => array( '{"a":"x', 'Unterminated string starting at: line 1 column 6 (char 5)' ),
			'control char'       => array( "{\"a\":\"x\ny\"}", 'Invalid control character at: line 1 column 8 (char 7)' ),
			'bad escape'         => array( '{"a":"\\q"}', 'Invalid \\escape: line 1 column 7 (char 6)' ),
			'bad u escape'       => array( '{"a":"\\u12g4"}', 'Invalid \\uXXXX escape: line 1 column 8 (char 7)' ),
			'short u escape'     => array( '{"a":"\\u12"}', 'Invalid \\uXXXX escape: line 1 column 8 (char 7)' ),
			'extra data'         => array( '  {}  x', 'Extra data: line 1 column 7 (char 6)' ),
			'leading zero'       => array( '{"a":01}', "Expecting ',' delimiter: line 1 column 7 (char 6)" ),
			'dangling exponent'  => array( '{"a":1e+}', "Expecting ',' delimiter: line 1 column 7 (char 6)" ),
			'lone minus'         => array( '[-]', 'Expecting value: line 1 column 2 (char 1)' ),
			'partial word'       => array( '{"a":tru}', 'Expecting value: line 1 column 6 (char 5)' ),
			'char not byte'      => array( '{"é":é}', 'Expecting value: line 1 column 6 (char 5)' ),
			'second line'        => array( "{\"a\":1,\n \"b\" 2}", "Expecting ':' delimiter: line 2 column 6 (char 13)" ),
			'surrogate at end'   => array( '"\\ud83d\\ude00', 'Invalid \\uXXXX escape: line 1 column 9 (char 8)' ),
			'bad low surrogate'  => array( '"\\ud83d\\uzzzz"', 'Invalid \\uXXXX escape: line 1 column 9 (char 8)' ),
			'escape at end'      => array( '"\\ud83d\\', 'Unterminated string starting at: line 1 column 1 (char 0)' ),
		);
	}

	/**
	 * @dataProvider json_error_provider
	 *
	 * @param string $json    Input.
	 * @param string $message Python's message.
	 */
	public function test_json_errors_match_python( $json, $message ) {
		$this->expectException( PyJsonError::class );
		$this->expectExceptionMessage( $message );
		PyJson::loads( $json );
	}

	public function test_json_values_match_python() {
		$value = PyJson::loads( '{"":1, "":2, "b":[1.5,-0,1e2,NaN,-Infinity,true,null], "s":"\\/\\b\\u00e9\\ud834\\udd1e", "e":{}, "l":[]}' );
		$this->assertSame( array( '', 'b', 's', 'e', 'l' ), array_map( 'strval', array_keys( $value ) ) );
		$this->assertSame( 2, $value[''] );
		$this->assertInstanceOf( PyList::class, $value['b'] );
		$this->assertSame( 1.5, $value['b']->items[0] );
		$this->assertSame( 0, $value['b']->items[1] );
		$this->assertSame( 100.0, $value['b']->items[2] );
		$this->assertNan( $value['b']->items[3] );
		$this->assertSame( -INF, $value['b']->items[4] );
		$this->assertSame( "/\x08é\u{1d11e}", $value['s'] );
		$this->assertSame( array(), $value['e'] );
		$this->assertEquals( new PyList(), $value['l'] );
	}
}
