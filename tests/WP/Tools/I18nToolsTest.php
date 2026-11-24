<?php
/**
 * Tests for the language tools (i18n_status, i18n_get_translations, i18n_set_language,
 * i18n_link_translation), against real Polylang.
 *
 * @package WPDM
 */

use WPDM\I18n\Languages;
use WPDM\I18n\ThemeBuilderFooter;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Mcp\ToolError;
use WPDM\Tools\I18n\Registrar;

/**
 * @group polylang
 */
class I18nToolsTest extends WP_UnitTestCase {

	const TOOLS = array( 'i18n_status', 'i18n_get_translations', 'i18n_set_language', 'i18n_link_translation' );

	/** @var int */
	private $admin;
	/** @var int */
	private $author;
	/** @var int */
	private $author2;

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
		$this->admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->author2 = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	public function tear_down() {
		unregister_post_type( 'wpdm_book' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	// ----- helpers ---------------------------------------------------------

	private function context( int $user_id, bool $read_only = false ): Context {
		return new Context( $user_id, array( 'id' => 'c1', 'label' => 'Test connection', 'mode' => $read_only ? 'read' : 'full' ), $read_only );
	}

	private function call( int $user_id, string $tool, array $args = array(), bool $read_only = false ): array {
		wp_set_current_user( $user_id );
		$server   = new Server( Registrar::add( new Registry() ), '' );
		$response = $server->handle(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => $tool,
					'arguments' => $args,
				),
			),
			$this->context( $user_id, $read_only )
		);
		$this->assertArrayHasKey( 'result', $response, 'Expected a JSON-RPC result' );
		return json_decode( wp_json_encode( $response['result'] ), true );
	}

