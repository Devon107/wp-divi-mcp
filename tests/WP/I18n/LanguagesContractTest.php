<?php
/**
 * Contract tests for the language adapters: the same cases run against Polylang (real, WPDM_TEST_POLYLANG=1,
 * PolylangLanguagesTest.php) and WPML (the in-memory WpmlDouble, WpmlLanguagesTest.php).
 *
 * Both sites have the languages en (default) and fr.
 *
 * @package WPDM
 */

use WPDM\I18n\Languages;

/**
 * The adapter behaviour every language plugin must give.
 */
abstract class LanguagesContractTest extends WP_UnitTestCase {

	/**
	 * Adapter name the case expects from Languages::name().
	 *
	 * @return string
	 */
	abstract protected function adapter_name(): string;

	private function adapter(): Languages {
		$languages = Languages::current();
		$this->assertInstanceOf( Languages::class, $languages );
		return $languages;
	}

	private function page( string $title = 'Page' ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
	}

	public function test_current_picks_adapter() {
		$this->assertSame( $this->adapter_name(), $this->adapter()->name() );
	}

	public function test_current_is_filterable() {
		add_filter( 'wpdm_languages', '__return_null' );
		$this->assertNull( Languages::current() );
		remove_filter( 'wpdm_languages', '__return_null' );
		$this->assertSame( $this->adapter_name(), $this->adapter()->name() );
	}

	public function test_languages_and_default() {
		$languages = $this->adapter()->languages();
		$this->assertSame( array( 'en', 'fr' ), array_column( $languages, 'code' ) );
		$this->assertSame( 'en', $this->adapter()->default_language() );
		$defaults = array_filter(
			$languages,
			static function ( $language ) {
				return true === $language['default'];
			}
		);
		$this->assertCount( 1, $defaults );
		$this->assertSame( 'en', reset( $defaults )['code'] );
		foreach ( $languages as $language ) {
			$this->assertIsString( $language['name'] );
			$this->assertNotSame( '', $language['name'] );
			$this->assertIsString( $language['locale'] );
			$this->assertNotSame( '', $language['locale'] );
			$this->assertIsBool( $language['default'] );
		}
	}

	public function test_set_and_get_language() {
		$id = $this->page();
		$this->assertNull( $this->adapter()->post_language( $id ) );

		$this->adapter()->set_language( $id, 'fr' );
		$this->assertSame( 'fr', $this->adapter()->post_language( $id ) );

		$this->adapter()->set_language( $id, 'en' );
		$this->assertSame( 'en', $this->adapter()->post_language( $id ) );
	}

	public function test_post_language_of_missing_post_is_null() {
		$this->assertNull( $this->adapter()->post_language( 99999999 ) );
	}

	public function test_translations_of_unlinked_post() {
		$none = $this->page();
		$this->assertSame( array(), $this->adapter()->translations( $none ), 'no language, no group' );

		$alone = $this->page();
		$this->adapter()->set_language( $alone, 'en' );
		$this->assertSame( array( 'en' => $alone ), $this->adapter()->translations( $alone ) );
	}

	public function test_link_and_translations() {
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $fr, 'fr' );

		$this->adapter()->link( $en, $fr, 'fr' );

		$expected = array(
			'en' => $en,
			'fr' => $fr,
		);
		$this->assertSame( $expected, $this->adapter()->translations( $en ) );
		$this->assertSame( $expected, $this->adapter()->translations( $fr ) );
	}

	public function test_translation_of() {
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $fr, 'fr' );
		$this->assertNull( $this->adapter()->translation_of( $en, 'fr' ), 'not linked yet' );

		$this->adapter()->link( $en, $fr, 'fr' );

		$this->assertSame( $fr, $this->adapter()->translation_of( $en, 'fr' ) );
		$this->assertSame( $en, $this->adapter()->translation_of( $fr, 'en' ) );
		$this->assertNull( $this->adapter()->translation_of( $en, 'de' ) );
	}

	public function test_is_translatable() {
		$this->assertTrue( $this->adapter()->is_translatable( 'page' ) );
		$this->assertTrue( $this->adapter()->is_translatable( 'post' ) );
		$this->assertFalse( $this->adapter()->is_translatable( 'wpdm_unregistered_type' ) );
	}
}
