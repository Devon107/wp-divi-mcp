<?php
/**
 * Tests for the MCP JSON-RPC server core.
 *
 * @package WPDM
 */

use WPDM\Mcp\Context;
use WPDM\Mcp\Prompt;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Resource;
use WPDM\Mcp\Server;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

class WPDM_Test_Read_Tool extends Tool {
	public function name(): string {
		return 'test_read';
	}
	public function description(): string {
		return 'Read-only test tool.';
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
		return array( 'echo' => $args );
	}
}

class WPDM_Test_Write_Tool extends Tool {
	public $mode = 'ok';
	public function name(): string {
		return 'test_write';
	}
	public function description(): string {
		return 'Write test tool.';
	}
	public function input_schema(): array {
		return array( 'type' => 'object', 'properties' => new stdClass() );
	}
	public function destructive(): bool {
		return true;
	}
	public function execute( array $args, Context $ctx ): array {
		if ( 'tool_error' === $this->mode ) {
			throw new ToolError( 'Nope, bad input', 'bad_input', array( 'field' => 'x' ) );
		}
		if ( 'exception' === $this->mode ) {
			throw new \RuntimeException( 'Boom' );
		}
		return array( 'written' => true );
	}
}

class ServerTest extends WP_UnitTestCase {

	/** @var WPDM_Test_Write_Tool */
	private $write;

	/** @var Registry */
	private $registry;

	/** @var Server */
	private $server;

	public function set_up() {
		parent::set_up();
		$this->write    = new WPDM_Test_Write_Tool();
		$this->registry = new Registry();
		$this->registry->add_tool( new WPDM_Test_Read_Tool() );
		$this->registry->add_tool( $this->write );
		$this->registry->add_resource(
			new Resource(
				'wpdm://guide',
				'Guide',
				'text/markdown',
				static function () {
					return '# Guide';
				},
				'A guide'
			)
		);
		$this->registry->add_resource_template(
			'wpdm://page/{id}/{slug}',
			'Page',
			'text/plain',
			static function ( array $vars ) {
				return 'page ' . $vars['id'] . ' ' . $vars['slug'];
			}
		);
		$this->registry->add_prompt(
			new Prompt(
				'greet',
				'Greets',
				array( array( 'name' => 'who', 'required' => true ) ),
				static function ( array $args ) {
					return array(
						array(
							'role'    => 'user',
							'content' => array( 'type' => 'text', 'text' => 'Hi ' . ( $args['who'] ?? '' ) ),
						),
					);
				}
			)
		);
		$this->server = new Server( $this->registry, 'Be nice.' );
	}

	private function admin_ctx( bool $read_only = false ): Context {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		return new Context( $id, array(), $read_only );
	}

