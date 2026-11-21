<?php
/**
 * Tests for the MCP Streamable HTTP transport (REST route).
 *
 * @package WPDM
 */

use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Tool;

class WPDM_Transport_Test_Tool extends Tool {
	public function name(): string {
		return 'whoami';
	}
	public function description(): string {
		return 'Reports the current user.';
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
			'user'         => get_current_user_id(),
			'can_manage'   => current_user_can( 'manage_options' ),
			'session'      => $ctx->session_id,
		);
	}
}

class TransportTest extends WP_UnitTestCase {

	const ROUTE = '/wp-divi-mcp/v1/mcp';

	/** @var int */
	private $admin_id;

	public function set_up() {
		parent::set_up();

		// Fresh REST server so rest_api_init (and our route) fires for this test.
		$GLOBALS['wp_rest_server'] = null;

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$admin_id       = $this->admin_id;

		add_filter(
			'wpdm_test_context',
			static function () use ( $admin_id ) {
				return new Context( $admin_id, array( 'id' => 'conn-1' ) );
			}
		);
		// The test site runs on plain http: don't enforce https unless a test asks for it.
		add_filter( 'wpdm_require_https', '__return_false' );
		add_filter(
			'wpdm_registry',
			static function ( Registry $registry ) {
				$registry->add_tool( new WPDM_Transport_Test_Tool() );
				return $registry;
			}
		);
	}

	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( 0 );
		delete_option( 'wpdm_disabled' );
		parent::tear_down();
	}

	/**
	 * @param array|string $payload Array is JSON-encoded; string is sent verbatim.
	 * @param array        $headers Extra request headers.
	 * @param string       $method  HTTP method.
	 * @return WP_REST_Response
	 */
	private function send( $payload = null, array $headers = array(), string $method = 'POST' ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		if ( null !== $payload ) {
			$request->set_body( is_string( $payload ) ? $payload : wp_json_encode( $payload ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function rpc( string $method, array $params = array(), $id = 1 ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => $params,
		);
	}

	private function initialize(): WP_REST_Response {
		return $this->send( $this->rpc( 'initialize', array( 'protocolVersion' => '2025-06-18' ) ) );
	}

	private function assert_rpc_error( WP_REST_Response $res, int $status, int $code ) {
		$this->assertSame( $status, $res->get_status() );
		$data = $res->get_data();
		$this->assertSame( '2.0', $data['jsonrpc'] );
		$this->assertNull( $data['id'] );
		$this->assertSame( $code, $data['error']['code'] );
		$this->assertIsString( $data['error']['message'] );
		$this->assertNotSame( '', $data['error']['message'] );
	}

	public function test_initialize_sets_session_header() {
		$res = $this->initialize();

		$this->assertSame( 200, $res->get_status() );
		$headers = $res->get_headers();
		$this->assertArrayHasKey( 'Mcp-Session-Id', $headers );
		$sid = $headers['Mcp-Session-Id'];
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $sid );
		$this->assertSame( 'conn-1', get_transient( 'wpdm_sess_' . $sid ) );

		// Raw JSON-RPC, not wrapped in a WP envelope.
		$data = $res->get_data();
		$this->assertSame( '2.0', $data['jsonrpc'] );
		$this->assertSame( 1, $data['id'] );
		$this->assertSame( '2025-06-18', $data['result']['protocolVersion'] );
	}

	public function test_initialize_inside_batch_sets_session_header() {
		$res = $this->send( array( $this->rpc( 'initialize', array(), 1 ), $this->rpc( 'ping', array(), 2 ) ) );

		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayHasKey( 'Mcp-Session-Id', $res->get_headers() );
		$data = $res->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 1, $data[0]['id'] );
		$this->assertSame( 2, $data[1]['id'] );
	}

	public function test_instructions_filter_reaches_initialize() {
		add_filter(
			'wpdm_instructions',
			static function () {
				return 'Use the tools wisely.';
			}
		);
		$data = $this->initialize()->get_data();
		$this->assertSame( 'Use the tools wisely.', $data['result']['instructions'] );
	}

	public function test_notification_returns_202_empty() {
		$res = $this->send( array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ) );

		$this->assertSame( 202, $res->get_status() );
		$this->assertNull( $res->get_data() );
	}

	public function test_batch_of_only_notifications_returns_202() {
		$res = $this->send(
			array(
				array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ),
				array( 'jsonrpc' => '2.0', 'method' => 'notifications/cancelled' ),
			)
		);
		$this->assertSame( 202, $res->get_status() );
		$this->assertNull( $res->get_data() );
	}

	public function test_mixed_batch_returns_responses_in_order() {
		$res = $this->send(
			array(
				$this->rpc( 'ping', array(), 'a' ),
				array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ),
				$this->rpc( 'ping', array(), 'b' ),
			)
		);
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'a', $data[0]['id'] );
		$this->assertSame( 'b', $data[1]['id'] );
	}

	public function test_request_without_session_header_is_accepted() {
		$res = $this->send( $this->rpc( 'ping' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayNotHasKey( 'Mcp-Session-Id', $res->get_headers() );
	}

	public function test_valid_session_is_echoed_and_passed_to_context() {
		$sid = $this->initialize()->get_headers()['Mcp-Session-Id'];

		$res = $this->send(
			$this->rpc( 'tools/call', array( 'name' => 'whoami' ) ),
			array( 'Mcp-Session-Id' => $sid )
		);

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( $sid, $res->get_headers()['Mcp-Session-Id'] );
		$result = $res->get_data()['result']['structuredContent'];
		$this->assertSame( $sid, $result['session'] );
	}

	public function test_unknown_session_404() {
		$res = $this->send( $this->rpc( 'ping' ), array( 'Mcp-Session-Id' => str_repeat( 'a', 32 ) ) );
		$this->assert_rpc_error( $res, 404, -32001 );
		$this->assertSame( 'Session not found', $res->get_data()['error']['message'] );
	}

	public function test_malformed_session_id_404() {
		$res = $this->send( $this->rpc( 'ping' ), array( 'Mcp-Session-Id' => '../../etc' ) );
		$this->assert_rpc_error( $res, 404, -32001 );
	}

	public function test_get_returns_405() {
		$res = $this->send( null, array(), 'GET' );

		$this->assertSame( 405, $res->get_status() );
		$this->assertNull( $res->get_data() );
		$this->assertStringContainsString( 'POST', $res->get_headers()['Allow'] );
	}

	public function test_delete_ends_session() {
		$sid = $this->initialize()->get_headers()['Mcp-Session-Id'];

		$res = $this->send( null, array( 'Mcp-Session-Id' => $sid ), 'DELETE' );
		$this->assertSame( 204, $res->get_status() );
		$this->assertNull( $res->get_data() );
		$this->assertFalse( get_transient( 'wpdm_sess_' . $sid ) );

		$after = $this->send( $this->rpc( 'ping' ), array( 'Mcp-Session-Id' => $sid ) );
		$this->assert_rpc_error( $after, 404, -32001 );
	}

	public function test_delete_unknown_session_404() {
		$res = $this->send( null, array( 'Mcp-Session-Id' => str_repeat( 'b', 32 ) ), 'DELETE' );
		$this->assert_rpc_error( $res, 404, -32001 );
	}

	public function test_body_over_limit_returns_413() {
		add_filter(
			'wpdm_max_body',
			static function () {
				return 50;
			}
		);
		$res = $this->send( str_repeat( ' ', 51 ) . wp_json_encode( $this->rpc( 'ping' ) ) );
		$this->assert_rpc_error( $res, 413, -32600 );
	}

	public function test_body_at_limit_is_accepted() {
		$body = wp_json_encode( $this->rpc( 'ping' ) );
		add_filter(
			'wpdm_max_body',
			static function () use ( $body ) {
				return strlen( $body );
			}
		);
		$this->assertSame( 200, $this->send( $body )->get_status() );
	}

	public function test_default_max_body_is_8_mb() {
		$seen = null;
		add_filter(
			'wpdm_max_body',
			static function ( $default ) use ( &$seen ) {
				$seen = $default;
				return $default;
			}
		);
		$this->send( $this->rpc( 'ping' ) );
		$this->assertSame( 8 * 1024 * 1024, $seen );
	}

	public function test_invalid_json_32700() {
		$res = $this->send( '{not json' );
		$this->assert_rpc_error( $res, 400, -32700 );
	}

	public function test_empty_body_is_parse_error() {
		$res = $this->send( '' );
		$this->assert_rpc_error( $res, 400, -32700 );
	}

	public function test_scalar_json_is_invalid_request() {
		$res = $this->send( '42' );
		$this->assert_rpc_error( $res, 400, -32600 );
	}

	public function test_empty_batch_is_invalid_request() {
		$res = $this->send( '[]' );
		$this->assert_rpc_error( $res, 400, -32600 );
	}

	public function test_disabled_returns_503() {
		update_option( 'wpdm_disabled', true );
		$res = $this->send( $this->rpc( 'ping' ) );
		$this->assert_rpc_error( $res, 503, -32000 );
	}

	public function test_disabled_wins_over_auth() {
		update_option( 'wpdm_disabled', true );
		remove_all_filters( 'wpdm_test_context' );
		$this->assert_rpc_error( $this->send( $this->rpc( 'ping' ) ), 503, -32000 );
	}

	public function test_http_rejected_without_constant() {
		remove_filter( 'wpdm_require_https', '__return_false' );
		add_filter( 'wpdm_require_https', '__return_true' );

		$res = $this->send( $this->rpc( 'ping' ) );
		$this->assert_rpc_error( $res, 403, -32000 );
	}

	public function test_https_not_enforced_when_ssl() {
		remove_filter( 'wpdm_require_https', '__return_false' );
		add_filter( 'wpdm_require_https', '__return_true' );
		$_SERVER['HTTPS'] = 'on';
		try {
			$this->assertSame( 200, $this->send( $this->rpc( 'ping' ) )->get_status() );
		} finally {
			unset( $_SERVER['HTTPS'] );
		}
	}

	public function test_unauthenticated_returns_401() {
		remove_all_filters( 'wpdm_test_context' );

		$res = $this->send( $this->rpc( 'ping' ) );
		$this->assert_rpc_error( $res, 401, -32001 );
		$this->assertSame( 'Unauthorized', $res->get_data()['error']['message'] );
	}

	public function test_method_checks_run_before_auth() {
		remove_all_filters( 'wpdm_test_context' );
		$this->assertSame( 405, $this->send( null, array(), 'GET' )->get_status() );
	}

	public function test_auth_runs_before_json_parsing() {
		remove_all_filters( 'wpdm_test_context' );
		$this->assert_rpc_error( $this->send( '{bad' ), 401, -32001 );
	}

	public function test_tool_runs_as_context_user() {
		$res = $this->send( $this->rpc( 'tools/call', array( 'name' => 'whoami' ) ) );

		$this->assertSame( 200, $res->get_status() );
		$result = $res->get_data()['result']['structuredContent'];
		$this->assertSame( $this->admin_id, $result['user'] );
		$this->assertTrue( $result['can_manage'] );
	}

	public function test_route_is_registered_with_open_permission_callback() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( self::ROUTE, $routes );
		foreach ( $routes[ self::ROUTE ] as $handler ) {
			$this->assertTrue( call_user_func( $handler['permission_callback'], new WP_REST_Request() ) );
		}
	}

	public function test_registry_filter_called_once_per_request() {
		$calls = 0;
		add_filter(
			'wpdm_registry',
			static function ( $registry ) use ( &$calls ) {
				++$calls;
				return $registry;
			}
		);
		$this->send( array( $this->rpc( 'ping', array(), 1 ), $this->rpc( 'ping', array(), 2 ) ) );
		$this->assertSame( 1, $calls );
	}

	public function test_rest_query_params_do_not_reshape_response() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_query_params( array( '_fields' => 'id' ) );
		$request->set_body( wp_json_encode( $this->rpc( 'ping' ) ) );

		$res = rest_get_server()->dispatch( $request );
		$this->assertSame( '2.0', $res->get_data()['jsonrpc'] );
		$this->assertArrayHasKey( 'result', $res->get_data() );
	}
}
