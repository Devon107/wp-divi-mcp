<?php
/**
 * Catalog::load() against the installed Divi theme, and its transient cache.
 *
 * Group "divi": needs WPDM_TEST_DIVI=1 and the Divi 5.19.1 theme mapped into wp-env.
 *
 * @package WPDM
 */

use WPDM\Divi\Catalog;
use WPDM\Divi\Validator;

/**
 * @group divi
 */
class CatalogTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Catalog::reset();
	}

	public function tear_down() {
		Catalog::reset();
		parent::tear_down();
	}

	public function test_catalog_from_installed_divi_has_5_19_modules() {
		$this->assertSame( 'Divi', get_template() );
		delete_transient( 'wpdm_catalog_5.19.1' );

		$catalog = Catalog::load();

		$this->assertSame( '5.19.1', $catalog['diviVersion'] );
		foreach ( array( 'divi/section', 'divi/row', 'divi/column', 'divi/group', 'divi/text', 'divi/link', 'divi/icon', 'divi/image' ) as $name ) {
			$this->assertArrayHasKey( $name, $catalog['modules'], $name );
		}
		$this->assertSame( array( 'decorationGroups', 'diviVersion', 'modules' ), array_keys( $catalog ) );

		$bundled = Catalog::bundled();
		foreach ( array( 'divi/text', 'divi/link', 'divi/accordion-item', 'divi/section' ) as $name ) {
			$this->assertSame( array_keys( $bundled['modules'][ $name ] ), array_keys( $catalog['modules'][ $name ] ), $name );
		}
		foreach ( $catalog['modules'] as $name => $entry ) {
			$this->assertIsString( $entry['category'], $name );
			$this->assertIsArray( $entry['children'], $name );
			$this->assertIsArray( $entry['elements'], $name );
		}
		$this->assertSame( array( 'content', 'icon', 'module' ), $catalog['modules']['divi/link']['elements'] );
		$this->assertSame( array( 'divi/accordion-item' ), $catalog['modules']['divi/accordion']['children'] );
		$this->assertContains( 'background', $catalog['decorationGroups'] );
		$this->assertSame( $catalog, get_transient( 'wpdm_catalog_5.19.1' ) );

		$names = array_keys( $catalog['modules'] );
		$sorted = $names;
		sort( $sorted, SORT_STRING );
		$this->assertSame( $sorted, $names );
	}

	public function test_catalog_load_reads_the_transient() {
		$fake = array(
			'decorationGroups' => array(),
			'diviVersion'      => '5.19.1',
			'modules'          => array( 'divi/only' => array( 'category' => 'module', 'children' => array(), 'd4Shortcode' => null, 'elements' => array( 'module' ), 'title' => 'Only' ) ),
		);
		set_transient( 'wpdm_catalog_5.19.1', $fake );
		try {
			$this->assertSame( $fake, Catalog::load() );
		} finally {
			delete_transient( 'wpdm_catalog_5.19.1' );
		}
	}

	public function test_validator_uses_installed_catalog_version() {
		delete_transient( 'wpdm_catalog_5.19.1' );
		$report = Validator::validate_content( '<!-- wp:divi/placeholder --><!-- wp:divi/nope /--><!-- /wp:divi/placeholder -->' );
		$this->assertSame( array( 'content line 1 divi/nope: unknown Divi block name (not in Divi 5.19.1 catalog)' ), $report->errors );
	}
}
