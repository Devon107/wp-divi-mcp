<?php
/**
 * Knowledge layer: resources (guide, attributes, module reference, layout schema),
 * prompts, server instructions, and the bundled Divi 5.19 catalog.
 *
 * Everything runs through a real Server built from WPDM\Knowledge\Registrar::add,
 * which registers regardless of Divi being active.
 *
 * @package WPDM
 */

use WPDM\Divi\Catalog;
use WPDM\Divi\D5;
use WPDM\Divi\LayoutTree;
use WPDM\Divi\Validator;
use WPDM\Knowledge\Instructions;
use WPDM\Knowledge\Registrar;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;

class KnowledgeTest extends WP_UnitTestCase {

	const STATIC_URIS = array(
		'divi://guide'         => 'text/markdown',
		'divi://attributes'    => 'text/markdown',
		'divi://modules/index' => 'text/markdown',
		'divi://schema/layout' => 'application/json',
	);

	// ----- helpers ---------------------------------------------------------

	private function rpc( string $method, array $params = array() ): array {
		$server   = new Server( Registrar::add( new Registry() ), Instructions::text() );
		$response = $server->handle(
			array(
				'jsonrpc' => '2.0',
				'id'      => 7,
				'method'  => $method,
				'params'  => $params,
			),
			new Context( 0, array( 'id' => 'c1', 'label' => 'Test', 'mode' => 'full' ) )
		);
		return json_decode( wp_json_encode( $response ), true );
	}

	private function read( string $uri ): array {
		$r = $this->rpc( 'resources/read', array( 'uri' => $uri ) );
		$this->assertArrayHasKey( 'result', $r, wp_json_encode( $r ) );
		$this->assertCount( 1, $r['result']['contents'] );
		return $r['result']['contents'][0];
	}

	private function prompt( string $name, array $args ): array {
		return $this->rpc(
			'prompts/get',
			array(
				'name'      => $name,
				'arguments' => $args,
			)
		);
	}

	private static function knowledge( string $file ): string {
		return (string) file_get_contents( WPDM_DIR . 'knowledge/' . $file );
	}

	/**
	 * Every ```json fenced block of the guide.
	 *
	 * @return string[]
	 */
	private static function guide_examples(): array {
		preg_match_all( '/^```json\n(.*?)\n```$/ms', self::knowledge( 'guide.md' ), $m );
		return $m[1];
	}

	// ----- resources -------------------------------------------------------

	public function test_resources_list_contains_guide_and_schema() {
		$r    = $this->rpc( 'resources/list' );
		$list = array_column( $r['result']['resources'], 'mimeType', 'uri' );
		foreach ( self::STATIC_URIS as $uri => $mime ) {
			$this->assertSame( $mime, $list[ $uri ] ?? null, $uri );
		}
		foreach ( $r['result']['resources'] as $res ) {
			$this->assertNotSame( '', $res['name'] );
			$this->assertNotSame( '', $res['description'] ?? '', $res['uri'] . ' has a description' );
		}

		$t = $this->rpc( 'resources/templates/list' );
		$this->assertSame(
			array( 'divi://modules/{name}' => 'text/markdown' ),
			array_column( $t['result']['resourceTemplates'], 'mimeType', 'uriTemplate' )
		);
	}

	public function test_static_resources_read_their_files() {
		$this->assertSame( self::knowledge( 'guide.md' ), $this->read( 'divi://guide' )['text'] );
		$this->assertSame( self::knowledge( 'attributes.md' ), $this->read( 'divi://attributes' )['text'] );
		$this->assertSame( self::knowledge( 'modules/INDEX.md' ), $this->read( 'divi://modules/index' )['text'] );

		$schema = $this->read( 'divi://schema/layout' );
		$this->assertSame( 'application/json', $schema['mimeType'] );
		$this->assertSame( LayoutTree::schema(), json_decode( $schema['text'], true ) );
	}

