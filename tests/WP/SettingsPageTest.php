<?php
/**
 * Tests for the Settings > Divi MCP screen and its form handlers.
 *
 * @package WPDM
 */

use WPDM\Admin\SettingsPage;
use WPDM\Auth\Tokens;
use WPDM\Log\ActivityLog;
use WPDM\Mcp\Context;

class SettingsPageTest extends WP_UnitTestCase {

	/** @var int */
	private $admin_id;

	/** @var string|null Last redirect location caught. */
	private $redirect = null;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$wpdb->suppress_errors( true );
		ActivityLog::install();
		$wpdb->suppress_errors( false );
		$wpdb->query( 'DELETE FROM ' . ActivityLog::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		$this->redirect = null;
		add_filter(
			'wp_redirect',
			function ( $location ) {
				$this->redirect = $location;
				return false; // Short-circuits wp_redirect(): no header, no exit.
			}
		);
	}

	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
		parent::tear_down();
	}

	private function post( string $action, array $fields, bool $with_nonce = true ): void {
		$_POST = $fields;
		if ( $with_nonce ) {
			$_POST['_wpnonce'] = wp_create_nonce( $action );
		}
		$_REQUEST = $_POST;
	}

	private function create_post( array $override = array() ): array {
		return array_merge(
			array(
				'label'   => 'Claude Desktop',
				'user_id' => (string) $this->admin_id,
				'mode'    => 'read',
				'expires' => '0',
			),
			$override
		);
	}

	private function render(): string {
		ob_start();
		try {
			SettingsPage::render();
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	// --- create ---------------------------------------------------------

	public function test_create_connection_requires_manage_options() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $editor ) ) );

		$this->expectException( WPDieException::class );
		try {
			SettingsPage::handle_create();
		} finally {
			$this->assertSame( array(), Tokens::all() );
		}
	}

	public function test_create_connection_requires_nonce() {
		$this->post( 'wpdm_create', $this->create_post(), false );

		$this->expectException( WPDieException::class );
		try {
			SettingsPage::handle_create();
		} finally {
			$this->assertSame( array(), Tokens::all() );
		}
	}

	public function test_create_connection_stores_it_and_redirects_without_token() {
		$before = time();
		$this->post( 'wpdm_create', $this->create_post( array( 'mode' => 'full', 'expires' => '30' ) ) );
		SettingsPage::handle_create();

		$all = array_values( Tokens::all() );
		$this->assertCount( 1, $all );
		$this->assertSame( 'Claude Desktop', $all[0]['label'] );
		$this->assertSame( 'full', $all[0]['mode'] );
		$this->assertSame( $this->admin_id, $all[0]['user_id'] );
		$this->assertEqualsWithDelta( $before + 30 * DAY_IN_SECONDS, $all[0]['expires'], 5 );

		$this->assertNotNull( $this->redirect );
		$this->assertStringContainsString( 'page=wp-divi-mcp', $this->redirect );
		$this->assertStringNotContainsString( 'key=', $this->redirect );
		$this->assertDoesNotMatchRegularExpression( '/[a-f0-9]{64}/', $this->redirect );

		$transient = get_transient( 'wpdm_new_conn_' . $this->admin_id );
		$this->assertIsArray( $transient );
		$this->assertSame( 'Claude Desktop', $transient['label'] );
	}

	public function test_create_with_no_expiry_sets_null() {
		$this->post( 'wpdm_create', $this->create_post() );
		SettingsPage::handle_create();
		$this->assertNull( array_values( Tokens::all() )[0]['expires'] );
	}

	public function test_create_rejects_user_without_edit_posts() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $subscriber ) ) );
		SettingsPage::handle_create();

		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_user', (string) $this->redirect );
	}

	public function test_create_for_other_user_current_user_can_edit() {
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id ); // Site admins cannot edit other users on multisite.
		}
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $editor ) ) );
		SettingsPage::handle_create();
		$this->assertSame( $editor, array_values( Tokens::all() )[0]['user_id'] );
	}

	public function test_create_rejects_user_current_user_cannot_edit() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $uid, $args ) use ( $editor ) {
				return ( 'edit_user' === $cap && (int) ( $args[0] ?? 0 ) === $editor ) ? array( 'do_not_allow' ) : $caps;
			},
			10,
			4
		);
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $editor ) ) );
		SettingsPage::handle_create();
		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_user', (string) $this->redirect );
		$this->assertStringNotContainsString( 'value="' . $editor . '"', $this->render() );
	}

	public function test_create_rejects_super_admin_target_unless_super_admin() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site semantics; see test_multisite_site_admin_cannot_mint_for_super_admin.' );
		}
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$me    = $this->admin_id;
		// Single site: is_super_admin() means delete_users. Strip it from the
		// current admin only, so the target is a super admin and the minter is not.
		add_filter(
			'user_has_cap',
			static function ( $allcaps, $caps, $args, $user ) use ( $me ) {
				if ( (int) $user->ID === $me ) {
					unset( $allcaps['delete_users'] );
				}
				return $allcaps;
			},
			10,
			4
		);
		$this->assertTrue( is_super_admin( $other ) );
		$this->assertFalse( is_super_admin( $me ) );
		$this->assertTrue( current_user_can( 'edit_user', $other ) );

		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $other ) ) );
		SettingsPage::handle_create();
		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_user', (string) $this->redirect );

		// Minting for oneself is still fine.
		$this->post( 'wpdm_create', $this->create_post() );
		SettingsPage::handle_create();
		$this->assertCount( 1, Tokens::all() );
	}

	public function test_create_by_super_admin_for_super_admin_ok() {
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id );
			grant_super_admin( $other );
		}
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $other ) ) );
		SettingsPage::handle_create();
		$this->assertSame( $other, array_values( Tokens::all() )[0]['user_id'] );
	}

	/**
	 * Run with WP_MULTISITE=1 bin/test --filter SettingsPageTest.
	 */
	public function test_multisite_create_rejects_user_not_member_of_this_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only (WP_MULTISITE=1 bin/test).' );
		}
		grant_super_admin( $this->admin_id );
		$outsider = self::factory()->user->create( array( 'role' => 'editor' ) );
		$blog     = self::factory()->blog->create( array( 'user_id' => $this->admin_id ) );
		remove_user_from_blog( $outsider, get_current_blog_id() );
		add_user_to_blog( $blog, $outsider, 'editor' );
		// Grant edit_posts here anyway, so only the membership rule can refuse.
		add_filter(
			'user_has_cap',
			static function ( $allcaps, $caps, $args, $user ) use ( $outsider ) {
				if ( (int) $user->ID === $outsider ) {
					$allcaps['edit_posts'] = true;
				}
				return $allcaps;
			},
			10,
			4
		);
		$this->assertTrue( user_can( $outsider, 'edit_posts' ) );
		$this->assertFalse( is_user_member_of_blog( $outsider ) );
		$this->assertTrue( current_user_can( 'edit_user', $outsider ) );

		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $outsider ) ) );
		SettingsPage::handle_create();
		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_user', (string) $this->redirect );
	}

	public function test_multisite_site_admin_cannot_mint_for_super_admin() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only (WP_MULTISITE=1 bin/test).' );
		}
		$super = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super );
		$this->assertFalse( is_super_admin( $this->admin_id ) );
		$this->post( 'wpdm_create', $this->create_post( array( 'user_id' => (string) $super ) ) );
		SettingsPage::handle_create();
		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_user', (string) $this->redirect );
	}

	public function test_create_rejects_bad_input() {
		foreach ( array(
			array( 'label' => '   ' ),
			array( 'mode' => 'root' ),
			array( 'expires' => '9999' ),
			array( 'user_id' => '999999' ),
		) as $bad ) {
			$this->post( 'wpdm_create', $this->create_post( $bad ) );
			SettingsPage::handle_create();
		}
		$this->assertSame( array(), Tokens::all() );
	}

	public function test_create_ignores_array_valued_fields() {
		$this->post( 'wpdm_create', $this->create_post( array( 'label' => array( 'x' ) ) ) );
		SettingsPage::handle_create();
		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'err_label', (string) $this->redirect );
	}

	public function test_create_connection_shows_url_once() {
		$this->post( 'wpdm_create', $this->create_post() );
		SettingsPage::handle_create();

		$_GET['wpdm_notice'] = 'created';
		$first               = $this->render();
		$second              = $this->render();

		$this->assertSame( 1, preg_match( '/value="([^"]*\?[^"]*key=([a-f0-9]{64})[^"]*)"/', str_replace( '&#038;', '&', $first ), $m ) );
		$token = $m[2];
		$this->assertStringContainsString( 'claude mcp add --transport http claude-desktop', $first );
		$this->assertStringContainsString( 'data-wpdm-copy', $first );
		$this->assertStringNotContainsString( $token, $second );
		$this->assertStringNotContainsString( 'data-wpdm-copy', $second );
		$this->assertFalse( get_transient( 'wpdm_new_conn_' . $this->admin_id ) );
	}

	public function test_desktop_download_link_in_new_connection_box() {
		$this->post( 'wpdm_create', $this->create_post( array( 'label' => 'Desk' ) ) );
		SettingsPage::handle_create();
		$connections = array_values( Tokens::all() );
		$this->assertCount( 1, $connections );
		$id = $connections[0]['id'];

		$first  = $this->render();
		$second = $this->render();

		$this->assertStringContainsString( 'download="' . \WPDM\Admin\Mcpb::filename( 'Desk', $id ) . '"', $first );
		$this->assertSame( 1, preg_match( '/href="data:application\/zip;base64,([A-Za-z0-9+\/=]+)"/', $first, $m ) );
		$this->assertSame( 1, preg_match( '/id="wpdm-url" value="([^"]+)"/', $first, $u ) );
		$url = html_entity_decode( $u[1], ENT_QUOTES );

		$file = wp_tempnam( 'mcpb-page' );
		file_put_contents( $file, base64_decode( $m[1] ) );
		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $file ) );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$zip->close();
		unlink( $file );
		$this->assertSame( $url, $manifest['server']['mcp_config']['env']['WPDM_URL'] );

		$this->assertStringNotContainsString( 'data:application/zip', $second );
	}

	public function test_desktop_row_without_ziparchive() {
		add_filter( 'wpdm_mcpb_available', '__return_false' );
		$this->post( 'wpdm_create', $this->create_post() );
		SettingsPage::handle_create();
		$html = $this->render();
		remove_filter( 'wpdm_mcpb_available', '__return_false' );
		$this->assertStringNotContainsString( 'data:application/zip', $html );
		$this->assertStringContainsString( 'ZipArchive', $html );
		$this->assertStringContainsString( 'id="wpdm-url"', $html );
	}

	public function test_desktop_build_error_keeps_the_box() {
		$break = static function () {
			return array( 'not a path' ); // file_get_contents( array ) throws a TypeError.
		};
		add_filter( 'wpdm_mcpb_server_path', $break );
		$this->post( 'wpdm_create', $this->create_post() );
		SettingsPage::handle_create();
		$html = $this->render();
		remove_filter( 'wpdm_mcpb_server_path', $break );

		$this->assertStringContainsString( 'id="wpdm-url"', $html );
		$this->assertStringContainsString( 'id="wpdm-cmd"', $html );
		$this->assertStringNotContainsString( 'data:application/zip', $html );
		$this->assertStringContainsString( 'Could not build the Claude Desktop file', $html );
		$this->assertStringContainsString( '</div>', $html );
	}

	public function test_desktop_link_without_connection_id_in_transient() {
		set_transient(
			'wpdm_new_conn_' . $this->admin_id,
			array(
				'url'   => 'https://example.test/?key=abc',
				'token' => 'abc',
				'label' => 'Old',
			),
			300
		);
		$html = $this->render();
		$this->assertMatchesRegularExpression( '/download="wp-divi-mcp-[a-z0-9-]+\.mcpb"/', $html );
	}

	public function test_command_uses_wp_divi_mcp_slug_for_unsluggable_label() {
		set_transient(
			'wpdm_new_conn_' . $this->admin_id,
			array(
				'url'   => 'https://example.test/?key=abc',
				'token' => 'abc',
				'label' => '!!!',
			),
			300
		);
		$this->assertStringContainsString( 'claude mcp add --transport http wp-divi-mcp', $this->render() );
	}

	public function test_new_connection_transient_is_per_user() {
		set_transient( 'wpdm_new_conn_' . ( $this->admin_id + 1 ), array( 'url' => 'https://example.test/?key=zzz', 'token' => 'zzz', 'label' => 'x' ), 300 );
		$this->assertStringNotContainsString( 'zzz', $this->render() );
	}

	// --- revoke ---------------------------------------------------------

	public function test_revoke_requires_nonce() {
		$created = Tokens::create( 'Keep me', $this->admin_id, 'read', null );
		$this->post( 'wpdm_revoke', array( 'connection_id' => $created['id'] ), false );

		$this->expectException( WPDieException::class );
		try {
			SettingsPage::handle_revoke();
		} finally {
			$this->assertArrayHasKey( $created['id'], Tokens::all() );
		}
	}

	public function test_revoke_requires_manage_options() {
		$created = Tokens::create( 'Keep me', $this->admin_id, 'read', null );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->post( 'wpdm_revoke', array( 'connection_id' => $created['id'] ) );

		$this->expectException( WPDieException::class );
		try {
			SettingsPage::handle_revoke();
		} finally {
			$this->assertArrayHasKey( $created['id'], Tokens::all() );
		}
	}

	public function test_revoke_removes_connection_and_redirects() {
		$created = Tokens::create( 'Drop me', $this->admin_id, 'read', null );
		$this->post( 'wpdm_revoke', array( 'connection_id' => $created['id'] ) );
		SettingsPage::handle_revoke();

		$this->assertSame( array(), Tokens::all() );
		$this->assertStringContainsString( 'wpdm_notice=revoked', (string) $this->redirect );
	}

	// --- settings -------------------------------------------------------

	public function test_disable_endpoint_toggle() {
		$this->post( 'wpdm_settings', array( 'wpdm_disabled' => '1' ) );
		SettingsPage::handle_settings();
		$this->assertTrue( (bool) get_option( 'wpdm_disabled' ) );
		$this->assertStringContainsString( 'checked', $this->render() );

		$this->post( 'wpdm_settings', array() );
		SettingsPage::handle_settings();
		$this->assertFalse( (bool) get_option( 'wpdm_disabled' ) );
	}

	public function test_settings_require_nonce_and_capability() {
		$this->post( 'wpdm_settings', array( 'wpdm_disabled' => '1' ), false );
		try {
			SettingsPage::handle_settings();
			$this->fail( 'Expected wp_die.' );
		} catch ( WPDieException $e ) {
			$this->assertFalse( (bool) get_option( 'wpdm_disabled' ) );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->post( 'wpdm_settings', array( 'wpdm_disabled' => '1' ) );
		$this->expectException( WPDieException::class );
		SettingsPage::handle_settings();
	}

	// --- render ---------------------------------------------------------

	public function test_render_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( WPDieException::class );
		$this->render();
	}

	public function test_companion_notice_when_active() {
		$this->assertStringNotContainsString( 'Companion', $this->render() );

		update_option( 'active_plugins', array( 'divi5-mcp-companion/divi5-mcp-companion.php' ) );
		$html = $this->render();
		$this->assertStringContainsString( 'Companion', $html );
		$this->assertStringContainsString( 'Deactivate', $html );
	}

	public function test_https_warning_on_http_site() {
		$html = $this->render();
		$this->assertStringContainsString( 'not served over HTTPS', $html ); // Test site is http://.
	}

	public function test_render_lists_connections_and_escapes_output() {
		update_option(
			Tokens::OPTION,
			array(
				'aabbccddeeff' => array(
					'id'        => 'aabbccddeeff',
					'label'     => '<script>alert(1)</script>',
					'user_id'   => $this->admin_id,
					'mode'      => 'full',
					'hash'      => 'nothash',
					'created'   => time(),
					'expires'   => null,
					'last_used' => time(),
					'last_ip'   => '"><img src=x onerror=1>',
				),
			)
		);
		$html = $this->render();

		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( 'value="aabbccddeeff"', $html ); // Revoke form.
		$this->assertStringNotContainsString( 'nothash', $html );
	}

	public function test_render_lists_activity_and_escapes_it() {
		$ctx = new Context( $this->admin_id, array( 'id' => 'aabbccddeeff', 'label' => '<b>lbl</b>' ) );
		ActivityLog::record( 'update_post', array( 'post_id' => 77 ), true, 5, $ctx );
		$html = $this->render();

		$this->assertStringContainsString( 'update_post', $html );
		$this->assertStringContainsString( '77', $html );
		$this->assertStringContainsString( '&lt;b&gt;lbl&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>lbl</b>', $html );
	}

	public function test_user_select_lists_only_users_who_can_edit_posts() {
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id ); // Site admins cannot edit other users on multisite.
		}
		$author     = self::factory()->user->create( array( 'role' => 'author', 'display_name' => 'Ann Author' ) );
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber', 'display_name' => 'Sue Subscriber' ) );
		$html       = $this->render();

		$this->assertStringContainsString( 'value="' . $author . '"', $html );
		$this->assertStringNotContainsString( 'value="' . $subscriber . '"', $html );
		$this->assertMatchesRegularExpression( '/<option value="' . $this->admin_id . '" selected=\'selected\'>/', $html );
	}

	public function test_stylesheet_enqueued_only_on_own_page() {
		SettingsPage::add_menu();
		wp_styles()->dequeue( 'wpdm-admin' );
		SettingsPage::enqueue( 'edit.php' );
		$this->assertFalse( wp_style_is( 'wpdm-admin', 'enqueued' ) );

		// The suffix add_options_page() returned (settings_page_wp-divi-mcp in a real admin request).
		$property = ( new ReflectionClass( SettingsPage::class ) )->getProperty( 'hook' );
		$property->setAccessible( true );
		$hook = $property->getValue();
		$this->assertStringContainsString( 'wp-divi-mcp', (string) $hook );

		SettingsPage::enqueue( $hook );
		$this->assertTrue( wp_style_is( 'wpdm-admin', 'enqueued' ) );
	}

	public function test_menu_page_registered_under_settings() {
		global $submenu;
		wp_set_current_user( $this->admin_id );
		SettingsPage::add_menu();
		$slugs = wp_list_pluck( $submenu['options-general.php'] ?? array(), 2 );
		$this->assertContains( 'wp-divi-mcp', $slugs );
	}
}
