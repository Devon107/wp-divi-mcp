<?php
/**
 * Transport-independent MCP / JSON-RPC 2.0 server.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatches decoded JSON-RPC messages to the registry. Parsing and HTTP
 * concerns (including -32700) belong to the transport.
 */
class Server {

	const LATEST_PROTOCOL     = '2025-06-18';
	const SUPPORTED_PROTOCOLS = array( '2025-06-18', '2025-03-26' );

	const ERR_INVALID_REQUEST = -32600;
	const ERR_METHOD_NOT_FOUND = -32601;
	const ERR_INVALID_PARAMS  = -32602;
	const ERR_INTERNAL        = -32603;

	/**
	 * @var Registry
	 */
	private $registry;

	/**
	 * @var string
	 */
	private $instructions;

	/**
	 * @param Registry $registry     Registry of tools/resources/prompts.
	 * @param string   $instructions Server instructions sent on initialize.
	 */
	public function __construct( Registry $registry, string $instructions ) {
		$this->registry     = $registry;
		$this->instructions = $instructions;
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param array   $message Decoded message.
	 * @param Context $ctx     Request context.
	 * @return array|null Response, or null for notifications / client responses.
	 */
	public function handle( array $message, Context $ctx ): ?array {
		$has_id = array_key_exists( 'id', $message );
		$id     = $has_id ? $message['id'] : null;

		if ( ! isset( $message['method'] ) || ! is_string( $message['method'] ) ) {
			// A response from the client to a server request: nothing to do.
			if ( array_key_exists( 'result', $message ) || array_key_exists( 'error', $message ) ) {
				return null;
			}
			return $this->error( $id, self::ERR_INVALID_REQUEST, 'Invalid Request' );
		}

		$method = $message['method'];

		if ( 0 === strncmp( $method, 'notifications/', 14 ) ) {
			return null;
		}

		$params = $message['params'] ?? array();
		if ( ! is_array( $params ) ) {
			return $has_id ? $this->error( $id, self::ERR_INVALID_PARAMS, 'Params must be an object' ) : null;
		}

		$response = $this->dispatch( $method, $params, $ctx, $id );

		// JSON-RPC: a message without an id is a notification and gets no reply.
		return $has_id ? $response : null;
	}

	/**
	 * Handle a JSON-RPC batch.
	 *
	 * @param array   $messages Decoded messages.
	 * @param Context $ctx      Request context.
	 * @return array List of responses (notifications produce none).
	 */
	public function handle_batch( array $messages, Context $ctx ): array {
		$out = array();
		foreach ( $messages as $message ) {
			$response = is_array( $message )
				? $this->handle( $message, $ctx )
				: $this->error( null, self::ERR_INVALID_REQUEST, 'Invalid Request' );
			if ( null !== $response ) {
				$out[] = $response;
			}
		}
		return $out;
	}

	/**
	 * Route a method to its handler.
	 *
	 * @param string  $method Method name.
	 * @param array   $params Params.
	 * @param Context $ctx    Context.
	 * @param mixed   $id     Request id.
	 * @return array Full response.
	 */
	private function dispatch( string $method, array $params, Context $ctx, $id ): array {
		switch ( $method ) {
			case 'initialize':
				return $this->ok( $id, $this->initialize( $params ) );
			case 'ping':
				return $this->ok( $id, new \stdClass() );
			case 'tools/list':
				return $this->ok( $id, array( 'tools' => $this->list_tools( $ctx ) ) );
			case 'tools/call':
				return $this->call_tool( $id, $params, $ctx );
			case 'resources/list':
				return $this->ok( $id, array( 'resources' => $this->list_resources() ) );
			case 'resources/templates/list':
				return $this->ok( $id, array( 'resourceTemplates' => $this->list_resource_templates() ) );
			case 'resources/read':
				return $this->read_resource( $id, $params );
			case 'prompts/list':
				return $this->ok( $id, array( 'prompts' => $this->list_prompts() ) );
			case 'prompts/get':
				return $this->get_prompt( $id, $params );
		}

		return $this->error( $id, self::ERR_METHOD_NOT_FOUND, 'Method not found: ' . $method );
	}

	/**
	 * @param array $params Params.
	 * @return array
	 */
	private function initialize( array $params ): array {
		$requested = $params['protocolVersion'] ?? null;
		$version   = ( is_string( $requested ) && in_array( $requested, self::SUPPORTED_PROTOCOLS, true ) )
			? $requested
			: self::LATEST_PROTOCOL;

		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools'     => array( 'listChanged' => false ),
				'resources' => new \stdClass(),
				'prompts'   => new \stdClass(),
			),
			'serverInfo'      => array(
				'name'    => 'wp-divi-mcp',
				'version' => WPDM_VERSION,
			),
			'instructions'    => $this->instructions,
		);
	}

	/**
	 * @param Context $ctx Context.
	 * @return array
	 */
	private function list_tools( Context $ctx ): array {
		$out = array();
		foreach ( $this->registry->tools( $ctx ) as $tool ) {
			$schema = $tool->input_schema();
			if ( empty( $schema ) ) {
				$schema = array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				);
			}
			$out[] = array(
				'name'        => $tool->name(),
				'description' => $tool->description(),
				'inputSchema' => $schema,
				'annotations' => array(
					'readOnlyHint'    => $tool->read_only(),
					'destructiveHint' => $tool->destructive(),
					'openWorldHint'   => false,
				),
			);
		}
		return $out;
	}

	/**
	 * @param mixed   $id     Request id.
	 * @param array   $params Params.
	 * @param Context $ctx    Context.
	 * @return array
	 */
	private function call_tool( $id, array $params, Context $ctx ): array {
		$name = $params['name'] ?? null;
		if ( ! is_string( $name ) || '' === $name ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Missing tool name' );
		}

		$args = $params['arguments'] ?? array();
		if ( ! is_array( $args ) ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Tool arguments must be an object' );
		}

		$tool = $this->registry->tool( $name );
		if ( null === $tool ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Unknown tool: ' . $name );
		}

		// Re-check at call time: listing is advisory, this is the enforcement point.
		if ( $ctx->read_only && ! $tool->read_only() ) {
			return $this->tool_failure( $id, $name, $args, $ctx, 'read_only', 'This connection is read-only; the tool "' . $name . '" can modify the site.' );
		}
		if ( ! user_can( $ctx->user_id, $tool->capability() ) ) {
			return $this->tool_failure( $id, $name, $args, $ctx, 'forbidden', 'The current user lacks the required capability: ' . $tool->capability() );
		}

		$start = microtime( true );
		try {
			$result = $tool->execute( $args, $ctx );
		} catch ( ToolError $e ) {
			return $this->tool_failure( $id, $name, $args, $ctx, $e->error_code(), $e->getMessage(), $start, $e->data() );
		} catch ( \Throwable $e ) {
			// Message only: never leak a stack trace to the client.
			return $this->tool_failure( $id, $name, $args, $ctx, 'internal_error', $e->getMessage(), $start );
		}

		$ms = ( microtime( true ) - $start ) * 1000;
		// Log hook: ( $name, $args, $ok, $ms, $ctx ).
		do_action( 'wpdm_tool_called', $name, $args, true, $ms, $ctx );

		$text = wp_json_encode( $result );
		return $this->ok(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => false === $text ? '' : $text,
					),
				),
				// structuredContent must be a JSON object, even when empty.
				'structuredContent' => empty( $result ) ? new \stdClass() : $result,
			)
		);
	}

	/**
	 * Build an isError tool result and fire the log action.
	 *
	 * The text content is "<code>: <message>"; structuredContent carries
	 * {error: code, message, data} so clients can act on the code.
	 *
	 * @param mixed      $id      Request id.
	 * @param string     $name    Tool name.
	 * @param array      $args    Arguments.
	 * @param Context    $ctx     Context.
	 * @param string     $code    Machine-readable error code.
	 * @param string     $message Message for the client.
	 * @param float|null $start   microtime() at start, null when never ran.
	 * @param array      $data    Extra structured data.
	 * @return array
	 */
	private function tool_failure( $id, string $name, array $args, Context $ctx, string $code, string $message, ?float $start = null, array $data = array() ): array {
		$ms = null === $start ? 0.0 : ( microtime( true ) - $start ) * 1000;
		do_action( 'wpdm_tool_called', $name, $args, false, $ms, $ctx );

		return $this->ok(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => $code . ': ' . $message,
					),
				),
				'structuredContent' => array(
					'error'   => $code,
					'message' => $message,
					// Must encode as a JSON object, even when empty.
					'data'    => empty( $data ) ? new \stdClass() : $data,
				),
				'isError'           => true,
			)
		);
	}

	/**
	 * @return array
	 */
	private function list_resources(): array {
		$out = array();
		foreach ( $this->registry->resources() as $res ) {
			$entry = array(
				'uri'      => $res->uri,
				'name'     => $res->name,
				'mimeType' => $res->mime,
			);
			if ( '' !== $res->description ) {
				$entry['description'] = $res->description;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function list_resource_templates(): array {
		$out = array();
		foreach ( $this->registry->resource_templates() as $t ) {
			$out[] = array(
				'uriTemplate' => $t['uri_template'],
				'name'        => $t['name'],
				'mimeType'    => $t['mime'],
			);
		}
		return $out;
	}

	/**
	 * @param mixed $id     Request id.
	 * @param array $params Params.
	 * @return array
	 */
	private function read_resource( $id, array $params ): array {
		$uri = $params['uri'] ?? null;
		if ( ! is_string( $uri ) || '' === $uri ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Missing resource uri' );
		}

		try {
			$content = $this->registry->read_resource( $uri );
		} catch ( \Throwable $e ) {
			return $this->error( $id, self::ERR_INTERNAL, $e->getMessage() );
		}

		if ( null === $content ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Unknown resource: ' . $uri );
		}

		return $this->ok( $id, array( 'contents' => array( $content ) ) );
	}

	/**
	 * @return array
	 */
	private function list_prompts(): array {
		$out = array();
		foreach ( $this->registry->prompts() as $prompt ) {
			$out[] = array(
				'name'        => $prompt->name,
				'description' => $prompt->description,
				'arguments'   => $prompt->arguments,
			);
		}
		return $out;
	}

	/**
	 * @param mixed $id     Request id.
	 * @param array $params Params.
	 * @return array
	 */
	private function get_prompt( $id, array $params ): array {
		$name = $params['name'] ?? null;
		if ( ! is_string( $name ) || '' === $name ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Missing prompt name' );
		}

		$prompt = $this->registry->prompt( $name );
		if ( null === $prompt ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Unknown prompt: ' . $name );
		}

		$args = $params['arguments'] ?? array();
		if ( ! is_array( $args ) ) {
			return $this->error( $id, self::ERR_INVALID_PARAMS, 'Prompt arguments must be an object' );
		}

		foreach ( $prompt->arguments as $def ) {
			if ( ! empty( $def['required'] ) && isset( $def['name'] ) && ! array_key_exists( $def['name'], $args ) ) {
				return $this->error( $id, self::ERR_INVALID_PARAMS, 'Missing required argument: ' . $def['name'] );
			}
		}

		try {
			$messages = call_user_func( $prompt->render, $args );
		} catch ( \Throwable $e ) {
			return $this->error( $id, self::ERR_INTERNAL, $e->getMessage() );
		}

		return $this->ok(
			$id,
			array(
				'description' => $prompt->description,
				'messages'    => $messages,
			)
		);
	}

	/**
	 * @param mixed $id     Request id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	private function ok( $id, $result ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * @param mixed  $id      Request id (null when unknown).
	 * @param int    $code    JSON-RPC error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private function error( $id, int $code, string $message ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
