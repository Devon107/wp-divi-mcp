<?php
/**
 * MCP Streamable HTTP transport (JSON responses only) over a WP REST route.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the MCP Server at POST /wp-json/wp-divi-mcp/v1/mcp.
 *
 * Authentication and every JSON-RPC error are handled inside dispatch() (the
 * route's permission_callback is open) so clients always get JSON-RPC error
 * bodies instead of WP's REST error shape.
 */
class Transport {

	const REST_NAMESPACE = 'wp-divi-mcp/v1';
	const ROUTE          = '/mcp';

	const SESSION_HEADER = 'Mcp-Session-Id';
	const SESSION_PREFIX = 'wpdm_sess_';

	const DEFAULT_MAX_BODY = 8388608; // 8 MB.

	const ERR_PARSE        = -32700;
	const ERR_SERVER       = -32000;
	const ERR_UNAUTHORIZED = -32001; // Also "Session not found".

	/**
	 * Register the REST route. Hooked to rest_api_init.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET,POST,DELETE',
				'callback'            => array( self::class, 'dispatch' ),
				'permission_callback' => '__return_true',
			)
		);

		// WP rejects a malformed JSON body with its own error shape before the
		// callback runs. We want a JSON-RPC -32700 instead, so let it through.
		add_filter( 'rest_request_before_callbacks', array( self::class, 'allow_invalid_json' ), 10, 3 );
	}

	/**
	 * Swallow WP's rest_invalid_json error for our route; dispatch() parses the body itself.
	 *
	 * @param mixed           $response Response so far.
	 * @param array           $handler  Matched handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function allow_invalid_json( $response, $handler, $request ) {
		if ( is_wp_error( $response )
			&& 'rest_invalid_json' === $response->get_error_code()
			&& $request instanceof WP_REST_Request
			&& '/' . self::REST_NAMESPACE . self::ROUTE === $request->get_route() ) {
			return null;
		}
		return $response;
	}

	/**
	 * Handle one HTTP request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function dispatch( WP_REST_Request $request ): WP_REST_Response {
		// Keep WP from reshaping or wrapping our JSON-RPC payload.
		$query = $request->get_query_params();
		unset( $query['_fields'], $query['_envelope'] );
		$request->set_query_params( $query );
		unset( $_GET['_envelope'] );

		if ( get_option( 'wpdm_disabled' ) ) {
			return self::error( 503, self::ERR_SERVER, 'MCP server is disabled' );
		}

		if ( self::https_required() && ! is_ssl() ) {
			return self::error( 403, self::ERR_SERVER, 'HTTPS is required' );
		}

		$body = $request->get_body();
		if ( strlen( $body ) > self::max_body() ) {
			return self::error( 413, Server::ERR_INVALID_REQUEST, 'Request body too large' );
		}

		$method = strtoupper( $request->get_method() );
		if ( 'GET' === $method ) {
			// No server-initiated SSE stream: this transport is POST-only.
			$response = new WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST, DELETE' );
			return $response;
		}
		if ( 'DELETE' === $method ) {
			return self::end_session( self::session_header( $request ) );
		}

		$ctx = self::authenticate( $request );
		if ( is_wp_error( $ctx ) ) {
			$status   = (int) ( $ctx->get_error_data()['status'] ?? 401 );
			$response = self::error( $status, self::ERR_UNAUTHORIZED, $ctx->get_error_message() );
			if ( 401 === $status ) {
				$response->header( 'WWW-Authenticate', 'Bearer' );
			}
			return $response;
		}

		$decoded = json_decode( $body, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return self::error( 400, self::ERR_PARSE, 'Parse error' );
		}
		if ( ! is_array( $decoded ) || array() === $decoded ) {
			// Scalars, null, {} and [] are valid JSON but not valid requests.
			return self::error( 400, Server::ERR_INVALID_REQUEST, 'Invalid Request' );
		}

		$is_batch = array_keys( $decoded ) === range( 0, count( $decoded ) - 1 );
		$messages = $is_batch ? $decoded : array( $decoded );

		// Session handling. A (re-)initialize starts a fresh session, so a stale
		// header on it is ignored rather than rejected.
		$session_id     = null;
		$new_session_id = null;
		if ( self::has_initialize( $messages ) ) {
			$new_session_id = bin2hex( random_bytes( 16 ) );
			$session_id     = $new_session_id;
		} else {
			$header = self::session_header( $request );
			if ( '' !== $header ) {
				if ( ! self::session_exists( $header ) ) {
					return self::error( 404, self::ERR_UNAUTHORIZED, 'Session not found' );
				}
				$session_id = $header;
			}
		}

		$ctx->session_id = $session_id;
		wp_set_current_user( $ctx->user_id );

		if ( null !== $new_session_id ) {
			set_transient(
				self::SESSION_PREFIX . $new_session_id,
				(string) ( $ctx->connection['id'] ?? '' ),
				12 * HOUR_IN_SECONDS
			);
		}

		try {
			$server = self::build_server();
			if ( $is_batch ) {
				$out = $server->handle_batch( $messages, $ctx );
				$out = array() === $out ? null : $out;
			} else {
				$out = $server->handle( $decoded, $ctx );
			}
		} catch ( \Throwable $e ) {
			return self::error( 500, Server::ERR_INTERNAL, 'Internal error' );
		}

		// Only notifications / responses: acknowledge with an empty 202.
		$response = new WP_REST_Response( $out, null === $out ? 202 : 200 );
		if ( null !== $session_id ) {
			$response->header( self::SESSION_HEADER, $session_id );
		}
		return $response;
	}

	/**
	 * Resolve the caller. Single seam: Task 4 replaces the body with token auth.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Context|WP_Error
	 */
	public static function authenticate( WP_REST_Request $request ) {
		$ctx = apply_filters( 'wpdm_test_context', null, $request );
		if ( $ctx instanceof Context ) {
			return $ctx;
		}
		return new WP_Error( 'wpdm_unauthorized', 'Unauthorized', array( 'status' => 401 ) );
	}

