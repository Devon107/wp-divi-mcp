<?php
/**
 * Divi WordPress layer against the installed Divi 5.19.1 theme: in-process
 * divi/v1 calls with nonces, global colors/variables merge, backup and restore.
 *
 * Group "divi": needs WPDM_TEST_DIVI=1 and the Divi theme mapped into wp-env.
 *
 * @package WPDM
 */

use ET\Builder\Packages\GlobalData\GlobalData;
use WPDM\Divi\Backups;
use WPDM\Divi\DiviRest;
use WPDM\Divi\Env;
use WPDM\Divi\Globals;
use WPDM\Mcp\ToolError;

/**
 * @group divi
 */
class DiviLayerTest extends WP_UnitTestCase {

	const LAYOUT = '<!-- wp:divi/placeholder --><!-- wp:divi/section {"module":{"meta":{"adminLabel":{"desktop":{"value":"Say \"hi\""}}}},"builderVersion":"5.19.1"} --><!-- wp:divi/row {"builderVersion":"5.19.1"} --><!-- wp:divi/column {"builderVersion":"5.19.1"} --><!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<p>C:\\\\dir</p>"}}},"builderVersion":"5.19.1"} /--><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';

	/** @var int */
	private $admin;

	/** @var array Globals before the test. */
	private $before;

	public function set_up() {
		parent::set_up();
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
		$this->before = Globals::read();
	}

	public function tear_down() {
		// Put back the globals this test changed, through Divi's own API, then
		// let the transaction rollback and the cache resets do the rest.
		if ( $this->before ) {
			wp_set_current_user( $this->admin );
			Globals::restore( $this->before );
		}
		parent::tear_down();
		self::reset_divi_caches();
		$this->assertSame( wp_json_encode( $this->before ), wp_json_encode( Globals::read() ), 'globals restored' );
		wp_set_current_user( 0 );
	}

	/**
	 * Divi caches globals in static properties and a global; the DB rollback
	 * does not reach them.
	 */
	private static function reset_divi_caches(): void {
		global $et_theme_options;
		$et_theme_options = null;
		wp_cache_flush();
		$prop = new ReflectionProperty( GlobalData::class, '_cached_data' );
		$prop->setAccessible( true );
		$cached = $prop->getValue();
		foreach ( array_keys( $cached ) as $key ) {
			$cached[ $key ] = null;
		}
		$prop->setValue( null, $cached );
	}

	private static function color_ids( array $colors ): array {
		return array_keys( $colors );
	}

	// ----- env ---------------------------------------------------------------

	public function test_env_reports_divi() {
		$this->assertTrue( Env::divi_active() );
		$this->assertSame( '5.19.1', Env::divi_version() );
	}

	// ----- DiviRest ----------------------------------------------------------

