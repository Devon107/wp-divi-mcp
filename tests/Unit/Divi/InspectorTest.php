<?php
/**
 * Unit tests for WPDM\Divi\Inspector (port of inspect_layout.py).
 *
 * @package WPDM
 */

use WPDM\Divi\Inspector;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class InspectorTest extends TestCase {

	const PAGE = '<!-- wp:divi/placeholder -->'
		. '<!-- wp:divi/section {"module":{"meta":{"adminLabel":{"desktop":{"value":"Hero"}}}}} -->'
		. '<!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"1_2,1_2"}}}}} -->'
		. '<!-- wp:divi/column -->'
		. '<!-- wp:divi/heading {"title":{"innerContent":{"desktop":{"value":"Big &amp; bold"}}}} /-->'
		. '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Some <strong>text</strong> here</p>"}}},"module":{"decoration":{"background":{"desktop":{"value":{"color":"$variable({\u0022type\u0022:\u0022color\u0022,\u0022value\u0022:{\u0022name\u0022:\u0022gcid-brand\u0022}})$"}}}}}} /-->'
		. '<!-- /wp:divi/column -->'
		. '<!-- /wp:divi/row -->'
		. '<!-- /wp:divi/section -->'
		. '<!-- wp:divi/section {"module":{"decoration":{"disabledOn":{"desktop":{"value":"on"},"tablet":{"value":"on"},"phone":{"value":"on"}}}}} -->'
		. '<!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/image {"image":{"innerContent":{"desktop":{"value":{"src":"https://x.test/a.png"}}}},"gap":"$variable({\u0022type\u0022:\u0022content\u0022,\u0022value\u0022:{\u0022name\u0022:\u0022gvid-gap\u0022}})$"} /--><!-- /wp:divi/column --><!-- /wp:divi/row -->'
		. '<!-- /wp:divi/section -->'
		. '<!-- /wp:divi/placeholder -->';

	public function test_tree() {
		$expected = implode(
			"\n",
			array(
				'SECTION 1  [Hero]',
				'  row (1_2,1_2)',
				'    column',
				'      heading  Big & bold',
				'      text  Some text here',
				'SECTION 2 [HIDDEN]',
				'  row',
				'    column',
				'      image  https://x.test/a.png',
			)
		);
		$this->assertSame( $expected, Inspector::tree( self::PAGE, 6 ) );
		$this->assertSame( "SECTION 1  [Hero]\n  row (1_2,1_2)\nSECTION 2 [HIDDEN]\n  row", Inspector::tree( self::PAGE, 2 ) );
	}

	public function test_section_example_paths_globals() {
		$section = Inspector::section( self::PAGE, 1 );
		$this->assertSame( 'divi/section', $section['name'] );
		$this->assertSame( 'Hero', $section['attrs']['module']['meta']['adminLabel']['desktop']['value'] );
		$this->assertSame( array( 'divi/heading', 'divi/text' ), array_column( $section['children'][0]['children'][0]['children'], 'name' ) );

		try {
			Inspector::section( self::PAGE, 3 );
			$this->fail( 'Expected OutOfRangeException' );
		} catch ( OutOfRangeException $e ) {
			$this->assertStringContainsString( '2 section', $e->getMessage() );
		}

		$ex = Inspector::example( self::PAGE, 'divi/column', 5 );
		$this->assertSame( 2, $ex['count'] );
		$this->assertCount( 2, $ex['examples'] );
		$this->assertSame( array( 'divi/heading', 'divi/text' ), $ex['examples'][0]['children'] );

		$paths = Inspector::paths( self::PAGE, '' );
		$this->assertSame( array( 'divi/heading', 'divi/image', 'divi/row', 'divi/section', 'divi/text' ), array_keys( $paths ) );
		$this->assertSame( '1_2,1_2', $paths['divi/row']['module.advanced.columnStructure.desktop.value'] );
		$this->assertSame( array( 'gap', 'image.innerContent.desktop.value.src' ), array_keys( Inspector::paths( self::PAGE, 'divi/image' )['divi/image'] ) );

		$this->assertSame(
			array(
				'colors'    => array( 'gcid-brand' ),
				'variables' => array( 'gvid-gap' ),
			),
			Inspector::globals( self::PAGE )
		);
	}

	public function test_large_attributes_do_not_hit_pcre_limits() {
		$big  = str_repeat( '} ', 600000 );
		$page = '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"' . $big . '"}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
		$tree = Inspector::tree( $page, 6 );
		$this->assertStringStartsWith( 'SECTION 1', $tree );
		$this->assertStringContainsString( '      text  } } }', $tree );
	}
}
