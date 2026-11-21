<?php
/**
 * Tests for the site tools: media, terms, menus, comments, options and users.
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
use WPDM\Support\Media;
use WPDM\Tools\WordPress\Registrar;

class SiteToolsTest extends WP_UnitTestCase {

	const SITE_TOOLS = array(
		'list_terms',
		'create_term',
		'update_term',
		'list_media',
		'upload_media',
		'update_media',
		'list_menus',
		'get_menu',
		'update_menu_items',
		'list_comments',
		'moderate_comment',
		'get_options',
		'update_options',
		'list_users',
	);

	/** 1x1 transparent PNG. */
	const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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
	/** @var callable|null */
	private $http_mock;
	/** @var array */
	private $http_requests = array();

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
		remove_all_filters( 'pre_http_request' );
		unregister_nav_menu( 'wpdm-test-primary' );
		unregister_nav_menu( 'wpdm-test-footer' );
		if ( taxonomy_exists( 'wpdm_flat' ) ) {
			unregister_taxonomy( 'wpdm_flat' );
		}
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

	private function ok( int $user_id, string $tool, array $args = array() ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) );
		$this->assertArrayHasKey( 'structuredContent', $result );
		return $result['structuredContent'];
	}

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

	private function make_attachment( int $author, string $title = 'Pic' ): int {
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'pic-' . wp_generate_password( 6, false ) . '.png',
				'post_title'     => $title,
				'post_mime_type' => 'image/png',
				'post_author'    => $author,
			)
		);
	}

	/**
	 * Mock every outgoing HTTP request. The callback gets (url, args) and
	 * returns array{code:int, body?:string, headers?:array}.
	 */
	private function mock_http( callable $respond ): void {
		$this->http_requests = array();
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $respond ) {
				$this->http_requests[] = array( 'url' => $url, 'args' => $args );
				$r                     = $respond( $url, $args );
				$body                  = $r['body'] ?? '';
				if ( ! empty( $args['filename'] ) ) {
					file_put_contents( $args['filename'], $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				return array(
					'headers'  => $r['headers'] ?? array(),
					'body'     => ! empty( $args['filename'] ) ? '' : $body,
					'response' => array( 'code' => $r['code'], 'message' => '' ),
					'cookies'  => array(),
					'filename' => $args['filename'] ?? null,
				);
			},
			10,
			3
		);
	}

	// ----- registration ----------------------------------------------------

	public function test_site_tools_registered_and_hooked_into_filter() {
		$registry = apply_filters( 'wpdm_registry', new Registry() );
		foreach ( self::SITE_TOOLS as $name ) {
			$this->assertNotNull( $registry->tool( $name ), $name );
		}
	}

	public function test_no_delete_tool_among_site_tools() {
		foreach ( self::SITE_TOOLS as $name ) {
			$this->assertStringNotContainsStringIgnoringCase( 'delete', $name );
		}
		$registry = Registrar::add( new Registry() );
		$this->assertNull( $registry->tool( 'delete_user' ) );
		$this->assertNull( $registry->tool( 'create_user' ) );
	}

	public function test_annotations_schemas_and_capabilities() {
		$registry = Registrar::add( new Registry() );
		$read     = array( 'list_terms', 'list_media', 'list_menus', 'get_menu', 'list_comments', 'get_options', 'list_users' );
		$destruct = array( 'update_menu_items', 'moderate_comment', 'update_options' );
		$caps     = array(
			'list_terms'        => 'edit_posts',
			'create_term'       => 'edit_posts',
			'update_term'       => 'edit_posts',
			'list_media'        => 'upload_files',
			'upload_media'      => 'upload_files',
			'update_media'      => 'upload_files',
			'list_menus'        => 'edit_theme_options',
			'get_menu'          => 'edit_theme_options',
			'update_menu_items' => 'edit_theme_options',
			'list_comments'     => 'moderate_comments',
			'moderate_comment'  => 'moderate_comments',
			'get_options'       => 'manage_options',
			'update_options'    => 'manage_options',
			'list_users'        => 'edit_posts',
		);
		foreach ( self::SITE_TOOLS as $name ) {
			$tool = $registry->tool( $name );
			$this->assertSame( in_array( $name, $read, true ), $tool->read_only(), "$name read_only" );
			$this->assertSame( in_array( $name, $destruct, true ), $tool->destructive(), "$name destructive" );
			$this->assertSame( $caps[ $name ], $tool->capability(), "$name capability" );

			$schema = $tool->input_schema();
			$this->assertSame( 'object', $schema['type'], $name );
			$this->assertFalse( $schema['additionalProperties'], $name );
			$this->assertArrayHasKey( 'required', $schema, $name );
			if ( is_array( $schema['properties'] ) ) {
				$this->assertNotEmpty( $schema['properties'], $name );
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

	public function test_read_only_connection_cannot_write_site_tools() {
		$result = $this->call( $this->admin, 'update_options', array( 'options' => array( 'blogname' => 'Nope' ) ), true );
		$this->assertTrue( $result['isError'] );
		$this->assertNotSame( 'Nope', get_option( 'blogname' ) );

		$ok = $this->call( $this->admin, 'get_options', array(), true );
		$this->assertArrayNotHasKey( 'isError', $ok );
	}

	// ----- media -----------------------------------------------------------

	public function test_upload_media_from_base64() {
		$data = $this->ok(
			$this->author,
			'upload_media',
			array(
				'base64'   => self::PNG,
				'filename' => 'My Photo.png',
				'alt'      => 'A pixel',
				'title'    => 'Pixel',
				'caption'  => 'Tiny',
			)
		);
		$this->assertGreaterThan( 0, $data['id'] );
		$this->assertStringContainsString( '.png', $data['url'] );

		$post = get_post( $data['id'] );
		$this->assertSame( 'attachment', $post->post_type );
		$this->assertSame( 'image/png', $post->post_mime_type );
		$this->assertSame( 'Pixel', $post->post_title );
		$this->assertSame( 'Tiny', $post->post_excerpt );
		$this->assertSame( $this->author, (int) $post->post_author );
		$this->assertSame( 'A pixel', get_post_meta( $data['id'], '_wp_attachment_image_alt', true ) );
		$this->assertFileExists( get_attached_file( $data['id'] ) );
	}

	public function test_upload_media_base64_over_limit() {
		$big     = base64_encode( str_repeat( 'a', 5 * 1024 * 1024 + 1 ) );
		$before  = wp_count_posts( 'attachment' )->inherit;
		$code    = $this->error_code( $this->admin, 'upload_media', array( 'base64' => $big, 'filename' => 'big.png' ) );
		$this->assertSame( 'too_large', $code );
		$this->assertEquals( $before, wp_count_posts( 'attachment' )->inherit );
	}

	public function test_upload_rejects_non_image() {
		$before = wp_count_posts( 'attachment' )->inherit;

		// PHP/HTML content with an image extension: the extension must not win.
		$spoof = $this->error_code( $this->admin, 'upload_media', array( 'base64' => base64_encode( '<?php echo "pwn"; ?>' ), 'filename' => 'evil.png' ) );
		$this->assertSame( 'invalid_type', $spoof );

		$html = $this->error_code( $this->admin, 'upload_media', array( 'base64' => base64_encode( '<html><script>1</script></html>' ), 'filename' => 'x.jpg' ) );
		$this->assertSame( 'invalid_type', $html );

		// A real non-image type with an honest extension.
		$txt = $this->error_code( $this->admin, 'upload_media', array( 'base64' => base64_encode( 'hello world' ), 'filename' => 'notes.txt' ) );
		$this->assertSame( 'invalid_type', $txt );

		// SVG is not accepted either (scriptable).
		$svg = $this->error_code( $this->admin, 'upload_media', array( 'base64' => base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>' ), 'filename' => 'x.svg' ) );
		$this->assertSame( 'invalid_type', $svg );

		$this->assertEquals( $before, wp_count_posts( 'attachment' )->inherit );
	}

	public function test_upload_rejects_decompression_bombs() {
		// A valid PNG header that claims 30000x30000 pixels in a few bytes.
		$ihdr = pack( 'NNCCCCC', 30000, 30000, 8, 2, 0, 0, 0 );
		$chunk = static function ( string $type, string $data ): string {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};
		$png = "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', $ihdr ) . $chunk( 'IEND', '' );
		$before = wp_count_posts( 'attachment' )->inherit;
		$this->assertSame( 'too_large', $this->error_code( $this->admin, 'upload_media', array( 'base64' => base64_encode( $png ), 'filename' => 'bomb.png' ) ) );
		$this->assertEquals( $before, wp_count_posts( 'attachment' )->inherit );
	}

	public function test_upload_forces_extension_matching_real_type() {
		$data = $this->ok( $this->admin, 'upload_media', array( 'base64' => self::PNG, 'filename' => 'wrong.jpg' ) );
		$this->assertSame( 'image/png', get_post( $data['id'] )->post_mime_type );
		$this->assertSame( 'png', strtolower( pathinfo( get_attached_file( $data['id'] ), PATHINFO_EXTENSION ) ) );
	}

	public function test_upload_media_argument_rules() {
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'upload_media', array() ) );
		$this->assertSame(
			'invalid_argument',
			$this->error_code( $this->admin, 'upload_media', array( 'url' => 'http://93.184.216.34/a.png', 'base64' => self::PNG, 'filename' => 'a.png' ) )
		);
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'upload_media', array( 'base64' => self::PNG ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'upload_media', array( 'url' => 'http://93.184.216.34/a.png', 'filename' => 'a.png' ) ) );
		$this->assertSame( 'invalid_base64', $this->error_code( $this->admin, 'upload_media', array( 'base64' => '!!!not base64!!!', 'filename' => 'a.png' ) ) );
	}

	public function test_upload_media_requires_upload_files_capability() {
		$result = $this->call( $this->contributor, 'upload_media', array( 'base64' => self::PNG, 'filename' => 'a.png' ) );
		$this->assertTrue( $result['isError'] );
		$this->assertStringContainsString( 'upload_files', $result['content'][0]['text'] );
	}

	/**
	 * @dataProvider unsafe_urls
	 */
	public function test_sideload_rejects_unsafe_urls( string $url ) {
		$this->mock_http(
			static function () {
				return array( 'code' => 200, 'body' => base64_decode( SiteToolsTest::PNG ) );
			}
		);
		try {
			Media::sideload( $url );
			$this->fail( 'Expected ToolError for ' . $url );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_url', $e->error_code(), $url );
		}
		$this->assertSame( array(), $this->http_requests, 'No request may leave for ' . $url );
	}

	public function unsafe_urls(): array {
		return array(
			'loopback'          => array( 'http://127.0.0.1/a.png' ),
			'localhost'         => array( 'http://localhost/a.png' ),
			'private 10'        => array( 'http://10.0.0.5/a.png' ),
			'private 192.168'   => array( 'http://192.168.1.1/a.png' ),
			'private 172.16'    => array( 'http://172.16.0.1/a.png' ),
			'cloud metadata'    => array( 'http://169.254.169.254/latest/meta-data/' ),
			'carrier-grade nat' => array( 'http://100.64.0.1/a.png' ),
			'ipv6 loopback'     => array( 'http://[::1]/a.png' ),
			'ipv4-mapped ipv6'  => array( 'http://[::ffff:127.0.0.1]/a.png' ),
			'ipv6 unique local' => array( 'http://[fd00::1]/a.png' ),
			'zero address'      => array( 'http://0.0.0.0/a.png' ),
			'file scheme'       => array( 'file:///etc/passwd' ),
			'ftp scheme'        => array( 'ftp://93.184.216.34/a.png' ),
			'gopher scheme'     => array( 'gopher://93.184.216.34/a' ),
			'credentials'       => array( 'http://user:pass@93.184.216.34/a.png' ),
			'odd port'          => array( 'http://93.184.216.34:3306/a.png' ),
			'no host'           => array( 'http:///a.png' ),
			'not a url'         => array( 'not a url' ),
		);
	}

	public function test_sideload_downloads_a_public_image() {
		$this->mock_http(
			static function () {
				return array( 'code' => 200, 'body' => base64_decode( SiteToolsTest::PNG ) );
			}
		);
		wp_set_current_user( $this->admin );
		$out = Media::sideload( 'http://93.184.216.34/photos/cat', 'Alt text', 'Cat' );

		$this->assertGreaterThan( 0, $out['id'] );
		$this->assertSame( wp_get_attachment_url( $out['id'] ), $out['url'] );
		$post = get_post( $out['id'] );
		$this->assertSame( 'Cat', $post->post_title );
		$this->assertSame( 'Alt text', get_post_meta( $out['id'], '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'image/png', $post->post_mime_type );

		$this->assertCount( 1, $this->http_requests );
		$args = $this->http_requests[0]['args'];
		$this->assertTrue( $args['reject_unsafe_urls'] );
		$this->assertSame( 0, $args['redirection'], 'Redirects are followed manually so every hop is validated' );
		$this->assertSame( 5 * 1024 * 1024 + 1, $args['limit_response_size'] );
	}

	public function test_sideload_rejects_non_image_response_and_cleans_up() {
		$this->mock_http(
			static function () {
				return array( 'code' => 200, 'body' => '<?php echo 1;' );
			}
		);
		$tmp_before = glob( get_temp_dir() . '*' ) ?: array();
		try {
			Media::sideload( 'http://93.184.216.34/evil.png' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_type', $e->error_code() );
		}
		$tmp_after = glob( get_temp_dir() . '*' ) ?: array();
		$this->assertSame( array(), array_values( array_diff( $tmp_after, $tmp_before ) ), 'Temp files must be removed' );
	}

	public function test_sideload_rejects_http_errors_and_oversize() {
		$this->mock_http(
			static function () {
				return array( 'code' => 404, 'body' => 'nope' );
			}
		);
		try {
			Media::sideload( 'http://93.184.216.34/missing.png' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'download_failed', $e->error_code() );
		}

		$this->mock_http(
			static function () {
				return array( 'code' => 200, 'body' => str_repeat( 'a', 5 * 1024 * 1024 + 1 ) );
			}
		);
		try {
			Media::sideload( 'http://93.184.216.34/huge.png' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'too_large', $e->error_code() );
		}
	}

	public function test_sideload_validates_every_redirect_hop() {
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, '93.184.216.34' ) ) {
					return array( 'code' => 302, 'headers' => array( 'location' => 'http://169.254.169.254/latest/meta-data/' ) );
				}
				return array( 'code' => 200, 'body' => 'secret' );
			}
		);
		try {
			Media::sideload( 'http://93.184.216.34/redirect.png' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_url', $e->error_code() );
		}
		$this->assertCount( 1, $this->http_requests, 'The private redirect target must never be requested' );
	}

	public function test_sideload_follows_safe_redirects_but_not_forever() {
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, '/start' ) ) {
					return array( 'code' => 301, 'headers' => array( 'location' => 'http://93.184.216.35/final.png' ) );
				}
				return array( 'code' => 200, 'body' => base64_decode( SiteToolsTest::PNG ) );
			}
		);
		wp_set_current_user( $this->admin );
		$out = Media::sideload( 'http://93.184.216.34/start' );
		$this->assertGreaterThan( 0, $out['id'] );
		$this->assertCount( 2, $this->http_requests );

		$this->mock_http(
			static function ( $url ) {
				return array( 'code' => 302, 'headers' => array( 'location' => 'http://93.184.216.34/loop' ) );
			}
		);
		try {
			Media::sideload( 'http://93.184.216.34/loop' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'download_failed', $e->error_code() );
		}
		$this->assertLessThanOrEqual( 6, count( $this->http_requests ) );
	}

	public function test_upload_media_from_url_via_tool() {
		$this->mock_http(
			static function () {
				return array( 'code' => 200, 'body' => base64_decode( SiteToolsTest::PNG ) );
			}
		);
		$data = $this->ok( $this->editor, 'upload_media', array( 'url' => 'https://93.184.216.34/pic.png', 'alt' => 'Remote', 'caption' => 'Cap' ) );
		$this->assertSame( 'Cap', get_post( $data['id'] )->post_excerpt );
		$this->assertSame( 'Remote', get_post_meta( $data['id'], '_wp_attachment_image_alt', true ) );
		$this->assertSame( $this->editor, (int) get_post( $data['id'] )->post_author );
	}

	public function test_from_base64_strict_decoding() {
		wp_set_current_user( $this->admin );
		try {
			Media::from_base64( 'abc$def', 'a.png' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_base64', $e->error_code() );
		}
		$out = Media::from_base64( self::PNG, 'ok.png', 'Alt' );
		$this->assertSame( 'Alt', get_post_meta( $out['id'], '_wp_attachment_image_alt', true ) );
	}

	public function test_list_media_scopes_authors_to_their_own_files() {
		$mine   = $this->make_attachment( $this->author, 'Mine' );
		$theirs = $this->make_attachment( $this->author2, 'Theirs' );

		$author_view = $this->ok( $this->author, 'list_media' );
		$ids         = wp_list_pluck( $author_view['items'], 'id' );
		$this->assertContains( $mine, $ids );
		$this->assertNotContains( $theirs, $ids );
		$this->assertSame( count( $ids ), $author_view['total'] );

		$editor_view = $this->ok( $this->editor, 'list_media' );
		$ids         = wp_list_pluck( $editor_view['items'], 'id' );
		$this->assertContains( $mine, $ids );
		$this->assertContains( $theirs, $ids );
	}

	public function test_list_media_search_mime_and_pagination() {
		$a = $this->make_attachment( $this->admin, 'Sunset beach' );
		$b = $this->make_attachment( $this->admin, 'Mountain' );
		$c = self::factory()->attachment->create_object(
			array(
				'file'           => 'doc.pdf',
				'post_title'     => 'Contract',
				'post_mime_type' => 'application/pdf',
				'post_author'    => $this->admin,
			)
		);

		$found = $this->ok( $this->admin, 'list_media', array( 'search' => 'Sunset' ) );
		$this->assertSame( array( $a ), wp_list_pluck( $found['items'], 'id' ) );
		$this->assertSame( 'Sunset beach', $found['items'][0]['title'] );
		$this->assertSame( 'image/png', $found['items'][0]['mime_type'] );

		$pdf = $this->ok( $this->admin, 'list_media', array( 'mime_type' => 'application/pdf' ) );
		$this->assertSame( array( $c ), wp_list_pluck( $pdf['items'], 'id' ) );

		$images = $this->ok( $this->admin, 'list_media', array( 'mime_type' => 'image' ) );
		$this->assertEqualSets( array( $a, $b ), wp_list_pluck( $images['items'], 'id' ) );

		$page = $this->ok( $this->admin, 'list_media', array( 'per_page' => 1, 'page' => 2 ) );
		$this->assertCount( 1, $page['items'] );
		$this->assertSame( 3, $page['total'] );
		$this->assertSame( 3, $page['pages'] );

		$capped = $this->ok( $this->admin, 'list_media', array( 'per_page' => 500 ) );
		$this->assertCount( 3, $capped['items'] );
	}

	public function test_update_media_edits_fields() {
		$id  = $this->make_attachment( $this->author, 'Old' );
		$out = $this->ok(
			$this->author,
			'update_media',
			array(
				'id'          => $id,
				'title'       => 'New title',
				'alt'         => 'New alt',
				'caption'     => 'New caption',
				'description' => 'New description',
			)
		);
		$this->assertSame( $id, $out['id'] );
		$post = get_post( $id );
		$this->assertSame( 'New title', $post->post_title );
		$this->assertSame( 'New caption', $post->post_excerpt );
		$this->assertSame( 'New description', $post->post_content );
		$this->assertSame( 'New alt', get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'New alt', $out['alt'] );
	}

	public function test_update_media_checks_edit_post_per_attachment() {
		$theirs = $this->make_attachment( $this->author2, 'Theirs' );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'update_media', array( 'id' => $theirs, 'title' => 'Hijack' ) ) );
		$this->assertSame( 'Theirs', get_post( $theirs )->post_title );

		// An editor may edit any attachment.
		$this->ok( $this->editor, 'update_media', array( 'id' => $theirs, 'title' => 'Edited' ) );
		$this->assertSame( 'Edited', get_post( $theirs )->post_title );
	}

	public function test_update_media_only_touches_attachments_and_needs_a_field() {
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Untouched', 'post_author' => $this->admin ) );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'update_media', array( 'id' => $page, 'title' => 'Changed' ) ) );
		$this->assertSame( 'Untouched', get_post( $page )->post_title );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'update_media', array( 'id' => 999999, 'title' => 'x' ) ) );

		$att = $this->make_attachment( $this->admin );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_media', array( 'id' => $att ) ) );
	}

	// ----- terms -----------------------------------------------------------

	public function test_list_terms_filters_and_paginates() {
		$a = self::factory()->category->create( array( 'name' => 'Apples' ) );
		$b = self::factory()->category->create( array( 'name' => 'Bananas' ) );
		$c = self::factory()->category->create( array( 'name' => 'Child apple', 'parent' => $a ) );
		$p = self::factory()->post->create();
		wp_set_object_terms( $p, array( $b ), 'category' );

		$all = $this->ok( $this->author, 'list_terms', array( 'taxonomy' => 'category' ) );
		$ids = wp_list_pluck( $all['items'], 'id' );
		$this->assertContains( $a, $ids );
		$this->assertContains( $c, $ids );
		$this->assertSame( count( $ids ), $all['total'] );
		$first = $all['items'][0];
		foreach ( array( 'id', 'name', 'slug', 'description', 'parent', 'count', 'taxonomy' ) as $key ) {
			$this->assertArrayHasKey( $key, $first );
		}

		$search = $this->ok( $this->author, 'list_terms', array( 'taxonomy' => 'category', 'search' => 'apple' ) );
		$this->assertEqualSets( array( $a, $c ), wp_list_pluck( $search['items'], 'id' ) );
		$this->assertSame( 2, $search['total'] );

		$children = $this->ok( $this->author, 'list_terms', array( 'taxonomy' => 'category', 'parent' => $a ) );
		$this->assertSame( array( $c ), wp_list_pluck( $children['items'], 'id' ) );

		$used = $this->ok( $this->author, 'list_terms', array( 'taxonomy' => 'category', 'hide_empty' => true ) );
		$this->assertContains( $b, wp_list_pluck( $used['items'], 'id' ) );
		$this->assertNotContains( $a, wp_list_pluck( $used['items'], 'id' ) );

		$page = $this->ok( $this->author, 'list_terms', array( 'taxonomy' => 'category', 'per_page' => 1, 'page' => 2 ) );
		$this->assertCount( 1, $page['items'] );
		$this->assertSame( $all['total'], $page['total'] );
		$this->assertSame( $all['total'], $page['pages'] );
	}

	public function test_list_terms_rejects_unknown_taxonomy_and_requires_it() {
		$this->assertSame( 'invalid_argument', $this->error_code( $this->author, 'list_terms', array( 'taxonomy' => 'nope' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->author, 'list_terms', array() ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->author, 'list_terms', array( 'taxonomy' => 'nav_menu' ) ) );
	}

	public function test_create_term_needs_the_taxonomy_capability() {
		$this->assertSame( 'forbidden', $this->error_code( $this->contributor, 'create_term', array( 'taxonomy' => 'category', 'name' => 'Nope' ) ) );
		$this->assertEmpty( term_exists( 'Nope', 'category' ) );

		$parent = self::factory()->category->create( array( 'name' => 'Top' ) );
		$out    = $this->ok(
			$this->editor,
			'create_term',
			array(
				'taxonomy'    => 'category',
				'name'        => 'News',
				'slug'        => 'latest-news',
				'description' => 'All news',
				'parent'      => $parent,
			)
		);
		$term = get_term( $out['id'], 'category' );
		$this->assertSame( 'News', $term->name );
		$this->assertSame( 'latest-news', $term->slug );
		$this->assertSame( 'All news', $term->description );
		$this->assertSame( $parent, (int) $term->parent );
		$this->assertSame( 'category', $out['taxonomy'] );
	}

	public function test_create_term_validation() {
		$this->assertSame( 'term_exists', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'category', 'name' => 'Uncategorized' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'category', 'name' => 'Orphan', 'parent' => 999999 ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'category' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'category', 'name' => '' ) ) );

		// Tags are flat: a parent makes no sense.
		$cat = self::factory()->category->create();
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'post_tag', 'name' => 'tagged', 'parent' => $cat ) ) );
		// The parent must be a term of the same taxonomy.
		$tag = self::factory()->tag->create();
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'create_term', array( 'taxonomy' => 'category', 'name' => 'cross', 'parent' => $tag ) ) );
	}

	public function test_update_term() {
		$a = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b = self::factory()->category->create( array( 'name' => 'Beta' ) );

		$out = $this->ok( $this->editor, 'update_term', array( 'id' => $b, 'taxonomy' => 'category', 'name' => 'Beta 2', 'slug' => 'beta-two', 'description' => 'D', 'parent' => $a ) );
		$this->assertSame( 'Beta 2', $out['name'] );
		$term = get_term( $b, 'category' );
		$this->assertSame( 'Beta 2', $term->name );
		$this->assertSame( 'beta-two', $term->slug );
		$this->assertSame( $a, (int) $term->parent );

		// Parent 0 detaches.
		$this->ok( $this->editor, 'update_term', array( 'id' => $b, 'taxonomy' => 'category', 'parent' => 0 ) );
		$this->assertSame( 0, (int) get_term( $b, 'category' )->parent );
	}

	public function test_update_term_guards() {
		$a = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b = self::factory()->category->create( array( 'name' => 'Beta', 'parent' => $a ) );
		$t = self::factory()->tag->create( array( 'name' => 'Tag' ) );

		$this->assertSame( 'forbidden', $this->error_code( $this->contributor, 'update_term', array( 'id' => $a, 'taxonomy' => 'category', 'name' => 'Hijack' ) ) );
		$this->assertSame( 'Alpha', get_term( $a, 'category' )->name );

		$this->assertSame( 'not_found', $this->error_code( $this->editor, 'update_term', array( 'id' => 999999, 'taxonomy' => 'category', 'name' => 'x' ) ) );
		// A term id of another taxonomy is not found in this one.
		$this->assertSame( 'not_found', $this->error_code( $this->editor, 'update_term', array( 'id' => $t, 'taxonomy' => 'category', 'name' => 'x' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'update_term', array( 'id' => $a, 'taxonomy' => 'category' ) ) );

		// A term may not become its own parent or a child of its descendant.
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'update_term', array( 'id' => $a, 'taxonomy' => 'category', 'parent' => $a ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'update_term', array( 'id' => $a, 'taxonomy' => 'category', 'parent' => $b ) ) );
		$this->assertSame( 0, (int) get_term( $a, 'category' )->parent );
	}

	// ----- menus -----------------------------------------------------------

	private function make_menu( string $name = 'Main' ): int {
		register_nav_menu( 'wpdm-test-primary', 'Primary' );
		register_nav_menu( 'wpdm-test-footer', 'Footer' );
		return (int) wp_create_nav_menu( $name );
	}

	private function menu_titles( int $menu_id ): array {
		$items = wp_get_nav_menu_items( $menu_id );
		$out   = array();
		foreach ( $items as $item ) {
			$out[ $item->title ] = $item;
		}
		return $out;
	}

	public function test_list_menus_with_locations() {
		$menu = $this->make_menu();
		set_theme_mod( 'nav_menu_locations', array( 'wpdm-test-primary' => $menu ) );

		$data = $this->ok( $this->admin, 'list_menus' );
		$ids  = wp_list_pluck( $data['menus'], 'id' );
		$this->assertContains( $menu, $ids );

		$by_loc = array();
		foreach ( $data['locations'] as $loc ) {
			$by_loc[ $loc['location'] ] = $loc;
		}
		$this->assertSame( $menu, $by_loc['wpdm-test-primary']['menu_id'] );
		$this->assertSame( 0, $by_loc['wpdm-test-footer']['menu_id'] );
		$this->assertSame( 'Primary', $by_loc['wpdm-test-primary']['description'] );
	}

	public function test_menu_tools_require_edit_theme_options() {
		$menu = $this->make_menu();
		foreach ( array( $this->editor, $this->author ) as $user ) {
			$this->assertTrue( $this->call( $user, 'list_menus' )['isError'] );
			$this->assertTrue( $this->call( $user, 'get_menu', array( 'menu_id' => $menu ) )['isError'] );
			$this->assertTrue( $this->call( $user, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'title' => 'X', 'url' => 'https://example.org/' ) ) ) )['isError'] );
		}
		$this->assertSame( array(), wp_get_nav_menu_items( $menu ) ?: array() );
	}

	public function test_menu_items_add_and_reorder() {
		$menu = $this->make_menu();
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'About us', 'post_status' => 'publish' ) );

		$out = $this->ok(
			$this->admin,
			'update_menu_items',
			array(
				'menu_id' => $menu,
				'items'   => array(
					array( 'title' => 'Home', 'url' => 'https://example.org/', 'position' => 1 ),
					array( 'title' => 'About', 'object_type' => 'page', 'object_id' => $page, 'position' => 2 ),
					array( 'title' => 'Blog', 'url' => '/blog/', 'position' => 3 ),
				),
			)
		);
		$this->assertCount( 3, $out['created'] );

		$menu_data = $this->ok( $this->admin, 'get_menu', array( 'menu_id' => $menu ) );
		$this->assertSame( $menu, $menu_data['id'] );
		$titles = wp_list_pluck( $menu_data['items'], 'title' );
		$this->assertSame( array( 'Home', 'About', 'Blog' ), $titles );
		$about = $menu_data['items'][1];
		foreach ( array( 'id', 'title', 'url', 'type', 'object', 'object_id', 'parent', 'position' ) as $key ) {
			$this->assertArrayHasKey( $key, $about );
		}
		$this->assertSame( 'post_type', $about['type'] );
		$this->assertSame( 'page', $about['object'] );
		$this->assertSame( $page, $about['object_id'] );
		$this->assertSame( get_permalink( $page ), $about['url'] );
		$this->assertSame( 'custom', $menu_data['items'][0]['type'] );

		// Reorder: Blog first, About second, Home last; rename Home on the way.
		$home  = $menu_data['items'][0]['id'];
		$blog  = $menu_data['items'][2]['id'];
		$about = $menu_data['items'][1]['id'];
		$this->ok(
			$this->admin,
			'update_menu_items',
			array(
				'menu_id' => $menu,
				'items'   => array(
					array( 'id' => $blog, 'position' => 1 ),
					array( 'id' => $about, 'position' => 2 ),
					array( 'id' => $home, 'title' => 'Start', 'position' => 3 ),
				),
			)
		);
		$after = $this->ok( $this->admin, 'get_menu', array( 'menu_id' => $menu ) );
		$this->assertSame( array( 'Blog', 'About', 'Start' ), wp_list_pluck( $after['items'], 'title' ) );
		// Unspecified attributes of an updated item are preserved.
		$this->assertSame( 'https://example.org/', $after['items'][2]['url'] );
		$this->assertSame( 'page', $after['items'][1]['object'] );
		$this->assertSame( $page, $after['items'][1]['object_id'] );
		$this->assertCount( 3, $after['items'] );
	}

	public function test_menu_items_parenting_and_cycles() {
		$menu = $this->make_menu();
		$out  = $this->ok(
			$this->admin,
			'update_menu_items',
			array(
				'menu_id' => $menu,
				'items'   => array(
					array( 'title' => 'Parent', 'url' => 'https://example.org/p', 'position' => 1 ),
					array( 'title' => 'Other', 'url' => 'https://example.org/o', 'position' => 2 ),
				),
			)
		);
		list( $parent, $other ) = $out['created'];

		$this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'title' => 'Child', 'url' => 'https://example.org/c', 'parent' => $parent, 'position' => 3 ) ) ) );
		$items = $this->menu_titles( $menu );
		$this->assertSame( $parent, (int) $items['Child']->menu_item_parent );
		$child = $items['Child']->ID;

		// A parent from another menu or a plain post is rejected.
		$other_menu = (int) wp_create_nav_menu( 'Other menu' );
		$foreign    = wp_update_nav_menu_item( $other_menu, 0, array( 'menu-item-title' => 'Foreign', 'menu-item-url' => 'https://example.org/f', 'menu-item-status' => 'publish' ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $other, 'parent' => $foreign ) ) ) ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $other, 'parent' => $page ) ) ) ) );

		// Self parent and cycles.
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $other, 'parent' => $other ) ) ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $parent, 'parent' => $child ) ) ) ) );
		$this->assertSame( 0, (int) get_post_meta( $parent, '_menu_item_menu_item_parent', true ) );
	}

	public function test_update_menu_items_rejects_foreign_items_and_writes_nothing() {
		$menu       = $this->make_menu();
		$other_menu = (int) wp_create_nav_menu( 'Other menu' );
		$mine       = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Mine', 'menu-item-url' => 'https://example.org/m', 'menu-item-status' => 'publish', 'menu-item-position' => 1 ) );
		$foreign    = wp_update_nav_menu_item( $other_menu, 0, array( 'menu-item-title' => 'Foreign', 'menu-item-url' => 'https://example.org/f', 'menu-item-status' => 'publish', 'menu-item-position' => 1 ) );
		$page       = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Plain page' ) );

		// Update of a foreign item, together with a valid change: nothing happens.
		$code = $this->error_code(
			$this->admin,
			'update_menu_items',
			array(
				'menu_id' => $menu,
				'items'   => array(
					array( 'id' => $mine, 'title' => 'Renamed' ),
					array( 'id' => $foreign, 'title' => 'Hijacked' ),
					array( 'title' => 'New one', 'url' => 'https://example.org/n' ),
				),
			)
		);
		$this->assertSame( 'invalid_argument', $code );
		$this->assertSame( 'Mine', get_post( $mine )->post_title );
		$this->assertSame( 'Foreign', get_post( $foreign )->post_title );
		$this->assertCount( 1, wp_get_nav_menu_items( $menu ) );

		// remove_ids of another menu, or of a non menu item, deletes nothing.
		$code = $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'remove_ids' => array( $mine, $foreign ) ) );
		$this->assertSame( 'invalid_argument', $code );
		$this->assertNotNull( get_post( $mine ) );
		$this->assertNotNull( get_post( $foreign ) );

		$code = $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'remove_ids' => array( $page ) ) );
		$this->assertSame( 'invalid_argument', $code );
		$this->assertSame( 'publish', get_post( $page )->post_status, 'A page must never be removed through remove_ids' );
		$this->assertNotNull( get_post( $page ) );
	}

	public function test_update_menu_items_validates_everything_before_writing() {
		$menu = $this->make_menu();
		$mine = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Mine', 'menu-item-url' => 'https://example.org/m', 'menu-item-status' => 'publish', 'menu-item-position' => 1 ) );

		$bad_items = array(
			'javascript url'     => array( 'title' => 'Bad', 'url' => 'javascript:alert(1)' ),
			'no target'          => array( 'title' => 'Bad' ),
			'url and object'     => array( 'title' => 'Bad', 'url' => 'https://example.org/', 'object_type' => 'page', 'object_id' => 1 ),
			'object type only'   => array( 'title' => 'Bad', 'object_type' => 'page' ),
			'unknown object'     => array( 'title' => 'Bad', 'object_type' => 'nope', 'object_id' => 1 ),
			'missing post'       => array( 'title' => 'Bad', 'object_type' => 'page', 'object_id' => 999999 ),
			'post of other type' => array( 'title' => 'Bad', 'object_type' => 'page', 'object_id' => self::factory()->post->create( array( 'post_type' => 'post' ) ) ),
			'no title'           => array( 'url' => 'https://example.org/' ),
			'unknown id'         => array( 'id' => 999999, 'title' => 'Bad' ),
			'unknown key'        => array( 'title' => 'Bad', 'url' => 'https://example.org/', 'evil' => 1 ),
		);
		foreach ( $bad_items as $label => $bad ) {
			$code = $this->error_code(
				$this->admin,
				'update_menu_items',
				array(
					'menu_id' => $menu,
					'items'   => array(
						array( 'id' => $mine, 'title' => 'Changed first' ),
						$bad,
					),
				)
			);
			$this->assertSame( 'invalid_argument', $code, $label );
			$this->assertSame( 'Mine', get_post( $mine )->post_title, "$label: valid item must not be written" );
			$this->assertCount( 1, wp_get_nav_menu_items( $menu ), $label );
		}

		// Duplicate ids, update + remove of the same id, bad menu and bad location.
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $mine, 'title' => 'A' ), array( 'id' => $mine, 'title' => 'B' ) ) ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $mine, 'title' => 'A' ) ), 'remove_ids' => array( $mine ) ) ) );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => 999999, 'items' => array() ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'id' => $mine, 'title' => 'A' ) ), 'set_location' => array( 'location' => 'no-such-location' ) ) ) );
		$this->assertSame( 'Mine', get_post( $mine )->post_title );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu ) ) );
	}

	public function test_update_menu_items_removes_and_assigns_location() {
		$menu = $this->make_menu();
		$out  = $this->ok(
			$this->admin,
			'update_menu_items',
			array(
				'menu_id'      => $menu,
				'items'        => array(
					array( 'title' => 'Keep', 'url' => 'https://example.org/k', 'position' => 1 ),
					array( 'title' => 'Drop', 'url' => 'https://example.org/d', 'position' => 2 ),
				),
				'set_location' => array( 'location' => 'wpdm-test-primary' ),
			)
		);
		$this->assertSame( 'wpdm-test-primary', $out['location'] );
		$this->assertSame( $menu, (int) get_nav_menu_locations()['wpdm-test-primary'] );

		list( , $drop ) = $out['created'];
		$res            = $this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'remove_ids' => array( $drop ) ) );
		$this->assertSame( array( $drop ), $res['removed'] );
		$this->assertSame( array( 'Keep' ), wp_list_pluck( wp_get_nav_menu_items( $menu ), 'title' ) );
		$this->assertNull( get_post( $drop ) );

		// set_location may name its own menu_id; it must exist.
		$other = (int) wp_create_nav_menu( 'Footer menu' );
		$this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'set_location' => array( 'location' => 'wpdm-test-footer', 'menu_id' => $other ) ) );
		$this->assertSame( $other, (int) get_nav_menu_locations()['wpdm-test-footer'] );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'set_location' => array( 'location' => 'wpdm-test-footer', 'menu_id' => 999999 ) ) ) );
	}

	public function test_removing_a_parent_item_reparents_children() {
		$menu = $this->make_menu();
		$out  = $this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'title' => 'P', 'url' => 'https://example.org/p', 'position' => 1 ) ) ) );
		$p    = $out['created'][0];
		$out2 = $this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'items' => array( array( 'title' => 'C', 'url' => 'https://example.org/c', 'parent' => $p, 'position' => 2 ) ) ) );
		$c    = $out2['created'][0];

		$this->ok( $this->admin, 'update_menu_items', array( 'menu_id' => $menu, 'remove_ids' => array( $p ) ) );
		$items = $this->menu_titles( $menu );
		$this->assertSame( array( 'C' ), array_keys( $items ) );
		$this->assertSame( 0, (int) $items['C']->menu_item_parent );
		$this->assertSame( $c, $items['C']->ID );
	}

	public function test_get_menu_unknown() {
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'get_menu', array( 'menu_id' => 999999 ) ) );
	}

	// ----- comments --------------------------------------------------------

	private function make_comment( int $post_id, array $extra = array() ): int {
		return self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'  => $post_id,
					'comment_content'  => 'A comment',
					'comment_approved' => '1',
				),
				$extra
			)
		);
	}

	public function test_comment_tools_require_moderate_comments() {
		$post = self::factory()->post->create();
		$cid  = $this->make_comment( $post );
		foreach ( array( $this->author, $this->contributor, $this->subscriber ) as $user ) {
			$this->assertTrue( $this->call( $user, 'list_comments' )['isError'] );
			$this->assertTrue( $this->call( $user, 'moderate_comment', array( 'id' => $cid, 'action' => 'trash' ) )['isError'] );
		}
		$this->assertSame( '1', get_comment( $cid )->comment_approved );
	}

	public function test_list_comments_filters_and_paginates() {
		$post1 = self::factory()->post->create( array( 'post_title' => 'First' ) );
		$post2 = self::factory()->post->create( array( 'post_title' => 'Second' ) );
		$ok    = $this->make_comment( $post1, array( 'comment_content' => 'Great post' ) );
		$hold  = $this->make_comment( $post1, array( 'comment_content' => 'Waiting', 'comment_approved' => '0' ) );
		$spam  = $this->make_comment( $post2, array( 'comment_content' => 'Buy pills', 'comment_approved' => 'spam' ) );
		$trash = $this->make_comment( $post2, array( 'comment_content' => 'Gone', 'comment_approved' => 'trash' ) );

		$all = $this->ok( $this->editor, 'list_comments' );
		$this->assertEqualSets( array( $ok, $hold ), wp_list_pluck( $all['items'], 'id' ), 'all = approved + pending' );

		$statuses = array(
			'approve' => $ok,
			'hold'    => $hold,
			'spam'    => $spam,
			'trash'   => $trash,
		);
		foreach ( $statuses as $status => $expected ) {
			$res = $this->ok( $this->editor, 'list_comments', array( 'status' => $status ) );
			$this->assertSame( array( $expected ), wp_list_pluck( $res['items'], 'id' ), $status );
			$this->assertSame( $status, $res['items'][0]['status'] );
			$this->assertSame( 1, $res['total'] );
		}

		$by_post = $this->ok( $this->editor, 'list_comments', array( 'post_id' => $post2, 'status' => 'spam' ) );
		$this->assertSame( array( $spam ), wp_list_pluck( $by_post['items'], 'id' ) );
		$this->assertSame( $post2, $by_post['items'][0]['post_id'] );
		$this->assertSame( 'Second', $by_post['items'][0]['post_title'] );

		$search = $this->ok( $this->editor, 'list_comments', array( 'search' => 'Great' ) );
		$this->assertSame( array( $ok ), wp_list_pluck( $search['items'], 'id' ) );

		$page = $this->ok( $this->editor, 'list_comments', array( 'per_page' => 1, 'page' => 2 ) );
		$this->assertCount( 1, $page['items'] );
		$this->assertSame( 2, $page['total'] );
		$this->assertSame( 2, $page['pages'] );

		foreach ( array( 'id', 'post_id', 'author', 'author_email', 'content', 'status', 'date_gmt', 'parent' ) as $key ) {
			$this->assertArrayHasKey( $key, $all['items'][0] );
		}
	}

	public function test_comment_status_actions() {
		$post = self::factory()->post->create();
		$cid  = $this->make_comment( $post, array( 'comment_approved' => '0' ) );

		$out = $this->ok( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'approve' ) );
		$this->assertSame( 'approve', $out['status'] );
		$this->assertSame( '1', get_comment( $cid )->comment_approved );

		$this->ok( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'unapprove' ) );
		$this->assertSame( '0', get_comment( $cid )->comment_approved );

		$this->ok( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'spam' ) );
		$this->assertSame( 'spam', get_comment( $cid )->comment_approved );

		$this->ok( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'trash' ) );
		$this->assertSame( 'trash', get_comment( $cid )->comment_approved );
		$this->assertNotNull( get_comment( $cid ), 'trash must never delete' );
	}

	public function test_comment_reply() {
		$post   = self::factory()->post->create();
		$parent = $this->make_comment( $post, array( 'comment_approved' => '0' ) );

		$out = $this->ok( $this->editor, 'moderate_comment', array( 'id' => $parent, 'action' => 'reply', 'content' => 'Thanks for reading!' ) );
		$this->assertGreaterThan( 0, $out['reply_id'] );

		$reply = get_comment( $out['reply_id'] );
		$this->assertSame( (string) $parent, $reply->comment_parent );
		$this->assertSame( (string) $post, $reply->comment_post_ID );
		$this->assertSame( (string) $this->editor, $reply->user_id );
		$this->assertSame( '1', $reply->comment_approved );
		$this->assertSame( 'Thanks for reading!', $reply->comment_content );
		$this->assertSame( get_userdata( $this->editor )->display_name, $reply->comment_author );
		$this->assertSame( get_userdata( $this->editor )->user_email, $reply->comment_author_email );
		$this->assertSame( 'comment', $reply->comment_type );

		// Replying does not approve the parent on its own.
		$this->assertSame( '0', get_comment( $parent )->comment_approved );
	}

	public function test_comment_reply_rules() {
		$post  = self::factory()->post->create();
		$cid   = $this->make_comment( $post );
		$spam  = $this->make_comment( $post, array( 'comment_approved' => 'spam' ) );
		$trash = $this->make_comment( $post, array( 'comment_approved' => 'trash' ) );

		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'reply' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'reply', 'content' => '   ' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'approve', 'content' => 'stray' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $spam, 'action' => 'reply', 'content' => 'Hi' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $trash, 'action' => 'reply', 'content' => 'Hi' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'delete' ) ) );
		$this->assertSame( 'not_found', $this->error_code( $this->editor, 'moderate_comment', array( 'id' => 999999, 'action' => 'approve' ) ) );
		$this->assertCount( 0, get_comments( array( 'parent' => $cid ) ) );
	}

	public function test_reply_content_is_sanitized() {
		$post = self::factory()->post->create();
		$cid  = $this->make_comment( $post );
		$out  = $this->ok( $this->editor, 'moderate_comment', array( 'id' => $cid, 'action' => 'reply', 'content' => 'Hi <script>alert(1)</script><b>there</b>' ) );
		$text = get_comment( $out['reply_id'] )->comment_content;
		$this->assertStringNotContainsString( '<script', $text );
		$this->assertStringContainsString( '<b>there</b>', $text );
	}

	public function test_comment_capability_is_checked_per_comment() {
		// A contributor granted moderate_comments is not an editor: they may not
		// touch comments on other people's posts.
		$user = new WP_User( $this->contributor );
		$user->add_cap( 'moderate_comments' );

		$own_post   = self::factory()->post->create( array( 'post_author' => $this->contributor, 'post_status' => 'draft' ) );
		$other_post = self::factory()->post->create( array( 'post_author' => $this->editor ) );
		$own        = $this->make_comment( $own_post );
		$other      = $this->make_comment( $other_post );

		$this->assertSame( 'forbidden', $this->error_code( $this->contributor, 'moderate_comment', array( 'id' => $other, 'action' => 'trash' ) ) );
		$this->assertSame( 'forbidden', $this->error_code( $this->contributor, 'moderate_comment', array( 'id' => $other, 'action' => 'reply', 'content' => 'Hi' ) ) );
		$this->assertSame( '1', get_comment( $other )->comment_approved );
		$this->assertCount( 0, get_comments( array( 'parent' => $other ) ) );

		$list = $this->ok( $this->contributor, 'list_comments' );
		$ids  = wp_list_pluck( $list['items'], 'id' );
		$this->assertNotContains( $other, $ids, 'comments on posts the user cannot edit must not be listed' );
		$this->assertSame( count( $ids ), $list['total'] );
		$this->assertSame( 'forbidden', $this->error_code( $this->contributor, 'list_comments', array( 'post_id' => $other_post ) ) );

		// Their own post's comments are within reach.
		$this->ok( $this->contributor, 'moderate_comment', array( 'id' => $own, 'action' => 'spam' ) );
		$this->assertSame( 'spam', get_comment( $own )->comment_approved );
	}

	// ----- options ---------------------------------------------------------

	public function test_get_options_returns_allowlist_and_permalink_structure() {
		update_option( 'blogname', 'Site' );
		update_option( 'posts_per_page', 7 );
		$data = $this->ok( $this->admin, 'get_options' );
		foreach ( array( 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'permalink_structure' ) as $key ) {
			$this->assertArrayHasKey( $key, $data, $key );
		}
		$this->assertCount( 10, $data );
		$this->assertSame( 'Site', $data['blogname'] );
		$this->assertSame( 7, $data['posts_per_page'] );
		$this->assertIsInt( $data['page_on_front'] );
		$this->assertSame( (string) get_option( 'permalink_structure' ), $data['permalink_structure'] );
	}

	public function test_options_tools_require_manage_options() {
		$this->assertTrue( $this->call( $this->editor, 'get_options' )['isError'] );
		$this->assertTrue( $this->call( $this->editor, 'update_options', array( 'options' => array( 'blogname' => 'Hijacked' ) ) )['isError'] );
		$this->assertNotSame( 'Hijacked', get_option( 'blogname' ) );
	}

	public function test_update_options_writes_allowlisted_keys() {
		$page  = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$posts = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$out = $this->ok(
			$this->admin,
			'update_options',
			array(
				'options' => array(
					'blogname'        => 'New name',
					'blogdescription' => 'New tagline',
					'timezone_string' => 'Europe/Madrid',
					'date_format'     => 'd/m/Y',
					'time_format'     => 'H:i',
					'show_on_front'   => 'page',
					'page_on_front'   => $page,
					'page_for_posts'  => $posts,
					'posts_per_page'  => 25,
				),
			)
		);
		$this->assertSame( 'New name', get_option( 'blogname' ) );
		$this->assertSame( 'New tagline', get_option( 'blogdescription' ) );
		$this->assertSame( 'Europe/Madrid', get_option( 'timezone_string' ) );
		$this->assertSame( 'd/m/Y', get_option( 'date_format' ) );
		$this->assertSame( 'H:i', get_option( 'time_format' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $page, (int) get_option( 'page_on_front' ) );
		$this->assertSame( $posts, (int) get_option( 'page_for_posts' ) );
		$this->assertSame( 25, (int) get_option( 'posts_per_page' ) );
		$this->assertSame( 'New name', $out['options']['blogname'] );
		$this->assertEqualSets( array( 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page' ), $out['updated'] );

		// 0 unsets a page.
		$this->ok( $this->admin, 'update_options', array( 'options' => array( 'page_for_posts' => 0 ) ) );
		$this->assertSame( 0, (int) get_option( 'page_for_posts' ) );
	}

	public function test_update_options_rejects_non_allowlisted_key() {
		update_option( 'blogname', 'Original' );
		$before_admin = get_option( 'admin_email' );

		foreach ( array( 'admin_email', 'siteurl', 'home', 'users_can_register', 'default_role', 'active_plugins', 'template', 'wpdm_tokens' ) as $key ) {
			$code = $this->error_code( $this->admin, 'update_options', array( 'options' => array( 'blogname' => 'Changed', $key => 'evil' ) ) );
			$this->assertSame( 'not_allowed', $code, $key );
			$this->assertSame( 'Original', get_option( 'blogname' ), "$key: nothing may be written" );
		}
		$this->assertSame( $before_admin, get_option( 'admin_email' ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_options', array( 'options' => array() ) ) );
	}

	public function test_permalink_structure_read_only() {
		$before = get_option( 'permalink_structure' );
		$code   = $this->error_code( $this->admin, 'update_options', array( 'options' => array( 'permalink_structure' => '/%postname%/' ) ) );
		$this->assertSame( 'not_allowed', $code );
		$this->assertSame( $before, get_option( 'permalink_structure' ) );
	}

	public function test_update_options_validates_values_without_partial_writes() {
		update_option( 'blogname', 'Original' );
		$draft = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft' ) );
		$post  = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$invalid = array(
			'show_on_front'         => array( 'show_on_front' => 'sidebar' ),
			'front page draft'      => array( 'page_on_front' => $draft ),
			'front page is a post'  => array( 'page_on_front' => $post ),
			'front page missing'    => array( 'page_on_front' => 999999 ),
			'posts page draft'      => array( 'page_for_posts' => $draft ),
			'front page negative'   => array( 'page_on_front' => -1 ),
			'posts per page zero'   => array( 'posts_per_page' => 0 ),
			'posts per page 101'    => array( 'posts_per_page' => 101 ),
			'posts per page string' => array( 'posts_per_page' => '10' ),
			'timezone'              => array( 'timezone_string' => 'Mars/Olympus' ),
			'timezone empty'        => array( 'timezone_string' => '' ),
			'blogname type'         => array( 'blogname' => 5 ),
			'blogname empty'        => array( 'blogname' => '' ),
			'date format empty'     => array( 'date_format' => '' ),
			'same front and posts'  => array( 'page_on_front' => $page, 'page_for_posts' => $page ),
		);
		foreach ( $invalid as $label => $options ) {
			$code = $this->error_code( $this->admin, 'update_options', array( 'options' => array_merge( array( 'blogname' => 'Changed' ), $options ) ) );
			$this->assertSame( 'invalid_argument', $code, $label );
			$this->assertSame( 'Original', get_option( 'blogname' ), "$label: nothing may be written" );
		}

		// The pair is validated against the stored values too.
		update_option( 'page_on_front', $page );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'update_options', array( 'options' => array( 'page_for_posts' => $page ) ) ) );

		// Boundaries are accepted.
		$this->ok( $this->admin, 'update_options', array( 'options' => array( 'posts_per_page' => 1 ) ) );
		$this->ok( $this->admin, 'update_options', array( 'options' => array( 'posts_per_page' => 100 ) ) );
		$this->assertSame( 100, (int) get_option( 'posts_per_page' ) );
	}

	// ----- users -----------------------------------------------------------

	public function test_list_users_returns_email_with_list_users_capability() {
		$data = $this->ok( $this->admin, 'list_users' );
		$this->assertGreaterThanOrEqual( 6, $data['total'] );
		$by_id = array();
		foreach ( $data['items'] as $item ) {
			$by_id[ $item['id'] ] = $item;
		}
		$row = $by_id[ $this->editor ];
		foreach ( array( 'id', 'login', 'display_name', 'roles', 'email' ) as $key ) {
			$this->assertArrayHasKey( $key, $row );
		}
		$this->assertSame( array( 'editor' ), $row['roles'] );
		$this->assertSame( get_userdata( $this->editor )->user_email, $row['email'] );
		$this->assertArrayHasKey( $this->subscriber, $by_id, 'admins see subscribers too' );
	}

	public function test_list_users_hides_email_without_cap() {
		$data = $this->ok( $this->editor, 'list_users' );
		$this->assertNotEmpty( $data['items'] );
		$by_id = array();
		foreach ( $data['items'] as $item ) {
			$this->assertArrayNotHasKey( 'email', $item );
			$this->assertSame( array( 'id', 'login', 'display_name', 'roles' ), array_keys( $item ) );
			$by_id[ $item['id'] ] = $item;
		}
		// Only people who can write content are listed.
		$this->assertArrayHasKey( $this->author, $by_id );
		$this->assertArrayHasKey( $this->contributor, $by_id );
		$this->assertArrayNotHasKey( $this->subscriber, $by_id );
		$this->assertSame( count( $data['items'] ), $data['total'] );

		$by_role = $this->ok( $this->author, 'list_users', array( 'role' => 'subscriber' ) );
		$this->assertSame( array(), $by_role['items'] );
		$this->assertSame( 0, $by_role['total'] );
	}

	public function test_list_users_search_does_not_leak_email_addresses() {
		wp_update_user( array( 'ID' => $this->author2, 'user_email' => 'secret-handle@example.org' ) );

		$none = $this->ok( $this->author, 'list_users', array( 'search' => 'secret-handle' ) );
		$this->assertSame( array(), $none['items'] );
		$none = $this->ok( $this->author, 'list_users', array( 'search' => 'secret-handle@example.org' ) );
		$this->assertSame( array(), $none['items'] );

		$found = $this->ok( $this->admin, 'list_users', array( 'search' => 'secret-handle' ) );
		$this->assertSame( array( $this->author2 ), wp_list_pluck( $found['items'], 'id' ) );
	}

	public function test_list_users_role_search_and_pagination() {
		$login = get_userdata( $this->author )->user_login;
		$found = $this->ok( $this->editor, 'list_users', array( 'search' => $login ) );
		$this->assertSame( array( $this->author ), wp_list_pluck( $found['items'], 'id' ) );

		$authors = $this->ok( $this->admin, 'list_users', array( 'role' => 'author' ) );
		$this->assertEqualSets( array( $this->author, $this->author2 ), wp_list_pluck( $authors['items'], 'id' ) );

		$page = $this->ok( $this->admin, 'list_users', array( 'per_page' => 2, 'page' => 2 ) );
		$this->assertCount( 2, $page['items'] );
		$this->assertGreaterThan( 2, $page['pages'] );

		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'list_users', array( 'role' => 'no-such-role' ) ) );
	}

	public function test_users_tools_cannot_create_or_delete() {
		$registry = Registrar::add( new Registry() );
		foreach ( array( 'create_user', 'update_user', 'delete_user', 'install_plugin', 'switch_theme', 'activate_plugin' ) as $name ) {
			$this->assertNull( $registry->tool( $name ), $name );
		}
	}
}
