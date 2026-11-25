<?php
/**
 * Smoke test of i18n_extract and i18n_create_translation through the WPML adapter, against
 * the in-memory double of WPML's public hooks.
 *
 * @package WPDM
 */

use WPDM\I18n\Languages;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Tests\Support\WpmlDouble;
use WPDM\Tools\I18n\Registrar;

require_once dirname( __DIR__ ) . '/Support/WpmlDouble.php';

/**
 * @group wpml
 */
class I18nTranslateWpmlTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Polylang may be loaded in the same run (WPDM_TEST_POLYLANG=1); it wins in current(), so step it aside.
		add_filter( 'wpdm_polylang_active', '__return_false' );
		WpmlDouble::install();
	}

	public function tear_down() {
		WpmlDouble::uninstall();
		remove_filter( 'wpdm_polylang_active', '__return_false' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function ok( int $user_id, string $tool, array $args ): array {
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
			new Context( $user_id, array( 'id' => 'c1', 'label' => 'Test connection', 'mode' => 'full' ), false )
		);
		$result = json_decode( wp_json_encode( $response['result'] ), true );
		$this->assertArrayNotHasKey( 'isError', $result, 'Unexpected error: ' . ( $result['content'][0]['text'] ?? '' ) );
		return $result['structuredContent'];
	}

	public function test_wpml_create_translation_links_via_hooks() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$l = Languages::current();
		$this->assertSame( 'wpml', $l->name() );

		$content = (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/i18n/page.html' );
		$id      = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Home',
					'post_author'  => $admin,
					'post_content' => $content,
				)
			)
		);
		$l->set_language( $id, 'en' );

		$ex = $this->ok( $admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'fr' ) );
		$this->assertSame( 'en', $ex['source_language'] );
		$this->assertContains( 'b5.button.text', array_column( $ex['segments'], 'id' ) );
		$this->assertStringContainsString( 'Global module #99 was not found', implode( "\n", $ex['warnings'] ) );

		$out = $this->ok(
			$admin,
			'i18n_create_translation',
			array(
				'source_id'       => $id,
				'target_language' => 'fr',
				'source_hash'     => $ex['source_hash'],
				'segments'        => array(
					't'              => 'Accueil',
					'b5.button.text' => 'Réserver une visite',
				),
			)
		);
		$new = $out['id'];
		$this->assertSame( 'draft', get_post_status( $new ) );
		$this->assertSame( 'Accueil', get_the_title( $new ) );
		$this->assertSame( 'fr', $l->post_language( $new ) );
		$this->assertSame( array( 'en' => $id, 'fr' => $new ), $l->translations( $id ) );
		$this->assertSame( $new, $out['translations']['fr']['id'] );
		$this->assertSame( $id, $l->translation_of( $new, 'en' ) );
		$this->assertStringContainsString( 'Réserver une visite', get_post_field( 'post_content', $new, 'raw' ) );
		$this->assertSame( $content, get_post_field( 'post_content', $id, 'raw' ), 'source untouched' );

		$details = apply_filters( 'wpml_get_element_translations', null, apply_filters( 'wpml_element_trid', null, $id, 'post_page' ), 'post_page' );
		$this->assertSame( 'en', $details['fr']->source_language_code );
	}
}
