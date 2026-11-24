<?php
/**
 * Language adapter contract against real Polylang (WPDM_TEST_POLYLANG=1).
 *
 * @package WPDM
 */

require_once __DIR__ . '/LanguagesContractTest.php';

/**
 * @group polylang
 */
class PolylangLanguagesTest extends LanguagesContractTest {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		// The suite deletes all terms (the language terms) after each test class.
		if ( function_exists( 'wpdm_test_polylang_languages' ) && function_exists( 'pll_languages_list' ) ) {
			wpdm_test_polylang_languages();
		}
	}

	public function set_up() {
		parent::set_up();
		if ( ! function_exists( 'pll_languages_list' ) ) {
			$this->markTestSkipped( 'Polylang is not loaded (run with WPDM_TEST_POLYLANG=1).' );
		}
	}

	protected function adapter_name(): string {
		return 'polylang';
	}
}