	private function ok( int $user_id, string $tool, array $args = array() ): array {
		$result = $this->call( $user_id, $tool, $args );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) );
		return $result['structuredContent'];
	}

	/**
	 * Run a tool and return the ToolError it throws.
	 */
	private function error( int $user_id, string $tool, array $args ): ToolError {
		wp_set_current_user( $user_id );
		$instance = Registrar::add( new Registry() )->tool( $tool );
		$this->assertNotNull( $instance, $tool . ' not registered' );
		try {
			$instance->execute( $args, $this->context( $user_id ) );
		} catch ( ToolError $e ) {
			return $e;
		}
		$this->fail( 'Expected ToolError' );
	}

	private function error_code( int $user_id, string $tool, array $args ): string {
		return $this->error( $user_id, $tool, $args )->error_code();
	}

	private function page( string $title = 'Page', array $extra = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'page',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => $this->admin,
				),
				$extra
			)
		);
	}

	private function languages(): Languages {
		$languages = Languages::current();
		$this->assertInstanceOf( Languages::class, $languages );
		return $languages;
	}

	// ----- registration ----------------------------------------------------

	public function test_tools_registered_with_annotations() {
		$server = new Server( Registrar::add( new Registry() ), '' );
		$res    = $server->handle( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list' ), $this->context( $this->admin ) );
		$tools  = array();
		foreach ( $res['result']['tools'] as $tool ) {
			$tools[ $tool['name'] ] = $tool;
		}
		foreach ( self::TOOLS as $name ) {
			$this->assertArrayHasKey( $name, $tools );
			if ( 'i18n_status' !== $name ) {
				$this->assertStringContainsString( 'i18n_status', $tools[ $name ]['description'], $name . ' points to i18n_status for the codes' );
			}
		}
		$this->assertTrue( $tools['i18n_status']['annotations']['readOnlyHint'] );
		$this->assertTrue( $tools['i18n_get_translations']['annotations']['readOnlyHint'] );
		$this->assertFalse( $tools['i18n_set_language']['annotations']['readOnlyHint'] );
		$this->assertFalse( $tools['i18n_link_translation']['annotations']['readOnlyHint'] );
	}

	public function test_tools_hidden_without_language_plugin() {
		add_filter( 'wpdm_languages', '__return_null' );
		$registry = Registrar::add( new Registry() );
		remove_filter( 'wpdm_languages', '__return_null' );
		foreach ( self::TOOLS as $name ) {
			$this->assertNull( $registry->tool( $name ), $name . ' must not be registered' );
		}
	}

	public function test_registered_through_the_registry_filter() {
		$registry = apply_filters( 'wpdm_registry', new Registry() );
		foreach ( self::TOOLS as $name ) {
			$this->assertNotNull( $registry->tool( $name ), $name );
		}
	}

	public function test_tool_without_adapter_is_not_available() {
		$registry = Registrar::add( new Registry() );
		add_filter( 'wpdm_languages', '__return_null' );
		try {
			$registry->tool( 'i18n_status' )->execute( array(), $this->context( $this->admin ) );
			$this->fail( 'Expected ToolError' );
		} catch ( ToolError $e ) {
			$this->assertSame( 'not_available', $e->error_code() );
		} finally {
			remove_filter( 'wpdm_languages', '__return_null' );
		}
	}

	// ----- i18n_status -----------------------------------------------------

	public function test_status() {
		$data = $this->ok( $this->admin, 'i18n_status' );
		$this->assertSame( 'polylang', $data['plugin'] );
		$this->assertSame( 'en', $data['default_language'] );
		$this->assertCount( 2, $data['languages'] );
		$this->assertSame( array( 'en', 'fr' ), array_column( $data['languages'], 'code' ) );
		$this->assertTrue( $data['languages'][0]['default'] );
		$this->assertFalse( $data['languages'][1]['default'] );
		$this->assertArrayHasKey( 'theme_builder_footer', $data );
		if ( ! \WPDM\Divi\Env::divi_active() ) {
			$this->assertNull( $data['theme_builder_footer'] );
		}
	}

	public function test_status_for_an_author() {
		$data = $this->ok( $this->author, 'i18n_status' );
		$this->assertSame( 'polylang', $data['plugin'] );
		$this->assertNull( $data['theme_builder_footer'] );
	}

	// ----- ThemeBuilderFooter ----------------------------------------------

	public function test_footer_without_divi_is_null() {
		if ( \WPDM\Divi\Env::divi_active() ) {
			$this->markTestSkipped( 'Divi is active.' );
		}
		$this->assertNull( ThemeBuilderFooter::info( $this->languages() ) );
	}

	public function test_footer_from_the_default_template() {
		$l   = $this->languages();
		$en  = $this->page( 'Footer' );
		$fr  = $this->page( 'Pied de page' );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );
		$l->link( $en, $fr, 'fr' );

		$templates = array(
			array( 'id' => 11, 'default' => false, 'layouts' => array( 'footer' => array( 'id' => 999 ) ) ),
			array( 'id' => 12, 'default' => true, 'layouts' => array( 'header' => array( 'id' => 5 ), 'footer' => array( 'id' => $en ) ) ),
		);
		$info      = ThemeBuilderFooter::from_templates( $l, $templates );
		$this->assertSame( $en, $info['layout_id'] );
		$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $info['translations'] );
	}

	public function test_footer_without_language_has_no_translations() {
		$layout = $this->page( 'Footer' );
		$info   = ThemeBuilderFooter::from_templates( $this->languages(), array( array( 'id' => 12, 'default' => true, 'layouts' => array( 'footer' => array( 'id' => $layout ) ) ) ) );
		$this->assertSame( $layout, $info['layout_id'] );
		$this->assertSame( array(), $info['translations'] );
	}

	public function test_footer_null_when_default_template_has_none() {
		$l = $this->languages();
		$this->assertNull( ThemeBuilderFooter::from_templates( $l, array() ) );
		$this->assertNull( ThemeBuilderFooter::from_templates( $l, array( array( 'id' => 12, 'default' => true, 'layouts' => array( 'header' => array( 'id' => 5 ) ) ) ) ) );
		$this->assertNull( ThemeBuilderFooter::from_templates( $l, array( array( 'id' => 12, 'default' => false, 'layouts' => array( 'footer' => array( 'id' => 5 ) ) ) ) ) );
	}

	// ----- i18n_set_language / i18n_get_translations -------------------------

	public function test_set_language_and_get_translations() {
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil', array( 'post_status' => 'draft' ) );

		$set = $this->ok( $this->admin, 'i18n_set_language', array( 'id' => $en, 'language' => 'en' ) );
		$this->assertSame( array( 'id' => $en, 'language' => 'en' ), $set );
		$this->ok( $this->admin, 'i18n_set_language', array( 'id' => $fr, 'language' => 'fr' ) );
		$this->assertSame( 'fr', $this->languages()->post_language( $fr ) );

		$solo = $this->ok( $this->admin, 'i18n_get_translations', array( 'id' => $en ) );
		$this->assertSame( $en, $solo['id'] );
		$this->assertSame( 'en', $solo['language'] );
		$this->assertSame( array( 'en' ), array_keys( $solo['translations'] ) );

		$this->ok( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) );
		$data = $this->ok( $this->admin, 'i18n_get_translations', array( 'id' => $fr ) );
		$this->assertSame( 'fr', $data['language'] );
		$this->assertSame( array( 'en', 'fr' ), array_keys( $data['translations'] ) );
		$this->assertSame( $en, $data['translations']['en']['id'] );
		$this->assertSame( 'Home', $data['translations']['en']['title'] );
		$this->assertSame( 'publish', $data['translations']['en']['status'] );
		$this->assertSame( get_permalink( $en ), $data['translations']['en']['link'] );
		$this->assertSame( $fr, $data['translations']['fr']['id'] );
		$this->assertSame( 'draft', $data['translations']['fr']['status'] );
	}

	public function test_set_language_is_idempotent() {
		$en = $this->page();
		$this->ok( $this->admin, 'i18n_set_language', array( 'id' => $en, 'language' => 'en' ) );
		$again = $this->ok( $this->admin, 'i18n_set_language', array( 'id' => $en, 'language' => 'en' ) );
		$this->assertSame( 'en', $again['language'] );
	}

	public function test_get_translations_of_post_without_language() {
		$id   = $this->page();
		$data = $this->ok( $this->admin, 'i18n_get_translations', array( 'id' => $id ) );
		$this->assertNull( $data['language'] );
		$this->assertSame( array(), $data['translations'] );
		wp_set_current_user( $this->admin );
		$raw = Registrar::add( new Registry() )->tool( 'i18n_get_translations' )->execute( array( 'id' => $id ), $this->context( $this->admin ) );
		$this->assertSame( '{}', wp_json_encode( $raw['translations'] ), 'an empty group is a JSON object' );
	}

	public function test_set_language_unknown_code() {
		$id = $this->page();
		$e  = $this->error( $this->admin, 'i18n_set_language', array( 'id' => $id, 'language' => 'xx' ) );
		$this->assertSame( 'invalid_argument', $e->error_code() );
		$this->assertStringContainsString( 'en', $e->getMessage() );
		$this->assertStringContainsString( 'fr', $e->getMessage() );
		$this->assertNull( $this->languages()->post_language( $id ) );
	}

	public function test_set_language_conflict_when_linked() {
		$l  = $this->languages();
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );
		$l->link( $en, $fr, 'fr' );

		$this->assertSame( 'conflict', $this->error_code( $this->admin, 'i18n_set_language', array( 'id' => $en, 'language' => 'fr' ) ) );
		$this->assertSame( 'en', $l->post_language( $en ), 'language untouched' );
		$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $l->translations( $en ), 'group untouched' );
	}

	public function test_set_language_requires_edit() {
		$id = $this->page( 'Mine', array( 'post_author' => $this->author2 ) );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'i18n_set_language', array( 'id' => $id, 'language' => 'fr' ) ) );
		$this->assertNull( $this->languages()->post_language( $id ) );
	}

	public function test_set_language_missing_post() {
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'i18n_set_language', array( 'id' => 99999999, 'language' => 'en' ) ) );
	}

	public function test_untranslatable_type_rejected() {
		register_post_type( 'wpdm_book', array( 'public' => true, 'label' => 'Books' ) );
		$id = self::factory()->post->create( array( 'post_type' => 'wpdm_book', 'post_status' => 'publish' ) );
		$this->assertFalse( $this->languages()->is_translatable( 'wpdm_book' ), 'fixture: not translated in Polylang' );

		$e = $this->error( $this->admin, 'i18n_set_language', array( 'id' => $id, 'language' => 'en' ) );
		$this->assertSame( 'invalid_argument', $e->error_code() );
		$this->assertStringContainsString( 'wpdm_book', $e->getMessage() );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $id, 'translation_id' => $this->page() ) ) );
	}

	public function test_get_translations_follows_post_visibility() {
		$l       = $this->languages();
		$en      = $this->page( 'Home' );
		$fr      = $this->page( 'Accueil', array( 'post_status' => 'draft', 'post_author' => $this->author2 ) );
		$private = $this->page( 'Secret', array( 'post_status' => 'draft', 'post_author' => $this->author2 ) );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );
		$l->link( $en, $fr, 'fr' );
		$l->set_language( $private, 'en' );

		// An author who did not write the draft reads the published source but not the draft translation.
		$data = $this->ok( $this->author, 'i18n_get_translations', array( 'id' => $en ) );
		$this->assertSame( array( 'en' ), array_keys( $data['translations'] ) );

		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'i18n_get_translations', array( 'id' => $private ) ) );
		$this->assertSame( 'not_found', $this->error_code( $this->author, 'i18n_get_translations', array( 'id' => 99999999 ) ) );
	}

	// ----- i18n_link_translation -------------------------------------------

	public function test_link_translation() {
		$l  = $this->languages();
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );

		$data = $this->ok( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) );
		$this->assertSame( array( 'en', 'fr' ), array_keys( $data['translations'] ) );
		$this->assertSame( $en, $data['translations']['en']['id'] );
		$this->assertSame( $fr, $data['translations']['fr']['id'] );
		$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $l->translations( $en ) );

		$other = $this->page( 'Autre' );
		$l->set_language( $other, 'fr' );
		$this->assertSame( 'conflict', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $other ) ) );
		$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $l->translations( $en ), 'group untouched' );

		$again = $this->ok( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) );
		$this->assertSame( $fr, $again['translations']['fr']['id'], 'relinking the same pair is allowed' );
	}

	public function test_link_requires_edit_on_both() {
		$l  = $this->languages();
		$en = $this->page( 'Mine', array( 'post_author' => $this->author ) );
		$fr = $this->page( 'Theirs', array( 'post_author' => $this->author2 ) );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );

		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) ) );
		$this->assertSame( 'forbidden', $this->error_code( $this->author, 'i18n_link_translation', array( 'source_id' => $fr, 'translation_id' => $en ) ) );
		$this->assertSame( array( 'en' => $en ), $l->translations( $en ), 'nothing linked' );
	}

	public function test_link_to_itself_rejected() {
		$l  = $this->languages();
		$en = $this->page( 'Home' );
		$l->set_language( $en, 'en' );

		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $en ) ) );
		$this->assertSame( array( 'en' => $en ), $l->translations( $en ), 'group untouched' );
		$this->assertSame( 'en', $l->post_language( $en ) );
	}

	public function test_link_needs_languages_on_both() {
		$l  = $this->languages();
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );

		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) ), 'neither has a language' );
		$l->set_language( $en, 'en' );
		$e = $this->error( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ) );
		$this->assertSame( 'invalid_argument', $e->error_code(), 'translation has no language' );
		$this->assertStringContainsString( 'i18n_set_language', $e->getMessage() );
		$this->assertNull( $l->post_language( $fr ), 'no language invented' );
	}

	public function test_link_same_language_rejected() {
		$l   = $this->languages();
		$en  = $this->page( 'Home' );
		$en2 = $this->page( 'Home 2' );
		$l->set_language( $en, 'en' );
		$l->set_language( $en2, 'en' );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $en2 ) ) );
	}

	public function test_link_across_post_types_rejected() {
		$l    = $this->languages();
		$page = $this->page( 'Home' );
		$post = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$l->set_language( $page, 'en' );
		$l->set_language( $post, 'fr' );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $page, 'translation_id' => $post ) ) );
	}

	public function test_link_refuses_to_steal_a_post_from_another_group() {
		$l   = $this->languages();
		$en  = $this->page( 'Home' );
		$fr  = $this->page( 'Accueil' );
		$en2 = $this->page( 'Home 2' );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );
		$l->set_language( $en2, 'en' );
		$l->link( $en, $fr, 'fr' );

		$this->assertSame( 'conflict', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en2, 'translation_id' => $fr ) ) );
		$this->assertSame( array( 'en' => $en, 'fr' => $fr ), $l->translations( $fr ), 'original group untouched' );
		$this->assertSame( array( 'en' => $en2 ), $l->translations( $en2 ) );
	}

	public function test_link_missing_post() {
		$en = $this->page( 'Home' );
		$this->languages()->set_language( $en, 'en' );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => 99999999 ) ) );
		$this->assertSame( 'not_found', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => 99999999, 'translation_id' => $en ) ) );
	}

	// ----- read-only and arguments -------------------------------------------

	public function test_write_tools_blocked_read_only() {
		$l  = $this->languages();
		$en = $this->page( 'Home' );
		$fr = $this->page( 'Accueil' );
		$l->set_language( $en, 'en' );
		$l->set_language( $fr, 'fr' );

		$set = $this->call( $this->admin, 'i18n_set_language', array( 'id' => $en, 'language' => 'fr' ), true );
		$this->assertTrue( $set['isError'] ?? false );
		$this->assertStringContainsString( 'read-only', $set['content'][0]['text'] );
		$this->assertSame( 'en', $l->post_language( $en ) );

		$link = $this->call( $this->admin, 'i18n_link_translation', array( 'source_id' => $en, 'translation_id' => $fr ), true );
		$this->assertTrue( $link['isError'] ?? false );
		$this->assertStringContainsString( 'read-only', $link['content'][0]['text'] );
		$this->assertSame( array( 'en' => $en ), $l->translations( $en ) );

		$read = $this->call( $this->admin, 'i18n_get_translations', array( 'id' => $en ), true );
		$this->assertArrayNotHasKey( 'isError', $read, 'read tools still work' );
	}

	public function test_argument_validation() {
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_set_language', array( 'id' => 1 ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_set_language', array( 'id' => 1, 'language' => '' ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_set_language', array( 'id' => 1, 'language' => 'en', 'x' => 1 ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_get_translations', array( 'id' => 0 ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_link_translation', array( 'source_id' => 1 ) ) );
		$this->assertSame( 'invalid_argument', $this->error_code( $this->admin, 'i18n_status', array( 'x' => 1 ) ) );
	}
}
