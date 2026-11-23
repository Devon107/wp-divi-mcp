<?php
/**
 * Tests for the per-connection Claude Desktop bundle (.mcpb).
 *
 * @package WPDM
 */

use WPDM\Admin\Mcpb;

class McpbTest extends WP_UnitTestCase {

	const URL = 'https://example.org/wp-json/wp-divi-mcp/v1/mcp?key=abc';

	/**
	 * Open zip bytes and return [entry name => contents].
	 *
	 * @param string $bytes Zip bytes.
	 * @return array<string,string>
	 */
	private function entries( string $bytes ): array {
		$file = wp_tempnam( 'mcpb-test' );
		file_put_contents( $file, $bytes );
		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $file ) );
		$out = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name         = $zip->getNameIndex( $i );
			$out[ $name ] = $zip->getFromIndex( $i );
		}
		$zip->close();
		unlink( $file );
		return $out;
	}

	public function test_zip_contains_manifest_and_server() {
		$entries = $this->entries( Mcpb::build( self::URL, 'Immedex Dev', 'a1b2c3' ) );
		$names   = array_keys( $entries );
		sort( $names );
		$this->assertSame( array( 'manifest.json', 'server/bridge.js', 'server/index.js' ), $names );
		$this->assertSame( file_get_contents( WPDM_DIR . 'assets/mcpb/server.js' ), $entries['server/index.js'] );
		$this->assertSame( file_get_contents( WPDM_DIR . 'assets/mcpb/bridge.js' ), $entries['server/bridge.js'] );
	}

	public function test_manifest_fields() {
		$entries  = $this->entries( Mcpb::build( self::URL, 'Immedex Dev', 'a1b2c3' ) );
		$manifest = json_decode( $entries['manifest.json'], true );
		$this->assertIsArray( $manifest );
		$this->assertSame( '0.3', $manifest['manifest_version'] );
		$this->assertSame( WPDM_VERSION, $manifest['version'] );
		$this->assertSame( Mcpb::name( 'Immedex Dev', 'a1b2c3' ), $manifest['name'] );
		$this->assertSame( 'node', $manifest['server']['type'] );
		$this->assertSame( 'server/index.js', $manifest['server']['entry_point'] );
		$this->assertSame( 'node', $manifest['server']['mcp_config']['command'] );
		$this->assertSame( array( '${__dirname}/server/index.js' ), $manifest['server']['mcp_config']['args'] );
		$this->assertSame( self::URL, $manifest['server']['mcp_config']['env']['WPDM_URL'] );
		$this->assertSame( WPDM_VERSION, $manifest['server']['mcp_config']['env']['WPDM_VERSION'] );
		$this->assertSame( array( 'darwin', 'win32', 'linux' ), $manifest['compatibility']['platforms'] );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->assertStringContainsString( 'Immedex Dev', $manifest['display_name'] );
		$this->assertStringContainsString( $host, $manifest['display_name'] );
		$this->assertStringContainsString( $host, $manifest['description'] );
	}

	public function test_name_is_safe_for_odd_labels() {
		foreach ( array( 'Ñandú – café ☕', '!!!', '', str_repeat( 'x', 200 ) ) as $label ) {
			$name = Mcpb::name( $label, 'a1b2c3' );
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]{1,64}$/', $name, $label );
			$this->assertStringStartsWith( 'wp-divi-mcp-', $name );
			$this->assertStringEndsWith( '-a1b2c3', $name );
			$this->assertStringNotContainsString( '--', $name );
		}
	}

	public function test_manifest_name_matches_name_for_empty_label() {
		$entries  = $this->entries( Mcpb::build( self::URL, '', 'a1b2c3' ) );
		$manifest = json_decode( $entries['manifest.json'], true );
		$this->assertSame( Mcpb::name( '', 'a1b2c3' ), $manifest['name'] );
	}

	public function test_names_differ_per_connection() {
		$this->assertNotSame( Mcpb::name( 'Site', 'aaa111' ), Mcpb::name( 'Site', 'bbb222' ) );
		$this->assertSame( Mcpb::name( 'Site', 'aaa111' ), Mcpb::name( 'Site', 'aaa111' ) );
	}

	public function test_filename_ends_with_mcpb() {
		$this->assertSame( Mcpb::name( 'Site', 'aaa111' ) . '.mcpb', Mcpb::filename( 'Site', 'aaa111' ) );
	}

	public function test_available_is_filterable() {
		$this->assertSame( class_exists( 'ZipArchive' ), Mcpb::available() );
		add_filter( 'wpdm_mcpb_available', '__return_false' );
		$this->assertFalse( Mcpb::available() );
		remove_filter( 'wpdm_mcpb_available', '__return_false' );
	}
}
