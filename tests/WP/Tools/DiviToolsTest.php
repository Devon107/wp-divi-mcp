<?php
/**
 * Tests for the Divi page tools.
 *
 * Tools run through a real Server with a Registry built by
 * WPDM\Tools\Divi\Registrar::add. Tests that need the Divi theme are in
 * group "divi" (WPDM_TEST_DIVI=1); build, validate and find_icon run without it.
 *
 * @package WPDM
 */

use ET\Builder\Packages\GlobalData\GlobalData;
use WPDM\Divi\Backups;
use WPDM\Divi\Env;
use WPDM\Divi\Globals;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Tools\Divi\Registrar;

class DiviToolsTest extends WP_UnitTestCase {

	const ALL_TOOLS = array(
		'divi_status',
		'divi_get_globals',
		'divi_merge_globals',
		'divi_build_layout',
		'divi_validate_layout',
		'divi_apply_layout',
		'divi_create_page',
		'divi_export_page',
		'divi_inspect_page',
		'divi_list_backups',
		'divi_restore_backup',
		'divi_set_page_css',
		'divi_find_icon',
	);

	const ALWAYS = array( 'divi_build_layout', 'divi_validate_layout', 'divi_find_icon' );

	/** 1x1 transparent PNG. */
	const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	/** @var int */
	private $admin;
	/** @var int */
	private $editor;
	/** @var int */
	private $author;

	/** @var array|null Globals before the test (Divi only). */
	private $before = null;

	public function set_up() {
		parent::set_up();
		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->author = self::factory()->user->create( array( 'role' => 'author' ) );
		if ( Env::divi_active() ) {
			wp_set_current_user( $this->admin );
			$this->before = Globals::read();
		}
	}

	public function tear_down() {
		if ( $this->before ) {
			wp_set_current_user( $this->admin );
			Globals::restore( $this->before );
		}
		parent::tear_down();
		if ( $this->before ) {
			self::reset_divi_caches();
			$this->assertSame( wp_json_encode( $this->before ), wp_json_encode( Globals::read() ), 'globals restored' );
		}
		wp_set_current_user( 0 );
	}

	private static function reset_divi_caches(): void {
		global $et_theme_options;
		$et_theme_options = null;
		wp_cache_flush();
		$prop = new ReflectionProperty( GlobalData::class, '_cached_data' );
		$prop->setAccessible( true );
		$cached = $prop->getValue();
		foreach ( array_keys( $cached ) as $key ) {
			$cached[ $key ] = null;
		}
		$prop->setValue( null, $cached );
	}

	// ----- helpers ---------------------------------------------------------

	private function server(): Server {
		return new Server( Registrar::add( new Registry() ), '' );
	}

