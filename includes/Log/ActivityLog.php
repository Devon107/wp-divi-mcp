<?php
/**
 * Activity log: one row per MCP tool call.
 *
 * @package WPDM
 */

namespace WPDM\Log;

use WPDM\Mcp\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Records tool calls (hooked to wpdm_tool_called) and serves them to the admin screen.
 *
 * Logging must never break a tool call or leak secrets: bulky or sensitive
 * argument values are replaced before anything is stored.
 */
class ActivityLog {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'wpdm_db_version';
	const CRON_HOOK         = 'wpdm_prune_log';

	const ARGS_MAX_CHARS = 2000;
	const OMITTED        = '[omitted]';

	/** Argument keys whose values are replaced by "[omitted]" at any depth. */
	const OMIT_KEYS = array( 'layout', 'blocks', 'content', 'data', 'base64' );

	/** Argument keys removed entirely at any depth (token defence in depth). */
	const STRIP_KEYS = array( 'key' );

	/** Argument keys that identify the object a call acted on, in priority order. */
	const TARGET_KEYS = array( 'post_id', 'id', 'template_id' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wpdm_tool_called', array( self::class, 'record' ), 10, 5 );
		add_action( 'plugins_loaded', array( self::class, 'maybe_install' ) );
		add_action( self::CRON_HOOK, array( self::class, 'run_prune' ) );
	}

	/**
	 * @return string Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpdm_log';
	}

	/**
	 * Create or upgrade the table and schedule the daily prune.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ts datetime NOT NULL,
  connection_id varchar(12) NOT NULL DEFAULT '',
  label varchar(190) NOT NULL DEFAULT '',
  tool varchar(100) NOT NULL DEFAULT '',
  target varchar(190) NOT NULL DEFAULT '',
  ok tinyint(1) NOT NULL DEFAULT 0,
  ms int(11) unsigned NOT NULL DEFAULT 0,
  args text,
  PRIMARY KEY  (id),
  KEY ts (ts)
) {$charset};"
		);
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		self::schedule_prune();
	}

	/**
	 * Install when the stored schema version is behind (covers plugins that
	 * were already active before this version, and upgrades).
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Schedule the daily prune if it is not scheduled yet.
	 *
	 * @return void
	 */
	public static function schedule_prune(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the scheduled prune.
	 *
	 * @return void
	 */
	public static function unschedule_prune(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function run_prune(): void {
		self::prune();
	}

	/**
	 * Record one tool call. Never throws and never prints.
	 *
	 * @param string       $tool Tool name.
	 * @param array        $args Tool arguments as received.
	 * @param bool         $ok   Whether the call succeeded.
	 * @param int|float    $ms   Duration in milliseconds (the server passes a float).
	 * @param Context      $ctx  Request context.
	 * @return void
	 */
	public static function record( string $tool, array $args, bool $ok, $ms, Context $ctx ): void {
		global $wpdb;

		$connection = is_array( $ctx->connection ) ? $ctx->connection : array();

		$previous = $wpdb->suppress_errors( true );
		$wpdb->insert(
			self::table(),
			array(
				'ts'            => current_time( 'mysql', true ),
				'connection_id' => self::cut( (string) ( $connection['id'] ?? '' ), 12 ),
				'label'         => self::cut( (string) ( $connection['label'] ?? '' ), 190 ),
				'tool'          => self::cut( $tool, 100 ),
				'target'        => self::cut( self::target( $args ), 190 ),
				'ok'            => $ok ? 1 : 0,
				'ms'            => max( 0, (int) round( (float) $ms ) ),
				'args'          => self::encode_args( $args ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		$wpdb->suppress_errors( $previous );
	}

	/**
	 * Latest rows, newest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array[] Rows as associative arrays.
	 */
	public static function recent( int $limit = 200 ): array {
		global $wpdb;
		$limit = max( 1, min( 1000, $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name.
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Age threshold in days.
	 * @return int Rows deleted.
	 */
	public static function prune( int $days = 30 ): int {
		global $wpdb;
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - max( 0, $days ) * DAY_IN_SECONDS );
		$deleted = $wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE ts < %s', $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name.
		);
		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * First of post_id / id / template_id present as a scalar.
	 *
	 * @param array $args Tool arguments.
	 * @return string
	 */
	private static function target( array $args ): string {
		foreach ( self::TARGET_KEYS as $key ) {
			if ( isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ) {
				return (string) $args[ $key ];
			}
		}
		return '';
	}

	/**
	 * Redact, encode and truncate arguments for storage.
	 *
	 * @param array $args Tool arguments.
	 * @return string JSON, at most ARGS_MAX_CHARS characters.
	 */
	private static function encode_args( array $args ): string {
		$json = wp_json_encode( self::redact( $args ) );
		if ( ! is_string( $json ) ) {
			return '';
		}
		if ( mb_strlen( $json, 'UTF-8' ) > self::ARGS_MAX_CHARS ) {
			// Character-based cut, so a multibyte sequence is never split.
			$json = mb_substr( $json, 0, self::ARGS_MAX_CHARS, 'UTF-8' );
		}
		return $json;
	}

	/**
	 * Replace bulky values and drop secret keys, at any depth.
	 *
	 * @param mixed $value Value to redact.
	 * @return mixed
	 */
	private static function redact( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$name = is_string( $k ) ? strtolower( $k ) : '';
			if ( in_array( $name, self::STRIP_KEYS, true ) ) {
				continue;
			}
			$out[ $k ] = in_array( $name, self::OMIT_KEYS, true ) ? self::OMITTED : self::redact( $v );
		}
		return $out;
	}

	/**
	 * Truncate to a number of characters without splitting a multibyte one.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	private static function cut( string $text, int $max ): string {
		return mb_substr( $text, 0, $max, 'UTF-8' );
	}
}
