<?php
/**
 * Tests for the Yoast SEO tools (seo_get, seo_update), against real Yoast.
 *
 * @package WPDM
 * @group yoast
 */

use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Mcp\ToolError;
use WPDM\Seo\Yoast;
use WPDM\Tools\Seo\Registrar;

/**
 * @group yoast
 */
class SeoToolsTest extends WP_UnitTestCase {

	/** @var int */
	private $admin;
	/** @var int */
	private $author;
	/** @var int */
	private $author2;

	public function set_up() {
		parent::set_up();
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->markTestSkipped( 'Yoast SEO is not loaded (run with WPDM_TEST_YOAST=1).' );
		}
		$this->admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->author2 = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	// ----- helpers ---------------------------------------------------------

	private function context( int $user_id, bool $read_only = false ): Context {
		return new Context( $user_id, array( 'id' => 'c1', 'label' => 'Test connection', 'mode' => $read_only ? 'read' : 'full' ), $read_only );
	}

	private function call( int $user_id, string $tool, array $args = array(), bool $read_only = false ): array {
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
			$this->context( $user_id, $read_only )
		);
		$this->assertArrayHasKey( 'result', $response, 'Expected a JSON-RPC result' );
		return json_decode( wp_json_encode( $response['result'] ), true );
	}

	private function ok( int $user_id, string $tool, array $args = array() ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) );
		return $result['structuredContent'];
	}

	private function error_code( int $user_id, string $tool, array $args ): string {
		wp_set_current_user( $user_id );
		$instance = Registrar::add( new Registry() )->tool( $tool );
		$this->assertNotNull( $instance, $tool . ' not registered' );
		try {
			$instance->execute( $args, $this->context( $user_id ) );
		} catch ( ToolError $e ) {
			return $e->error_code();
		}
		$this->fail( 'Expected ToolError' );
		return '';
	}

	private function post( array $extra = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'page',
					'post_title'  => 'Lodi Junk Removal',
					'post_status' => 'publish',
					'post_author' => $this->admin,
				),
				$extra
			)
		);
	}

	// ----- registration ----------------------------------------------------

	public function test_tools_registered_with_annotations() {
		$server = new Server( Registrar::add( new Registry() ), '' );
		$res    = $server->handle( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list' ), $this->context( $this->admin ) );
		$tools  = array();
		foreach ( $res['result']['tools'] as $tool ) {
			$tools[ $tool['name'] ] = $tool;
		}
		$this->assertArrayHasKey( 'seo_get', $tools );
		$this->assertArrayHasKey( 'seo_update', $tools );
		$this->assertTrue( $tools['seo_get']['annotations']['readOnlyHint'] );
		$this->assertFalse( $tools['seo_update']['annotations']['readOnlyHint'] );
	}

	public function test_tools_hidden_without_yoast() {
		add_filter( 'wpdm_yoast_active', '__return_false' );
		$registry = Registrar::add( new Registry() );
		remove_filter( 'wpdm_yoast_active', '__return_false' );
		$this->assertNull( $registry->tool( 'seo_get' ) );
		$this->assertNull( $registry->tool( 'seo_update' ) );
	}

	// ----- seo_get ---------------------------------------------------------

	public function test_seo_get_unset() {
		$id   = $this->post();
		$data = $this->ok( $this->admin, 'seo_get', array( 'id' => $id ) );
		$this->assertSame( $id, $data['id'] );
		$this->assertSame( '', $data['title'] );
		$this->assertSame( '', $data['description'] );
		$this->assertStringContainsString( 'Lodi Junk Removal', $data['effective_title'] );
		$this->assertSame( WPSEO_VERSION, $data['yoast_version'] );
	}

	public function test_seo_get_unexposed_type_not_found() {
		$parent = $this->post();
		$rev    = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $parent,
				'post_name'   => $parent . '-revision-v1',
			)
		);
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'seo_get', array( 'id' => $rev ) ) );
	}

	public function test_seo_get_published_other_author_readable_draft_not() {
		$published = $this->post( array( 'post_type' => 'post', 'post_author' => $this->author2 ) );
		$draft     = $this->post( array( 'post_type' => 'post', 'post_author' => $this->author2, 'post_status' => 'draft' ) );
		$this->assertSame( $published, $this->ok( $this->author, 'seo_get', array( 'id' => $published ) )['id'] );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'seo_get', array( 'id' => $draft ) ) );
	}

	// ----- seo_update ------------------------------------------------------

	public function test_seo_update_writes_and_returns_previous() {
		$id  = $this->post();
		$old = 'Old description long enough to pass fifty characters.';
		$this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => 'Old', 'description' => $old ) );
		$data = $this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => 'New title' ) );
		$this->assertSame( 'Old', $data['previous']['title'] );
		$this->assertSame( $old, $data['previous']['description'] );
		$this->assertSame( 'New title', $data['title'] );
		$this->assertSame( $old, $data['description'] );
		$this->assertStringContainsString( 'New title', $data['effective_title'] );
	}

	public function test_seo_update_effective_changes_immediately() {
		$id = $this->post();
		$this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => 'Fresh immediate title' ) );
		$this->assertStringContainsString( 'Fresh immediate title', Yoast::effective( $id )['title'] );
	}

	public function test_seo_update_empty_resets_to_template() {
		$id = $this->post();
		$this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => 'Custom' ) );
		$data = $this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => '' ) );
		$this->assertSame( '', $data['title'] );
		$this->assertSame( '', Yoast::stored( $id )['title'] );
		$this->assertStringContainsString( 'Lodi Junk Removal', $data['effective_title'] );
	}

	public function test_seo_update_keeps_yoast_variables() {
		$id   = $this->post();
		$tpl  = '%%title%% %%sep%% %%sitename%%';
		$data = $this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => $tpl ) );
		$this->assertSame( $tpl, Yoast::stored( $id )['title'] );
		$this->assertStringContainsString( 'Lodi Junk Removal', $data['effective_title'] );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $data['effective_title'] );
		$this->assertStringNotContainsString( '%%', $data['effective_title'] );
	}

	public function test_seo_update_strips_html() {
		$id = $this->post();
		$this->ok( $this->admin, 'seo_update', array( 'id' => $id, 'title' => "<b>Bold</b>\nTitle" ) );
		$this->assertSame( 'Bold Title', Yoast::stored( $id )['title'] );
	}

	public function test_seo_update_requires_a_field() {
		$id = $this->post();
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'seo_update', array( 'id' => $id ) ) );
		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_title' ) );
	}

	public function test_seo_update_forbidden_on_other_authors_post() {
		$id = $this->post( array( 'post_type' => 'post', 'post_author' => $this->author2 ) );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'seo_update', array( 'id' => $id, 'title' => 'Hijack' ) ) );
		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_title' ) );
	}

	public function test_seo_update_blocked_on_read_only_connection() {
		$id     = $this->post();
		$result = $this->call( $this->admin, 'seo_update', array( 'id' => $id, 'title' => 'X' ), true );
		$this->assertTrue( $result['isError'] ?? false );
		$this->assertStringContainsString( 'read-only', $result['content'][0]['text'] );
		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_title' ) );
	}

	public function test_seo_update_length_warnings() {
		$id   = $this->post();
		$long = $this->ok(
			$this->admin,
			'seo_update',
			array(
				'id'          => $id,
				'title'       => str_repeat( 'a', 70 ),
				'description' => str_repeat( 'd', 30 ),
			)
		);
		$this->assertCount( 2, $long['warnings'] );
		$this->assertStringContainsString( '60', implode( ' ', $long['warnings'] ) );
		$this->assertStringContainsString( '50', implode( ' ', $long['warnings'] ) );

		$fine = $this->ok(
			$this->admin,
			'seo_update',
			array(
				'id'          => $id,
				'title'       => str_repeat( 'a', 60 ),
				'description' => str_repeat( 'd', 120 ),
			)
		);
		$this->assertSame( array(), $fine['warnings'] );
	}
}