	/**
	 * Build the Server for this request. wpdm_registry runs once here.
	 *
	 * @return Server
	 */
	private static function build_server(): Server {
		$registry = new Registry();
		$filtered = apply_filters( 'wpdm_registry', $registry );
		if ( ! $filtered instanceof Registry ) {
			$filtered = $registry;
		}
		return new Server( $filtered, (string) apply_filters( 'wpdm_instructions', '' ) );
	}

	/**
	 * Whether plain-http requests must be refused.
	 *
	 * @return bool
	 */
	private static function https_required(): bool {
		$required = ! ( defined( 'WP_DIVI_MCP_ALLOW_HTTP' ) && WP_DIVI_MCP_ALLOW_HTTP );
		return (bool) apply_filters( 'wpdm_require_https', $required );
	}

	/**
	 * Maximum accepted request body size in bytes.
	 *
	 * @return int
	 */
	private static function max_body(): int {
		$default = defined( 'WPDM_MAX_BODY' ) ? (int) WPDM_MAX_BODY : self::DEFAULT_MAX_BODY;
		return (int) apply_filters( 'wpdm_max_body', $default );
	}

	/**
	 * Whether any message is an initialize request.
	 *
	 * @param array $messages Decoded messages.
	 * @return bool
	 */
	private static function has_initialize( array $messages ): bool {
		foreach ( $messages as $message ) {
			if ( is_array( $message ) && 'initialize' === ( $message['method'] ?? null ) && array_key_exists( 'id', $message ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return string Session id header, '' when absent.
	 */
	private static function session_header( WP_REST_Request $request ): string {
		$value = $request->get_header( self::SESSION_HEADER );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * @param string $id Session id from the client.
	 * @return bool
	 */
	private static function session_exists( string $id ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $id )
			&& false !== get_transient( self::SESSION_PREFIX . $id );
	}

	/**
	 * Handle DELETE: terminate a session.
	 *
	 * @param string $id Session id header.
	 * @return WP_REST_Response
	 */
	private static function end_session( string $id ): WP_REST_Response {
		if ( ! self::session_exists( $id ) ) {
			return self::error( 404, self::ERR_UNAUTHORIZED, 'Session not found' );
		}
		delete_transient( self::SESSION_PREFIX . $id );
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Build a JSON-RPC error response.
	 *
	 * @param int    $status  HTTP status.
	 * @param int    $code    JSON-RPC error code.
	 * @param string $message Message.
	 * @return WP_REST_Response
	 */
	private static function error( int $status, int $code, string $message ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => null,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
				),
			),
			$status
		);
	}
}