	public function test_module_template_reads_known_module() {
		$c = $this->read( 'divi://modules/group' );
		$this->assertSame( 'divi://modules/group', $c['uri'] );
		$this->assertSame( 'text/markdown', $c['mimeType'] );
		$this->assertSame( self::knowledge( 'modules/group.md' ), $c['text'] );
		$this->assertStringStartsWith( '# divi/group — Group', $c['text'] );

		$this->assertStringStartsWith( '# divi/accordion-item', $this->read( 'divi://modules/accordion-item' )['text'] );
	}

	public function test_unknown_module_resource_error() {
		foreach ( array( 'nope', 'Group', 'group.md', '..', '.', 'divi-group', '%2e%2e', 'INDEX', 'group%00' ) as $name ) {
			$r = $this->rpc( 'resources/read', array( 'uri' => 'divi://modules/' . $name ) );
			$this->assertArrayNotHasKey( 'result', $r, $name );
			$this->assertSame( Server::ERR_INVALID_PARAMS, $r['error']['code'], $name );
			$this->assertSame( 'Unknown resource: divi://modules/' . $name, $r['error']['message'], $name );
		}
		// Path traversal never reaches the reader (the template segment has no "/").
		$r = $this->rpc( 'resources/read', array( 'uri' => 'divi://modules/../guide' ) );
		$this->assertArrayHasKey( 'error', $r );
	}

	// ----- prompts ---------------------------------------------------------

	public function test_prompts_render() {
		$list = $this->rpc( 'prompts/list' )['result']['prompts'];
		$defs = array_column( $list, 'arguments', 'name' );
		$this->assertSame( array( 'build_divi_page', 'match_existing_site' ), array_keys( $defs ) );
		$this->assertSame(
			array( 'purpose' => true, 'sections' => false, 'language' => false, 'brand' => false ),
			array_map( 'boolval', array_column( $defs['build_divi_page'], 'required', 'name' ) )
		);
		$this->assertSame( array( 'post_id' => true ), array_map( 'boolval', array_column( $defs['match_existing_site'], 'required', 'name' ) ) );

		$r = $this->prompt(
			'build_divi_page',
			array(
				'purpose'  => 'Landing page for a dental clinic',
				'sections' => 'hero, services, testimonials, contact',
				'language' => 'Spanish',
				'brand'    => 'navy #0b1f3a, Inter',
			)
		);
		$this->assertCount( 1, $r['result']['messages'] );
		$msg = $r['result']['messages'][0];
		$this->assertSame( 'user', $msg['role'] );
		$this->assertSame( 'text', $msg['content']['type'] );
		$text = $msg['content']['text'];
		foreach ( array( 'Landing page for a dental clinic', 'hero, services, testimonials, contact', 'Spanish', 'navy #0b1f3a, Inter', 'divi://guide', 'divi_status', 'divi_get_globals', 'divi_inspect_page', 'divi_validate_layout', 'divi_create_page', 'divi_apply_layout', 'dry_run', 'draft' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text );
		}
		$this->assertLessThan( strpos( $text, 'divi_get_globals' ), strpos( $text, 'divi://guide' ), 'guide first' );

		// Optional arguments left out: still one message, with sensible defaults.
		$min = $this->prompt( 'build_divi_page', array( 'purpose' => 'About us' ) );
		$this->assertCount( 1, $min['result']['messages'] );
		$this->assertStringContainsString( 'About us', $min['result']['messages'][0]['content']['text'] );

		$this->assertSame( Server::ERR_INVALID_PARAMS, $this->prompt( 'build_divi_page', array() )['error']['code'] );

		$m = $this->prompt( 'match_existing_site', array( 'post_id' => '42' ) );
		$this->assertCount( 1, $m['result']['messages'] );
		$text = $m['result']['messages'][0]['content']['text'];
		foreach ( array( '42', 'divi://guide', 'divi_get_globals', 'divi_inspect_page', 'divi_validate_layout', 'divi_create_page', 'draft' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text );
		}
		$this->assertSame( Server::ERR_INVALID_PARAMS, $this->prompt( 'match_existing_site', array() )['error']['code'] );
		$this->assertArrayHasKey( 'error', $this->prompt( 'match_existing_site', array( 'post_id' => 'abc' ) ) );
	}

	// ----- instructions ----------------------------------------------------

