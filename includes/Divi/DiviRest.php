<?php
/**
 * In-process calls to Divi's divi/v1 REST routes.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatches divi/v1 requests through rest_do_request() as the current user.
 *
 * Divi checks the X-ET-Nonce header in a rest_request_before_callbacks filter
 * against the action RESTController::get_nonce_name() =
 * get_full_route($namespace, $route) . '--' . $method, where get_full_route()
 * is '/' . trim($namespace, '/') . '/' . trim($route, '/')
 * (Framework/Route/RESTRoute.php:131, Framework/Controllers/RESTController.php:158-160, 201-203).
 * Routes in use: /outside-vb/posts/set-layout (post_id + layout_content or
 * source_layout_post_id, OutsideVbController.php:1662-1730),
 * /outside-vb/theme-builder/{list-templates,create-template,delete-template,upsert-layout}
 * and /outside-vb/theme-options/get (RESTRegistration.php:1305-1403). All are POST.
 */
final class DiviRest {

	const NAMESPACE_PATH = '/divi/v1';

	/**
	 * Call a divi/v1 route.
	 *
	 * @param string $route  Route below /divi/v1, e.g. "/outside-vb/posts/set-layout".
	 * @param array  $body   Parameters (JSON body; query string for GET).
	 * @param string $method HTTP method.
	 * @return array Response data.
	 * @throws ToolError On a logged-out user or a non-2xx response.
	 */
	public static function call( string $route, array $body = array(), string $method = 'POST' ): array {
		if ( ! is_user_logged_in() ) {
			throw new ToolError( 'Divi REST calls need a logged-in user.', 'not_logged_in' );
		}
		$method = strtoupper( $method );
		$path   = self::NAMESPACE_PATH . '/' . trim( $route, '/' );

		$response = null;
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$response = rest_do_request( self::request( $path, $body, $method ) );
			if ( 0 === $attempt && 'invalid_nonce' === self::error_code( $response ) ) {
				// Retry once with a freshly created nonce. Within one nonce tick
				// wp_create_nonce() returns the same value, so this only helps when
				// the first request straddled a tick boundary (or a filter changed
				// the nonce); a persistently invalid nonce fails on the retry too.
				continue;
			}
			break;
		}

		$status = $response->get_status();
		$data   = $response->get_data();
		if ( $status < 200 || $status >= 300 ) {
			$code    = self::error_code( $response );
			$message = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) && '' !== $data['message']
				? $data['message']
				: 'Divi request ' . $method . ' ' . $path . ' failed with HTTP ' . $status . '.';
			throw new ToolError( $message, '' !== $code ? $code : 'divi_rest_error', array( 'status' => $status ) );
		}

		if ( is_array( $data ) ) {
			return $data;
		}
		$decoded = json_decode( (string) wp_json_encode( $data ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Nonce for a divi/v1 path and method, created for the current user.
	 *
	 * @param string $path   Full route, e.g. /divi/v1/outside-vb/posts/set-layout.
	 * @param string $method HTTP method.
	 * @return string
	 */
	public static function nonce( string $path, string $method ): string {
		$action = $path . '--' . strtoupper( $method );
		/**
		 * Filters the X-ET-Nonce sent to Divi (tests use it to simulate a stale nonce).
		 *
		 * @param string $nonce  Nonce.
		 * @param string $action Nonce action.
		 */
		return (string) apply_filters( 'wpdm_divi_nonce', wp_create_nonce( $action ), $action );
	}

	/**
	 * @param string $path   Full route.
	 * @param array  $body   Parameters.
	 * @param string $method HTTP method.
	 * @return \WP_REST_Request
	 */
	private static function request( string $path, array $body, string $method ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method, $path );
		if ( 'GET' === $method || 'HEAD' === $method ) {
			$request->set_query_params( $body );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		$request->set_header( 'X-ET-Nonce', self::nonce( $path, $method ) );
		return $request;
	}

	/**
	 * @param \WP_REST_Response $response Response.
	 * @return string Error code, '' when none.
	 */
	private static function error_code( \WP_REST_Response $response ): string {
		if ( $response->get_status() < 400 ) {
			return '';
		}
		$data = $response->get_data();
		return is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
	}

	private function __construct() {}
}
