<?php
/**
 * Tests for the WordPress content tools (posts and pages of any type).
 *
 * Tools are exercised through a real Server with a Registry built by
 * Registrar::add, acting as a factory-created user.
 *
 * @package WPDM
 */

use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\Registrar;

class ContentToolsTest extends WP_UnitTestCase {

	const EXPECTED_TOOLS = array(
		'site_info',
		'list_post_types',
		'list_taxonomies',
		'list_posts',
		'get_post',
		'create_post',
		'update_post',
		'trash_post',
		'restore_post',
		'search',
	);

	/** @var int */
	private $admin;
	/** @var int */
	private $editor;
	/** @var int */
	private $author;
	/** @var int */
	private $author2;
	/** @var int */
	private $contributor;
	/** @var int */
	private $subscriber;

	public function set_up() {
		parent::set_up();
		$this->admin       = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->author      = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->author2     = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$this->subscriber  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tear_down() {
		if ( taxonomy_exists( 'topic' ) ) {
			unregister_taxonomy( 'topic' );
		}
		unregister_meta_key( 'post', '_wpdm_test' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	// ----- helpers ---------------------------------------------------------

	private function context( int $user_id, bool $read_only = false ): Context {
		return new Context( $user_id, array( 'id' => 'c1', 'label' => 'Test connection', 'mode' => $read_only ? 'read' : 'full' ), $read_only );
	}

	private function server(): Server {
		return new Server( Registrar::add( new Registry() ), '' );
	}

	/**
	 * Run tools/call over a real Server and return the wire-format result
	 * (JSON round-tripped).
	 */
	private function call( int $user_id, string $tool, array $args = array(), bool $read_only = false ): array {
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
			$this->context( $user_id, $read_only )
		);
		$this->assertArrayHasKey( 'result', $response, 'Expected a JSON-RPC result' );
		return json_decode( wp_json_encode( $response['result'] ), true );
	}

	/**
	 * Call a tool and assert success; returns structuredContent.
	 */
	private function ok( int $user_id, string $tool, array $args = array() ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) );
		$this->assertArrayHasKey( 'structuredContent', $result );
		return $result['structuredContent'];
	}

	/**
	 * Call a tool and assert a tool error; returns the message.
	 */
	private function fails( int $user_id, string $tool, array $args = array(), string $contains = '' ): string {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertTrue( $result['isError'] ?? false, 'Expected isError for ' . $tool );
		$message = $result['content'][0]['text'];
		if ( '' !== $contains ) {
			$this->assertStringContainsString( $contains, $message );
		}
		return $message;
	}

	/**
	 * Execute a tool directly and return the ToolError code it throws.
	 */
	private function error_code( int $user_id, string $tool, array $args ): string {
		wp_set_current_user( $user_id );
		$instance = Registrar::add( new Registry() )->tool( $tool );
		try {
			$instance->execute( $args, $this->context( $user_id ) );
		} catch ( ToolError $e ) {
			return $e->error_code();
		}
		$this->fail( 'Expected ToolError' );
		return '';
	}