	public function test_divi_rest_set_layout_with_in_process_nonce() {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => 'old',
			)
		);

		$data = DiviRest::call(
			'/outside-vb/posts/set-layout',
			array(
				'post_id'        => $page,
				'layout_content' => self::LAYOUT,
			)
		);

		$this->assertSame( array( 'post_id' => $page ), $data );
		$this->assertSame( self::LAYOUT, get_post( $page )->post_content );
		$this->assertSame( 'on', get_post_meta( $page, '_et_pb_use_builder', true ) );
		$this->assertSame( 'draft', get_post_status( $page ) );
	}

	public function test_divi_rest_retries_once_after_invalid_nonce() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$seen = array();
		$filter = static function ( $nonce, $action ) use ( &$seen ) {
			$seen[] = $nonce;
			// First attempt: hand Divi a broken nonce.
			return 1 === count( $seen ) ? 'broken' : $nonce;
		};
		add_filter( 'wpdm_divi_nonce', $filter, 10, 2 );
		try {
			$data = DiviRest::call( '/outside-vb/posts/set-layout', array( 'post_id' => $page, 'layout_content' => self::LAYOUT ) );
		} finally {
			remove_filter( 'wpdm_divi_nonce', $filter, 10 );
		}
		$this->assertCount( 2, $seen );
		$this->assertSame( array( 'post_id' => $page ), $data );
	}

	public function test_divi_rest_maps_errors_to_tool_error() {
		// Missing layout_content: Divi's args layer rejects it with 400.
		try {
			DiviRest::call( '/outside-vb/posts/set-layout', array( 'post_id' => 999999 ) );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'rest_invalid_param', $e->error_code() );
			$this->assertSame( 400, $e->data()['status'] );
		}

		// Nonce that never verifies: one retry, then invalid_nonce.
		$filter = static function () {
			return 'broken';
		};
		add_filter( 'wpdm_divi_nonce', $filter );
		try {
			DiviRest::call( '/outside-vb/theme-options/get' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'invalid_nonce', $e->error_code() );
		} finally {
			remove_filter( 'wpdm_divi_nonce', $filter );
		}

		// A subscriber gets Divi's permission error.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		try {
			DiviRest::call( '/outside-vb/theme-builder/list-templates' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertContains( $e->data()['status'], array( 401, 403 ) );
		}

		// Logged out: refused before reaching Divi.
		wp_set_current_user( 0 );
		try {
			DiviRest::call( '/outside-vb/theme-options/get' );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'not_logged_in', $e->error_code() );
		}
	}

	public function test_nonce_filter_recreates_nonces_for_current_user() {
		$data = apply_filters(
			'divi_visual_builder_rest_nonces',
			array(
				'wp_rest'                                   => array( 'ALL' => 'stale' ),
				'/divi/v1/outside-vb/posts/set-layout' => array( 'POST' => 'stale' ),
				'junk'                                      => 'x',
			)
		);
		$this->assertSame( wp_create_nonce( 'wp_rest' ), $data['wp_rest']['ALL'] );
		$this->assertSame( wp_create_nonce( '/divi/v1/outside-vb/posts/set-layout--POST' ), $data['/divi/v1/outside-vb/posts/set-layout']['POST'] );
		$this->assertSame( 'x', $data['junk'] );

		wp_set_current_user( 0 );
		$untouched = apply_filters( 'divi_visual_builder_rest_nonces', array( 'wp_rest' => array( 'ALL' => 'stale' ) ) );
		$this->assertSame( 'stale', $untouched['wp_rest']['ALL'] );
	}

	// ----- globals -----------------------------------------------------------

	public function test_globals_read_shape() {
		$globals = Globals::read();
		$this->assertSame( array( 'colors', 'variables' ), array_keys( $globals ) );
		$this->assertArrayHasKey( 'gcid-primary-color', $globals['colors'] );
		foreach ( array( 'numbers', 'strings', 'images', 'links', 'fonts' ) as $type ) {
			$this->assertArrayHasKey( $type, $globals['variables'] );
			$this->assertIsArray( $globals['variables'][ $type ] );
		}
	}

	public function test_globals_merge_reports_conflict_and_keeps_value() {
		$first = Globals::merge(
			array( array( 'gcid-wpdm-test-a', array( 'color' => '#112233', 'label' => 'Test A' ) ) ),
			array( array( 'id' => 'gvid-wpdm-test-a', 'type' => 'numbers', 'value' => '12px', 'label' => 'Radius' ) ),
			false,
			false,
			'Laptop'
		);
		$this->assertSame( array( 'gcid-wpdm-test-a' ), $first['colors']['added'] );
		$this->assertSame( array( 'gvid-wpdm-test-a' ), $first['variables']['added'] );
		$this->assertIsInt( $first['backup_id'] );
		$this->assertFalse( $first['dry_run'] );
		$backup = Backups::get( $first['backup_id'] );
		$this->assertSame( 'globals', $backup['kind'] );
		$this->assertSame( 0, $backup['source_id'] );
		$this->assertSame( 'Laptop', $backup['connection'] );
		$this->assertSame( wp_json_encode( $this->before ), $backup['content'], 'backup holds the state before the merge' );

		$now = Globals::read();
		$this->assertSame( '#112233', $now['colors']['gcid-wpdm-test-a']['color'] );
		$this->assertSame( '12px', $now['variables']['numbers']['gvid-wpdm-test-a']['value'] );

		// Same values again: nothing to write, no backup.
		$same = Globals::merge(
			array( array( 'gcid-wpdm-test-a', array( 'color' => '#112233' ) ) ),
			array( array( 'id' => 'gvid-wpdm-test-a', 'type' => 'numbers', 'value' => '12px' ) ),
			false,
			false
		);
		$this->assertSame( array( 'gcid-wpdm-test-a' ), $same['colors']['same'] );
		$this->assertSame( array( 'gvid-wpdm-test-a' ), $same['variables']['same'] );
		$this->assertNull( $same['backup_id'] );

		// Different values without overwrite: conflicts, site values kept.
		$backups = count( Backups::list( 0, 'globals' ) );
		$conflict = Globals::merge(
			array( array( 'gcid-wpdm-test-a', array( 'color' => '#ffffff' ) ) ),
			array( array( 'id' => 'gvid-wpdm-test-a', 'type' => 'numbers', 'value' => '99px' ) ),
			false,
			false
		);
		$this->assertSame(
			array(
				'colors'    => array(
					'added'           => array(),
					'same'            => array(),
					'conflicts'       => array( array( 'id' => 'gcid-wpdm-test-a', 'site' => '#112233', 'file' => '#ffffff' ) ),
					'overwritten'     => array(),
					'skipped_builtin' => array(),
				),
				'variables' => array(
					'added'       => array(),
					'same'        => array(),
					'conflicts'   => array( array( 'id' => 'gvid-wpdm-test-a', 'site' => '12px', 'file' => '99px' ) ),
					'overwritten' => array(),
				),
				'dry_run'   => false,
				'backup_id' => null,
			),
			$conflict
		);
		$this->assertCount( $backups, Backups::list( 0, 'globals' ), 'no backup when nothing changes' );
		$now = Globals::read();
		$this->assertSame( '#112233', $now['colors']['gcid-wpdm-test-a']['color'] );
		$this->assertSame( '12px', $now['variables']['numbers']['gvid-wpdm-test-a']['value'] );

		// Dry run with overwrite: reports, writes nothing.
		$dry = Globals::merge( array( array( 'gcid-wpdm-test-a', array( 'color' => '#ffffff' ) ) ), array(), true, true );
		$this->assertSame( array( 'gcid-wpdm-test-a' ), $dry['colors']['overwritten'] );
		$this->assertTrue( $dry['dry_run'] );
		$this->assertNull( $dry['backup_id'] );
		$this->assertSame( '#112233', Globals::read()['colors']['gcid-wpdm-test-a']['color'] );

		// overwrite=true: written, after a backup.
		$over = Globals::merge(
			array( array( 'gcid-wpdm-test-a', array( 'color' => '#FFFFFF' ) ) ),
			array( array( 'id' => 'gvid-wpdm-test-a', 'type' => 'numbers', 'value' => '99px' ) ),
			true,
			false
		);
		$this->assertSame( array( 'gcid-wpdm-test-a' ), $over['colors']['overwritten'] );
		$this->assertSame( array( 'gvid-wpdm-test-a' ), $over['variables']['overwritten'] );
		$this->assertIsInt( $over['backup_id'] );
		$now = Globals::read();
		$this->assertSame( '#FFFFFF', $now['colors']['gcid-wpdm-test-a']['color'] );
		$this->assertSame( '99px', $now['variables']['numbers']['gvid-wpdm-test-a']['value'] );
	}

	public function test_builtin_color_skipped_even_with_overwrite() {
		$primary = Globals::read()['colors']['gcid-primary-color']['color'];
		$option  = et_get_option( 'accent_color' );

		$report = Globals::merge(
			array(
				array( 'gcid-primary-color', array( 'color' => '#010203' ) ),
				array( 'gcid-link-color', array( 'color' => '#010203' ) ),
			),
			array(),
			true,
			false
		);

		$this->assertSame( array( 'gcid-primary-color', 'gcid-link-color' ), $report['colors']['skipped_builtin'] );
		$this->assertSame( array(), $report['colors']['overwritten'] );
		$this->assertNull( $report['backup_id'] );
		$this->assertSame( $primary, Globals::read()['colors']['gcid-primary-color']['color'] );
		$this->assertSame( $option, et_get_option( 'accent_color' ) );
	}

	public function test_merge_rejects_invalid_input_and_writes_nothing() {
		$valid_color = array( 'gcid-wpdm-test-ok', array( 'color' => '#123123' ) );
		$cases       = array(
			'colors not a list'  => array( array( 'x' => $valid_color ), array() ),
			'color entry shape'  => array( array( array( 'gcid-wpdm-test-b' ) ), array() ),
			'color not string'   => array( array( array( 'gcid-wpdm-test-b', array( 'color' => 123 ) ) ), array() ),
			'color empty'        => array( array( array( 'gcid-wpdm-test-b', array( 'color' => '' ) ) ), array() ),
			'label not string'   => array( array( array( 'gcid-wpdm-test-b', array( 'color' => '#fff', 'label' => array() ) ) ), array() ),
			'bad gcid'           => array( array( array( 'wpdm-test-b', array( 'color' => '#fff' ) ) ), array() ),
			'variables not list' => array( array(), array( 'a' => array( 'id' => 'gvid-x', 'type' => 'numbers', 'value' => '1' ) ) ),
			'variable missing'   => array( array(), array( array( 'id' => 'gvid-wpdm-test-b', 'type' => 'numbers' ) ) ),
			'variable bad type'  => array( array(), array( array( 'id' => 'gvid-wpdm-test-b', 'type' => 'colors', 'value' => '1' ) ) ),
			'bad gvid'           => array( array(), array( array( 'id' => 'wpdm-test-b', 'type' => 'numbers', 'value' => '1' ) ) ),
		);
		foreach ( $cases as $name => $case ) {
			// A valid entry first proves nothing is written before validation completes.
			$colors = $case[0];
			if ( array_values( $colors ) === $colors ) {
				array_unshift( $colors, $valid_color );
			}
			try {
				Globals::merge( $colors, $case[1], true, false );
				$this->fail( 'Expected invalid_globals for: ' . $name );
			} catch ( ToolError $e ) {
				$this->assertSame( 'invalid_globals', $e->error_code(), $name );
			}
		}
		$this->assertArrayNotHasKey( 'gcid-wpdm-test-ok', Globals::read()['colors'] );
		$this->assertSame( array(), Backups::list( 0, 'globals' ) );
	}

	public function test_merge_requires_edit_theme_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$colors = array( array( 'gcid-wpdm-test-c', array( 'color' => '#123456' ) ) );

		$dry = Globals::merge( $colors, array(), false, true );
		$this->assertSame( array( 'gcid-wpdm-test-c' ), $dry['colors']['added'] );

		try {
			Globals::merge( $colors, array(), false, false );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'forbidden', $e->error_code() );
		}
		$this->assertArrayNotHasKey( 'gcid-wpdm-test-c', Globals::read()['colors'] );
	}

	public function test_globals_backup_and_restore() {
		Globals::merge(
			array( array( 'gcid-wpdm-test-r', array( 'color' => '#aa0000', 'label' => 'Red' ) ) ),
			array( array( 'id' => 'gvid-wpdm-test-r', 'type' => 'strings', 'value' => 'Hello "world" \\ ok', 'label' => 'Greeting' ) ),
			false,
			false
		);
		$snapshot = Globals::read();

		$report = Globals::merge(
			array(
				array( 'gcid-wpdm-test-r', array( 'color' => '#00aa00' ) ),
				array( 'gcid-wpdm-test-s', array( 'color' => '#0000aa' ) ),
			),
			array(
				array( 'id' => 'gvid-wpdm-test-r', 'type' => 'strings', 'value' => 'Changed' ),
				array( 'id' => 'gvid-wpdm-test-s', 'type' => 'numbers', 'value' => '4px', 'label' => 'Gap' ),
			),
			true,
			false
		);
		$backup = Backups::get( $report['backup_id'] );
		$this->assertSame( wp_json_encode( $snapshot ), $backup['content'] );

		$restored = Globals::restore( json_decode( $backup['content'], true ) );

		$now = Globals::read();
		$this->assertSame( '#aa0000', $now['colors']['gcid-wpdm-test-r']['color'] );
		$this->assertArrayNotHasKey( 'gcid-wpdm-test-s', $now['colors'], 'colors added after the backup are removed' );
		$this->assertSame( 'Hello "world" \\ ok', $now['variables']['strings']['gvid-wpdm-test-r']['value'] );
		// Divi never deletes variables (GlobalData::validate_global_variable_ids): they are archived.
		$this->assertSame( 'archived', $now['variables']['numbers']['gvid-wpdm-test-s']['status'] );
		$this->assertSame( array( 'gvid-wpdm-test-s' ), $restored['variables_archived'] );
		$this->assertIsInt( $restored['backup_id'], 'restore backs up the state it replaces' );
		$this->assertSame( self::color_ids( $snapshot['colors'] ), self::color_ids( $now['colors'] ) );
	}

	public function test_merge_type_mismatch_is_conflict() {
		Globals::merge( array(), array( array( 'id' => 'gvid-wpdm-test-t', 'type' => 'numbers', 'value' => '10px', 'label' => 'T' ) ), false, false );
		$backups = count( Backups::list( 0, 'globals' ) );

		foreach ( array( false, true ) as $overwrite ) {
			$report = Globals::merge( array(), array( array( 'id' => 'gvid-wpdm-test-t', 'type' => 'strings', 'value' => 'ten' ) ), $overwrite, false );
			$this->assertSame( array( array( 'id' => 'gvid-wpdm-test-t', 'site_type' => 'numbers', 'file_type' => 'strings' ) ), $report['variables']['conflicts'], 'overwrite=' . var_export( $overwrite, true ) );
			$this->assertSame( array(), $report['variables']['overwritten'] );
			$this->assertNull( $report['backup_id'] );
		}
		$now = Globals::read()['variables'];
		$this->assertSame( '10px', $now['numbers']['gvid-wpdm-test-t']['value'] );
		$this->assertArrayNotHasKey( 'gvid-wpdm-test-t', $now['strings'] );
		$this->assertCount( $backups, Backups::list( 0, 'globals' ) );
	}

	public function test_merge_rejects_duplicate_ids() {
		$cases = array(
			'colors'    => array( array( array( 'gcid-wpdm-test-d', array( 'color' => '#111111' ) ), array( 'gcid-wpdm-test-d', array( 'color' => '#222222' ) ) ), array() ),
			'variables' => array( array(), array( array( 'id' => 'gvid-wpdm-test-d', 'type' => 'numbers', 'value' => '1px' ), array( 'id' => 'gvid-wpdm-test-d', 'type' => 'strings', 'value' => 'x' ) ) ),
		);
		foreach ( $cases as $name => $case ) {
			try {
				Globals::merge( $case[0], $case[1], true, false );
				$this->fail( 'Expected invalid_globals for duplicate ' . $name );
			} catch ( ToolError $e ) {
				$this->assertSame( 'invalid_globals', $e->error_code(), $name );
				$this->assertStringContainsString( 'duplicate', strtolower( $e->getMessage() ) );
			}
		}
		$this->assertArrayNotHasKey( 'gcid-wpdm-test-d', Globals::read()['colors'] );
	}
}
