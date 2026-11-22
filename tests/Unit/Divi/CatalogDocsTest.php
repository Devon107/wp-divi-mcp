<?php
/**
 * CatalogDocs renders the module reference exactly like the skill's generate_catalog.py.
 *
 * tests/golden/catalog-docs/theme is a synthetic theme folder (one module that hits
 * subName, options, responsive=false, syncImageData, button-link, attrName, a
 * decoration attrName, an outline and defaults with non-ASCII text and floats);
 * expected/ is what generate_catalog.py wrote for it.
 *
 * @package WPDM
 */

use WPDM\Divi\CatalogDocs;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class CatalogDocsTest extends TestCase {

	/**
	 * @return string
	 */
	private static function dir() {
		return dirname( __DIR__, 2 ) . '/golden/catalog-docs';
	}

	/**
	 * @param string $name Expected file.
	 * @return string
	 */
	private static function expected( $name ) {
		return (string) file_get_contents( self::dir() . '/expected/' . $name );
	}

	public function test_render_matches_generate_catalog_py() {
		$out = CatalogDocs::render( self::dir() . '/theme' );
		$this->assertSame( array( 'card' ), array_keys( $out['modules'] ) );
		$this->assertSame( self::expected( 'card.md' ), $out['modules']['card'] );
		$this->assertSame( self::expected( 'INDEX.md' ), $out['index'] );
		$this->assertSame( self::expected( 'module-catalog.json' ), $out['catalog_json'] );
		$this->assertSame( self::expected( 'icons.json' ), $out['icons'] );
		$this->assertSame( 2, $out['icon_count'] );
	}

	public function test_py_dumps_matches_json_dumps() {
		$this->assertSame( '{"a": [1, 2.5], "b": {}}', CatalogDocs::py_dumps( json_decode( '{"a":[1,2.5],"b":{}}' ) ) );
		$this->assertSame( "{\n \"a\": [],\n \"b\": [\n  true,\n  null\n ]\n}", CatalogDocs::py_dumps( array( 'b' => array( true, null ), 'a' => array() ), 1, true ) );
		$this->assertSame( '"\\u00e9\\n\\u007f/"', CatalogDocs::py_dumps( "é\n\x7f/" ) );
		$this->assertSame( "\"é\\n\x7f/\"", CatalogDocs::py_dumps( "é\n\x7f/", null, false, false ) );
		$this->assertSame( '[1.0, 1e-05, 1e+16, 1234567890123456.0, 0.1, -2.5]', CatalogDocs::py_dumps( array( 1.0, 0.00001, 1e16, 1234567890123456.0, 0.1, -2.5 ) ) );
	}

	public function test_truthy_follows_python() {
		$this->assertFalse( CatalogDocs::truthy( new stdClass() ) );
		$this->assertFalse( CatalogDocs::truthy( '' ) );
		$this->assertFalse( CatalogDocs::truthy( array() ) );
		$this->assertTrue( CatalogDocs::truthy( '0' ) );
		$this->assertTrue( CatalogDocs::truthy( json_decode( '{"a":1}' ) ) );
	}
}
