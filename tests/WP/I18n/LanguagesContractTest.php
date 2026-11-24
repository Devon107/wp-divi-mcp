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

	public function test_translations_include_draft_translation() {
		$en = $this->page( 'Home' );
		$fr = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Accueil',
				'post_status' => 'draft',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $fr, 'fr' );
		$this->adapter()->link( $en, $fr, 'fr' );

		$expected = array(
			'en' => $en,
			'fr' => $fr,
		);
		$this->assertSame( $expected, $this->adapter()->translations( $en ) );
		$this->assertSame( $expected, $this->adapter()->translations( $fr ) );
		$this->assertSame( $fr, $this->adapter()->translation_of( $en, 'fr' ) );
	}

	public function test_set_language_on_missing_post_throws() {
		$this->expectException( InvalidArgumentException::class );
		$this->adapter()->set_language( 99999999, 'fr' );
	}

	public function test_link_with_missing_post_throws() {
		$en = $this->page();
		$this->adapter()->set_language( $en, 'en' );
		try {
			$this->adapter()->link( $en, 99999999, 'fr' );
			$this->fail( 'Missing translation post must throw.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}
		$fr = $this->page();
		$this->expectException( InvalidArgumentException::class );
		$this->adapter()->link( 99999999, $fr, 'fr' );
	}

	public function test_link_with_source_without_language_throws() {
		$source = $this->page( 'No language' );
		$fr     = $this->page( 'Accueil' );
		$this->adapter()->set_language( $fr, 'fr' );

		$this->expectException( InvalidArgumentException::class );
		$this->adapter()->link( $source, $fr, 'fr' );
	}

	public function test_link_with_source_language_throws() {
		$en  = $this->page( 'Home' );
		$en2 = $this->page( 'Home 2' );
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $en2, 'en' );

		try {
			$this->adapter()->link( $en, $en2, 'en' );
			$this->fail( 'Linking in the source language must throw.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( array( 'en' => $en ), $this->adapter()->translations( $en ), 'source group untouched' );
		}
	}

	public function test_link_across_post_types_throws() {
		$en = $this->page( 'Home' );
		$fr = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $fr, 'fr' );

		$this->expectException( InvalidArgumentException::class );
		$this->adapter()->link( $en, $fr, 'fr' );
	}

	public function test_link_when_group_has_another_post_in_language_throws() {
		$en     = $this->page( 'Home' );
		$fr     = $this->page( 'Accueil' );
		$other  = $this->page( 'Autre' );
		$this->adapter()->set_language( $en, 'en' );
		$this->adapter()->set_language( $fr, 'fr' );
		$this->adapter()->set_language( $other, 'fr' );
		$this->adapter()->link( $en, $fr, 'fr' );

		try {
			$this->adapter()->link( $en, $other, 'fr' );
			$this->fail( 'A second post in the same language must throw.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $this->adapter()->translations( $en ), 'group untouched' );
		}
		$this->adapter()->link( $en, $fr, 'fr' );
		$this->assertSame( $fr, $this->adapter()->translation_of( $en, 'fr' ), 'relinking the same post is allowed' );
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
