<?php
/**
 * Per-connection bearer tokens.
 *
 * @package WPDM
 */

namespace WPDM\Auth;

use WP_Error;
use WP_REST_Request;
use WPDM\Mcp\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Connection tokens: each one grants a WordPress user's rights to its holder.
 *
 * Only an HMAC of the token is stored (option wpdm_connections, not
 * autoloaded); the plaintext is returned once by create() and never again.
 */
class Tokens {

	const OPTION = 'wpdm_connections';
	const MODES  = array( 'read', 'full' );

	const FAIL_PREFIX = 'wpdm_fail_';
	const FAIL_LIMIT  = 10;
	const FAIL_WINDOW = 60; // Seconds.

	const TOUCH_INTERVAL = 60; // Seconds between last_used/last_ip writes.

	/**
	 * Create a connection.
	 *
	 * @param string   $label   Human label.
	 * @param int      $user_id WordPress user the connection acts as.
	 * @param string   $mode    'read' or 'full'.
	 * @param int|null $expires Unix timestamp, or null for no expiry.
	 * @return array{id:string,token:string,url:string}
	 * @throws \InvalidArgumentException On an unknown mode.
	 */
	public static function create( string $label, int $user_id, string $mode, ?int $expires ): array {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			throw new \InvalidArgumentException( 'Mode must be "read" or "full".' );
		}

		$connections = self::load();
		do {
			$id = bin2hex( random_bytes( 6 ) );
		} while ( isset( $connections[ $id ] ) );

		$token = bin2hex( random_bytes( 32 ) );

		$connections[ $id ] = array(
			'id'        => $id,
			'label'     => sanitize_text_field( $label ),
			'user_id'   => $user_id,
			'mode'      => $mode,
			'hash'      => self::hash( $token ),
			'created'   => time(),
			'expires'   => $expires,
			'last_used' => null,
			'last_ip'   => null,
		);
		self::save( $connections );

