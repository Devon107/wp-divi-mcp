<?php
/**
 * Tests for connection tokens and request authentication.
 *
 * @package WPDM
 */

use WPDM\Auth\Tokens;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Tool;

class WPDM_Tokens_Read_Tool extends Tool {
	public function name(): string {
		return 'read_thing';
	}
	public function description(): string {
		return 'Reads.';
	}
	public function input_schema(): array {
		return array( 'type' => 'object', 'properties' => new stdClass() );
	}
	public function read_only(): bool {
		return true;
	}
	public function capability(): string {
		return 'read';
	}
	public function execute( array $args, Context $ctx ): array {
		return array(
			'user'       => get_current_user_id(),
			'connection' => $ctx->connection,
			'read_only'  => $ctx->read_only,
		);
	}
}

class WPDM_Tokens_Write_Tool extends Tool {
	public function name(): string {
		return 'create_post';
	}
	public function description(): string {
		return 'Publishes a post.';
	}
	public function input_schema(): array {
		return array( 'type' => 'object', 'properties' => new stdClass() );
	}
	public function read_only(): bool {
		return false;
	}
	public function capability(): string {
		return 'publish_posts';
	}
	public function execute( array $args, Context $ctx ): array {
		return array( 'created' => true );
	}
}

class TokensTest extends WP_UnitTestCase {

	const ROUTE = '/wp-divi-mcp/v1/mcp';

	/** @var int */
	private $editor_id;

