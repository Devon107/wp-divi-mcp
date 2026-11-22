<?php
/**
 * Tests for uninstall.php.
 *
 * @package WPDM
 */

use WPDM\Auth\Tokens;
use WPDM\Divi\Backups;
use WPDM\Log\ActivityLog;

class UninstallTest extends WP_UnitTestCase {

	/** @var string[] Queries seen while uninstalling. */
	private $queries = array();

	public function set_up() {
		parent::set_up();
		$this->queries = array();
		add_filter(
			'query',
			function ( $query ) {
				$this->queries[] = $query;
				return $query;
			}
		);
		global $wpdb;
		$wpdb->suppress_errors( true );
		ActivityLog::install();
		$wpdb->suppress_errors( false );
	}

	public function tear_down() {
		global $wpdb;
		// Put the log table back for the tests that follow.
		$wpdb->suppress_errors( true );
		ActivityLog::install();
		$wpdb->suppress_errors( false );
		parent::tear_down();
	}

	private static function table_exists(): bool {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$ok = false !== $wpdb->query( 'SELECT 1 FROM ' . ActivityLog::table() . ' LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->suppress_errors( false );
		return $ok;
	}

	/**
	 * Store one of everything the plugin keeps; return a backup id.
	 */
	private function seed(): int {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		Tokens::create( 'x', $admin, 'full', null );
		update_option( 'wpdm_disabled', 1 );
		set_transient( 'wpdm_sess_abc', 'c1', 3600 );
		set_transient( 'wpdm_fail_abc', array( 'count' => 1 ), 60 );
		set_transient( 'wpdmx_not_ours', 'keep', 3600 ); // Prefix "wpdmx_" is not "wpdm_".
		set_transient( 'other_plugin', 'keep', 3600 );
		ActivityLog::schedule_prune();
		$this->assertNotFalse( wp_next_scheduled( ActivityLog::CRON_HOOK ) );
		$this->assertTrue( self::table_exists() );
		$this->assertNotFalse( get_option( 'wpdm_db_version' ) );
		return Backups::save( 'post_content', 1, 'content' );
	}

	private function assert_cleaned(): void {
		global $wpdb;
		foreach ( array( 'wpdm_connections', 'wpdm_disabled', 'wpdm_db_version' ) as $option ) {
			wp_cache_delete( $option, 'options' );
			$this->assertFalse( get_option( $option ), $option );
		}
		$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpdm\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpdm\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( array(), $left );
		$this->assertFalse( get_transient( 'wpdm_sess_abc' ) );
		$this->assertSame( 'keep', get_transient( 'wpdmx_not_ours' ) );
		$this->assertSame( 'keep', get_transient( 'other_plugin' ) );
		// The WP test suite turns DROP TABLE into DROP TEMPORARY TABLE (and the
		// real table outlives it), so check the statement rather than the table.
		$drop = preg_grep( '/^DROP (TEMPORARY )?TABLE IF EXISTS ' . preg_quote( ActivityLog::table(), '/' ) . '$/', $this->queries );
		$this->assertNotEmpty( $drop, 'log table dropped' );
		$this->assertFalse( wp_next_scheduled( ActivityLog::CRON_HOOK ) );
	}

	public function test_uninstall_cleans_up_and_keeps_backups_by_default() {
		$backup = $this->seed();
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-divi-mcp/wp-divi-mcp.php' );
		}
		$this->assertFalse( defined( 'WPDM_UNINSTALL_DELETE_BACKUPS' ) );
		include WPDM_DIR . 'uninstall.php';

		$this->assert_cleaned();
		$this->assertInstanceOf( WP_Post::class, get_post( $backup ), 'backups are kept' );
	}

	public function test_uninstall_deletes_backups_in_batches_when_asked() {
		$first = $this->seed();
		$ids   = array( $first );
		for ( $i = 2; $i <= 105; $i++ ) { // More than one batch of 100.
			$ids[] = Backups::save( 'post_content', $i, 'c' . $i );
		}
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-divi-mcp/wp-divi-mcp.php' );
		}
		include WPDM_DIR . 'uninstall.php'; // Defines the functions; keeps backups.
		$this->assertInstanceOf( WP_Post::class, get_post( $first ) );

		// WPDM_UNINSTALL_DELETE_BACKUPS=true makes the file call this with true.
		wpdm_uninstall( true );
		$this->assert_cleaned();
		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertSame( array(), get_post_meta( $first ) );
	}

	public function test_uninstall_runs_on_every_site_of_a_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only (WP_MULTISITE=1 bin/test).' );
		}
		$blog = self::factory()->blog->create();
		switch_to_blog( $blog );
		update_option( 'wpdm_disabled', 1 );
		set_transient( 'wpdm_sess_other', 'c2', 3600 );
		restore_current_blog();
		$this->seed();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-divi-mcp/wp-divi-mcp.php' );
		}
		include WPDM_DIR . 'uninstall.php';

		$this->assert_cleaned();
		switch_to_blog( $blog );
		wp_cache_delete( 'wpdm_disabled', 'options' );
		$this->assertFalse( get_option( 'wpdm_disabled' ) );
		$this->assertFalse( get_transient( 'wpdm_sess_other' ) );
		restore_current_blog();
	}
}
