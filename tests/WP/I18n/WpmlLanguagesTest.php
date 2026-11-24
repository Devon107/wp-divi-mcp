<?php
/**
 * Language adapter contract against the WPML test double.
 *
 * @package WPDM
 */

require_once __DIR__ . '/LanguagesContractTest.php';
use WPDM\I18n\Languages;
use WPDM\Tests\Support\WpmlDouble;

require_once dirname( __DIR__ ) . '/Support/WpmlDouble.php';

/**
 * WPML, against the in-memory double of its public hooks.
 *
 * @group wpml
 */
class WpmlLanguagesTest extends LanguagesContractTest {

	public function set_up() {
		parent::set_up();
		// Polylang may be loaded in the same run (WPDM_TEST_POLYLANG=1); it wins in current(), so step it aside.
		add_filter( 'wpdm_polylang_active', '__return_false' );
		WpmlDouble::install();
	}

	public function tear_down() {
		WpmlDouble::uninstall();
		remove_filter( 'wpdm_polylang_active', '__return_false' );
		parent::tear_down();
	}

	protected function adapter_name(): string {
		return 'wpml';
	}

	public function test_uninstall_leaves_no_adapter() {
		WpmlDouble::uninstall();
		$this->assertNull( Languages::current() );
	}

	public function test_wpml_needs_its_constant_and_filter() {
		$this->assertTrue( defined( 'ICL_SITEPRESS_VERSION' ) );
		$this->assertSame( 'wpml', Languages::current()->name() );
		remove_all_filters( 'wpml_active_languages' );
		$this->assertNull( Languages::current(), 'no wpml_active_languages filter, no WPML' );
	}

	public function test_polylang_wins_when_both_are_active() {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			$this->markTestSkipped( 'Polylang is not loaded (run with WPDM_TEST_POLYLANG=1).' );
		}
		remove_filter( 'wpdm_polylang_active', '__return_false' );
		$this->assertSame( 'polylang', Languages::current()->name() );
	}

	public function test_link_uses_source_language_and_trid() {
		$en = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$fr = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$languages = Languages::current();
		$languages->set_language( $en, 'en' );
		$languages->set_language( $fr, 'fr' );
		$trid_before = apply_filters( 'wpml_element_trid', null, $fr, 'post_page' );

		$languages->link( $en, $fr, 'fr' );

		$trid = apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		$this->assertSame( $trid, apply_filters( 'wpml_element_trid', null, $fr, 'post_page' ) );
		$this->assertNotSame( $trid_before, $trid );
		$details = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_page' );
		$this->assertSame( 'en', $details['fr']->source_language_code );
	}
}
