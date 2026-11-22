<?php
/**
 * Uninstall: remove what WP Divi MCP stored, on every site of a network.
 *
 * Removed: the options wpdm_connections, wpdm_disabled and wpdm_db_version,
 * every wpdm_* transient, the {prefix}wpdm_log table and the wpdm_prune_log
 * cron event. Kept: the wpdm_backup posts (copies of pages, globals and Theme
 * Builder layouts taken before each change), unless wp-config.php defines
 * WPDM_UNINSTALL_DELETE_BACKUPS as true.
 *
 * Self-contained: WordPress does not load the plugin when it runs this file.
 *
 * @package WPDM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'wpdm_uninstall_site' ) ) {
	/**
	 * Clean up the current site.
	 *
	 * @param bool $delete_backups Also delete the wpdm_backup posts.
	 * @return void
	 */
	function wpdm_uninstall_site( bool $delete_backups ): void {
		global $wpdb;

		foreach ( array( 'wpdm_connections', 'wpdm_disabled', 'wpdm_db_version' ) as $option ) {
			delete_option( $option );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		// Transients (sessions, rate-limit counters, one-time URLs, catalog cache):
		// delete_transient() keeps the caches right; the DELETE removes leftovers.
		$prefix = '_transient_wpdm_';
		$names  = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' )
		);
		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_wpdm_' ) . '%'
			)
		);
		wp_cache_delete( 'alloptions', 'options' );

		$table = $wpdb->prefix . 'wpdm_log';
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseSchemaChange

		wp_clear_scheduled_hook( 'wpdm_prune_log' );

		if ( $delete_backups ) {
			// Batches, so a site with many backups never loads them all at once.
			do {
				$ids = $wpdb->get_col(
					$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 100", 'wpdm_backup' )
				);
				foreach ( $ids as $id ) {
					wp_delete_post( (int) $id, true );
				}
			} while ( count( $ids ) > 0 );
		}
		// phpcs:enable
	}
}

if ( ! function_exists( 'wpdm_uninstall' ) ) {
	/**
	 * Clean up this site, or every site of a network.
	 *
	 * @param bool $delete_backups Also delete the wpdm_backup posts.
	 * @return void
	 */
	function wpdm_uninstall( bool $delete_backups ): void {
		if ( ! is_multisite() ) {
			wpdm_uninstall_site( $delete_backups );
			return;
		}
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try {
				wpdm_uninstall_site( $delete_backups );
			} finally {
				restore_current_blog();
			}
		}
	}
}

wpdm_uninstall( defined( 'WPDM_UNINSTALL_DELETE_BACKUPS' ) && true === WPDM_UNINSTALL_DELETE_BACKUPS );