	private function call( int $user_id, string $tool, array $args = array() ): array {
		wp_set_current_user( $user_id );
		$response = $this->server()->handle(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => $tool,
					'arguments' => $args,
				),
			),
			new Context( $user_id, array( 'id' => 'c1', 'label' => 'Test connection', 'mode' => 'full' ) )
		);
		$this->assertArrayHasKey( 'result', $response, 'Expected a JSON-RPC result: ' . wp_json_encode( $response ) );
		return json_decode( wp_json_encode( $response['result'] ), true );
	}

	private function ok( int $user_id, string $tool, array $args = array() ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) . ' ' . wp_json_encode( $result['structuredContent'] ?? null ) );
		return $result['structuredContent'];
	}

	/**
	 * Call a tool and assert a tool error with the given code; returns structuredContent.
	 */
	private function fails( int $user_id, string $tool, array $args, string $code ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertTrue( $result['isError'] ?? false, 'Expected isError for ' . $tool );
		$this->assertSame( $code, $result['structuredContent']['error'] ?? null, $result['content'][0]['text'] ?? '' );
		return $result['structuredContent'];
	}

	private static function tree( array $column_nodes, array $top = array() ): array {
		return array_merge(
			$top,
			array(
				'blocks' => array(
					array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( array( 'column' => array( 'children' => $column_nodes ) ) ) ) ) ) ) ),
				),
			)
		);
	}

	private static function text_tree( string $html = '<p>Hello</p>' ): array {
		return self::tree( array( array( 'text' => array( 'html' => $html ) ) ) );
	}

	private static function text_markup( string $text = 'Hi', string $section_attrs = '' ): string {
		$attrs = '' === $section_attrs ? '' : ' ' . $section_attrs;
		return '<!-- wp:divi/placeholder --><!-- wp:divi/section' . $attrs . ' --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>' . $text . '</p>"}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
	}

	private function page( string $status = 'draft', string $content = 'old content' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => $status,
				// wp_insert_post() expects slashed data.
				'post_content' => wp_slash( $content ),
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Every "$ref" in a schema, with its JSON path.
	 */
	private static function refs( $node, string $path, array &$out ): void {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( '$ref' === $key && is_string( $value ) ) {
				$out[ $path ] = $value;
			} else {
				self::refs( $value, $path . '/' . $key, $out );
			}
		}
	}

	// ----- registration ----------------------------------------------------

	public function test_divi_tools_hidden_without_divi() {
		$this->assertFalse( Env::divi_active() );
		$names = array();
		foreach ( Registrar::add( new Registry() )->tools( new Context( $this->admin ) ) as $tool ) {
			$names[] = $tool->name();
		}
		sort( $names );
		$expected = self::ALWAYS;
		sort( $expected );
		$this->assertSame( $expected, $names );
		$this->assertSame( 10, has_filter( 'wpdm_registry', array( Registrar::class, 'add' ) ) );
	}

	public function test_annotations_and_capabilities() {
		$tools = array();
		foreach ( Registrar::tools() as $tool ) {
			$tools[ $tool->name() ] = $tool;
		}
		$names = array_keys( $tools );
		sort( $names );
		$expected = self::ALL_TOOLS;
		sort( $expected );
		$this->assertSame( $expected, $names );

		$read_only   = array( 'divi_status', 'divi_get_globals', 'divi_build_layout', 'divi_validate_layout', 'divi_export_page', 'divi_inspect_page', 'divi_list_backups', 'divi_find_icon' );
		$destructive = array( 'divi_apply_layout', 'divi_restore_backup', 'divi_merge_globals', 'divi_set_page_css' );
		foreach ( $tools as $name => $tool ) {
			$this->assertSame( in_array( $name, $read_only, true ), $tool->read_only(), $name . ' read_only' );
			$this->assertSame( in_array( $name, $destructive, true ), $tool->destructive(), $name . ' destructive' );
		}
		$this->assertSame( 'edit_theme_options', $tools['divi_get_globals']->capability() );
		$this->assertSame( 'edit_theme_options', $tools['divi_merge_globals']->capability() );
		$this->assertSame( 'edit_posts', $tools['divi_status']->capability() );
	}

	public function test_schema_refs_resolve() {
		$checked = 0;
		foreach ( Registrar::tools() as $tool ) {
			// Wire format, as tools/list sends it.
			$schema = json_decode( wp_json_encode( $tool->input_schema() ), true );
			$this->assertSame( 'object', $schema['type'], $tool->name() );
			$refs = array();
			self::refs( $schema, '#', $refs );
			foreach ( $refs as $where => $ref ) {
				$this->assertStringStartsWith( '#/', $ref, $tool->name() . ' ' . $where );
				$node = $schema;
				foreach ( explode( '/', substr( $ref, 2 ) ) as $part ) {
					$part = str_replace( array( '~1', '~0' ), array( '/', '~' ), $part );
					$this->assertIsArray( $node, $tool->name() . ': ' . $ref );
					$this->assertArrayHasKey( $part, $node, $tool->name() . ': ' . $ref . ' (at ' . $where . ') does not resolve' );
					$node = $node[ $part ];
				}
				++$checked;
			}
			if ( isset( $schema['properties']['layout'] ) ) {
				$this->assertArrayNotHasKey( '$defs', $schema['properties']['layout'], $tool->name() );
				$this->assertArrayNotHasKey( '$id', $schema['properties']['layout'], $tool->name() );
				$this->assertArrayHasKey( 'node', $schema['$defs'], $tool->name() );
			}
		}
		$this->assertGreaterThan( 100, $checked );
	}

	// ----- build / validate ------------------------------------------------

	public function test_build_layout_returns_markup_and_report() {
		$out = $this->ok( $this->author, 'divi_build_layout', array( 'layout' => self::text_tree( '<p>Built</p>' ) ) );
		$this->assertStringStartsWith( '<!-- wp:divi/placeholder -->', $out['markup'] );
		$this->assertStringContainsString( 'wp:divi/text', $out['markup'] );
		$this->assertStringContainsString( 'Built', $out['markup'] );
		$this->assertTrue( $out['report']['ok'] );
		$this->assertSame( array(), $out['report']['errors'] );
		$this->assertSame( 1, $out['report']['counts']['divi/text'] );
		$this->assertSame( 'et_builder', $out['portability']['context'] );
		$this->assertSame( strlen( $out['markup'] ), $out['bytes'] );
	}

	public function test_layout_input_needs_exactly_one_source() {
		$this->fails( $this->author, 'divi_build_layout', array(), 'invalid_input' );
		$this->fails( $this->author, 'divi_build_layout', array( 'layout' => self::text_tree(), 'markup' => self::text_markup() ), 'invalid_input' );
		$this->fails( $this->author, 'divi_validate_layout', array( 'markup' => self::text_markup(), 'portability' => array( 'context' => 'et_builder' ) ), 'invalid_input' );
	}

	public function test_error_codes_on_wire() {
		$result = $this->call( $this->author, 'divi_build_layout', array( 'layout' => array( 'blocks' => array( array( 'text' => array( 'html' => 'x' ) ) ) ) ) );
		$this->assertTrue( $result['isError'] );
		$this->assertSame( 'invalid_layout', $result['structuredContent']['error'] );
		$this->assertSame( 'blocks[0].text', $result['structuredContent']['data']['path'] );
		$this->assertStringStartsWith( 'invalid_layout: ', $result['content'][0]['text'] );
		$this->assertSame( $result['content'][0]['text'], 'invalid_layout: ' . $result['structuredContent']['message'] );

		$bad = $this->fails( $this->author, 'divi_build_layout', array( 'markup' => '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- /wp:divi/placeholder -->' ), 'validation_failed' );
		$this->assertFalse( $bad['data']['report']['ok'] );
		$this->assertNotEmpty( $bad['data']['report']['errors'] );
	}

	public function test_validate_returns_report_with_errors() {
		$out = $this->ok( $this->author, 'divi_validate_layout', array( 'markup' => '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- /wp:divi/placeholder -->' ) );
		$this->assertFalse( $out['ok'] );
		$this->assertNotEmpty( $out['report']['errors'] );

		$good = $this->ok( $this->author, 'divi_validate_layout', array( 'markup' => self::text_markup() ) );
		$this->assertTrue( $good['ok'] );
		$this->assertSame( 1, $good['report']['counts']['divi/text'] );
	}

	public function test_too_complex_tree_rejected() {
		// Nesting deeper than 64.
		$deep = array( 'x' => 1 );
		for ( $i = 0; $i < 70; $i++ ) {
			$deep = array( 'a' => $deep );
		}
		$out = $this->fails( $this->author, 'divi_build_layout', array( 'layout' => self::tree( array( array( 'text' => array( 'html' => 'x', 'attrs' => $deep ) ) ) ) ), 'too_complex' );
		$this->assertStringStartsWith( 'blocks[0].section.children[0].row.children[0].column.children[0].text.attrs.a.a', $out['data']['path'] );

		// More than 5000 nodes.
		$nodes = array();
		for ( $i = 0; $i < 2600; $i++ ) {
			$nodes[] = array( 'text' => array( 'html' => 'x' ) );
		}
		$out = $this->fails( $this->author, 'divi_build_layout', array( 'layout' => self::tree( $nodes ) ), 'too_complex' );
		$this->assertArrayHasKey( 'path', $out['data'] );

		// Markup over 8 MB.
		$this->fails( $this->author, 'divi_validate_layout', array( 'markup' => str_repeat( 'x', 8 * 1024 * 1024 + 1 ) ), 'too_large' );
	}

	public function test_find_icon_tooth() {
		$out = $this->ok( $this->author, 'divi_find_icon', array( 'query' => 'tooth' ) );
		$this->assertNotEmpty( $out['icons'] );
		$first = $out['icons'][0];
		$this->assertSame( 'tooth', strtolower( $first['name'] ) );
		$this->assertSame( 'fa', $first['family'] );
		$this->assertSame( array( '$fa' => $first['unicode'], 'weight' => $first['weight'] ), $first['value'] );
		$this->assertStringStartsWith( '&#x', $first['unicode'] );

		$limited = $this->ok( $this->author, 'divi_find_icon', array( 'query' => 'arrow', 'limit' => 3 ) );
		$this->assertCount( 3, $limited['icons'] );

		$divi = $this->ok( $this->author, 'divi_find_icon', array( 'query' => 'arrow up' ) );
		$names = array_column( $divi['icons'], 'family' );
		$this->assertContains( 'divi', $names );
		foreach ( $divi['icons'] as $icon ) {
			if ( 'divi' === $icon['family'] ) {
				$this->assertSame( array( '$divi_icon' => $icon['unicode'] ), $icon['value'] );
			}
		}
	}

	// ----- Divi: status, globals ------------------------------------------

	/**
	 * @group divi
	 */
	public function test_status_and_globals_tools() {
		$status = $this->ok( $this->editor, 'divi_status' );
		$this->assertTrue( $status['divi_active'] );
		$this->assertSame( Env::divi_version(), $status['divi_version'] );

		$this->fails( $this->editor, 'divi_get_globals', array(), 'forbidden' );
		$globals = $this->ok( $this->admin, 'divi_get_globals' );
		$this->assertContains( 'gcid-primary-color', array_column( $globals['colors'], 'id' ) );

		$this->fails( $this->editor, 'divi_merge_globals', array( 'colors' => array( array( 'gcid-wpdm-t13', array( 'color' => '#123456' ) ) ) ), 'forbidden' );
		$dry = $this->ok( $this->admin, 'divi_merge_globals', array( 'colors' => array( array( 'gcid-wpdm-t13', array( 'color' => '#123456' ) ) ), 'dry_run' => true ) );
		$this->assertSame( array( 'gcid-wpdm-t13' ), $dry['colors']['added'] );
		$this->assertArrayNotHasKey( 'gcid-wpdm-t13', Globals::read()['colors'] );
		$real = $this->ok( $this->admin, 'divi_merge_globals', array( 'colors' => array( array( 'gcid-wpdm-t13', array( 'color' => '#123456' ) ) ) ) );
		$this->assertIsInt( $real['backup_id'] );
		$this->assertSame( 'Test connection', Backups::get( $real['backup_id'] )['connection'] );
		$this->assertSame( '#123456', Globals::read()['colors']['gcid-wpdm-t13']['color'] );
	}

	/**
	 * @group divi
	 */
	public function test_markup_validation_knows_site_globals() {
		Globals::merge( array( array( 'gcid-wpdm-known', array( 'color' => '#101010' ) ) ), array(), false, false );
		$markup = '<!-- wp:divi/placeholder --><!-- wp:divi/section {"module":{"decoration":{"background":{"desktop":{"value":{"color":"$variable({\u0022type\u0022:\u0022color\u0022,\u0022value\u0022:{\u0022name\u0022:\u0022gcid-wpdm-known\u0022}})$"}}}}}} --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>x</p>"}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
		$out    = $this->ok( $this->author, 'divi_validate_layout', array( 'markup' => $markup ) );
		foreach ( $out['report']['warnings'] as $w ) {
			$this->assertStringNotContainsString( 'gcid-wpdm-known', $w );
		}
		$unknown = $this->ok( $this->author, 'divi_validate_layout', array( 'markup' => str_replace( 'gcid-wpdm-known', 'gcid-wpdm-unknown', $markup ) ) );
		$this->assertStringContainsString( 'gcid-wpdm-unknown', implode( "\n", $unknown['report']['warnings'] ) );
	}

	// ----- Divi: apply ----------------------------------------------------

	/**
	 * @group divi
	 */
	public function test_apply_refuses_on_validation_error() {
		$page    = $this->page();
		$backups = count( Backups::list( $page, 'post_content' ) );
		$out     = $this->fails(
			$this->admin,
			'divi_apply_layout',
			array(
				'post_id' => $page,
				'markup'  => '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- /wp:divi/placeholder -->',
			),
			'validation_failed'
		);
		$this->assertNotEmpty( $out['data']['report']['errors'] );
		$this->assertSame( 'old content', get_post( $page )->post_content );
		$this->assertCount( $backups, Backups::list( $page, 'post_content' ) );
	}

	/**
	 * @group divi
	 */
	public function test_apply_refuses_published_without_confirm() {
		foreach ( array( 'publish', 'private' ) as $status ) {
			$page = $this->page( $status );
			$this->fails( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => self::text_tree() ), 'confirmation_required' );
			$this->assertSame( 'old content', get_post( $page )->post_content );
			$this->assertSame( array(), Backups::list( $page, 'post_content' ) );
		}

		$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => self::text_tree(), 'confirm_published' => true ) );
		$this->assertTrue( $out['ok'] );
		$this->assertStringContainsString( 'wp:divi/text', get_post( $page )->post_content );
		$this->assertSame( 'private', get_post_status( $page ) );
	}

	/**
	 * @group divi
	 */
	public function test_apply_dry_run_changes_nothing() {
		$page = $this->page();
		$tree = self::text_tree();
		$tree['global_colors'] = array( array( 'gcid-wpdm-dry', array( 'color' => '#abcdef', 'status' => 'active', 'label' => 'Dry' ) ) );
		$tree['custom_css']    = '.x{color:red}';
		$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => $tree, 'dry_run' => true ) );
		$this->assertTrue( $out['dry_run'] );
		$this->assertTrue( $out['report']['ok'] );
		$this->assertSame( array( 'gcid-wpdm-dry' ), $out['globals_preview']['colors']['added'] );
		$this->assertSame( 0, $out['images_to_upload'] );
		$this->assertGreaterThan( 0, $out['content_bytes'] );

		$this->assertSame( 'old content', get_post( $page )->post_content );
		$this->assertSame( '', get_post_meta( $page, '_et_pb_custom_css', true ) );
		$this->assertSame( '', get_post_meta( $page, '_et_pb_use_builder', true ) );
		$this->assertArrayNotHasKey( 'gcid-wpdm-dry', Globals::read()['colors'] );
		$this->assertSame( array(), Backups::list( $page, 'post_content' ) );
	}

	/**
	 * @group divi
	 */
	public function test_backup_taken_at_write_time() {
		$page = $this->page();
		$this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => self::text_tree(), 'dry_run' => true ) );
		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => 'changed between dry run and apply',
			)
		);
		update_post_meta( $page, '_et_pb_custom_css', '.old{}' );

		$tree               = self::text_tree();
		$tree['custom_css'] = '.new{color:blue}';
		$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => $tree ) );
		$backup = Backups::get( $out['backup_id'] );
		$this->assertSame( 'post_content', $backup['kind'] );
		$this->assertSame( $page, $backup['source_id'] );
		$this->assertSame( 'changed between dry run and apply', $backup['content'] );
		$this->assertSame( 'Test connection', $backup['connection'] );
		$this->assertSame( 'page', $backup['meta']['post_type'] );
		$this->assertSame( 'draft', $backup['meta']['status'] );
		$this->assertSame( '.old{}', $backup['meta']['custom_css'] );

		$this->assertSame( '.new{color:blue}', get_post_meta( $page, '_et_pb_custom_css', true ) );
		$this->assertSame( 'on', get_post_meta( $page, '_et_pb_use_builder', true ) );
		$this->assertStringContainsString( 'wp:divi/text', get_post( $page )->post_content );
		$this->assertSame( add_query_arg( 'et_fb', '1', get_permalink( $page ) ), $out['edit_divi'] );
		$this->assertSame( $page, $out['post_id'] );
	}

	/**
	 * @group divi
	 */
	public function test_apply_uploads_embedded_image_and_rewrites_url() {
		$page   = $this->page();
		$remote = 'https://images.example.org/photos/team.png';
		$markup = '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/image {"image":{"innerContent":{"desktop":{"value":{"src":"' . $remote . '","alt":"Team"}}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
		$doc    = array(
			'context'            => 'et_builder',
			'data'               => array( '7' => $markup ),
			'presets'            => null,
			'global_colors'      => array(),
			'global_variables'   => array(),
			'page_settings_meta' => array( '_et_pb_custom_css' => array( '' ) ),
			'canvases'           => array( 'local' => array(), 'global' => array() ),
			'images'             => array(
				$remote => array(
					'encoded' => self::PNG,
					'url'     => $remote,
				),
			),
			'thumbnails'         => array(),
		);

		$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'portability' => $doc ) );
		$this->assertCount( 1, $out['images_uploaded'] );
		$this->assertSame( $remote, $out['images_uploaded'][0]['from'] );
		$this->assertSame( array(), $out['images_failed'] );
		$attachment = $out['images_uploaded'][0]['id'];
		$this->assertSame( 'attachment', get_post_type( $attachment ) );
		$new_url = wp_get_attachment_url( $attachment );
		$this->assertSame( $new_url, $out['images_uploaded'][0]['to'] );

		$content = get_post( $page )->post_content;
		$this->assertStringNotContainsString( $remote, $content );
		$this->assertStringContainsString( $new_url, $content );
	}

	/**
	 * @group divi
	 */
	public function test_apply_sideloads_external_tree_images_and_keeps_failed_urls() {
		$page    = $this->page();
		$local   = home_url( '/wp-content/uploads/local.png' );
		$remote  = 'https://cdn.example.org/hero.png';
		$tree    = self::tree(
			array(
				array( 'image' => array( 'src' => $remote ) ),
				array( 'image' => array( 'src' => $local ) ),
			)
		);
		$seen    = array();
		$blocker = static function ( $pre, $args, $url ) use ( &$seen ) {
			$seen[] = $url;
			return new WP_Error( 'http_request_failed', 'blocked in test' );
		};
		add_filter( 'pre_http_request', $blocker, 10, 3 );
		try {
			$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => $tree ) );
		} finally {
			remove_filter( 'pre_http_request', $blocker, 10 );
		}
		$this->assertSame( array(), $out['images_uploaded'] );
		$this->assertCount( 1, $out['images_failed'] );
		$this->assertSame( $remote, $out['images_failed'][0]['url'] );
		$this->assertNotContains( $local, $seen, 'URLs on this site are not downloaded' );
		$content = get_post( $page )->post_content;
		$this->assertStringContainsString( $remote, $content );
		$this->assertStringContainsString( $local, $content );

		// upload_images=false: nothing fetched.
		$seen = array();
		add_filter( 'pre_http_request', $blocker, 10, 3 );
		try {
			$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => $tree, 'upload_images' => false ) );
		} finally {
			remove_filter( 'pre_http_request', $blocker, 10 );
		}
		$this->assertSame( array(), $seen );
		$this->assertSame( array(), $out['images_failed'] );
	}

	/**
	 * @group divi
	 */
	public function test_apply_merges_globals_without_overwrite() {
		Globals::merge( array( array( 'gcid-wpdm-keep', array( 'color' => '#111111' ) ) ), array(), false, false );
		$page = $this->page();
		$tree = self::text_tree();
		$tree['global_colors'] = array(
			array( 'gcid-wpdm-keep', array( 'color' => '#222222', 'status' => 'active', 'label' => 'Keep' ) ),
			array( 'gcid-wpdm-new', array( 'color' => '#333333', 'status' => 'active', 'label' => 'New' ) ),
		);
		$out = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => $tree ) );
		$this->assertSame( array( 'gcid-wpdm-new' ), $out['globals']['colors']['added'] );
		$this->assertSame( 'gcid-wpdm-keep', $out['globals']['colors']['conflicts'][0]['id'] );
		$now = Globals::read()['colors'];
		$this->assertSame( '#111111', $now['gcid-wpdm-keep']['color'] );
		$this->assertSame( '#333333', $now['gcid-wpdm-new']['color'] );

		// An editor cannot change globals: refused before anything is written.
		$page2 = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_author' => $this->editor, 'post_content' => 'editor page' ) );
		$tree['global_colors'][1][0] = 'gcid-wpdm-editor';
		$this->fails( $this->editor, 'divi_apply_layout', array( 'post_id' => $page2, 'layout' => $tree ), 'forbidden' );
		$this->assertSame( 'editor page', get_post( $page2 )->post_content );
		$this->assertSame( array(), Backups::list( $page2, 'post_content' ) );
		// ... and can apply with sync_globals=false.
		$this->ok( $this->editor, 'divi_apply_layout', array( 'post_id' => $page2, 'layout' => $tree, 'sync_globals' => false ) );
		$this->assertArrayNotHasKey( 'gcid-wpdm-editor', Globals::read()['colors'] );
	}

	/**
	 * @group divi
	 */
	public function test_apply_checks_edit_post_on_target() {
		$page = $this->page();
		$this->fails( $this->author, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => self::text_tree() ), 'forbidden' );
		$this->fails( $this->author, 'divi_export_page', array( 'post_id' => $page ), 'forbidden' );
		$this->fails( $this->author, 'divi_inspect_page', array( 'post_id' => $page ), 'forbidden' );
		$this->fails( $this->author, 'divi_set_page_css', array( 'post_id' => $page, 'css' => 'a{}' ), 'forbidden' );
		$this->assertSame( 'old content', get_post( $page )->post_content );
	}

	/**
	 * @group divi
	 */
	public function test_default_builder_version_is_site_version() {
		$out = $this->ok( $this->author, 'divi_build_layout', array( 'layout' => self::text_tree() ) );
		$this->assertStringContainsString( '"builderVersion":"' . Env::divi_version() . '"', $out['markup'] );
		$tree                    = self::text_tree();
		$tree['builder_version'] = '5.0.1';
		$out                     = $this->ok( $this->author, 'divi_build_layout', array( 'layout' => $tree ) );
		$this->assertStringContainsString( '"builderVersion":"5.0.1"', $out['markup'] );
	}

	// ----- Divi: create page ----------------------------------------------

	/**
	 * @group divi
	 */
	public function test_create_page_with_layout() {
		$out = $this->ok( $this->editor, 'divi_create_page', array( 'title' => 'Landing', 'slug' => 'landing-t13', 'layout' => self::text_tree( '<p>New page</p>' ) ) );
		$page = $out['post_id'];
		$this->assertSame( 'draft', get_post_status( $page ) );
		$this->assertSame( 'page', get_post_type( $page ) );
		$this->assertSame( 'landing-t13', get_post( $page )->post_name );
		$this->assertStringContainsString( 'New page', get_post( $page )->post_content );
		$this->assertIsInt( $out['backup_id'] );

		$empty = $this->ok( $this->editor, 'divi_create_page', array( 'title' => 'Empty' ) );
		$this->assertSame( 'draft', get_post_status( $empty['post_id'] ) );
		$this->assertSame( '', get_post( $empty['post_id'] )->post_content );

		// Invalid layout: nothing created.
		$count = count( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
		$this->fails( $this->editor, 'divi_create_page', array( 'title' => 'Broken', 'layout' => array( 'blocks' => array( array( 'text' => array( 'html' => 'x' ) ) ) ) ), 'invalid_layout' );
		$this->assertCount( $count, get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );

		// Publishing needs the publish capability.
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$this->fails( $contributor, 'divi_create_page', array( 'title' => 'X', 'status' => 'publish' ), 'forbidden' );
	}

	// ----- Divi: restore --------------------------------------------------

	/**
	 * @group divi
	 */
	public function test_restore_empty_backup_uses_fallback() {
		$created = $this->ok( $this->admin, 'divi_create_page', array( 'title' => 'Fresh' ) );
		$page    = $created['post_id'];
		$applied = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'layout' => self::text_tree() ) );
		$this->assertSame( '', Backups::get( $applied['backup_id'] )['content'] );
		$designed = get_post( $page )->post_content;

		$out = $this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $applied['backup_id'] ) );
		$this->assertSame( 'wp_update_post', $out['method'] );
		$this->assertSame( '', get_post( $page )->post_content );
		$this->assertSame( $designed, Backups::get( $out['backup_id'] )['content'], 'the replaced content is backed up first' );

		// Restoring that safety backup goes through Divi.
		$again = $this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $out['backup_id'] ) );
		$this->assertSame( 'set-layout', $again['method'] );
		$this->assertSame( $designed, get_post( $page )->post_content );
	}

	/**
	 * @group divi
	 */
	public function test_restore_published_needs_confirm_and_restores_css() {
		$page = $this->page( 'publish', self::text_markup( 'Original' ) );
		update_post_meta( $page, '_et_pb_custom_css', '.orig{}' );
		$applied = $this->ok( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'markup' => self::text_markup( 'Replaced' ), 'confirm_published' => true ) );
		$this->ok( $this->admin, 'divi_set_page_css', array( 'post_id' => $page, 'css' => '.changed{}', 'confirm_published' => true ) );

		$this->fails( $this->admin, 'divi_restore_backup', array( 'backup_id' => $applied['backup_id'] ), 'confirmation_required' );
		$this->assertStringContainsString( 'Replaced', get_post( $page )->post_content );

		$this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $applied['backup_id'], 'confirm_published' => true ) );
		$this->assertStringContainsString( 'Original', get_post( $page )->post_content );
		$this->assertSame( '.orig{}', get_post_meta( $page, '_et_pb_custom_css', true ) );
	}

	/**
	 * @group divi
	 */
	public function test_restore_globals_backup() {
		$merged = $this->ok( $this->admin, 'divi_merge_globals', array( 'colors' => array( array( 'gcid-wpdm-rb', array( 'color' => '#445566' ) ) ) ) );
		$this->assertArrayHasKey( 'gcid-wpdm-rb', Globals::read()['colors'] );
		$out = $this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $merged['backup_id'] ) );
		$this->assertSame( 'globals', $out['kind'] );
		$this->assertArrayNotHasKey( 'gcid-wpdm-rb', Globals::read()['colors'] );
	}

	/**
	 * @group divi
	 */
	public function test_restore_theme_builder_not_supported_yet() {
		$id = Backups::save( 'theme_builder', 0, 'x' );
		$this->fails( $this->admin, 'divi_restore_backup', array( 'backup_id' => $id ), 'not_supported_yet' );
	}

	/**
	 * @group divi
	 */
	public function test_backup_tools_respect_capabilities() {
		$mine   = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $this->author, 'post_content' => 'mine' ) );
		$theirs = $this->page( 'draft', 'theirs' );
		wp_set_current_user( $this->admin );
		$b_mine    = Backups::save( 'post_content', $mine, 'mine' );
		$b_theirs  = Backups::save( 'post_content', $theirs, 'theirs' );
		$b_globals = Backups::save( 'globals', 0, wp_json_encode( Globals::read() ) );

		$list = $this->ok( $this->author, 'divi_list_backups' );
		$ids  = array_column( $list['backups'], 'id' );
		$this->assertContains( $b_mine, $ids );
		$this->assertNotContains( $b_theirs, $ids );
		$this->assertNotContains( $b_globals, $ids );

		$this->assertSame( array(), $this->ok( $this->author, 'divi_list_backups', array( 'kind' => 'globals' ) )['backups'] );
		$this->fails( $this->author, 'divi_list_backups', array( 'post_id' => $theirs ), 'forbidden' );

		$this->fails( $this->author, 'divi_restore_backup', array( 'backup_id' => $b_theirs ), 'forbidden' );
		$this->fails( $this->author, 'divi_restore_backup', array( 'backup_id' => $b_globals ), 'forbidden' );
		$this->fails( $this->editor, 'divi_restore_backup', array( 'backup_id' => $b_globals ), 'forbidden' );
		$this->fails( $this->author, 'divi_restore_backup', array( 'backup_id' => 999999 ), 'not_found' );
		$this->assertSame( 'theirs', get_post( $theirs )->post_content );

		$admin_list = $this->ok( $this->admin, 'divi_list_backups', array( 'limit' => 200 ) );
		$admin_ids  = array_column( $admin_list['backups'], 'id' );
		$this->assertContains( $b_theirs, $admin_ids );
		$this->assertContains( $b_globals, $admin_ids );
		$this->assertCount( 1, $this->ok( $this->admin, 'divi_list_backups', array( 'limit' => 1 ) )['backups'] );

		$restored = $this->ok( $this->author, 'divi_restore_backup', array( 'backup_id' => $b_mine ) );
		$this->assertSame( $mine, $restored['post_id'] );
	}

	// ----- Divi: export / inspect / css -----------------------------------

	/**
	 * @group divi
	 */
	public function test_export_includes_only_used_globals() {
		Globals::merge(
			array(
				array( 'gcid-wpdm-used', array( 'color' => '#0a0a0a', 'label' => 'Used' ) ),
				array( 'gcid-wpdm-unused', array( 'color' => '#0b0b0b', 'label' => 'Unused' ) ),
			),
			array(
				array( 'id' => 'gvid-wpdm-used', 'type' => 'numbers', 'value' => '8px', 'label' => 'Gap' ),
				array( 'id' => 'gvid-wpdm-unused', 'type' => 'numbers', 'value' => '9px', 'label' => 'Other' ),
			),
			false,
			false
		);
		$markup = '<!-- wp:divi/section {"module":{"decoration":{"background":{"desktop":{"value":{"color":"$variable({\u0022type\u0022:\u0022color\u0022,\u0022value\u0022:{\u0022name\u0022:\u0022gcid-wpdm-used\u0022}})$"}}},"spacing":{"desktop":{"value":{"padding":{"top":"$variable({\u0022type\u0022:\u0022content\u0022,\u0022value\u0022:{\u0022name\u0022:\u0022gvid-wpdm-used\u0022}})$"}}}}}}} --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Export me</p>"}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section -->';
		$page   = $this->page( 'publish', $markup );
		update_post_meta( $page, '_et_pb_custom_css', '.p{}' );

		$out = $this->ok( $this->admin, 'divi_export_page', array( 'post_id' => $page ) );
		$doc = $out['portability'];
		$this->assertSame( 'et_builder', $doc['context'] );
		$this->assertSame( '<!-- wp:divi/placeholder -->' . $markup . '<!-- /wp:divi/placeholder -->', $doc['data'][ (string) $page ] );
		$this->assertSame( array( 'gcid-wpdm-used' ), array_column( $doc['global_colors'], 0 ) );
		$this->assertSame( '#0a0a0a', $doc['global_colors'][0][1]['color'] );
		$this->assertSame( array( 'gvid-wpdm-used' ), array_column( $doc['global_variables'], 'id' ) );
		$this->assertSame( 'numbers', $doc['global_variables'][0]['type'] );
		$this->assertSame( array( '.p{}' ), $doc['page_settings_meta']['_et_pb_custom_css'] );
		$this->assertStringContainsString( 'SECTION 1', $out['summary'] );
		$this->assertSame( "SECTION 1\n  row\n    column", $out['summary'], 'summary is the tree at depth 3' );

		// The export validates and applies elsewhere.
		$this->assertTrue( $this->ok( $this->admin, 'divi_validate_layout', array( 'portability' => $doc ) )['ok'] );
	}

	/**
	 * @group divi
	 */
	public function test_inspect_tree_marks_hidden_sections() {
		$hidden = '{"module":{"decoration":{"disabledOn":{"desktop":{"value":"on"},"tablet":{"value":"on"},"phone":{"value":"on"}}}}}';
		$partly = '{"module":{"decoration":{"disabledOn":{"desktop":{"value":"on"}}}}}';
		$markup = '<!-- wp:divi/placeholder -->'
			. str_replace( array( '<!-- wp:divi/placeholder -->', '<!-- /wp:divi/placeholder -->' ), '', self::text_markup( 'Visible' ) )
			. str_replace( array( '<!-- wp:divi/placeholder -->', '<!-- /wp:divi/placeholder -->' ), '', self::text_markup( 'Gone', $hidden ) )
			. str_replace( array( '<!-- wp:divi/placeholder -->', '<!-- /wp:divi/placeholder -->' ), '', self::text_markup( 'Desktop only hidden', $partly ) )
			. '<!-- /wp:divi/placeholder -->';
		$page   = $this->page( 'draft', $markup );

		$tree  = $this->ok( $this->admin, 'divi_inspect_page', array( 'post_id' => $page ) )['tree'];
		$lines = explode( "\n", $tree );
		$this->assertSame( 'SECTION 1', $lines[0] );
		$this->assertSame( '      text  Visible', $lines[3] );
		$sec2 = array_values( preg_grep( '/^SECTION 2/', $lines ) );
		$this->assertStringStartsWith( 'SECTION 2 [HIDDEN]', $sec2[0] );
		$sec3 = array_values( preg_grep( '/^SECTION 3/', $lines ) );
		$this->assertStringNotContainsString( '[HIDDEN]', $sec3[0] );
		$this->assertContains( '  row', $lines );

		$shallow = $this->ok( $this->admin, 'divi_inspect_page', array( 'post_id' => $page, 'depth' => 1 ) )['tree'];
		$this->assertCount( 3, explode( "\n", $shallow ) );

		$section = $this->ok( $this->admin, 'divi_inspect_page', array( 'post_id' => $page, 'mode' => 'section', 'n' => 2 ) )['section'];
		$this->assertSame( 'divi/section', $section['name'] );
		$this->assertSame( 'on', $section['attrs']['module']['decoration']['disabledOn']['phone']['value'] );
		$this->assertSame( 'divi/row', $section['children'][0]['name'] );
		$this->fails( $this->admin, 'divi_inspect_page', array( 'post_id' => $page, 'mode' => 'section', 'n' => 9 ), 'invalid_argument' );

		$example = $this->ok( $this->admin, 'divi_inspect_page', array( 'post_id' => $page, 'mode' => 'example', 'block' => 'divi/text', 'n' => 2 ) );
		$this->assertSame( 3, $example['count'] );
		$this->assertCount( 2, $example['examples'] );
		$this->assertSame( '<p>Visible</p>', $example['examples'][0]['attrs']['content']['innerContent']['desktop']['value'] );

		$paths = $this->ok( $this->admin, 'divi_inspect_page', array( 'post_id' => $page, 'mode' => 'paths', 'block' => 'divi/section' ) )['paths'];
		$this->assertSame( array( 'divi/section' ), array_keys( $paths ) );
		$this->assertSame( 'on', $paths['divi/section']['module.decoration.disabledOn.desktop.value'] );
	}

	/**
	 * @group divi
	 */
	public function test_set_page_css_backs_up_and_needs_confirm_on_published() {
		$page = $this->page( 'publish', self::text_markup() );
		update_post_meta( $page, '_et_pb_custom_css', '.before{}' );
		$this->fails( $this->admin, 'divi_set_page_css', array( 'post_id' => $page, 'css' => '.after{}' ), 'confirmation_required' );
		$this->assertSame( '.before{}', get_post_meta( $page, '_et_pb_custom_css', true ) );

		$out = $this->ok( $this->admin, 'divi_set_page_css', array( 'post_id' => $page, 'css' => '.after{color:red}', 'confirm_published' => true ) );
		$this->assertSame( '.after{color:red}', get_post_meta( $page, '_et_pb_custom_css', true ) );
		$this->assertSame( '.before{}', Backups::get( $out['backup_id'] )['meta']['custom_css'] );
	}

	// ----- review fixes ---------------------------------------------------

	/**
	 * Make Divi's set-layout route fail.
	 */
	private static function break_set_layout(): callable {
		$filter = static function ( $result, $server, $request ) {
			if ( '/divi/v1/outside-vb/posts/set-layout' === $request->get_route() ) {
				return new WP_Error( 'wpdm_test_down', 'set-layout is down', array( 'status' => 500 ) );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		return $filter;
	}

	private static function portability( string $markup, array $extra = array() ): array {
		return array_merge(
			array(
				'context'            => 'et_builder',
				'data'               => array( '1' => $markup ),
				'presets'            => null,
				'global_colors'      => array(),
				'global_variables'   => array(),
				'page_settings_meta' => array( '_et_pb_custom_css' => array( '' ) ),
				'canvases'           => array( 'local' => array(), 'global' => array() ),
				'images'             => array(),
				'thumbnails'         => array(),
			),
			$extra
		);
	}

	private static function image_markup( string $url ): string {
		return '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/image {"image":{"innerContent":{"desktop":{"value":{"src":"' . $url . '","alt":"A"}}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
	}

	/**
	 * @group divi
	 */
	public function test_create_publish_with_layout_never_empty_live() {
		$seen     = array();
		$watch    = static function ( $new, $old, $post ) use ( &$seen ) {
			if ( 'page' === $post->post_type ) {
				$seen[] = array( $new, (string) get_post_field( 'post_content', $post->ID, 'raw' ) );
			}
		};
		add_action( 'transition_post_status', $watch, 10, 3 );
		try {
			$out = $this->ok( $this->admin, 'divi_create_page', array( 'title' => 'Live', 'status' => 'publish', 'layout' => self::text_tree( '<p>Live now</p>' ) ) );
		} finally {
			remove_action( 'transition_post_status', $watch, 10 );
		}
		$this->assertSame( 'publish', get_post_status( $out['post_id'] ) );
		$this->assertSame( 'publish', $out['status'] );
		$this->assertStringContainsString( 'Live now', get_post( $out['post_id'] )->post_content );
		$this->assertContains( 'publish', array_column( $seen, 0 ) );
		foreach ( $seen as $event ) {
			if ( 'publish' === $event[0] ) {
				$this->assertStringContainsString( 'Live now', $event[1], 'published only once the layout is in' );
			}
		}

		// private works the same way.
		$private = $this->ok( $this->admin, 'divi_create_page', array( 'title' => 'Members', 'status' => 'private', 'layout' => self::text_tree() ) );
		$this->assertSame( 'private', get_post_status( $private['post_id'] ) );

		// A failed apply never leaves a published page behind.
		$seen   = array();
		$broken = self::break_set_layout();
		add_action( 'transition_post_status', $watch, 10, 3 );
		try {
			$fail = $this->fails( $this->admin, 'divi_create_page', array( 'title' => 'Broken live', 'status' => 'publish', 'layout' => self::text_tree() ), 'wpdm_test_down' );
		} finally {
			remove_action( 'transition_post_status', $watch, 10 );
			remove_filter( 'rest_pre_dispatch', $broken, 10 );
		}
		$this->assertNotContains( 'publish', array_column( $seen, 0 ) );
		$this->assertArrayHasKey( 'page_trashed', $fail['data'] );
		$this->assertSame( 'trash', get_post_status( $fail['data']['page_trashed'] ) );
	}

	/**
	 * @group divi
	 */
	public function test_apply_images_require_upload_files() {
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$post        = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $contributor, 'post_content' => 'draft text' ) );
		$remote      = 'https://images.example.org/c.png';
		$doc         = self::portability( self::image_markup( $remote ), array( 'images' => array( $remote => array( 'encoded' => self::PNG, 'url' => $remote ) ) ) );
		$attachments = count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );

		$err = $this->fails( $contributor, 'divi_apply_layout', array( 'post_id' => $post, 'portability' => $doc ), 'forbidden' );
		$this->assertStringContainsString( 'upload_images=false', $err['message'] );
		$this->assertSame( 'draft text', get_post( $post )->post_content );
		$this->assertSame( array(), Backups::list( $post, 'post_content' ) );

		// External tree images too.
		$this->fails( $contributor, 'divi_apply_layout', array( 'post_id' => $post, 'layout' => self::tree( array( array( 'image' => array( 'src' => $remote ) ) ) ) ), 'forbidden' );

		// Dry run warns instead.
		$dry = $this->ok( $contributor, 'divi_apply_layout', array( 'post_id' => $post, 'portability' => $doc, 'dry_run' => true ) );
		$this->assertStringContainsString( 'cannot upload media', implode( "\n", $dry['warnings'] ) );

		// upload_images=false keeps the original URLs.
		$this->ok( $contributor, 'divi_apply_layout', array( 'post_id' => $post, 'portability' => $doc, 'upload_images' => false ) );
		$this->assertStringContainsString( $remote, get_post( $post )->post_content );
		$this->assertCount( $attachments, get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
	}

	/**
	 * @group divi
	 */
	public function test_set_layout_failure_restores_css_and_reports_backups() {
		$page = $this->page( 'draft', self::text_markup( 'Before' ) );
		update_post_meta( $page, '_et_pb_custom_css', '.old{}' );
		$remote = 'https://images.example.org/f.png';
		$doc    = self::portability(
			self::image_markup( $remote ),
			array(
				'images'             => array( $remote => array( 'encoded' => self::PNG, 'url' => $remote ) ),
				'global_colors'      => array( array( 'gcid-wpdm-fail', array( 'color' => '#121212', 'status' => 'active', 'label' => 'Fail' ) ) ),
				'page_settings_meta' => array( '_et_pb_custom_css' => array( '.new{}' ) ),
			)
		);

		$broken = self::break_set_layout();
		try {
			$err = $this->fails( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'portability' => $doc ), 'wpdm_test_down' );
		} finally {
			remove_filter( 'rest_pre_dispatch', $broken, 10 );
		}
		$data = $err['data'];
		$this->assertSame( 'set_layout', $data['step'] );
		$this->assertSame( self::text_markup( 'Before' ), Backups::get( $data['backup_id'] )['content'] );
		$this->assertIsInt( $data['globals_backup_id'] );
		$this->assertSame( 'globals', Backups::get( $data['globals_backup_id'] )['kind'] );
		$this->assertCount( 1, $data['uploaded_ids'] );
		$this->assertSame( 'attachment', get_post_type( $data['uploaded_ids'][0] ) );
		$this->assertTrue( $data['css_restored'] );

		$this->assertSame( '.old{}', get_post_meta( $page, '_et_pb_custom_css', true ) );
		$this->assertStringContainsString( 'Before', get_post( $page )->post_content );
		// Globals are reported, not rolled back.
		$this->assertArrayHasKey( 'gcid-wpdm-fail', Globals::read()['colors'] );

		// A non-ToolError failure is reported the same way, as internal_error.
		$throw = static function ( $result, $server, $request ) {
			if ( '/divi/v1/outside-vb/posts/set-layout' === $request->get_route() ) {
				throw new RuntimeException( 'kaboom' );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $throw, 10, 3 );
		try {
			$err = $this->fails( $this->admin, 'divi_apply_layout', array( 'post_id' => $page, 'portability' => $doc, 'upload_images' => false ), 'internal_error' );
		} finally {
			remove_filter( 'rest_pre_dispatch', $throw, 10 );
		}
		$this->assertIsInt( $err['data']['backup_id'] );
		$this->assertTrue( $err['data']['css_restored'] );
		$this->assertSame( '.old{}', get_post_meta( $page, '_et_pb_custom_css', true ) );
	}
}
