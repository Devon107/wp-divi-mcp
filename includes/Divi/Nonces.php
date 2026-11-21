<?php
/**
 * Divi REST nonces for the authenticated user.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

defined( 'ABSPATH' ) || exit;

/**
 * Divi creates its X-ET-Nonce values while the theme loads
 * (Framework/Route/RESTRoute.php:85-89, Nonce::add_data), which for a token
 * or Application Password request is before WordPress has authenticated the
 * user, so they belong to the anonymous user. Nonce::get_data() passes them
 * through the `divi_visual_builder_rest_nonces` filter
 * (VisualBuilder/REST/Nonce.php:57); this re-creates them for the current
 * user. Ported verbatim from the divi5-mcp-companion plugin.
 */
final class Nonces {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'divi_visual_builder_rest_nonces', array( self::class, 'filter' ), 99 );
	}

	/**
	 * @param mixed $data {path: {METHOD: nonce}}.
	 * @return mixed
	 */
	public static function filter( $data ) {
		if ( ! is_user_logged_in() || ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $path => $methods ) {
			if ( ! is_array( $methods ) ) {
				continue;
			}
			foreach ( $methods as $method => $nonce ) {
				$data[ $path ][ $method ] = 'wp_rest' === $path
					? wp_create_nonce( 'wp_rest' )
					: wp_create_nonce( $path . '--' . $method );
			}
		}
		return $data;
	}

	private function __construct() {}
}