	/** @var string|null */
	private $saved_ip;

	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_rest_server'] = null;
		$this->saved_ip            = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR']    = '203.0.113.7';

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		add_filter( 'wpdm_require_https', '__return_false' );
		add_filter(
			'wpdm_registry',
			static function ( Registry $registry ) {
				$registry->add_tool( new WPDM_Tokens_Read_Tool() );
				$registry->add_tool( new WPDM_Tokens_Write_Tool() );
				return $registry;
			}
		);
	}

	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( 0 );
		if ( null === $this->saved_ip ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->saved_ip;
		}
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tear_down();
	}

	private function rpc( string $method, array $params = array(), $id = 1 ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => $params,
		);
	}

	private function request( ?string $key = null, ?string $bearer = null, ?array $payload = null ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		if ( null !== $key ) {
			$request->set_query_params( array( 'key' => $key ) );
		}
		if ( null !== $bearer ) {
			$request->set_header( 'Authorization', 'Bearer ' . $bearer );
		}
		$request->set_body( wp_json_encode( $payload ?? $this->rpc( 'ping' ) ) );
		return $request;
	}

	private function send( ?string $key, ?array $payload = null, ?string $bearer = null ): WP_REST_Response {
		return rest_get_server()->dispatch( $this->request( $key, $bearer, $payload ) );
	}

	public function test_create_returns_token_once_and_stores_hash_only() {
		$created = Tokens::create( 'Claude desktop', $this->editor_id, 'full', null );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{12}$/', $created['id'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $created['token'] );
		$this->assertSame( Tokens::url( $created['token'] ), $created['url'] );

		$stored = get_option( 'wpdm_connections' );
		$this->assertArrayHasKey( $created['id'], $stored );
		$conn = $stored[ $created['id'] ];
		$this->assertSame( hash_hmac( 'sha256', $created['token'], wp_salt( 'auth' ) ), $conn['hash'] );
		$this->assertSame( 'Claude desktop', $conn['label'] );
		$this->assertSame( $this->editor_id, $conn['user_id'] );
		$this->assertSame( 'full', $conn['mode'] );
		$this->assertIsInt( $conn['created'] );
		$this->assertNull( $conn['expires'] );
		$this->assertNull( $conn['last_used'] );
		$this->assertNull( $conn['last_ip'] );

		// The plaintext token is stored nowhere.
		$this->assertStringNotContainsString( $created['token'], maybe_serialize( $stored ) );
		$this->assertStringNotContainsString( $created['token'], wp_json_encode( Tokens::all() ) );

		// all() never exposes the hash.
		$all = Tokens::all();
		$this->assertArrayHasKey( $created['id'], $all );
		$this->assertArrayNotHasKey( 'hash', $all[ $created['id'] ] );
	}

	public function test_create_rejects_unknown_mode() {
		$this->expectException( InvalidArgumentException::class );
		Tokens::create( 'x', $this->editor_id, 'admin', null );
	}

	public function test_connections_option_not_autoloaded() {
		Tokens::create( 'a', $this->editor_id, 'full', null );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertArrayNotHasKey( 'wpdm_connections', wp_load_alloptions() );
	}

	public function test_url_format() {
		$url = Tokens::url( 'abc123' );
		$this->assertSame( add_query_arg( 'key', 'abc123', rest_url( 'wp-divi-mcp/v1/mcp' ) ), $url );
		$this->assertStringContainsString( 'key=abc123', $url );
	}

	public function test_verify_valid_and_invalid() {
		$created = Tokens::create( 'a', $this->editor_id, 'read', null );

		$conn = Tokens::verify( $created['token'] );
		$this->assertIsArray( $conn );
		$this->assertSame( $created['id'], $conn['id'] );

		$this->assertNull( Tokens::verify( '' ) );
		$this->assertNull( Tokens::verify( str_repeat( '0', 64 ) ) );
		$this->assertNull( Tokens::verify( strtoupper( $created['token'] ) ) );
		$this->assertNull( Tokens::verify( $created['token'] . 'a' ) );
		$this->assertNull( Tokens::verify( substr( $created['token'], 0, 63 ) ) );
		// The stored hash itself is not a credential.
		$this->assertNull( Tokens::verify( get_option( 'wpdm_connections' )[ $created['id'] ]['hash'] ) );
	}

	public function test_expired_token_rejected() {
		$past   = Tokens::create( 'old', $this->editor_id, 'full', time() - 1 );
		$future = Tokens::create( 'new', $this->editor_id, 'full', time() + 3600 );

		$this->assertNull( Tokens::verify( $past['token'] ) );
		$this->assertIsArray( Tokens::verify( $future['token'] ) );
		$this->assert_rpc_error( $this->send( $past['token'] ), 401, -32001 );
	}

	public function test_revoke() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		$other   = Tokens::create( 'b', $this->editor_id, 'full', null );

		$this->assertTrue( Tokens::revoke( $created['id'] ) );
		$this->assertFalse( Tokens::revoke( $created['id'] ) );
		$this->assertFalse( Tokens::revoke( 'nope' ) );

		$this->assertNull( Tokens::verify( $created['token'] ) );
		$this->assertArrayNotHasKey( $created['id'], Tokens::all() );
		$this->assertIsArray( Tokens::verify( $other['token'] ) );
		$this->assert_rpc_error( $this->send( $created['token'] ), 401, -32001 );
	}

	public function test_query_and_bearer_both_work() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );

		$by_query = Tokens::authenticate( $this->request( $created['token'] ) );
		$this->assertIsArray( $by_query );
		$this->assertSame( $created['id'], $by_query['connection']['id'] );
		$this->assertInstanceOf( Context::class, $by_query['context'] );
		$this->assertSame( $this->editor_id, $by_query['context']->user_id );
		$this->assertSame( $this->editor_id, get_current_user_id() );

		wp_set_current_user( 0 );
		$by_bearer = Tokens::authenticate( $this->request( null, '  ' . $created['token'] . ' ' ) );
		$this->assertIsArray( $by_bearer );
		$this->assertSame( $created['id'], $by_bearer['connection']['id'] );

		// Query param is trimmed too.
		$this->assertIsArray( Tokens::authenticate( $this->request( ' ' . $created['token'] . "\n" ) ) );

		// End to end through the REST route.
		$this->assertSame( 200, $this->send( $created['token'] )->get_status() );
		$this->assertSame( 200, $this->send( null, null, $created['token'] )->get_status() );

		$err = Tokens::authenticate( $this->request() );
		$this->assertWPError( $err );
		$this->assertSame( 'wpdm_unauthorized', $err->get_error_code() );
		$this->assertSame( 401, $err->get_error_data()['status'] );
	}

	public function test_body_key_is_not_a_token_source() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		$payload = $this->rpc( 'ping' ) + array( 'key' => $created['token'] );

		// A valid token only in the JSON body is ignored.
		$this->assertWPError( Tokens::authenticate( $this->request( null, null, $payload ) ) );
		$this->assert_rpc_error( $this->send( null, $payload ), 401, -32001 );

		// A bogus body key does not shadow a valid ?key=.
		$bogus = $this->rpc( 'ping' ) + array( 'key' => str_repeat( 'f', 64 ) );
		$this->assertSame( 200, $this->send( $created['token'], $bogus )->get_status() );
	}

	public function test_key_stripped_from_request_after_auth() {
		$created          = Tokens::create( 'a', $this->editor_id, 'full', null );
		$_GET['key']      = $created['token'];
		$_REQUEST['key']  = $created['token'];
		$request          = $this->request( $created['token'] );
		try {
			$ctx = \WPDM\Mcp\Transport::authenticate( $request );
			$this->assertInstanceOf( Context::class, $ctx );
			$this->assertArrayNotHasKey( 'key', $request->get_query_params() );
			$this->assertNull( $request->get_param( 'key' ) );
			$this->assertArrayNotHasKey( 'key', $_GET );
			$this->assertArrayNotHasKey( 'key', $_REQUEST );
		} finally {
			unset( $_GET['key'], $_REQUEST['key'] );
		}
	}

	public function test_context_carries_connection_without_hash() {
		$created = Tokens::create( 'a', $this->editor_id, 'read', null );

		$auth = Tokens::authenticate( $this->request( $created['token'] ) );
		$this->assertTrue( $auth['context']->read_only );
		$this->assertArrayNotHasKey( 'hash', $auth['context']->connection );
		$this->assertArrayNotHasKey( 'hash', $auth['connection'] );
		$this->assertSame( $created['id'], $auth['context']->connection['id'] );

		$full = Tokens::create( 'b', $this->editor_id, 'full', null );
		$this->assertFalse( Tokens::authenticate( $this->request( $full['token'] ) )['context']->read_only );
	}

	public function test_rate_limit_429_after_10_failures() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );

		for ( $i = 0; $i < 10; $i++ ) {
			$res = $this->send( str_repeat( 'f', 64 ) );
			$this->assert_rpc_error( $res, 401, -32001 );
		}

		// Blocked before verifying: even the valid token is refused.
		$res = $this->send( $created['token'] );
		$this->assert_rpc_error( $res, 429, -32002 );
		$this->assertSame( 'Too many failed attempts', $res->get_data()['error']['message'] );
		$this->assertSame( '60', (string) $res->get_headers()['Retry-After'] );
		$this->assertArrayNotHasKey( 'WWW-Authenticate', $res->get_headers() );

		// Another client IP is unaffected.
		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		$this->assertSame( 200, $this->send( $created['token'] )->get_status() );
	}

	public function test_rate_limit_ignores_x_forwarded_for() {
		for ( $i = 0; $i < 10; $i++ ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.' . $i;
			$request                         = $this->request( 'bad' );
			$request->set_header( 'X-Forwarded-For', '10.0.0.' . $i );
			rest_get_server()->dispatch( $request );
		}
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.9.9.9';
		$request                         = $this->request( 'bad' );
		$request->set_header( 'X-Forwarded-For', '10.9.9.9' );
		$this->assert_rpc_error( rest_get_server()->dispatch( $request ), 429, -32002 );
	}

	public function test_rate_limit_window_expires() {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->send( 'bad' );
		}
		$this->assertSame( 429, $this->send( 'bad' )->get_status() );

		// Simulate the 60 s window passing.
		delete_transient( 'wpdm_fail_' . md5( '203.0.113.7' ) );
		$this->assertSame( 401, $this->send( 'bad' )->get_status() );
	}

	public function test_failure_messages_never_echo_the_token() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		Tokens::revoke( $created['id'] );

		$res  = $this->send( $created['token'] );
		$dump = wp_json_encode( $res->get_data() ) . wp_json_encode( $res->get_headers() );
		$this->assertStringNotContainsString( $created['token'], $dump );
	}

	public function test_token_of_deleted_user_rejected() {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		$this->assertIsArray( Tokens::verify( $created['token'] ) );

		wp_delete_user( $this->editor_id );

		$this->assertNull( Tokens::verify( $created['token'] ) );
		$this->assert_rpc_error( $this->send( $created['token'] ), 401, -32001 );
	}

	public function test_token_of_user_without_edit_posts_rejected() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		( new WP_User( $this->editor_id ) )->set_role( 'subscriber' );

		$this->assertNull( Tokens::verify( $created['token'] ) );
		$this->assert_rpc_error( $this->send( $created['token'] ), 401, -32001 );
	}

	public function test_capabilities_checked_per_call() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );
		$call    = $this->rpc( 'tools/call', array( 'name' => 'create_post' ) );

		$ok = $this->send( $created['token'], $call );
		$this->assertSame( 200, $ok->get_status() );
		$this->assertArrayNotHasKey( 'isError', $ok->get_data()['result'] );

		// Contributor still has edit_posts (token stays valid) but not publish_posts.
		( new WP_User( $this->editor_id ) )->set_role( 'contributor' );
		$denied = $this->send( $created['token'], $call );
		$this->assertSame( 200, $denied->get_status() );
		$this->assertTrue( $denied->get_data()['result']['isError'] );
	}

	public function test_read_mode_hides_write_tools() {
		$created = Tokens::create( 'a', $this->editor_id, 'read', null );

		$list  = $this->send( $created['token'], $this->rpc( 'tools/list' ) );
		$names = wp_list_pluck( $list->get_data()['result']['tools'], 'name' );
		$this->assertContains( 'read_thing', $names );
		$this->assertNotContains( 'create_post', $names );

		$call = $this->send( $created['token'], $this->rpc( 'tools/call', array( 'name' => 'create_post' ) ) );
		$this->assertTrue( $call->get_data()['result']['isError'] );

		$read = $this->send( $created['token'], $this->rpc( 'tools/call', array( 'name' => 'read_thing' ) ) );
		$out  = $read->get_data()['result']['structuredContent'];
		$this->assertSame( $this->editor_id, $out['user'] );
		$this->assertTrue( $out['read_only'] );
		$this->assertArrayNotHasKey( 'hash', $out['connection'] );
	}

	public function test_last_used_updated_at_most_once_per_minute() {
		$created = Tokens::create( 'a', $this->editor_id, 'full', null );

		Tokens::authenticate( $this->request( $created['token'] ) );
		$conn = Tokens::all()[ $created['id'] ];
		$this->assertIsInt( $conn['last_used'] );
		$this->assertSame( '203.0.113.7', $conn['last_ip'] );

		// Pretend it was used 30 s ago from elsewhere: no rewrite yet.
		$stored                                = get_option( 'wpdm_connections' );
		$stored[ $created['id'] ]['last_used'] = time() - 30;
		$stored[ $created['id'] ]['last_ip']   = '192.0.2.1';
		update_option( 'wpdm_connections', $stored, false );

		Tokens::authenticate( $this->request( $created['token'] ) );
		$this->assertSame( '192.0.2.1', Tokens::all()[ $created['id'] ]['last_ip'] );

		// Older than a minute: rewritten.
		$stored[ $created['id'] ]['last_used'] = time() - 61;
		update_option( 'wpdm_connections', $stored, false );

		Tokens::authenticate( $this->request( $created['token'] ) );
		$conn = Tokens::all()[ $created['id'] ];
		$this->assertSame( '203.0.113.7', $conn['last_ip'] );
		$this->assertGreaterThanOrEqual( time() - 5, $conn['last_used'] );
	}

	public function test_test_context_filter_is_not_an_auth_bypass() {
		$editor_id = $this->editor_id;
		add_filter(
			'wpdm_test_context',
			static function () use ( $editor_id ) {
				return new Context( $editor_id, array( 'id' => 'evil' ) );
			}
		);
		$this->assert_rpc_error( $this->send( null ), 401, -32001 );
	}

	private function assert_rpc_error( WP_REST_Response $res, int $status, int $code ) {
		$this->assertSame( $status, $res->get_status() );
		$data = $res->get_data();
		$this->assertSame( '2.0', $data['jsonrpc'] );
		$this->assertNull( $data['id'] );
		$this->assertSame( $code, $data['error']['code'] );
	}
}