	private function rpc( string $method, array $params = array(), $id = 1 ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => $params,
		);
	}

	public function test_initialize_negotiates_version() {
		$ctx = $this->admin_ctx();

		$res = $this->server->handle( $this->rpc( 'initialize', array( 'protocolVersion' => '2025-03-26' ) ), $ctx );
		$this->assertSame( '2025-03-26', $res['result']['protocolVersion'] );

		$res = $this->server->handle( $this->rpc( 'initialize', array( 'protocolVersion' => '1999-01-01' ) ), $ctx );
		$this->assertSame( '2025-06-18', $res['result']['protocolVersion'] );
		$this->assertSame( '2.0', $res['jsonrpc'] );
		$this->assertSame( 1, $res['id'] );
		$this->assertSame( 'wp-divi-mcp', $res['result']['serverInfo']['name'] );
		$this->assertSame( WPDM_VERSION, $res['result']['serverInfo']['version'] );
		$this->assertSame( 'Be nice.', $res['result']['instructions'] );
		$this->assertFalse( $res['result']['capabilities']['tools']['listChanged'] );
		$this->assertArrayHasKey( 'resources', $res['result']['capabilities'] );
		$this->assertArrayHasKey( 'prompts', $res['result']['capabilities'] );
	}

	public function test_unknown_method_is_32601() {
		$res = $this->server->handle( $this->rpc( 'nope/nothing' ), $this->admin_ctx() );
		$this->assertSame( -32601, $res['error']['code'] );
		$this->assertArrayNotHasKey( 'result', $res );
	}

	public function test_ping_returns_empty_result() {
		$res = $this->server->handle( $this->rpc( 'ping' ), $this->admin_ctx() );
		$this->assertArrayHasKey( 'result', $res );
		$this->assertEmpty( (array) $res['result'] );
	}

	public function test_tools_call_tool_error_is_iserror_not_protocol_error() {
		$this->write->mode = 'tool_error';
		$res               = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_write', 'arguments' => array() ) ),
			$this->admin_ctx()
		);
		$this->assertArrayNotHasKey( 'error', $res );
		$this->assertTrue( $res['result']['isError'] );
		$this->assertSame( 'text', $res['result']['content'][0]['type'] );
		$this->assertSame( 'bad_input: Nope, bad input', $res['result']['content'][0]['text'] );
		$this->assertSame(
			array(
				'error'   => 'bad_input',
				'message' => 'Nope, bad input',
				'data'    => array( 'field' => 'x' ),
			),
			$res['result']['structuredContent']
		);
	}

	public function test_tools_call_generic_exception_is_iserror_without_trace() {
		$this->write->mode = 'exception';
		$res               = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_write', 'arguments' => array() ) ),
			$this->admin_ctx()
		);
		$this->assertTrue( $res['result']['isError'] );
		$this->assertSame( 'internal_error: Boom', $res['result']['content'][0]['text'] );
		$this->assertSame( 'internal_error', $res['result']['structuredContent']['error'] );
		$this->assertEquals( new stdClass(), $res['result']['structuredContent']['data'], 'empty data is a JSON object' );
		$this->assertStringNotContainsString( '#0', $res['result']['content'][0]['text'] );
	}

	public function test_tools_call_success_has_content_and_structured_content() {
		$res = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_write', 'arguments' => array() ) ),
			$this->admin_ctx()
		);
		$this->assertArrayNotHasKey( 'isError', $res['result'] );
		$this->assertSame( array( 'written' => true ), $res['result']['structuredContent'] );
		$this->assertSame( '{"written":true}', $res['result']['content'][0]['text'] );
	}

	public function test_tools_call_unknown_tool_is_32602() {
		$res = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'missing' ) ),
			$this->admin_ctx()
		);
		$this->assertSame( -32602, $res['error']['code'] );
	}

	public function test_tools_call_missing_name_is_32602() {
		$res = $this->server->handle( $this->rpc( 'tools/call', array() ), $this->admin_ctx() );
		$this->assertSame( -32602, $res['error']['code'] );
	}

	public function test_tools_call_fires_log_action() {
		$seen = array();
		add_action(
			'wpdm_tool_called',
			static function ( $name, $args, $ok, $ms, $ctx ) use ( &$seen ) {
				$seen = array( $name, $args, $ok, $ms, $ctx );
			},
			10,
			5
		);
		$ctx = $this->admin_ctx();
		$this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_read', 'arguments' => array( 'a' => 1 ) ) ),
			$ctx
		);
		$this->assertSame( 'test_read', $seen[0] );
		$this->assertSame( array( 'a' => 1 ), $seen[1] );
		$this->assertTrue( $seen[2] );
		$this->assertIsNumeric( $seen[3] );
		$this->assertSame( $ctx, $seen[4] );
	}

	public function test_read_only_context_hides_write_tools() {
		$ctx   = $this->admin_ctx( true );
		$names = array_map(
			static function ( $t ) {
				return $t->name();
			},
			$this->registry->tools( $ctx )
		);
		$this->assertSame( array( 'test_read' ), array_values( $names ) );

		$list = $this->server->handle( $this->rpc( 'tools/list' ), $ctx );
		$this->assertCount( 1, $list['result']['tools'] );

		$res = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_write', 'arguments' => array() ) ),
			$ctx
		);
		$this->assertTrue( $res['result']['isError'] );
		$this->assertStringContainsString( 'read-only', $res['result']['content'][0]['text'] );
		$this->assertSame( 'read_only', $res['result']['structuredContent']['error'] );
	}

	public function test_tools_list_entry_shape() {
		$res   = $this->server->handle( $this->rpc( 'tools/list' ), $this->admin_ctx() );
		$tools = array();
		foreach ( $res['result']['tools'] as $t ) {
			$tools[ $t['name'] ] = $t;
		}
		$this->assertArrayHasKey( 'inputSchema', $tools['test_write'] );
		$this->assertSame( 'Write test tool.', $tools['test_write']['description'] );
		$this->assertFalse( $tools['test_write']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['test_write']['annotations']['destructiveHint'] );
		$this->assertFalse( $tools['test_write']['annotations']['openWorldHint'] );
		$this->assertTrue( $tools['test_read']['annotations']['readOnlyHint'] );
	}

	public function test_tools_list_hides_tools_without_capability() {
		$sub = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$res = $this->server->handle( $this->rpc( 'tools/list' ), new Context( $sub ) );
		$names = wp_list_pluck( $res['result']['tools'], 'name' );
		$this->assertSame( array( 'test_read' ), array_values( $names ) );
	}

	public function test_tools_call_rechecks_capability() {
		$sub = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$res = $this->server->handle(
			$this->rpc( 'tools/call', array( 'name' => 'test_write', 'arguments' => array() ) ),
			new Context( $sub )
		);
		$this->assertTrue( $res['result']['isError'] );
		$this->assertStringContainsString( 'capability', $res['result']['content'][0]['text'] );
		$this->assertSame( 'forbidden', $res['result']['structuredContent']['error'] );
	}

	public function test_notification_returns_null() {
		$res = $this->server->handle(
			array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ),
			$this->admin_ctx()
		);
		$this->assertNull( $res );
	}

	public function test_any_notifications_prefix_is_ignored() {
		$res = $this->server->handle(
			array( 'jsonrpc' => '2.0', 'method' => 'notifications/whatever' ),
			$this->admin_ctx()
		);
		$this->assertNull( $res );
	}

	public function test_batch_mixed_requests_and_notifications() {
		$out = $this->server->handle_batch(
			array(
				$this->rpc( 'ping', array(), 1 ),
				array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ),
				$this->rpc( 'tools/list', array(), 2 ),
			),
			$this->admin_ctx()
		);
		$this->assertCount( 2, $out );
		$this->assertSame( array( 0, 1 ), array_keys( $out ) );
		$this->assertSame( 1, $out[0]['id'] );
		$this->assertSame( 2, $out[1]['id'] );
	}

	public function test_resources_list_and_templates() {
		$ctx = $this->admin_ctx();
		$res = $this->server->handle( $this->rpc( 'resources/list' ), $ctx );
		$this->assertSame( 'wpdm://guide', $res['result']['resources'][0]['uri'] );
		$this->assertSame( 'text/markdown', $res['result']['resources'][0]['mimeType'] );
		$this->assertSame( 'A guide', $res['result']['resources'][0]['description'] );

		$res = $this->server->handle( $this->rpc( 'resources/templates/list' ), $ctx );
		$this->assertSame( 'wpdm://page/{id}/{slug}', $res['result']['resourceTemplates'][0]['uriTemplate'] );
		$this->assertSame( 'Page', $res['result']['resourceTemplates'][0]['name'] );
	}

	public function test_resources_read_static_template_and_unknown() {
		$ctx = $this->admin_ctx();

		$res = $this->server->handle( $this->rpc( 'resources/read', array( 'uri' => 'wpdm://guide' ) ), $ctx );
		$this->assertSame(
			array(
				array(
					'uri'      => 'wpdm://guide',
					'mimeType' => 'text/markdown',
					'text'     => '# Guide',
				),
			),
			$res['result']['contents']
		);

		$res = $this->server->handle( $this->rpc( 'resources/read', array( 'uri' => 'wpdm://page/42/about-us' ) ), $ctx );
		$this->assertSame( 'page 42 about-us', $res['result']['contents'][0]['text'] );
		$this->assertSame( 'wpdm://page/42/about-us', $res['result']['contents'][0]['uri'] );
		$this->assertSame( 'text/plain', $res['result']['contents'][0]['mimeType'] );

		$res = $this->server->handle( $this->rpc( 'resources/read', array( 'uri' => 'wpdm://nope' ) ), $ctx );
		$this->assertSame( -32602, $res['error']['code'] );

		$res = $this->server->handle( $this->rpc( 'resources/read', array() ), $ctx );
		$this->assertSame( -32602, $res['error']['code'] );
	}

	public function test_prompts_list_and_get() {
		$ctx = $this->admin_ctx();
		$res = $this->server->handle( $this->rpc( 'prompts/list' ), $ctx );
		$this->assertSame( 'greet', $res['result']['prompts'][0]['name'] );
		$this->assertSame( 'Greets', $res['result']['prompts'][0]['description'] );
		$this->assertSame( 'who', $res['result']['prompts'][0]['arguments'][0]['name'] );

		$res = $this->server->handle(
			$this->rpc( 'prompts/get', array( 'name' => 'greet', 'arguments' => array( 'who' => 'Bob' ) ) ),
			$ctx
		);
		$this->assertSame( 'Hi Bob', $res['result']['messages'][0]['content']['text'] );

		$res = $this->server->handle( $this->rpc( 'prompts/get', array( 'name' => 'missing' ) ), $ctx );
		$this->assertSame( -32602, $res['error']['code'] );
	}
}