	public function test_instructions_under_1500_chars() {
		$text = Instructions::text();
		$this->assertLessThanOrEqual( 1500, strlen( $text ) );
		foreach ( array( 'divi://guide', 'divi_status', 'divi_get_globals', 'divi_validate_layout', 'divi_create_page', 'divi_apply_layout', 'dry_run', 'draft', 'confirm_published', 'confirm_default_template', 'confirm_live_template', 'trash', 'blurb' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text );
		}
		$this->assertSame( $text, apply_filters( 'wpdm_instructions', '' ), 'hooked on wpdm_instructions' );

		$init = $this->rpc( 'initialize', array( 'protocolVersion' => '2025-06-18' ) );
		$this->assertSame( $text, $init['result']['instructions'] );
	}

	// ----- catalog and guide -----------------------------------------------

	public function test_catalog_has_5_19_modules() {
		$catalog = json_decode( self::knowledge( 'module-catalog.json' ), true );
		$this->assertMatchesRegularExpression( '/^5\.19\./', $catalog['diviVersion'] );
		$this->assertSame( $catalog, Catalog::bundled() );
		$this->assertArrayHasKey( 'divi/global-slot', $catalog['modules'] );
		$this->assertStringStartsWith( '# Divi 5 module index (generated from Divi 5.19.', self::knowledge( 'modules/INDEX.md' ) );
		foreach ( array_keys( $catalog['modules'] ) as $name ) {
			$this->assertFileExists( WPDM_DIR . 'knowledge/modules/' . substr( $name, 5 ) . '.md' );
		}
		$this->assertStringContainsString( 'Changes for Divi 5.19', self::knowledge( 'attributes.md' ) );
	}

	public function test_guide_examples_build_and_validate() {
		$examples = self::guide_examples();
		$this->assertGreaterThanOrEqual( 3, count( $examples ) );
		$all = implode( "\n", $examples );
		foreach ( array( '"pill_link"', '"grid_row"', '"icon_card"', '"testimonial_card"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $all );
		}

		$before = D5::builder_version();
		foreach ( $examples as $i => $json ) {
			$tree = json_decode( $json );
			$this->assertInstanceOf( stdClass::class, $tree, "example {$i}: " . json_last_error_msg() );
			$built  = LayoutTree::build( (array) $tree );
			$report = Validator::validate_doc( $built['envelope'], Catalog::bundled(), true );
			$this->assertSame( array(), $report->errors, "example {$i} has validation errors" );
			$this->assertSame( array(), $report->warnings, "example {$i} has validation warnings" );
			$this->assertSame( $before, D5::builder_version() );
		}
	}

	public function test_guide_and_instructions_name_real_tools() {
		// Result fields and error codes that look like tool names.
		$names = array( 'divi_version', 'divi_active', 'divi_not_active' );
		foreach ( \WPDM\Tools\Divi\Registrar::tools() as $tool ) {
			$names[] = $tool->name();
		}
		$texts = array( self::knowledge( 'guide.md' ), Instructions::text() );
		foreach ( array( 'build_divi_page' => array( 'purpose' => 'x' ), 'match_existing_site' => array( 'post_id' => '3' ) ) as $p => $args ) {
			$texts[] = $this->prompt( $p, $args )['result']['messages'][0]['content']['text'];
		}
		foreach ( $texts as $text ) {
			preg_match_all( '/(?<![$\w])divi_[a-z_]+/', $text, $m ); // Not $divi_icon.
			$this->assertNotEmpty( $m[0] );
			foreach ( array_unique( $m[0] ) as $name ) {
				$this->assertContains( $name, $names, "{$name} is not a Divi tool" );
			}
		}
	}

	public function test_registered_without_divi() {
		$registry = Registrar::add( new Registry() );
		$this->assertCount( 4, $registry->resources() );
		$this->assertCount( 1, $registry->resource_templates() );
		$this->assertCount( 2, $registry->prompts() );
		$this->assertSame( array(), ( new Registry() )->resources() );
		$this->assertNotFalse( has_filter( 'wpdm_registry', array( Registrar::class, 'add' ) ) );
	}
}