		return array(
			'id'    => $id,
			'token' => $token,
			'url'   => self::url( $token ),
		);
	}

	/**
	 * Resolve a token to its connection.
	 *
	 * @param string $token Plaintext token.
	 * @return array|null Connection (including hash) or null when invalid,
	 *                    expired, or its user is gone / lacks edit_posts.
	 */
	public static function verify( string $token ): ?array {
		// Format is public knowledge; rejecting early leaks nothing about secrets.
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		$hash  = self::hash( $token );
		$match = null;
		// Compare against every stored hash in constant time; no early exit.
		foreach ( self::load() as $connection ) {
			if ( is_array( $connection ) && isset( $connection['hash'] ) && is_string( $connection['hash'] )
				&& hash_equals( $connection['hash'], $hash ) ) {
				$match = $connection;
			}
		}
		if ( null === $match ) {
			return null;
		}

		if ( isset( $match['expires'] ) && null !== $match['expires'] && (int) $match['expires'] <= time() ) {
			return null;
		}

		$user_id = (int) ( $match['user_id'] ?? 0 );
		if ( $user_id <= 0 || ! get_userdata( $user_id ) || ! user_can( $user_id, 'edit_posts' ) ) {
			return null;
		}

		return $match;
	}

	/**
	 * Delete a connection.
	 *
	 * @param string $id Connection id.
	 * @return bool Whether it existed.
	 */
	public static function revoke( string $id ): bool {
		$connections = self::load();
		if ( ! isset( $connections[ $id ] ) ) {
			return false;
		}
		unset( $connections[ $id ] );
		self::save( $connections );
		return true;
	}

	/**
	 * All connections, keyed by id, without their hashes.
	 *
	 * @return array
	 */
	public static function all(): array {
		$out = array();
		foreach ( self::load() as $id => $connection ) {
			if ( is_array( $connection ) ) {
				$out[ $id ] = self::public_fields( $connection );
			}
		}
		return $out;
	}

	/**
	 * Connection URL for a token.
	 *
	 * @param string $token Plaintext token.
	 * @return string
	 */
	public static function url( string $token ): string {
		return add_query_arg( 'key', $token, rest_url( 'wp-divi-mcp/v1/mcp' ) );
	}

	/**
	 * Authenticate a REST request by its token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error ['connection' => array, 'context' => Context] on success.
	 */
	public static function authenticate( WP_REST_Request $request ) {
		$fail_key = self::FAIL_PREFIX . md5( self::client_ip() );

		// Throttle before doing any verification work.
		$fails = get_transient( $fail_key );
		if ( is_array( $fails ) && (int) ( $fails['count'] ?? 0 ) >= self::FAIL_LIMIT
			&& time() - (int) ( $fails['start'] ?? 0 ) < self::FAIL_WINDOW ) {
			return new WP_Error( 'wpdm_rate_limited', 'Too many failed attempts', array( 'status' => 429 ) );
		}

		$token      = self::token_from( $request );
		$connection = '' === $token ? null : self::verify( $token );

		if ( null === $connection ) {
			self::record_failure( $fail_key, $fails );
			// Never include the token (or any part of it) in the message.
			return new WP_Error( 'wpdm_unauthorized', 'Unauthorized', array( 'status' => 401 ) );
		}

		$user_id = (int) $connection['user_id'];
		wp_set_current_user( $user_id );
		$connection = self::touch( $connection );

		$public = self::public_fields( $connection );
		return array(
			'connection' => $public,
			'context'    => new Context( $user_id, $public, 'read' === $connection['mode'] ),
		);
	}

	/**
	 * Extract the token: ?key= first, else Authorization: Bearer.
	 *
	 * Only the query string is read, never get_param(): that would also take a
	 * "key" member from the JSON/form body, which then shadows ?key= and stays
	 * in the payload handed to the Server.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string Trimmed token, '' when absent.
	 */
	private static function token_from( WP_REST_Request $request ): string {
		$query = $request->get_query_params();
		$key   = $query['key'] ?? null;
		if ( is_string( $key ) && '' !== trim( $key ) ) {
			return trim( $key );
		}

		$header = $request->get_header( 'authorization' );
		if ( is_string( $header ) && 1 === preg_match( '/^\s*Bearer\s+(.+)$/is', $header, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Count one failed attempt for this client in a fixed 60 s window.
	 *
	 * @param string     $fail_key Transient key.
	 * @param mixed      $fails    Current transient value.
	 * @return void
	 */
	private static function record_failure( string $fail_key, $fails ): void {
		$now = time();
		if ( ! is_array( $fails ) || $now - (int) ( $fails['start'] ?? 0 ) >= self::FAIL_WINDOW ) {
			$fails = array(
				'count' => 0,
				'start' => $now,
			);
		}
		++$fails['count'];
		$ttl = max( 1, self::FAIL_WINDOW - ( $now - (int) $fails['start'] ) );
		set_transient( $fail_key, $fails, $ttl );
	}

	/**
	 * Record last_used / last_ip, at most once per TOUCH_INTERVAL.
	 *
	 * @param array $connection Verified connection.
	 * @return array Possibly updated connection.
	 */
	private static function touch( array $connection ): array {
		$now = time();
		if ( null !== $connection['last_used'] && $now - (int) $connection['last_used'] < self::TOUCH_INTERVAL ) {
			return $connection;
		}
		// Re-read from the DB right before writing, so a revoke or create made
		// by another request since verify() is not overwritten (resurrected).
		wp_cache_delete( self::OPTION, 'options' );
		$connections = self::load();
		if ( ! isset( $connections[ $connection['id'] ] ) ) {
			return $connection;
		}
		$connection['last_used'] = $now;
		$connection['last_ip']   = self::client_ip();

		$connections[ $connection['id'] ]['last_used'] = $connection['last_used'];
		$connections[ $connection['id'] ]['last_ip']   = $connection['last_ip'];
		self::save( $connections );
		return $connection;
	}

	/**
	 * Client IP from REMOTE_ADDR only. X-Forwarded-For is client-controlled
	 * and would let anyone dodge the rate limit, so it is never consulted.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}

	/**
	 * @param string $token Plaintext token.
	 * @return string Hex HMAC-SHA256.
	 */
	private static function hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * @param array $connection Stored connection.
	 * @return array Same without the hash.
	 */
	private static function public_fields( array $connection ): array {
		unset( $connection['hash'] );
		return $connection;
	}

	/**
	 * @return array Stored connections keyed by id.
	 */
	private static function load(): array {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persist connections, never autoloaded.
	 *
	 * @param array $connections Connections keyed by id.
	 * @return void
	 */
	private static function save( array $connections ): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $connections, '', false );
		} else {
			update_option( self::OPTION, $connections, false );
		}
	}
}
