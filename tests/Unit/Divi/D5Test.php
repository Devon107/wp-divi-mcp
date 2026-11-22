<?php
/**
 * Unit tests for the Divi 5 builder library (WPDM\Divi\D5, WPDM\Divi\Block).
 *
 * @package WPDM
 */

use WPDM\Divi\Block;
use WPDM\Divi\D5;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class D5Test extends TestCase {

	const SPECIAL = 'Ñandú — «citas» & <b>x</b> -- 😀 \\ "q"';

	public function set_up() {
		parent::set_up();
		D5::set_builder_version( '5.19.1' );
	}

	public function test_default_builder_version_constant() {
		$this->assertSame( '5.19.1', D5::BUILDER_VERSION );
		$this->assertSame( '5.19.1', D5::builder_version() );
	}

	public function test_encode_matches_wordpress_serializer() {
		$a = array(
			'title'  => self::SPECIAL,
			'nested' => array( 'list' => array( 1, -2, 'x' ), 'flag' => true, 'none' => null ),
		);
		$this->assertSame( serialize_block_attributes( $a ), D5::encode_attrs( $a ) );

		$b      = D5::block( 'divi/text', $a, null, false );
		$parsed = parse_blocks( D5::serialize( $b ) );
		$this->assertSame( 'divi/text', $parsed[0]['blockName'] );
		$this->assertEquals( $a, $parsed[0]['attrs'] );
	}

	public function test_block_name_must_start_with_divi() {
		$this->expectException( InvalidArgumentException::class );
		new Block( 'core/paragraph', array() );
	}

	public function test_block_filters_null_children() {
		$child = D5::text( 'x' );
		$block = new Block( 'divi/group', array(), array( null, $child, null ) );
		$this->assertSame( array( $child ), $block->children );
		$this->assertNull( ( new Block( 'divi/text' ) )->children );
	}

	public function test_empty_object_stays_object() {
		$this->assertSame( '{"a":{},"b":[],"c":[{}]}', D5::encode_attrs( array( 'a' => new stdClass(), 'b' => array(), 'c' => array( new stdClass() ) ) ) );
		$this->assertSame(
			'{"module":{"advanced":{"htmlAttributes":{"desktop":{"value":{}}}}}}',
			D5::encode_attrs( D5::html_attrs() )
		);
		$this->assertSame( '{"module":{"decoration":{}}}', D5::encode_attrs( D5::module_deco( array() ) ) );
	}

	public function test_float_encoding_matches_python() {
		$cases = array(
			'1.5'                 => 1.5,
			'2.0'                 => 2.0,
			'1e-07'               => 1e-7,
			'0.0001'              => 0.0001,
			'1e+16'               => 1e16,
			'1234567890123456.0'  => 1234567890123456.0,
			'-0.0'                => -0.0,
			'0.1'                 => 0.1,
			'1.5e+300'            => 1.5e300,
			'123456789012345.67'  => 123456789012345.67,
			'5.960464477539063e-08' => 5.960464477539063e-08, // 2**-24: asymmetric rounding interval.
			'6.070840288205403e+82'  => 6.070840288205403e+82, // 0x5120000000000000.
		);
		foreach ( $cases as $expected => $float ) {
			$this->assertSame( (string) $expected, D5::encode_attrs( $float ), (string) $expected );
		}
	}

	public function test_unknown_option_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		D5::heading( 'x', array( 'levle' => 'h1' ) );
	}

	public function test_contact_form_generates_random_uuid_without_key() {
		$form = D5::contact_form();
		$id   = $form->attrs['module']['advanced']['uniqueId']['desktop']['value'];
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id );
		$this->assertNotSame( $id, D5::contact_form()->attrs['module']['advanced']['uniqueId']['desktop']['value'] );
	}

	public function test_contact_form_uuid5_is_stable() {
		// uuid.uuid5(uuid.NAMESPACE_URL, "https://example.com/contact") in Python.
		$form = D5::contact_form( array( 'unique_key' => 'https://example.com/contact' ) );
		$this->assertSame(
			'e215d50a-fd85-5564-947a-0632f4dea75c',
			$form->attrs['module']['advanced']['uniqueId']['desktop']['value']
		);
	}

	public function test_row_rejects_widths_that_do_not_fill() {
		$this->expectException( InvalidArgumentException::class );
		D5::row( D5::column( array( 'width' => '1_2' ) ), D5::column( array( 'width' => '1_3' ) ) );
	}

	public function test_row_requires_columns() {
		$this->expectException( InvalidArgumentException::class );
		D5::row( D5::text( 'x' ) );
	}

	public function test_global_ids_are_checked() {
		$this->expectException( InvalidArgumentException::class );
		D5::global_color( 'primary', '#000', 'Primary' );
	}

	public function test_unknown_breakpoint_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		D5::rv( 'a', null, null, array( 'watch' => 'b' ) );
	}

	public function test_d5_has_no_wordpress_dependency() {
		foreach ( array( 'D5.php', 'Block.php' ) as $file ) {
			$src = file_get_contents( dirname( __DIR__, 3 ) . '/includes/Divi/' . $file );
			$this->assertDoesNotMatchRegularExpression( '/\b(wp_[a-z_]+|esc_[a-z_]+|apply_filters|add_action|ABSPATH)\b/', $src, $file );
		}
	}
}
