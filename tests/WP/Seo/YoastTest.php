<?php
/**
 * Tests for the Yoast SEO adapter, against real Yoast (WPDM_TEST_YOAST=1).
 *
 * @package WPDM
 * @group yoast
 */

use WPDM\Seo\Yoast;

/**
 * @group yoast
 */
class YoastTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->markTestSkipped( 'Yoast SEO is not loaded (run with WPDM_TEST_YOAST=1).' );
		}
	}

	private function page( string $title = 'Hello', string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
	}

	public function test_active_and_version() {
		$this->assertTrue( Yoast::active() );
		$this->assertSame( WPSEO_VERSION, Yoast::version() );
	}

	public function test_active_is_filterable() {
		add_filter( 'wpdm_yoast_active', '__return_false' );
		$this->assertFalse( Yoast::active() );
		remove_filter( 'wpdm_yoast_active', '__return_false' );
	}

	public function test_stored_empty_when_unset() {
		$this->assertSame( array( 'title' => '', 'description' => '' ), Yoast::stored( $this->page() ) );
	}

	public function test_write_and_stored() {
		$id = $this->page();
		Yoast::write( $id, 'T', 'D' );
		$this->assertSame( array( 'title' => 'T', 'description' => 'D' ), Yoast::stored( $id ) );
		$this->assertSame( 'T', get_post_meta( $id, '_yoast_wpseo_title', true ) );
	}

	public function test_write_null_keeps_and_empty_deletes() {
		$id = $this->page();
		Yoast::write( $id, 'T', 'D' );
		Yoast::write( $id, null, '' );
		$this->assertSame( array( 'title' => 'T', 'description' => '' ), Yoast::stored( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_metadesc' ) );
	}

	public function test_effective_follows_write_after_refresh() {
		$id = $this->page( 'Hello' );
		Yoast::write( $id, 'Custom SEO title', 'Custom description for testing.' );
		$this->assertTrue( Yoast::refresh( $id ) );
		$effective = Yoast::effective( $id );
		$this->assertStringContainsString( 'Custom SEO title', $effective['title'] );
		$this->assertSame( 'Custom description for testing.', $effective['description'] );
	}

	public function test_effective_uses_template_when_empty() {
		$id = $this->page( 'Hello' );
		Yoast::refresh( $id );
		$this->assertStringContainsString( 'Hello', Yoast::effective( $id )['title'] );
	}

	public function test_refresh_failure_is_reported_not_fatal() {
		$this->assertIsBool( Yoast::refresh( 999999 ) );
		$break = static function () {
			return 'No\\Such\\Class';
		};
		add_filter( 'wpdm_yoast_watcher_class', $break );
		$this->assertFalse( Yoast::refresh( $this->page() ) );
		remove_filter( 'wpdm_yoast_watcher_class', $break );
	}
}
