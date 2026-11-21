<?php
/**
 * Tests for the activity log.
 *
 * @package WPDM
 */

use WPDM\Log\ActivityLog;
use WPDM\Mcp\Context;

class ActivityLogTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$wpdb->suppress_errors( true );
		ActivityLog::install();
		$wpdb->suppress_errors( false );
		$wpdb->query( 'DELETE FROM ' . ActivityLog::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function ctx(): Context {
		return new Context( 1, array( 'id' => 'abc123def456', 'label' => 'Claude Desktop' ), false );
	}

	private function last_row(): array {
		$rows = ActivityLog::recent( 1 );
		$this->assertCount( 1, $rows );
		return $rows[0];
	}

	public function test_record_stores_connection_target_and_utc_timestamp() {
		ActivityLog::record( 'update_post', array( 'post_id' => 42, 'id' => 7 ), true, 12, $this->ctx() );
		$row = $this->last_row();

		$this->assertSame( 'abc123def456', $row['connection_id'] );
		$this->assertSame( 'Claude Desktop', $row['label'] );
		$this->assertSame( 'update_post', $row['tool'] );
		$this->assertSame( '42', $row['target'] );
		$this->assertSame( '1', $row['ok'] );
		$this->assertSame( '12', $row['ms'] );
		$this->assertEqualsWithDelta( time(), strtotime( $row['ts'] . ' UTC' ), 5 );
	}

	public function test_target_falls_back_to_id_then_template_id() {
		ActivityLog::record( 'a', array( 'template_id' => 9, 'id' => 5 ), true, 1, $this->ctx() );
		$this->assertSame( '5', $this->last_row()['target'] );

		ActivityLog::record( 'b', array( 'template_id' => 9 ), false, 1, $this->ctx() );
		$row = $this->last_row();
		$this->assertSame( '9', $row['target'] );
		$this->assertSame( '0', $row['ok'] );

		ActivityLog::record( 'c', array(), true, 1, $this->ctx() );
		$this->assertSame( '', $this->last_row()['target'] );
	}

	public function test_record_accepts_fractional_ms() {
		ActivityLog::record( 'a', array(), true, 12.6, $this->ctx() );
		$this->assertSame( '13', $this->last_row()['ms'] );
	}

	public function test_record_omits_large_fields() {
		$args = array(
			'post_id' => 3,
			'title'   => 'Hello',
			'layout'  => str_repeat( 'x', 5000 ),
			'blocks'  => array( 'a', 'b' ),
			'content' => '<p>secret body</p>',
			'data'    => array( 'k' => 'v' ),
			'base64'  => 'AAAA',
			'nested'  => array(
				'deep' => array( 'content' => 'nested body', 'keep' => 'yes' ),
			),
		);
		ActivityLog::record( 'create_page', $args, true, 1, $this->ctx() );
		$stored = json_decode( $this->last_row()['args'], true );

		foreach ( array( 'layout', 'blocks', 'content', 'data', 'base64' ) as $key ) {
			$this->assertSame( '[omitted]', $stored[ $key ], $key );
		}
		$this->assertSame( 'Hello', $stored['title'] );
		$this->assertSame( '[omitted]', $stored['nested']['deep']['content'] );
		$this->assertSame( 'yes', $stored['nested']['deep']['keep'] );
		$this->assertStringNotContainsString( 'secret body', $this->last_row()['args'] );
	}

	public function test_record_strips_key_at_any_depth() {
		$token = str_repeat( 'a1', 32 );
		ActivityLog::record( 'x', array( 'key' => $token, 'opts' => array( 'key' => $token, 'ok' => 1 ) ), true, 1, $this->ctx() );
		$raw = $this->last_row()['args'];

		$this->assertStringNotContainsString( $token, $raw );
		$this->assertSame( array( 'opts' => array( 'ok' => 1 ) ), json_decode( $raw, true ) );
	}

	public function test_record_truncates_args_to_2000_chars() {
		ActivityLog::record( 'x', array( 'note' => str_repeat( 'é', 5000 ) ), true, 1, $this->ctx() );
		$raw = $this->last_row()['args'];

		$this->assertLessThanOrEqual( 2000, mb_strlen( $raw, 'UTF-8' ) );
		$this->assertGreaterThan( 1500, mb_strlen( $raw, 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $raw, 'UTF-8' ) );
	}

	public function test_record_cuts_long_label_and_tool() {
		$ctx = new Context( 1, array( 'id' => str_repeat( 'f', 40 ), 'label' => str_repeat( 'é', 300 ) ) );
		ActivityLog::record( str_repeat( 't', 300 ), array(), true, 1, $ctx );
		$row = $this->last_row();

		$this->assertSame( 12, strlen( $row['connection_id'] ) );
		$this->assertSame( 190, mb_strlen( $row['label'], 'UTF-8' ) );
		$this->assertSame( 100, strlen( $row['tool'] ) );
	}

	public function test_hooked_to_tool_called_action() {
		do_action( 'wpdm_tool_called', 'list_posts', array(), true, 3.2, $this->ctx() );
		$this->assertSame( 'list_posts', $this->last_row()['tool'] );
	}

	public function test_recent_returns_newest_first_and_respects_limit() {
		foreach ( array( 'one', 'two', 'three' ) as $tool ) {
			ActivityLog::record( $tool, array(), true, 1, $this->ctx() );
		}
		$tools = wp_list_pluck( ActivityLog::recent( 2 ), 'tool' );
		$this->assertSame( array( 'three', 'two' ), $tools );
	}

	public function test_prune_removes_older_than_30_days() {
		global $wpdb;
		ActivityLog::record( 'new', array(), true, 1, $this->ctx() );
		foreach ( array( 31, 45 ) as $days ) {
			$wpdb->insert(
				ActivityLog::table(),
				array(
					'ts'   => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					'tool' => 'old' . $days,
				)
			);
		}
		$wpdb->insert(
			ActivityLog::table(),
			array(
				'ts'   => gmdate( 'Y-m-d H:i:s', time() - 29 * DAY_IN_SECONDS ),
				'tool' => 'recent29',
			)
		);

		$this->assertSame( 2, ActivityLog::prune() );
		$tools = wp_list_pluck( ActivityLog::recent(), 'tool' );
		sort( $tools );
		$this->assertSame( array( 'new', 'recent29' ), $tools );
	}

	public function test_prune_honours_custom_days() {
		global $wpdb;
		$wpdb->insert(
			ActivityLog::table(),
			array(
				'ts'   => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ),
				'tool' => 'three_days',
			)
		);
		$this->assertSame( 1, ActivityLog::prune( 2 ) );
		$this->assertSame( array(), ActivityLog::recent() );
	}

	public function test_install_sets_schema_version_and_schedules_prune() {
		wp_clear_scheduled_hook( ActivityLog::CRON_HOOK );
		delete_option( 'wpdm_db_version' );
		global $wpdb;
		$wpdb->suppress_errors( true );
		ActivityLog::install();
		$wpdb->suppress_errors( false );

		$this->assertSame( 1, (int) get_option( 'wpdm_db_version' ) );
		$this->assertNotFalse( wp_next_scheduled( 'wpdm_prune_log' ) );
		$this->assertSame( 'daily', wp_get_schedule( 'wpdm_prune_log' ) );
	}

	public function test_deactivation_clears_cron() {
		ActivityLog::schedule_prune();
		WPDM\Plugin::deactivate();
		$this->assertFalse( wp_next_scheduled( 'wpdm_prune_log' ) );
	}

	public function test_cron_hook_prunes() {
		global $wpdb;
		$wpdb->insert(
			ActivityLog::table(),
			array(
				'ts'   => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ),
				'tool' => 'old',
			)
		);
		do_action( 'wpdm_prune_log' );
		$this->assertSame( array(), ActivityLog::recent() );
	}
}
