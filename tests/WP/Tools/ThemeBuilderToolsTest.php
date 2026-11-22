<?php
/**
 * Tests for the Theme Builder tools.
 *
 * Every test works on its own sandbox template (deleted in tear_down) and
 * never writes to the Default Website Template. A spy on rest_pre_dispatch
 * checks that every Theme Builder request made during a tool call has
 * live === true (Divi's draft Theme Builder starts empty and would replace
 * the published one if saved).
 *
 * @package WPDM
 */

use WPDM\Divi\Backups;
use WPDM\Divi\DiviRest;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Tools\Divi\Registrar;

class ThemeBuilderToolsTest extends WP_UnitTestCase {

	const TB_TOOLS = array(
		'divi_list_theme_builder_templates',
		'divi_create_theme_builder_template',
		'divi_delete_theme_builder_template',
		'divi_set_theme_builder_layout',
	);

	/** @var int */
	private $admin;
	/** @var int */
	private $editor;

	/** @var int[] Sandbox templates to delete in tear_down. */
	private $sandboxes = array();

	/** @var array[] Theme Builder requests seen: route + live. */
	private $seen = array();

	/** @var callable */
	private $spy;

	public function set_up() {
		parent::set_up();
		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->seen   = array();
		$this->spy    = function ( $result, $server, $request ) {
			$route = $request->get_route();
			if ( 0 === strpos( $route, '/divi/v1/outside-vb/theme-builder/' ) ) {
				$this->seen[] = array(
					'route' => $route,
					'live'  => $request->get_param( 'live' ),
				);
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $this->spy, 10, 3 );
	}

	public function tear_down() {
		wp_set_current_user( $this->admin );
		foreach ( $this->sandboxes as $id ) {
			if ( 'publish' === get_post_status( $id ) ) {
				DiviRest::call( '/outside-vb/theme-builder/delete-template', array( 'live' => true, 'template_id' => $id ) );
			}
		}
		remove_filter( 'rest_pre_dispatch', $this->spy, 10 );
		$seen = $this->seen;
		parent::tear_down();
		wp_set_current_user( 0 );
		foreach ( $seen as $req ) {
			$this->assertTrue( true === $req['live'], 'live must be exactly true: ' . wp_json_encode( $req ) );
		}
	}

	// ----- helpers ---------------------------------------------------------

	private function call( int $user_id, string $tool, array $args = array() ): array {
		wp_set_current_user( $user_id );
		$server   = new Server( Registrar::add( new Registry() ), '' );
		$response = $server->handle(
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

	private function fails( int $user_id, string $tool, array $args, string $code ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertTrue( $result['isError'] ?? false, 'Expected isError for ' . $tool );
		$this->assertSame( $code, $result['structuredContent']['error'] ?? null, $result['content'][0]['text'] ?? '' );
		return $result['structuredContent'];
	}

	private function sandbox( string $title = 'WPDM sandbox' ): int {
		$out                = $this->ok( $this->admin, 'divi_create_theme_builder_template', array( 'title' => $title ) );
		$this->sandboxes[] = $out['template_id'];
		return $out['template_id'];
	}

	private function find( int $template_id ): ?array {
		$list = $this->ok( $this->admin, 'divi_list_theme_builder_templates' );
		foreach ( $list['templates'] as $t ) {
			if ( (int) $t['id'] === $template_id ) {
				return $t;
			}
		}
		return null;
	}

	private function default_template_id(): int {
		$list = $this->ok( $this->admin, 'divi_list_theme_builder_templates' );
		foreach ( $list['templates'] as $t ) {
			if ( ! empty( $t['default'] ) ) {
				return (int) $t['id'];
			}
		}
		$this->fail( 'No Default Website Template in the live Theme Builder.' );
	}

	private static function header_tree( string $text = 'Header' ): array {
		return array(
			'blocks' => array(
				array( 'section' => array( 'children' => array( array( 'row' => array( 'children' => array( array( 'column' => array( 'children' => array( array( 'text' => array( 'html' => '<p>' . $text . '</p>' ) ) ) ) ) ) ) ) ) ) ),
			),
		);
	}

	private static function layout_content( int $template_id, string $type ): string {
		$id = (int) get_post_meta( $template_id, '_et_' . $type . '_layout_id', true );
		clean_post_cache( $id );
		return $id > 0 ? (string) get_post_field( 'post_content', $id, 'raw' ) : '';
	}

	// ----- registration (no Divi needed) -----------------------------------

	public function test_annotations_and_capabilities() {
		$tools = array();
		foreach ( Registrar::tools() as $tool ) {
			$tools[ $tool->name() ] = $tool;
		}
		foreach ( self::TB_TOOLS as $name ) {
			$this->assertArrayHasKey( $name, $tools );
			$this->assertSame( 'edit_theme_options', $tools[ $name ]->capability(), $name );
		}
		$this->assertTrue( $tools['divi_list_theme_builder_templates']->read_only() );
		$this->assertFalse( $tools['divi_list_theme_builder_templates']->destructive() );
		$this->assertFalse( $tools['divi_create_theme_builder_template']->read_only() );
		$this->assertFalse( $tools['divi_create_theme_builder_template']->destructive() );
		$this->assertTrue( $tools['divi_delete_theme_builder_template']->destructive() );
		$this->assertTrue( $tools['divi_set_theme_builder_layout']->destructive() );

		$schema = $tools['divi_set_theme_builder_layout']->input_schema();
		$this->assertSame( array( 'header', 'footer' ), $schema['properties']['layout_type']['enum'] );
		$this->assertArrayHasKey( 'layout', $schema['properties'] );
		$this->assertArrayHasKey( 'confirm_default_template', $schema['properties'] );
	}

	public function test_hidden_without_divi() {
		$names = array();
		foreach ( Registrar::add( new Registry() )->tools( new Context( $this->admin ) ) as $tool ) {
			$names[] = $tool->name();
		}
		foreach ( self::TB_TOOLS as $name ) {
			$this->assertNotContains( $name, $names );
		}
	}

	// ----- Divi ------------------------------------------------------------

	/**
	 * @group divi
	 */
	public function test_create_template_is_unassigned() {
		$id = $this->sandbox( 'WPDM unassigned' );
		$t  = $this->find( $id );
		$this->assertNotNull( $t, 'listed in the live Theme Builder' );
		$this->assertFalse( (bool) $t['default'] );
		$this->assertSame( 'WPDM unassigned', $t['title'] );
		$this->assertSame( array(), $t['use_on'] );
		$this->assertSame( array(), $t['exclude_from'] );
		$this->assertSame( 0, (int) $t['layouts']['header']['id'] );
		$this->assertSame( 0, (int) $t['layouts']['footer']['id'] );
	}

	/**
	 * @group divi
	 */
	public function test_editor_cannot_use_theme_builder_tools() {
		$this->fails( $this->editor, 'divi_list_theme_builder_templates', array(), 'forbidden' );
	}

	/**
	 * @group divi
	 */
	public function test_delete_default_refused() {
		$this->sandbox(); // Makes sure a default template exists.
		$default = $this->default_template_id();
		$this->fails( $this->admin, 'divi_delete_theme_builder_template', array( 'template_id' => $default ), 'refused' );
		$this->assertSame( 'publish', get_post_status( $default ) );
		$this->assertNotNull( $this->find( $default ) );
	}

	/**
	 * @group divi
	 */
	public function test_delete_missing_is_not_found() {
		$this->fails( $this->admin, 'divi_delete_theme_builder_template', array( 'template_id' => 999999 ), 'not_found' );
	}

	/**
	 * @group divi
	 */
	public function test_delete_backs_up_layouts() {
		$id  = $this->sandbox( 'WPDM to delete' );
		$set = $this->ok( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'header', 'layout' => self::header_tree( 'Doomed header' ) ) );
		$out = $this->ok( $this->admin, 'divi_delete_theme_builder_template', array( 'template_id' => $id ) );
		$this->assertTrue( $out['ok'] );
		$this->assertNull( $this->find( $id ) );
		$this->assertCount( 1, $out['backups'] );
		$backup = Backups::get( $out['backups'][0]['backup_id'] );
		$this->assertSame( 'theme_builder', $backup['kind'] );
		$this->assertSame( $id, $backup['source_id'] );
		$this->assertStringContainsString( 'Doomed header', $backup['content'] );
		$this->assertSame( 'header', $backup['meta']['layout_type'] );
		$this->assertSame( $set['layout_id'], $backup['meta']['layout_id'] );
		$this->assertSame( 'WPDM to delete', $backup['meta']['template_title'] );
		$this->assertTrue( $backup['meta']['deleted'] );

		// The template is gone: restore cannot put the layout back.
		$err = $this->fails( $this->admin, 'divi_restore_backup', array( 'backup_id' => $backup['id'] ), 'template_missing' );
		$this->assertSame( $backup['id'], $err['data']['content_backup_id'] );
	}

	/**
	 * @group divi
	 */
	public function test_set_layout_default_requires_confirm() {
		$this->sandbox();
		$default = $this->default_template_id();
		$before  = self::layout_content( $default, 'header' );
		$count   = count( Backups::list( $default, 'theme_builder' ) );

		$err = $this->fails( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $default, 'layout_type' => 'header', 'layout' => self::header_tree() ), 'confirmation_required' );
		$this->assertStringContainsString( 'whole site', $err['message'] );
		$this->assertSame( $before, self::layout_content( $default, 'header' ) );
		$this->assertCount( $count, Backups::list( $default, 'theme_builder' ), 'no backup, no write' );
	}

	/**
	 * @group divi
	 */
	public function test_set_layout_refuses_missing_template_and_invalid_layout() {
		$this->fails( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => 999999, 'layout_type' => 'header', 'layout' => self::header_tree() ), 'not_found' );
		$id = $this->sandbox();
		$this->fails( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'body', 'layout' => self::header_tree() ), 'invalid_argument' );
		$bad = array( 'blocks' => array( array( 'text' => array( 'html' => '<p>no section</p>' ) ) ) );
		$this->fails( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'header', 'layout' => $bad ), 'invalid_layout' );
		$this->assertSame( array(), Backups::list( $id, 'theme_builder' ) );
	}

	/**
	 * @group divi
	 */
	public function test_set_layout_backs_up_previous() {
		$id = $this->sandbox();

		$first = $this->ok( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'footer', 'layout' => self::header_tree( 'First footer' ) ) );
		$this->assertTrue( $first['ok'] );
		$this->assertSame( $id, $first['template_id'] );
		$this->assertSame( 'footer', $first['layout_type'] );
		$this->assertSame( 0, $first['previous_layout_id'] );
		$this->assertGreaterThan( 0, $first['layout_id'] );
		$created = Backups::get( $first['backup_id'] );
		$this->assertSame( '', $created['content'] );
		$this->assertTrue( $created['meta']['created'] );
		$this->assertStringContainsString( 'First footer', self::layout_content( $id, 'footer' ) );

		$first_content = self::layout_content( $id, 'footer' );
		$second        = $this->ok( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'footer', 'markup' => '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>Second footer</p>"}}}} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->' ) );
		$this->assertSame( $first['layout_id'], $second['previous_layout_id'] );
		$prev = Backups::get( $second['backup_id'] );
		$this->assertSame( 'theme_builder', $prev['kind'] );
		$this->assertSame( $id, $prev['source_id'] );
		$this->assertSame( $first_content, $prev['content'] );
		$this->assertSame( 'footer', $prev['meta']['layout_type'] );
		$this->assertSame( $first['layout_id'], $prev['meta']['layout_id'] );
		$this->assertStringContainsString( 'Second footer', self::layout_content( $id, 'footer' ) );

		// Restore puts the first footer back, after backing up the second.
		$restored = $this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $second['backup_id'] ) );
		$this->assertSame( 'theme_builder', $restored['kind'] );
		$this->assertSame( $id, $restored['template_id'] );
		$this->assertStringContainsString( 'First footer', self::layout_content( $id, 'footer' ) );
		$this->assertStringContainsString( 'Second footer', Backups::get( $restored['backup_id'] )['content'] );

		// The "created" backup has nothing to restore.
		$err = $this->fails( $this->admin, 'divi_restore_backup', array( 'backup_id' => $first['backup_id'] ), 'not_supported' );
		$this->assertStringContainsString( 'Theme Builder', $err['message'] );
	}

	/**
	 * @group divi
	 */
	public function test_restore_to_default_template_requires_confirm() {
		$this->sandbox();
		$default = $this->default_template_id();
		$before  = self::layout_content( $default, 'header' );
		// A backup as set_layout would have written it; restoring it is refused without confirm.
		$id = Backups::save( 'theme_builder', $default, '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->', array( 'layout_type' => 'header', 'layout_id' => 1 ) );
		$err = $this->fails( $this->admin, 'divi_restore_backup', array( 'backup_id' => $id ), 'confirmation_required' );
		$this->assertStringContainsString( 'whole site', $err['message'] );
		$this->assertSame( $before, self::layout_content( $default, 'header' ) );
	}

	/**
	 * @group divi
	 */
	public function test_never_calls_with_live_false() {
		$id = $this->sandbox( 'WPDM live spy' );
		$this->ok( $this->admin, 'divi_list_theme_builder_templates' );
		$set = $this->ok( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'header', 'layout' => self::header_tree() ) );
		$this->ok( $this->admin, 'divi_set_theme_builder_layout', array( 'template_id' => $id, 'layout_type' => 'header', 'layout' => self::header_tree( 'Again' ) ) );
		$second = Backups::list( $id, 'theme_builder', 1 )[0]['id'];
		$this->ok( $this->admin, 'divi_restore_backup', array( 'backup_id' => $second ) );
		$this->ok( $this->admin, 'divi_delete_theme_builder_template', array( 'template_id' => $id ) );
		$this->assertGreaterThan( 0, $set['layout_id'] );

		$routes = array_unique( array_column( $this->seen, 'route' ) );
		foreach ( array( 'list-templates', 'create-template', 'upsert-layout', 'delete-template' ) as $r ) {
			$this->assertContains( '/divi/v1/outside-vb/theme-builder/' . $r, $routes );
		}
		foreach ( $this->seen as $req ) {
			$this->assertTrue( true === $req['live'], 'live must be exactly true: ' . wp_json_encode( $req ) );
		}

		// The spy itself sees a false live flag (read-only draft listing, nothing written).
		$count = count( $this->seen );
		DiviRest::call( '/outside-vb/theme-builder/list-templates', array( 'live' => false ) );
		$this->assertFalse( $this->seen[ $count ]['live'] );
		array_pop( $this->seen ); // Not a tool call.
	}
}