	private function make_divi_page( array $extra = array() ): int {
		$id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Divi page',
					'post_content' => '<!-- wp:divi/section {} --><!-- /wp:divi/section -->',
				),
				$extra
			)
		);
		update_post_meta( $id, '_et_pb_use_builder', 'on' );
		return $id;
	}

	// ----- registration ----------------------------------------------------

	public function test_registered_tool_names_exactly_match_and_no_delete() {
		$registry = Registrar::add( new Registry() );
		$ctx      = $this->context( $this->admin );
		wp_set_current_user( $this->admin );

		$names = array_map(
			static function ( $tool ) {
				return $tool->name();
			},
			$registry->tools( $ctx )
		);
		sort( $names );
		$expected = self::EXPECTED_TOOLS;
		sort( $expected );
		$this->assertSame( $expected, $names );
	}

	public function test_no_permanent_delete_tool_registered() {
		$registry = Registrar::add( new Registry() );
		wp_set_current_user( $this->admin );
		foreach ( $registry->tools( $this->context( $this->admin ) ) as $tool ) {
			$this->assertStringNotContainsStringIgnoringCase( 'delete', $tool->name() );
		}
		$this->assertNull( $registry->tool( 'delete_post' ) );
	}

	public function test_registrar_is_hooked_into_registry_filter() {
		$registry = apply_filters( 'wpdm_registry', new Registry() );
		$this->assertNotNull( $registry->tool( 'create_post' ) );
	}

	public function test_annotations_and_schemas() {
		$registry = Registrar::add( new Registry() );
		$read     = array( 'site_info', 'list_post_types', 'list_taxonomies', 'list_posts', 'get_post', 'search' );
		$destruct = array( 'trash_post', 'update_post' );
		foreach ( self::EXPECTED_TOOLS as $name ) {
			$tool = $registry->tool( $name );
			$this->assertSame( in_array( $name, $read, true ), $tool->read_only(), "$name read_only" );
			$this->assertSame( in_array( $name, $destruct, true ), $tool->destructive(), "$name destructive" );

			$schema = $tool->input_schema();
			$this->assertSame( 'object', $schema['type'], $name );
			$this->assertFalse( $schema['additionalProperties'], $name );
			$this->assertArrayHasKey( 'required', $schema, $name );
			$this->assertArrayHasKey( 'properties', $schema, $name );
			if ( is_array( $schema['properties'] ) ) {
				$this->assertNotEmpty( $schema['properties'], "$name: an empty properties map must be a stdClass" );
				foreach ( $schema['properties'] as $prop => $def ) {
					$this->assertArrayHasKey( 'type', $def, "$name.$prop type" );
					$this->assertArrayHasKey( 'description', $def, "$name.$prop description" );
				}
				foreach ( $schema['required'] as $req ) {
					$this->assertArrayHasKey( $req, $schema['properties'], "$name required $req" );
				}
			} else {
				$this->assertInstanceOf( stdClass::class, $schema['properties'] );
			}
		}
	}

	public function test_tools_list_reports_annotations_over_the_wire() {
		wp_set_current_user( $this->editor );
		$response = $this->server()->handle( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list' ), $this->context( $this->editor ) );
		$tools    = array();
		foreach ( $response['result']['tools'] as $t ) {
			$tools[ $t['name'] ] = $t;
		}
		$this->assertCount( 10, $tools );
		$this->assertTrue( $tools['get_post']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['trash_post']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['create_post']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['restore_post']['annotations']['destructiveHint'] );
	}

	public function test_read_only_connection_cannot_write() {
		$result = $this->call( $this->admin, 'create_post', array( 'title' => 'Nope' ), true );
		$this->assertTrue( $result['isError'] );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'page', 'title' => 'Nope', 'post_status' => 'any' ) ) );

		$ok = $this->call( $this->admin, 'list_posts', array(), true );
		$this->assertArrayNotHasKey( 'isError', $ok );
	}

	// ----- create_post -----------------------------------------------------

	public function test_create_post_defaults_to_draft() {
		$out = $this->call( $this->editor, 'create_post', array( 'post_type' => 'post', 'title' => 'Hello', 'content' => '<p>Body</p>' ) );
		$this->assertArrayNotHasKey( 'isError', $out );
		$data = $out['structuredContent'];

		$this->assertSame( 'draft', $data['status'] );
		$this->assertSame( 'post', $data['type'] );
		$this->assertSame( 'Hello', $data['title'] );
		$this->assertSame( '<p>Body</p>', $data['content_raw'] );
		$this->assertFalse( $data['divi'] );

		$post = get_post( $data['id'] );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( $this->editor, (int) $post->post_author );
	}

	public function test_create_post_post_type_defaults_to_page() {
		$data = $this->ok( $this->editor, 'create_post', array( 'title' => 'A page' ) );
		$this->assertSame( 'page', $data['type'] );
		$this->assertSame( 'draft', $data['status'] );
	}

	public function test_create_page_with_parent_and_terms() {
		register_taxonomy(
			'topic',
			'page',
			array(
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
			)
		);
		$parent   = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$existing = self::factory()->term->create( array( 'taxonomy' => 'topic', 'name' => 'Existing' ) );

		$data = $this->ok(
			$this->admin,
			'create_post',
			array(
				'post_type' => 'page',
				'title'     => 'Child',
				'slug'      => 'my-child',
				'parent'    => $parent,
				'terms'     => array( 'topic' => array( $existing, 'Brand New' ) ),
			)
		);

		$this->assertSame( $parent, $data['parent'] );
		$this->assertSame( 'my-child', $data['slug'] );
		$new = get_term_by( 'name', 'Brand New', 'topic' );
		$this->assertNotFalse( $new );
		$ids = $data['terms']['topic'];
		sort( $ids );
		$expected = array( $existing, $new->term_id );
		sort( $expected );
		$this->assertSame( $expected, $ids );
		$this->assertEqualSets( $expected, wp_get_object_terms( $data['id'], 'topic', array( 'fields' => 'ids' ) ) );
	}

	public function test_unknown_taxonomy_and_bad_term_ids_are_errors() {
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'post', 'title' => 'x', 'terms' => array( 'nope' => array( 'a' ) ) ), 'nope' );
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'post', 'title' => 'x', 'terms' => array( 'category' => array( 999999 ) ) ) );
		// Taxonomy not attached to the post type.
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'page', 'title' => 'x', 'terms' => array( 'category' => array( 'News' ) ) ) );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'any', 'title' => 'x', 'post_status' => 'any' ) ), 'A failed create must not leave a post behind' );
		$this->assertFalse( get_term_by( 'name', 'News', 'category' ) );
	}

	public function test_new_term_names_require_edit_terms_capability() {
		// Authors may assign existing tags but cannot create new ones.
		self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Known' ) );
		$data = $this->ok( $this->author, 'create_post', array( 'post_type' => 'post', 'title' => 'T', 'terms' => array( 'post_tag' => array( 'Known' ) ) ) );
		$this->assertCount( 1, $data['terms']['post_tag'] );

		$this->fails( $this->author, 'create_post', array( 'post_type' => 'post', 'title' => 'T2', 'terms' => array( 'post_tag' => array( 'Unknown tag' ) ) ) );
		$this->assertFalse( get_term_by( 'name', 'Unknown tag', 'post_tag' ) );
	}

	public function test_status_restrictions() {
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'status' => 'trash' ) );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'status' => 'auto-draft' ) );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'status' => 'bogus' ) );
		// future needs a date, and it must be in the future.
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'status' => 'future' ), 'date' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'status' => 'future', 'date' => '2001-01-01 10:00:00' ), 'future' );
		// date without future status is refused.
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'date' => '2090-01-01 10:00:00' ) );

		$data = $this->ok( $this->admin, 'create_post', array( 'title' => 'Scheduled', 'status' => 'future', 'date' => '2090-01-01 10:00:00' ) );
		$this->assertSame( 'future', $data['status'] );

		$data = $this->ok( $this->admin, 'create_post', array( 'title' => 'Live', 'status' => 'publish' ) );
		$this->assertSame( 'publish', $data['status'] );
	}

	public function test_publishing_requires_publish_capability() {
		$pending = $this->ok( $this->contributor, 'create_post', array( 'post_type' => 'post', 'title' => 'C', 'status' => 'pending' ) );
		$this->assertSame( 'pending', $pending['status'] );

		$this->fails( $this->contributor, 'create_post', array( 'post_type' => 'post', 'title' => 'C2', 'status' => 'publish' ) );
		$this->fails( $this->contributor, 'create_post', array( 'post_type' => 'post', 'title' => 'C3', 'status' => 'private' ) );
		$this->fails( $this->contributor, 'create_post', array( 'post_type' => 'post', 'title' => 'C4', 'status' => 'future', 'date' => '2090-01-01 10:00:00' ) );
		// Contributors cannot create pages at all.
		$this->fails( $this->contributor, 'create_post', array( 'post_type' => 'page', 'title' => 'P' ) );

		$this->assertSame( array(), get_posts( array( 'post_type' => 'post', 'title' => 'C2', 'post_status' => 'any' ) ) );

		// Contributors cannot publish by updating either.
		$this->fails( $this->contributor, 'update_post', array( 'id' => $pending['id'], 'status' => 'publish' ) );
		$this->assertSame( 'pending', get_post_status( $pending['id'] ) );
	}

	public function test_create_validates_input() {
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'no_such_type', 'title' => 'x' ), 'no_such_type' );
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'attachment', 'title' => 'x' ) );
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'revision', 'title' => 'x' ) );
		$this->fails( $this->admin, 'create_post', array( 'title' => 123 ), 'title' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'parent' => '5' ), 'parent' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'terms' => 'category' ), 'terms' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'meta' => array( 1, 2 ) ), 'meta' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'surprise' => true ), 'surprise' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'x', 'parent' => 999999 ) );
		// A non-hierarchical type has no parents.
		$post = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'post', 'title' => 'x', 'parent' => $post ) );
	}

	public function test_create_stores_content_with_slashes_and_quotes_intact() {
		$content = '<p class="a">He said "hi" \\ and it\'s fine</p>';
		$data    = $this->ok( $this->admin, 'create_post', array( 'title' => 'Quote "t" \\ x', 'content' => $content, 'excerpt' => "It's" ) );
		$post    = get_post( $data['id'] );
		$this->assertSame( $content, $post->post_content );
		$this->assertSame( 'Quote "t" \\ x', $post->post_title );
		$this->assertSame( "It's", $post->post_excerpt );
	}

	public function test_parent_must_be_readable() {
		$private = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_author' => $this->admin ) );
		$this->fails( $this->author, 'create_post', array( 'post_type' => 'page', 'title' => 'x', 'parent' => $private ) );
	}

	public function test_create_with_meta_rules() {
		$data = $this->ok( $this->admin, 'create_post', array( 'title' => 'M', 'meta' => array( 'color' => 'red', 'sizes' => array( 1, 2 ) ) ) );
		$this->assertSame( 'red', get_post_meta( $data['id'], 'color', true ) );
		$this->assertSame( array( 1, 2 ), get_post_meta( $data['id'], 'sizes', true ) );
		$this->assertSame( 'red', $data['meta']['color'] );

		// Underscore keys are refused unless registered with show_in_rest.
		$this->fails( $this->admin, 'create_post', array( 'title' => 'M2', 'meta' => array( '_et_pb_use_builder' => 'on' ) ), '_et_pb_use_builder' );
		$this->fails( $this->admin, 'create_post', array( 'title' => 'M3', 'meta' => array( '_edit_lock' => '1' ) ) );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'page', 'title' => 'M2', 'post_status' => 'any' ) ) );

		register_post_meta( 'page', '_wpdm_test', array( 'show_in_rest' => true, 'single' => true, 'type' => 'string', 'auth_callback' => '__return_true' ) );
		$data = $this->ok( $this->admin, 'create_post', array( 'title' => 'M4', 'meta' => array( '_wpdm_test' => 'yes' ) ) );
		$this->assertSame( 'yes', get_post_meta( $data['id'], '_wpdm_test', true ) );
		// Protected keys are never echoed back.
		$this->assertArrayNotHasKey( '_wpdm_test', $data['meta'] ?? array() );
	}

	public function test_create_with_featured_media() {
		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => 'img.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$data = $this->ok( $this->admin, 'create_post', array( 'post_type' => 'post', 'title' => 'F', 'featured_media' => $attachment ) );
		$this->assertSame( $attachment, $data['featured_media'] );
		$this->assertSame( $attachment, (int) get_post_thumbnail_id( $data['id'] ) );

		$notmedia = self::factory()->post->create();
		$this->fails( $this->admin, 'create_post', array( 'post_type' => 'post', 'title' => 'F2', 'featured_media' => $notmedia ) );
	}

	// ----- update_post -----------------------------------------------------

	public function test_update_post_changes_fields() {
		$parent = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$id     = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Old', 'post_content' => 'old', 'post_author' => $this->editor ) );
		$before = get_post( $id );

		$data = $this->ok(
			$this->editor,
			'update_post',
			array(
				'id'      => $id,
				'title'   => 'New',
				'content' => 'new <b>content</b>',
				'excerpt' => 'ex',
				'slug'    => 'new-slug',
				'parent'  => $parent,
				'status'  => 'publish',
			)
		);
		$this->assertSame( 'New', $data['title'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( 'new-slug', $data['slug'] );
		$this->assertSame( $parent, $data['parent'] );
		$this->assertSame( 'new <b>content</b>', get_post( $id )->post_content );
		$this->assertSame( $before->post_type, get_post( $id )->post_type );

		// A partial update leaves the rest alone.
		$this->ok( $this->editor, 'update_post', array( 'id' => $id, 'title' => 'Only title' ) );
		$this->assertSame( 'new <b>content</b>', get_post( $id )->post_content );
		$this->assertSame( 'Only title', get_post( $id )->post_title );
	}

	public function test_update_refuses_a_page_as_its_own_parent_and_unknown_id() {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->fails( $this->admin, 'update_post', array( 'id' => $id, 'parent' => $id ) );
		$this->fails( $this->admin, 'update_post', array( 'id' => 999999, 'title' => 'x' ), 'not found' );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'update_post', array( 'id' => 999999, 'title' => 'x' ) ) );
		$this->fails( $this->admin, 'update_post', array( 'title' => 'x' ), 'id' );
	}

	public function test_update_refuses_hierarchy_loops() {
		$a = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$b = self::factory()->post->create( array( 'post_type' => 'page', 'post_parent' => $a ) );
		$this->fails( $this->admin, 'update_post', array( 'id' => $a, 'parent' => $b ), 'ancestor' );
		$this->assertSame( 0, (int) get_post( $a )->post_parent );
	}

	public function test_update_refuses_divi_content_without_force() {
		$id = $this->make_divi_page();
		$original = get_post( $id )->post_content;

		$message = $this->fails( $this->admin, 'update_post', array( 'id' => $id, 'content' => '<p>clobber</p>' ), 'divi_apply_layout' );
		$this->assertNotSame( '', $message );
		$this->assertSame( $original, get_post( $id )->post_content );

		// force is not available yet (Task 12 replaces this).
		$message = $this->fails( $this->admin, 'update_post', array( 'id' => $id, 'content' => '<p>clobber</p>', 'force' => true ) );
		$this->assertSame( 'force is not available until backups are implemented', $message );
		$this->assertSame( 'force_unavailable', $this->error_code( $this->admin, 'update_post', array( 'id' => $id, 'content' => 'x', 'force' => true ) ) );
		$this->assertSame( $original, get_post( $id )->post_content );

		// Non-content edits without force are still fine.
		$data = $this->ok( $this->admin, 'update_post', array( 'id' => $id, 'title' => 'Renamed' ) );
		$this->assertSame( 'Renamed', $data['title'] );
		$this->assertTrue( $data['divi'] );
		$this->assertSame( $original, get_post( $id )->post_content );
	}

	public function test_update_detects_divi_by_block_markup_without_meta() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:divi/placeholder --><!-- /wp:divi/placeholder -->',
			)
		);
		$this->fails( $this->admin, 'update_post', array( 'id' => $id, 'content' => 'x' ), 'divi_apply_layout' );
	}

	public function test_update_content_on_plain_post_and_force_is_ignored() {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => 'plain' ) );
		$this->ok( $this->admin, 'update_post', array( 'id' => $id, 'content' => 'updated', 'force' => true ) );
		$this->assertSame( 'updated', get_post( $id )->post_content );
	}

	public function test_update_cannot_change_trashed_post() {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_trash_post( $id );
		$this->fails( $this->admin, 'update_post', array( 'id' => $id, 'title' => 'x' ), 'restore' );
	}

	public function test_update_meta_null_deletes_key() {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $id, 'color', 'red' );
		$this->ok( $this->admin, 'update_post', array( 'id' => $id, 'meta' => array( 'color' => null ) ) );
		$this->assertSame( '', get_post_meta( $id, 'color', true ) );
		$this->assertSame( array(), get_post_meta( $id, 'color' ) );
	}

	public function test_update_replaces_terms_for_given_taxonomy_only() {
		$a  = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'A' ) );
		$b  = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'B' ) );
		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_category' => array( $a ) ) );
		wp_set_object_terms( $id, array( 'tagged' ), 'post_tag' );

		$data = $this->ok( $this->admin, 'update_post', array( 'id' => $id, 'terms' => array( 'category' => array( $b ) ) ) );
		$this->assertSame( array( $b ), $data['terms']['category'] );
		$this->assertCount( 1, $data['terms']['post_tag'] );
	}

	public function test_author_cannot_edit_others_post() {
		$theirs = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $this->author2, 'post_title' => 'Theirs' ) );
		$draft  = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $this->author2, 'post_title' => 'Their draft' ) );
		$mine   = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $this->author, 'post_title' => 'Mine' ) );

		$this->fails( $this->author, 'update_post', array( 'id' => $theirs, 'title' => 'Hacked' ) );
		$this->assertSame( 'Theirs', get_post( $theirs )->post_title );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'update_post', array( 'id' => $theirs, 'title' => 'Hacked' ) ) );

		$this->fails( $this->author, 'trash_post', array( 'id' => $theirs ) );
		$this->assertSame( 'publish', get_post_status( $theirs ) );
		$this->fails( $this->author, 'trash_post', array( 'id' => $draft ) );
		$this->assertSame( 'draft', get_post_status( $draft ) );

		// A draft the user cannot edit is forbidden, not leaked.
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'get_post', array( 'id' => $draft ) ) );
		$this->fails( $this->author, 'get_post', array( 'id' => $draft ) );

		// Own draft works.
		$data = $this->ok( $this->author, 'get_post', array( 'id' => $mine ) );
		$this->assertSame( 'Mine', $data['title'] );

		// A user without edit rights cannot restore either.
		wp_trash_post( $theirs );
		$this->fails( $this->author, 'restore_post', array( 'id' => $theirs ) );
		$this->assertSame( 'trash', get_post_status( $theirs ) );
	}

	// ----- get_post --------------------------------------------------------

	public function test_get_post_returns_full_representation() {
		$id = $this->make_divi_page( array( 'post_excerpt' => 'Exc', 'post_name' => 'divi-page' ) );
		update_post_meta( $id, 'visible', 'v' );
		update_post_meta( $id, '_hidden', 'h' );

		$data = $this->ok( $this->admin, 'get_post', array( 'id' => $id ) );
		foreach ( array( 'id', 'type', 'status', 'title', 'slug', 'link', 'modified', 'parent', 'divi', 'content_raw', 'excerpt', 'terms', 'featured_media', 'meta' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, $key );
		}
		$this->assertSame( $id, $data['id'] );
		$this->assertSame( 'Exc', $data['excerpt'] );
		$this->assertTrue( $data['divi'] );
		$this->assertSame( add_query_arg( 'et_fb', '1', get_permalink( $id ) ), $data['edit_divi'] );
		$this->assertSame( 'v', $data['meta']['visible'] );
		$this->assertArrayNotHasKey( '_hidden', $data['meta'] );
		$this->assertArrayNotHasKey( '_et_pb_use_builder', $data['meta'] );

		$plain = $this->ok( $this->admin, 'get_post', array( 'id' => self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->assertFalse( $plain['divi'] );
		$this->assertArrayNotHasKey( 'edit_divi', $plain );
	}

	public function test_get_post_missing_and_unexposed_types() {
		$this->fails( $this->admin, 'get_post', array( 'id' => 999999 ) );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'get_post', array( 'id' => 999999 ) ) );

		// Revisions, nav items, etc. are not exposed.
		$post = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$rev  = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post,
				'post_name'   => $post . '-revision-v1',
			)
		);
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'get_post', array( 'id' => $rev ) ) );
	}

	public function test_get_published_post_of_another_user_hides_meta() {
		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $this->author2 ) );
		update_post_meta( $id, 'secretish', 'value' );

		$data = $this->ok( $this->author, 'get_post', array( 'id' => $id ) );
		$this->assertSame( $id, $data['id'] );
		$this->assertArrayNotHasKey( 'secretish', $data['meta'] ?? array() );
	}

	public function test_get_post_password_protected_content_not_exposed_to_non_editors() {
		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $this->author2, 'post_password' => 'pw', 'post_content' => 'secret body' ) );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'get_post', array( 'id' => $id ) ) );
		$data = $this->ok( $this->author2, 'get_post', array( 'id' => $id ) );
		$this->assertSame( 'secret body', $data['content_raw'] );
	}

	// ----- trash / restore -------------------------------------------------

	public function test_trash_and_restore() {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'To trash' ) );

		$trashed = $this->ok( $this->admin, 'trash_post', array( 'id' => $id ) );
		$this->assertSame( 'trash', $trashed['status'] );
		$this->assertSame( 'trash', get_post_status( $id ) );
		$this->assertNotNull( get_post( $id ), 'trash must not permanently delete' );

		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'page' ) )['items'], 'id' );
		$this->assertNotContains( $id, $ids );
		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'page', 'status' => 'trash' ) )['items'], 'id' );
		$this->assertContains( $id, $ids );

		$this->fails( $this->admin, 'trash_post', array( 'id' => $id ), 'trash' );

		$restored = $this->ok( $this->admin, 'restore_post', array( 'id' => $id ) );
		$this->assertSame( 'draft', $restored['status'] );
		$this->assertSame( 'draft', get_post_status( $id ) );

		// Restoring something that is not in the trash is an error.
		$this->fails( $this->admin, 'restore_post', array( 'id' => $id ), 'trash' );
		$this->fails( $this->admin, 'restore_post', array( 'id' => 999999 ) );
	}

	// ----- list_posts / search --------------------------------------------

	public function test_list_posts_pagination_and_max_100() {
		self::factory()->post->create_many( 105, array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$total = (int) wp_count_posts( 'post' )->publish;

		$default = $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post' ) );
		$this->assertCount( 20, $default['items'] );
		$this->assertSame( $total, $default['total'] );
		$this->assertSame( (int) ceil( $total / 20 ), $default['pages'] );

		$capped = $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post', 'per_page' => 500 ) );
		$this->assertCount( 100, $capped['items'] );
		$this->assertSame( (int) ceil( $total / 100 ), $capped['pages'] );

		$page2 = $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post', 'per_page' => 10, 'page' => 2 ) );
		$this->assertCount( 10, $page2['items'] );
		$first = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post', 'per_page' => 10 ) )['items'], 'id' );
		$this->assertSame( array(), array_intersect( $first, wp_list_pluck( $page2['items'], 'id' ) ) );

		$this->fails( $this->admin, 'list_posts', array( 'post_type' => 'post', 'per_page' => 0 ) );
		$this->fails( $this->admin, 'list_posts', array( 'post_type' => 'post', 'per_page' => '5' ) );
		$this->fails( $this->admin, 'list_posts', array( 'post_type' => 'post', 'page' => 0 ) );
	}

	public function test_list_posts_filters() {
		$a = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Alpha needle', 'post_author' => $this->editor ) );
		$b = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Beta', 'post_author' => $this->admin ) );

		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'search' => 'needle' ) )['items'], 'id' );
		$this->assertSame( array( $a ), $ids );

		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'status' => 'draft' ) )['items'], 'id' );
		$this->assertSame( array( $b ), $ids );

		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'author' => $this->editor ) )['items'], 'id' );
		$this->assertSame( array( $a ), $ids );

		$this->fails( $this->admin, 'list_posts', array( 'status' => 'bogus' ) );
		$this->fails( $this->admin, 'list_posts', array( 'post_type' => 'nope' ) );
	}

	public function test_list_posts_tax_query_by_id_and_name() {
		$cat = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Newsy' ) );
		$in  = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_category' => array( $cat ) ) );
		self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );

		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post', 'tax_query' => array( 'taxonomy' => 'category', 'terms' => array( $cat ) ) ) )['items'], 'id' );
		$this->assertSame( array( $in ), $ids );
		$ids = wp_list_pluck( $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'post', 'tax_query' => array( 'taxonomy' => 'category', 'terms' => array( 'Newsy' ) ) ) )['items'], 'id' );
		$this->assertSame( array( $in ), $ids );
		$this->fails( $this->admin, 'list_posts', array( 'post_type' => 'post', 'tax_query' => array( 'taxonomy' => 'nope', 'terms' => array( 1 ) ) ) );
	}

	public function test_list_posts_summary_shape() {
		$id   = $this->make_divi_page();
		$data = $this->ok( $this->admin, 'list_posts', array( 'post_type' => 'page' ) );
		$item = null;
		foreach ( $data['items'] as $row ) {
			if ( $row['id'] === $id ) {
				$item = $row;
			}
		}
		$this->assertNotNull( $item );
		foreach ( array( 'id', 'type', 'status', 'title', 'slug', 'link', 'modified', 'parent', 'divi', 'edit_divi' ) as $key ) {
			$this->assertArrayHasKey( $key, $item, $key );
		}
		$this->assertArrayNotHasKey( 'content_raw', $item );
		$this->assertTrue( $item['divi'] );
	}

	public function test_lists_never_leak_posts_the_user_cannot_read() {
		$pub   = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $this->author2, 'post_title' => 'zzfind pub' ) );
		$draft = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $this->author2, 'post_title' => 'zzfind draft' ) );
		$priv  = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'private', 'post_author' => $this->author2, 'post_title' => 'zzfind private' ) );
		$mine  = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $this->author, 'post_title' => 'zzfind mine' ) );

		foreach ( array( 'any', 'draft', 'private', 'publish', 'pending', 'future' ) as $status ) {
			$list = $this->ok( $this->author, 'list_posts', array( 'post_type' => 'post', 'status' => $status ) );
			$ids  = wp_list_pluck( $list['items'], 'id' );
			$this->assertNotContains( $draft, $ids, "list status=$status leaked another user's draft" );
			$this->assertNotContains( $priv, $ids, "list status=$status leaked another user's private post" );
			$this->assertSame( count( $list['items'] ), $list['total'], "list status=$status total must not count hidden posts" );
		}
		$ids = wp_list_pluck( $this->ok( $this->author, 'list_posts', array( 'post_type' => 'post' ) )['items'], 'id' );
		$this->assertContains( $pub, $ids );
		$this->assertContains( $mine, $ids );

		// Another user's drafts are still hidden when filtering by their authorship.
		$list = $this->ok( $this->author, 'list_posts', array( 'post_type' => 'post', 'author' => $this->author2 ) );
		$this->assertSame( array( $pub ), wp_list_pluck( $list['items'], 'id' ) );
		$this->assertSame( 1, $list['total'] );

		// An editor sees everything.
		$ids = wp_list_pluck( $this->ok( $this->editor, 'list_posts', array( 'post_type' => 'post', 'search' => 'zzfind' ) )['items'], 'id' );
		$this->assertEqualSets( array( $pub, $draft, $priv, $mine ), $ids );

		// Search obeys the same rules.
		$found = $this->ok( $this->author, 'search', array( 'query' => 'zzfind' ) );
		$ids   = wp_list_pluck( $found['items'], 'id' );
		$this->assertEqualSets( array( $pub, $mine ), $ids );
		$this->assertSame( 2, $found['total'] );
	}

	public function test_search_does_not_match_password_protected_content_for_non_editors() {
		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $this->author2, 'post_password' => 'pw', 'post_content' => 'zzsecretphrase inside' ) );

		$found = $this->ok( $this->author, 'search', array( 'query' => 'zzsecretphrase' ) );
		$this->assertSame( array(), $found['items'] );
		$this->assertSame( 0, $found['total'] );
		$found = $this->ok( $this->author, 'list_posts', array( 'post_type' => 'post', 'search' => 'zzsecretphrase' ) );
		$this->assertSame( 0, $found['total'] );

		$found = $this->ok( $this->author2, 'search', array( 'query' => 'zzsecretphrase' ) );
		$this->assertSame( array( $id ), wp_list_pluck( $found['items'], 'id' ) );
	}

	public function test_search_defaults_filters_and_pagination() {
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'qqneedle page' ) );
		$post = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'qqneedle post' ) );
		self::factory()->post->create_many( 25, array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'qqmany item' ) );

		$all = $this->ok( $this->admin, 'search', array( 'query' => 'qqneedle' ) );
		$this->assertEqualSets( array( $page, $post ), wp_list_pluck( $all['items'], 'id' ) );
		$this->assertSame( 2, $all['total'] );
		$this->assertSame( 1, $all['pages'] );

		$only = $this->ok( $this->admin, 'search', array( 'query' => 'qqneedle', 'post_types' => array( 'page' ) ) );
		$this->assertSame( array( $page ), wp_list_pluck( $only['items'], 'id' ) );

		$many = $this->ok( $this->admin, 'search', array( 'query' => 'qqmany' ) );
		$this->assertCount( 20, $many['items'] );
		$this->assertSame( 25, $many['total'] );
		$this->assertSame( 2, $many['pages'] );
		$p2 = $this->ok( $this->admin, 'search', array( 'query' => 'qqmany', 'page' => 2 ) );
		$this->assertCount( 5, $p2['items'] );
		$capped = $this->ok( $this->admin, 'search', array( 'query' => 'qqmany', 'per_page' => 1000 ) );
		$this->assertCount( 25, $capped['items'] );

		$this->fails( $this->admin, 'search', array(), 'query' );
		$this->fails( $this->admin, 'search', array( 'query' => '' ), 'query' );
		$this->fails( $this->admin, 'search', array( 'query' => 'x', 'post_types' => array( 'nope' ) ) );
		$this->fails( $this->admin, 'search', array( 'query' => 'x', 'post_types' => 'page' ) );
	}

	// ----- discovery -------------------------------------------------------

	public function test_list_post_types_and_taxonomies() {
		$types = $this->ok( $this->admin, 'list_post_types' );
		$by    = array();
		foreach ( $types['items'] as $t ) {
			$by[ $t['name'] ] = $t;
		}
		$this->assertArrayHasKey( 'page', $by );
		$this->assertArrayHasKey( 'post', $by );
		$this->assertArrayNotHasKey( 'attachment', $by );
		$this->assertArrayNotHasKey( 'revision', $by );
		$this->assertTrue( $by['page']['hierarchical'] );
		$this->assertFalse( $by['post']['hierarchical'] );
		$this->assertContains( 'category', $by['post']['taxonomies'] );

		$tax = $this->ok( $this->admin, 'list_taxonomies' );
		$by  = array();
		foreach ( $tax['items'] as $t ) {
			$by[ $t['name'] ] = $t;
		}
		$this->assertArrayHasKey( 'category', $by );
		$this->assertArrayHasKey( 'post_tag', $by );
		$this->assertTrue( $by['category']['hierarchical'] );
		$this->assertContains( 'post', $by['category']['post_types'] );

		$only = $this->ok( $this->admin, 'list_taxonomies', array( 'post_type' => 'page' ) );
		$this->assertNotContains( 'category', wp_list_pluck( $only['items'], 'name' ) );
		$this->fails( $this->admin, 'list_taxonomies', array( 'post_type' => 'nope' ) );
	}

	// ----- site_info -------------------------------------------------------

	public function test_site_info_hides_plugins_without_capability() {
		update_option( 'active_plugins', array( plugin_basename( WPDM_FILE ) ) );

		$admin = $this->ok( $this->admin, 'site_info' );
		$this->assertArrayHasKey( 'plugins_active', $admin );
		$this->assertContains( 'WP Divi MCP', wp_list_pluck( $admin['plugins_active'], 'name' ) );
		foreach ( $admin['plugins_active'] as $plugin ) {
			$this->assertArrayHasKey( 'name', $plugin );
			$this->assertArrayHasKey( 'version', $plugin );
		}

		$editor = $this->ok( $this->editor, 'site_info' );
		$this->assertArrayNotHasKey( 'plugins_active', $editor );
	}

	public function test_site_info_shape() {
		$data = $this->ok( $this->editor, 'site_info' );

		$this->assertSame( get_bloginfo( 'version' ), $data['wp_version'] );
		$this->assertSame( PHP_VERSION, $data['php_version'] );
		$this->assertSame( site_url(), $data['site_url'] );
		$this->assertSame( home_url(), $data['home_url'] );
		foreach ( array( 'name', 'stylesheet', 'version', 'parent' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['theme'], $key );
		}
		$this->assertIsBool( $data['divi']['active'] );
		$this->assertArrayHasKey( 'version', $data['divi'] );

		$this->assertSame( get_userdata( $this->editor )->user_login, $data['user']['login'] );
		$this->assertSame( array( 'editor' ), $data['user']['roles'] );
		$this->assertSame(
			array(
				'edit_pages'         => true,
				'publish_pages'      => true,
				'edit_theme_options' => false,
				'upload_files'       => true,
				'manage_options'     => false,
			),
			$data['user']['caps']
		);
		$this->assertSame( 'Test connection', $data['connection']['label'] );
		$this->assertSame( 'full', $data['connection']['mode'] );
	}

	public function test_site_info_available_to_a_subscriber_but_content_tools_are_not() {
		$this->ok( $this->subscriber, 'site_info' );
		$result = $this->call( $this->subscriber, 'list_posts' );
		$this->assertTrue( $result['isError'] );
		$result = $this->call( $this->subscriber, 'create_post', array( 'title' => 'x' ) );
		$this->assertTrue( $result['isError'] );
	}
}
