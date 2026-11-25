<?php
/**
 * Tests for i18n_extract and i18n_create_translation (same-design translation into a linked
 * draft), against real Polylang. The Yoast test needs WPDM_TEST_YOAST=1 as well.
 *
 * The source page is tests/fixtures/i18n/page.html with real ids put in: its dynamic button link
 * (b5) points to the About page and its link module (b7) to the About page's URL, which both have
 * a French translation; the text module (b4) links to a page with no translation; the global
 * layout (b12) uses an English Divi library item.
 *
 * @package WPDM
 */

use WPDM\Divi\BlockScanner;
use WPDM\I18n\Languages;
use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Mcp\ToolError;
use WPDM\Seo\Yoast;
use WPDM\Tools\I18n\Registrar;

/**
 * @group polylang
 */
class I18nTranslateTest extends WP_UnitTestCase {

	/** @var int */
	private $admin;
	/** @var int */
	private $author;
	/** @var int */
	private $about_en;
	/** @var int */
	private $about_fr;
	/** @var int */
	private $plain;
	/** @var int */
	private $library;

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
		if ( ! post_type_exists( 'et_pb_layout' ) ) {
			// Divi's library post type, as Divi registers it (Divi is not active in the tests env).
			register_post_type( 'et_pb_layout', array( 'public' => false, 'show_ui' => true, 'label' => 'Layouts' ) );
		}
		add_filter( 'pll_get_post_types', array( $this, 'translate_library' ) );
		PLL()->model->cache->clean( 'post_types' );

		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $this->admin );

		$l              = $this->languages();
		$this->about_en = $this->page( 'About' );
		$this->about_fr = $this->page( 'À propos' );
		$this->plain    = $this->page( 'Untranslated' );
		$l->set_language( $this->about_en, 'en' );
		$l->set_language( $this->plain, 'en' );
		$l->link( $this->about_en, $this->about_fr, 'fr' );

		$this->library = $this->insert(
			array(
				'post_type'    => 'et_pb_layout',
				'post_title'   => 'Footer',
				'post_content' => str_replace( 'Texte de pied de page', 'Footer placeholder text', self::fixture( 'global-91-fr.html' ) ),
			)
		);
		update_post_meta( $this->library, '_et_pb_built_for_post_type', 'page' );
		$l->set_language( $this->library, 'en' );
	}

	public function tear_down() {
		remove_filter( 'pll_get_post_types', array( $this, 'translate_library' ) );
		if ( function_exists( 'PLL' ) ) {
			PLL()->model->cache->clean( 'post_types' );
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Filter pll_get_post_types: translate the Divi library like a site that enables it.
	 *
	 * @param array $types Post types.
	 * @return array
	 */
	public function translate_library( $types ) {
		$types['et_pb_layout'] = 'et_pb_layout';
		return $types;
	}

	// ----- helpers ---------------------------------------------------------

	private static function fixture( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/i18n/' . $name );
	}

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

	private function languages(): Languages {
		$languages = Languages::current();
		$this->assertInstanceOf( Languages::class, $languages );
		return $languages;
	}

	/**
	 * Insert a post with exact content (block markup keeps its backslashes).
	 */
	private function insert( array $postarr ): int {
		$id = wp_insert_post(
			wp_slash(
				array_merge(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_author' => $this->admin,
					),
					$postarr
				)
			),
			true
		);
		$this->assertIsInt( $id );
		if ( isset( $postarr['post_content'] ) ) {
			$this->assertSame( $postarr['post_content'], get_post_field( 'post_content', $id, 'raw' ), 'fixture stored exactly' );
		}
		return $id;
	}

	private function page( string $title, array $extra = array() ): int {
		return $this->insert( array_merge( array( 'post_title' => $title ), $extra ) );
	}

	/**
	 * The fixture page with this test's ids and URLs, optionally with a second copy of its global layout.
	 */
	private function source_content( bool $global_twice = false ): string {
		$content = self::fixture( 'page.html' );
		$content = str_replace( '\u002267\u0022', '\u0022' . $this->about_en . '\u0022', $content );
		$content = str_replace( '"linkUrl":"/contact/"', '"linkUrl":"' . get_permalink( $this->about_en ) . '"', $content );
		$content = str_replace( 'href=\u0022/about/\u0022', 'href=\u0022' . get_permalink( $this->plain ) . '\u0022', $content );
		$content = str_replace( array( '"globalModule":"99"', '"globalParent":"99"' ), array( '"globalModule":"' . $this->library . '"', '"globalParent":"' . $this->library . '"' ), $content );
		if ( $global_twice ) {
			$start   = strpos( $content, '<!-- wp:divi/global-layout' );
			$closer  = '<!-- /wp:divi/global-layout -->';
			$end     = strpos( $content, $closer ) + strlen( $closer );
			$block   = substr( $content, $start, $end - $start );
			$content = str_replace( '<!-- /wp:divi/placeholder -->', $block . "\n" . '<!-- /wp:divi/placeholder -->', $content );
		}
		return $content;
	}

	private function source( bool $global_twice = false, array $extra = array() ): int {
		$id = $this->insert(
			array_merge(
				array(
					'post_title'   => 'Home',
					'post_excerpt' => 'The bike shop',
					'post_content' => $this->source_content( $global_twice ),
				),
				$extra
			)
		);
		update_post_meta( $id, '_et_pb_use_builder', 'on' );
		update_post_meta( $id, '_et_pb_page_layout', 'et_full_width_page' );
		$this->languages()->set_language( $id, 'en' );
		return $id;
	}

	private function extract( int $id, int $user = 0 ): array {
		return $this->ok( $user ?: $this->admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'fr' ) );
	}

	/**
	 * A French "translation" of every segment: "FR " in front keeps the tags and markers.
	 */
	private static function translate_all( array $extract ): array {
		$out = array();
		foreach ( $extract['segments'] as $seg ) {
			$out[ $seg['id'] ] = 'FR ' . $seg['text'];
		}
		return $out;
	}

	private function create( int $id, array $extract, ?array $segments = null, int $user = 0 ): array {
		return $this->ok(
			$user ?: $this->admin,
			'i18n_create_translation',
			array(
				'source_id'       => $id,
				'target_language' => 'fr',
				'source_hash'     => $extract['source_hash'],
				'segments'        => null === $segments ? self::translate_all( $extract ) : $segments,
			)
		);
	}

	/**
	 * Decoded attrs of the $n-th divi/* opening or void block, counting like segment ids
	 * (global-layout children skipped).
	 */
	private static function attrs_of( string $content, int $n ): ?array {
		$i     = 0;
		$depth = 0;
		$skip  = null;
		foreach ( BlockScanner::scan( $content ) as $b ) {
			if ( $b['closer'] ) {
				--$depth;
				if ( null !== $skip && $depth === $skip ) {
					$skip = null;
				}
				continue;
			}
			if ( null === $skip && 0 === strpos( $b['name'], 'divi/' ) ) {
				if ( $i === $n ) {
					return null === $b['raw'] ? array() : json_decode( $b['raw'], true );
				}
				++$i;
				if ( 'divi/global-layout' === $b['name'] && ! $b['void'] ) {
					$skip = $depth;
				}
			}
			if ( ! $b['void'] ) {
				++$depth;
			}
		}
		return null;
	}

	/**
	 * Block names and non-text attrs, each global-layout block as one opaque unit (its
	 * globalModule and inline children left out).
	 */
	private static function structure( string $content ): array {
		$out   = array();
		$depth = 0;
		$skip  = null;
		foreach ( BlockScanner::scan( $content ) as $b ) {
			if ( $b['closer'] ) {
				--$depth;
				if ( null !== $skip && $depth === $skip ) {
					$skip = null;
				}
				if ( null === $skip ) {
					$out[] = '/' . $b['name'];
				}
				continue;
			}
			if ( null === $skip ) {
				$attrs = null === $b['raw'] ? array() : json_decode( $b['raw'], true );
				if ( 'divi/global-layout' === $b['name'] ) {
					unset( $attrs['globalModule'] );
					if ( ! $b['void'] ) {
						$skip = $depth;
					}
				}
				$out[] = array( $b['name'], self::blank( $attrs, false ) );
			}
			if ( ! $b['void'] ) {
				++$depth;
			}
		}
		return $out;
	}

	/**
	 * Replace every string under an innerContent key with '*'.
	 *
	 * @param mixed $node   Node.
	 * @param bool  $inside Under innerContent.
	 * @return mixed
	 */
	private static function blank( $node, bool $inside ) {
		if ( is_array( $node ) ) {
			foreach ( $node as $k => $v ) {
				$node[ $k ] = self::blank( $v, $inside || 'innerContent' === $k );
			}
			return $node;
		}
		return $inside && is_string( $node ) ? '*' : $node;
	}

	/**
	 * Inner markup of the $nth divi/global-layout block.
	 */
	private static function global_inner( string $content, int $nth = 0 ): string {
		$blocks = BlockScanner::scan( $content );
		$seen   = 0;
		foreach ( $blocks as $j => $b ) {
			if ( 'divi/global-layout' !== $b['name'] || $b['closer'] ) {
				continue;
			}
			if ( $seen++ !== $nth ) {
				continue;
			}
			for ( $k = $j + 1; $k < count( $blocks ); $k++ ) {
				if ( $blocks[ $k ]['closer'] && 'divi/global-layout' === $blocks[ $k ]['name'] ) {
					return substr( $content, $b['end'], $blocks[ $k ]['start'] - $b['end'] );
				}
			}
		}
		return '';
	}

	private function global_layouts( string $content ): array {
		$out = array();
		foreach ( BlockScanner::scan( $content ) as $b ) {
			if ( 'divi/global-layout' === $b['name'] && ! $b['closer'] ) {
				$out[] = json_decode( $b['raw'], true );
			}
		}
		return $out;
	}

	private static function post_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
	}

	private static function warnings_text( array $result ): string {
		return implode( "\n", $result['warnings'] );
	}

	/**
	 * A divi/code block holding $html, its attrs escaped the way Divi stores them (<, >, &, " as \u00XX).
	 */
	private static function code_block( string $html ): string {
		$attrs = array(
			'content'        => array( 'innerContent' => array( 'desktop' => array( 'value' => $html ) ) ),
			'builderVersion' => '5.11.0',
		);
		return '<!-- wp:divi/code ' . json_encode( $attrs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
	}

	// ----- registration ----------------------------------------------------

	public function test_tools_registered_with_annotations() {
		$server = new Server( Registrar::add( new Registry() ), '' );
		$res    = $server->handle( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list' ), $this->context( $this->admin ) );
		$tools  = array();
		foreach ( $res['result']['tools'] as $tool ) {
			$tools[ $tool['name'] ] = $tool;
		}
		$this->assertTrue( $tools['i18n_extract']['annotations']['readOnlyHint'] );
		$this->assertFalse( $tools['i18n_create_translation']['annotations']['readOnlyHint'] );
		$this->assertStringContainsString( 'i18n_status', $tools['i18n_extract']['description'] );
		$this->assertStringContainsString( 'i18n_status', $tools['i18n_create_translation']['description'] );

		add_filter( 'wpdm_languages', '__return_null' );
		$registry = Registrar::add( new Registry() );
		remove_filter( 'wpdm_languages', '__return_null' );
		$this->assertNull( $registry->tool( 'i18n_extract' ) );
		$this->assertNull( $registry->tool( 'i18n_create_translation' ) );
	}

	// ----- extract ---------------------------------------------------------

	public function test_extract_lists_segments_and_globals() {
		$id = $this->source();
		$ex = $this->extract( $id );

		$this->assertSame( $id, $ex['source_id'] );
		$this->assertSame( 'en', $ex['source_language'] );
		$this->assertSame( 'fr', $ex['target_language'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $ex['source_hash'] );

		$ids = array_column( $ex['segments'], 'id' );
		$this->assertSame( 't', $ids[0] );
		$this->assertSame( 'x', $ids[1] );
		foreach ( array( 'b4.content', 'b5.button.text', 'b6.image.alt', 'b7.content.text', 'b8.content', 'g' . $this->library . '.b4.content' ) as $expected ) {
			$this->assertContains( $expected, $ids );
		}
		$by_id = array_column( $ex['segments'], null, 'id' );
		$this->assertSame( 'Home', $by_id['t']['text'] );
		$this->assertSame( '<p>Footer placeholder text</p>', $by_id[ 'g' . $this->library . '.b4.content' ]['text'] );
		$this->assertStringContainsString( "'Footer' (no fr version yet)", $by_id[ 'g' . $this->library . '.b4.content' ]['context'] );
		foreach ( $ids as $seg_id ) {
			$this->assertStringStartsNotWith( 'b12.', $seg_id, 'global-layout children are not page segments' );
		}

		$this->assertSame( array( array( 'id' => $this->library, 'title' => 'Footer', 'translation' => null ) ), $ex['global_modules'] );
		$this->assertStringContainsString( '2 code module(s)', self::warnings_text( $ex ) );

		$this->assertSame( $ex['source_hash'], $this->extract( $id )['source_hash'], 'stable' );
	}

	// ----- create ----------------------------------------------------------

	public function test_create_translation_happy_path() {
		$l      = $this->languages();
		$id     = $this->source();
		$before = get_post_field( 'post_content', $id, 'raw' );
		$ex     = $this->extract( $id );
		$out    = $this->create( $id, $ex );

		$new = $out['id'];
		$this->assertSame( 'draft', $out['status'] );
		$this->assertSame( 'fr', $out['language'] );
		$this->assertSame( 'draft', get_post_status( $new ) );
		$this->assertSame( 'fr', $l->post_language( $new ) );
		$this->assertSame( $new, $l->translations( $id )['fr'] );
		$this->assertSame( $new, $out['translations']['fr']['id'] );
		$this->assertNotEmpty( $out['view'] );
		$this->assertStringContainsString( 'et_fb=1', $out['edit_divi'] );
		$group = $this->ok( $this->admin, 'i18n_get_translations', array( 'id' => $id ) );
		$this->assertSame( $new, $group['translations']['fr']['id'] );

		$post = get_post( $new );
		$this->assertSame( 'FR Home', $post->post_title );
		$this->assertSame( 'FR The bike shop', $post->post_excerpt );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'on', get_post_meta( $new, '_et_pb_use_builder', true ) );
		$this->assertSame( 'et_full_width_page', get_post_meta( $new, '_et_pb_page_layout', true ) );

		$content = $post->post_content;
		$this->assertSame( self::structure( $before ), self::structure( $content ), 'same blocks, same non-text attrs' );
		$this->assertSame( 'FR Book a visit', self::attrs_of( $content, 5 )['button']['innerContent']['desktop']['value']['text'] );
		$this->assertSame( 'FR Mechanic repairing a bicycle wheel', self::attrs_of( $content, 6 )['image']['innerContent']['desktop']['value']['alt'] );

		// Global module: a new fr library item, linked, used by the page.
		$this->assertCount( 1, $out['created_global_modules'] );
		$created = $out['created_global_modules'][0];
		$lib_fr  = $created['new_id'];
		$this->assertSame( $this->library, $created['source_id'] );
		$this->assertSame( 'Footer', $created['title'] );
		$this->assertSame( 0, $created['used_in_pages_count'] );
		$this->assertSame( 'et_pb_layout', get_post_type( $lib_fr ) );
		$this->assertSame( $lib_fr, $l->translation_of( $this->library, 'fr' ) );
		$this->assertSame( 'page', get_post_meta( $lib_fr, '_et_pb_built_for_post_type', true ) );
		$this->assertStringContainsString( 'FR \\u003cp\\u003eFooter placeholder text', get_post_field( 'post_content', $lib_fr, 'raw' ) );
		$this->assertSame( 'fr', $l->post_language( $lib_fr ) );
		$this->assertStringContainsString( "global module 'Footer' (#" . $lib_fr . ')', self::warnings_text( $out ) );
		$layout = $this->global_layouts( $content )[0];
		$this->assertSame( (string) $lib_fr, $layout['globalModule'] );
		$inner = self::global_inner( $content );
		$this->assertStringContainsString( 'FR \\u003cp\\u003eFooter placeholder text', $inner );
		$this->assertStringContainsString( '"globalParent":"' . $lib_fr . '"', $inner );
		$this->assertStringNotContainsString( '"globalParent":"' . $this->library . '"', $inner );

		// Links: translated targets rewritten, the untranslated one kept and reported.
		$this->assertSame( get_permalink( $this->about_fr ), self::attrs_of( $content, 7 )['content']['innerContent']['desktop']['value']['linkUrl'] );
		$this->assertStringContainsString( '\u0022post_id\u0022:\u0022' . $this->about_fr . '\u0022', $content );
		$this->assertStringContainsString( 'href="' . get_permalink( $this->plain ) . '"', self::attrs_of( $content, 4 )['content']['innerContent']['desktop']['value'] );
		$this->assertStringContainsString( get_permalink( $this->plain ), self::warnings_text( $out ) );

		// The code module kept its protected parts byte for byte.
		$code = self::attrs_of( $content, 8 )['content']['innerContent']['desktop']['value'];
		$this->assertStringContainsString( "<script>document.querySelector('.hours').dataset.ready='1';</script>", $code );
		$this->assertStringContainsString( 'FR <style>', $code );

		$this->assertSame( $before, get_post_field( 'post_content', $id, 'raw' ), 'source untouched' );
		$this->assertSame( 'Home', get_post_field( 'post_title', $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_same_global_module_twice_creates_one() {
		$id = $this->source( true );
		$ex = $this->extract( $id );
		$this->assertCount( 1, $ex['global_modules'] );
		$g_ids = array_filter(
			array_column( $ex['segments'], 'id' ),
			function ( $seg_id ) {
				return 0 === strpos( $seg_id, 'g' . $this->library . '.' );
			}
		);
		$this->assertCount( 1, $g_ids );

		$layouts_before = count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) );
		$out            = $this->create( $id, $ex );
		$this->assertCount( 1, $out['created_global_modules'] );
		$lib_fr = $out['created_global_modules'][0]['new_id'];
		$this->assertSame( $layouts_before + 1, count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) ) );

		$content = get_post_field( 'post_content', $out['id'], 'raw' );
		$layouts = $this->global_layouts( $content );
		$this->assertCount( 2, $layouts );
		$this->assertSame( (string) $lib_fr, $layouts[0]['globalModule'] );
		$this->assertSame( (string) $lib_fr, $layouts[1]['globalModule'] );
		$this->assertStringContainsString( 'FR \\u003cp\\u003eFooter placeholder text', self::global_inner( $content, 1 ) );
	}

	public function test_existing_global_translation_is_used_not_created() {
		$l      = $this->languages();
		$lib_fr = $this->insert(
			array(
				'post_type'    => 'et_pb_layout',
				'post_title'   => 'Pied de page',
				'post_content' => self::fixture( 'global-91-fr.html' ),
			)
		);
		$l->link( $this->library, $lib_fr, 'fr' );

		$id = $this->source();
		$ex = $this->extract( $id );
		$this->assertSame( $lib_fr, $ex['global_modules'][0]['translation'] );
		foreach ( $ex['segments'] as $seg ) {
			$this->assertStringStartsNotWith( 'g', $seg['id'] );
		}

		$layouts_before = count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) );
		$out            = $this->create( $id, $ex );
		$this->assertSame( array(), $out['created_global_modules'] );
		$this->assertSame( $layouts_before, count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) ) );

		$content = get_post_field( 'post_content', $out['id'], 'raw' );
		$this->assertSame( (string) $lib_fr, $this->global_layouts( $content )[0]['globalModule'] );
		$inner = self::global_inner( $content );
		$this->assertStringContainsString( 'Texte de pied de page', $inner );
		$this->assertStringContainsString( '"globalParent":"' . $lib_fr . '"', $inner );
		$this->assertStringNotContainsString( 'Footer button', $inner, 'inline children replaced by the fr library item' );
	}

	public function test_create_conflicts_when_source_changed() {
		$id = $this->source();
		$ex = $this->extract( $id );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Home page' ) );
		$count = self::post_count();

		$e = $this->error(
			$this->admin,
			'i18n_create_translation',
			array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => self::translate_all( $ex ) )
		);
		$this->assertSame( 'conflict', $e->error_code() );
		$this->assertStringContainsString( 'extract again', $e->getMessage() );
		$this->assertSame( $count, self::post_count(), 'nothing created' );
		$this->assertSame( array( 'en' => $id ), $this->languages()->translations( $id ) );
	}

	public function test_create_conflicts_when_library_item_changed() {
		$id = $this->source();
		$ex = $this->extract( $id );
		$this->insert_library_update();
		$e = $this->error(
			$this->admin,
			'i18n_create_translation',
			array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => self::translate_all( $ex ) )
		);
		$this->assertSame( 'conflict', $e->error_code() );
	}

	private function insert_library_update(): void {
		global $wpdb;
		$content = get_post_field( 'post_content', $this->library, 'raw' );
		$wpdb->update( $wpdb->posts, array( 'post_content' => str_replace( 'Footer placeholder text', 'Footer text', $content ) ), array( 'ID' => $this->library ) );
		clean_post_cache( $this->library );
	}

	public function test_create_conflicts_when_translation_exists() {
		$l  = $this->languages();
		$id = $this->source();
		$fr = $this->page( 'Accueil' );
		$l->link( $id, $fr, 'fr' );

		$ex = $this->extract( $id );
		$this->assertStringContainsString( 'already is the fr translation', self::warnings_text( $ex ) );
		$count = self::post_count();
		$e     = $this->error(
			$this->admin,
			'i18n_create_translation',
			array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => array( 't' => 'Accueil' ) )
		);
		$this->assertSame( 'conflict', $e->error_code() );
		$this->assertSame( array( 'existing_id' => $fr ), $e->data() );
		$this->assertSame( $count, self::post_count() );
	}

	public function test_unknown_segment_id_invalid() {
		$id    = $this->source();
		$ex    = $this->extract( $id );
		$count = self::post_count();
		$e     = $this->error(
			$this->admin,
			'i18n_create_translation',
			array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => array( 't' => 'Accueil', 'b99.content' => 'x', 'g1.b0.content' => 'y' ) )
		);
		$this->assertSame( 'invalid_argument', $e->error_code() );
		$this->assertStringContainsString( 'b99.content', $e->getMessage() );
		$this->assertStringContainsString( 'g1.b0.content', $e->getMessage() );
		$this->assertSame( $count, self::post_count() );
	}

	public function test_unknown_language_invalid() {
		$id = $this->source();
		$e  = $this->error( $this->admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'de' ) );
		$this->assertSame( 'invalid_argument', $e->error_code() );
		$this->assertStringContainsString( 'en, fr', $e->getMessage() );
		$this->assertSame( 'invalid_argument', $this->error( $this->admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'en' ) )->error_code(), 'same language' );
	}

	public function test_missing_segments_keep_source_and_warn() {
		$id  = $this->source();
		$ex  = $this->extract( $id );
		$out = $this->create(
			$id,
			$ex,
			array(
				't'          => 'Accueil',
				'b4.content' => 'FR <h1>Bienvenue</h1><p>Vélos <a href="/x/">nous</a></p>',
				'b7.content.text' => 'Devis <b>gratuit</b>',
			)
		);
		$content = get_post_field( 'post_content', $out['id'], 'raw' );
		$this->assertSame( 'Accueil', get_the_title( $out['id'] ) );
		$this->assertSame( 'Book a visit', self::attrs_of( $content, 5 )['button']['innerContent']['desktop']['value']['text'] );
		$this->assertSame( 'Get a free quote', self::attrs_of( $content, 7 )['content']['innerContent']['desktop']['value']['text'], 'added tags fall back' );
		$this->assertStringContainsString( 'Bienvenue', self::attrs_of( $content, 4 )['content']['innerContent']['desktop']['value'] );
		$this->assertStringContainsString( 'Bienvenue', self::attrs_of( $content, 4 )['content']['innerContent']['tablet']['value'], 'equal tablet filled' );
		$warnings = self::warnings_text( $out );
		$this->assertStringContainsString( 'b5.button.text', $warnings );
		$this->assertStringContainsString( 'g' . $this->library . '.b4.content', $warnings );
		$this->assertStringContainsString( 'Segment b7.content.text kept the source text', $warnings );
		$this->assertStringContainsString( 'Footer placeholder text', get_post_field( 'post_content', $out['created_global_modules'][0]['new_id'], 'raw' ) );
	}

	public function test_legacy_divi4_rejected() {
		$id = $this->insert( array( 'post_title' => 'Old', 'post_content' => '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]Hello[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]' ) );
		$this->languages()->set_language( $id, 'en' );
		$e = $this->error( $this->admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'fr' ) );
		$this->assertSame( 'invalid_argument', $e->error_code() );
		$this->assertStringContainsString( 'convert the page to Divi 5 first', $e->getMessage() );
		$e = $this->error( $this->admin, 'i18n_create_translation', array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => str_repeat( '0', 64 ), 'segments' => array() ) );
		$this->assertSame( 'invalid_argument', $e->error_code() );
	}

	public function test_classic_post_translated() {
		$id = $this->insert( array( 'post_type' => 'post', 'post_title' => 'News', 'post_content' => '<p>Hello <a href="' . get_permalink( $this->about_en ) . '">about</a></p>' ) );
		$this->languages()->set_language( $id, 'en' );
		$ex = $this->extract( $id );
		$this->assertSame( array( 't', 'c' ), array_column( $ex['segments'], 'id' ) );
		$out = $this->create( $id, $ex, array( 't' => 'Nouvelles', 'c' => '<p>Bonjour <a href="' . get_permalink( $this->about_en ) . '">à propos</a></p>' ) );
		$this->assertSame( '<p>Bonjour <a href="' . get_permalink( $this->about_fr ) . '">à propos</a></p>', get_post_field( 'post_content', $out['id'], 'raw' ) );
		$this->assertSame( 'post', get_post_type( $out['id'] ) );
	}

	public function test_yoast_fields_translated() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->markTestSkipped( 'Yoast SEO is not loaded (run with WPDM_TEST_YOAST=1).' );
		}
		$id = $this->source();
		Yoast::write( $id, 'Bike repairs %%sep%% %%sitename%%', 'We fix bikes.' );
		$ex    = $this->extract( $id );
		$by_id = array_column( $ex['segments'], null, 'id' );
		$this->assertSame( 'Bike repairs %%sep%% %%sitename%%', $by_id['seo.title']['text'] );
		$this->assertSame( 'We fix bikes.', $by_id['seo.description']['text'] );

		$segments                    = self::translate_all( $ex );
		$segments['seo.title']       = 'Réparation de vélos %%sep%% %%sitename%%';
		$segments['seo.description'] = 'Nous réparons les vélos.';
		$out                         = $this->create( $id, $ex, $segments );
		$this->assertSame(
			array( 'title' => 'Réparation de vélos %%sep%% %%sitename%%', 'description' => 'Nous réparons les vélos.' ),
			Yoast::stored( $out['id'] )
		);
		$this->assertSame( array( 'title' => 'Bike repairs %%sep%% %%sitename%%', 'description' => 'We fix bikes.' ), Yoast::stored( $id ), 'source untouched' );

		// The SEO fields are part of the hash.
		$id2 = $this->source();
		Yoast::write( $id2, 'One', 'Two' );
		$ex2 = $this->extract( $id2 );
		Yoast::write( $id2, 'One changed', null );
		$e = $this->error( $this->admin, 'i18n_create_translation', array( 'source_id' => $id2, 'target_language' => 'fr', 'source_hash' => $ex2['source_hash'], 'segments' => array() ) );
		$this->assertSame( 'conflict', $e->error_code() );
	}

	public function test_terms_parent_featured_image_mapped() {
		$l = $this->languages();

		// Parent: mapped to its translation.
		$parent_en = $this->page( 'Services' );
		$parent_fr = $this->page( 'Services FR' );
		$l->set_language( $parent_en, 'en' );
		$l->link( $parent_en, $parent_fr, 'fr' );
		$child = $this->page( 'Repairs', array( 'post_parent' => $parent_en, 'menu_order' => 3 ) );
		update_post_meta( $child, '_wp_page_template', 'default' );
		$l->set_language( $child, 'en' );
		$out = $this->create( $child, $this->extract( $child ), array( 't' => 'Réparations' ) );
		$this->assertSame( $parent_fr, (int) get_post( $out['id'] )->post_parent );
		$this->assertSame( 3, (int) get_post( $out['id'] )->menu_order );

		// Terms (translated one mapped, untranslated one left out) and featured image.
		$news_en = self::factory()->category->create( array( 'name' => 'News' ) );
		$news_fr = self::factory()->category->create( array( 'name' => 'Actualités' ) );
		pll_set_term_language( $news_en, 'en' );
		pll_set_term_language( $news_fr, 'fr' );
		pll_save_term_translations( array( 'en' => $news_en, 'fr' => $news_fr ) );
		$tag = self::factory()->tag->create( array( 'name' => 'Bikes' ) );
		pll_set_term_language( $tag, 'en' );
		$image = self::factory()->attachment->create_object( 'shop-front.jpg', 0, array( 'post_mime_type' => 'image/jpeg', 'post_type' => 'attachment' ) );

		$post = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Open day', 'post_content' => '<p>Come in</p>' ) );
		$l->set_language( $post, 'en' );
		wp_set_object_terms( $post, array( $news_en ), 'category' );
		wp_set_object_terms( $post, array( $tag ), 'post_tag' );
		set_post_thumbnail( $post, $image );

		$out = $this->create( $post, $this->extract( $post ), array( 't' => 'Portes ouvertes', 'c' => '<p>Entrez</p>' ) );
		$this->assertSame( array( $news_fr ), wp_get_object_terms( $out['id'], 'category', array( 'fields' => 'ids' ) ) );
		$this->assertSame( array(), wp_get_object_terms( $out['id'], 'post_tag', array( 'fields' => 'ids' ) ) );
		$this->assertStringContainsString( "post_tag 'Bikes'", self::warnings_text( $out ) );
		$this->assertSame( $image, (int) get_post_thumbnail_id( $out['id'] ) );
		$this->assertSame( array( $news_en ), wp_get_object_terms( $post, 'category', array( 'fields' => 'ids' ) ), 'source terms untouched' );
	}

	public function test_create_needs_edit_on_source_and_is_blocked_read_only() {
		$id = $this->source();
		$ex = $this->extract( $id, $this->author );
		$this->assertSame( $id, $ex['source_id'], 'a published page is readable' );
		$args = array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => array( 't' => 'Accueil' ) );

		$draft = $this->insert( array( 'post_title' => 'Hidden', 'post_status' => 'draft' ) );
		$this->languages()->set_language( $draft, 'en' );
		$count = self::post_count();
		$this->assertSame( 'forbidden', $this->error( $this->author, 'i18n_create_translation', $args )->error_code() );

		$this->assertSame( 'forbidden', $this->error( $this->author, 'i18n_extract', array( 'id' => $draft, 'target_language' => 'fr' ) )->error_code() );

		$read_only = $this->call( $this->admin, 'i18n_create_translation', $args, true );
		$this->assertTrue( $read_only['isError'] ?? false );
		$this->assertStringContainsString( 'read-only', $read_only['content'][0]['text'] );
		$this->assertSame( $count, self::post_count() );

		$read = $this->call( $this->admin, 'i18n_extract', array( 'id' => $id, 'target_language' => 'fr' ), true );
		$this->assertArrayNotHasKey( 'isError', $read, 'extract works on a read-only connection' );
	}

	public function test_scripts_styles_svg_refused_without_unfiltered_html() {
		$this->assertFalse( user_can( $this->author, 'unfiltered_html' ) );
		$code = self::code_block( '<style>.a{color:red}</style><p>Hello</p><svg viewBox="0 0 1 1"><path d="M0 0"/></svg>' );
		$id   = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Widget', 'post_author' => $this->author, 'post_content' => $code ) );
		$this->languages()->set_language( $id, 'en' );

		$ex = $this->extract( $id, $this->author );
		$this->assertStringContainsString( 'cannot save (unfiltered_html)', self::warnings_text( $ex ) );

		$count = self::post_count();
		$e     = $this->error( $this->author, 'i18n_create_translation', array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => self::translate_all( $ex ) ) );
		$this->assertSame( 'forbidden', $e->error_code() );
		$this->assertSame( 'This page contains scripts, styles or SVG that your account cannot save (unfiltered_html). Ask an administrator to create the translation.', $e->getMessage() );
		$this->assertSame( $count, self::post_count(), 'nothing created' );

		// A code module whose markup kses would change (an iframe) is refused the same way.
		wp_set_current_user( $this->admin ); // The fixture is stored by an administrator.
		$iframe = self::code_block( '<p>Map</p><iframe src="https://maps.example.com/"></iframe>' );
		$map    = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Map', 'post_author' => $this->author, 'post_content' => $iframe ) );
		$this->languages()->set_language( $map, 'en' );
		$ex2 = $this->extract( $map, $this->author );
		$this->assertStringContainsString( 'cannot save (unfiltered_html)', self::warnings_text( $ex2 ) );
		$e2 = $this->error( $this->author, 'i18n_create_translation', array( 'source_id' => $map, 'target_language' => 'fr', 'source_hash' => $ex2['source_hash'], 'segments' => array() ) );
		$this->assertSame( 'forbidden', $e2->error_code() );

		// An administrator translates the same page, markup intact.
		$admin_ex = $this->extract( $id );
		$this->assertStringNotContainsString( 'unfiltered_html', self::warnings_text( $admin_ex ) );
		$out   = $this->create( $id, $admin_ex );
		$value = self::attrs_of( get_post_field( 'post_content', $out['id'], 'raw' ), 0 )['content']['innerContent']['desktop']['value'];
		$this->assertStringContainsString( '<style>.a{color:red}</style>', $value );
		$this->assertStringContainsString( '<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>', $value );
	}

	public function test_link_to_draft_translation_uses_pretty_permalink_and_warns() {
		$this->set_permalink_structure( '/%postname%/' );
		$l       = $this->languages();
		$team_en = $this->page( 'Team' );
		$team_fr = $this->page( 'Équipe', array( 'post_status' => 'draft' ) );
		$l->set_language( $team_en, 'en' );
		$l->link( $team_en, $team_fr, 'fr' );
		$this->assertStringContainsString( 'page_id=', get_permalink( $team_fr ), 'a draft has no pretty permalink yet' );

		$content = '<!-- wp:divi/button {"button":{"innerContent":{"desktop":{"value":{"text":"Our team","linkUrl":"' . get_permalink( $team_en ) . '"}}}},"builderVersion":"5.11.0"} /-->';
		$id      = $this->page( 'Contact', array( 'post_content' => $content ) );
		$l->set_language( $id, 'en' );

		$out  = $this->create( $id, $this->extract( $id ) );
		$link = self::attrs_of( get_post_field( 'post_content', $out['id'], 'raw' ), 0 )['button']['innerContent']['desktop']['value']['linkUrl'];
		$this->assertStringNotContainsString( 'page_id=', $link );
		$this->assertStringContainsString( '/equipe/', $link );
		$this->assertStringContainsString( 'not published', self::warnings_text( $out ) );
		$this->assertStringContainsString( '#' . $team_fr, self::warnings_text( $out ) );
	}

	public function test_yoast_primary_category_mapped_or_dropped() {
		$news_en = self::factory()->category->create( array( 'name' => 'News' ) );
		$news_fr = self::factory()->category->create( array( 'name' => 'Actualités' ) );
		pll_set_term_language( $news_en, 'en' );
		pll_set_term_language( $news_fr, 'fr' );
		pll_save_term_translations( array( 'en' => $news_en, 'fr' => $news_fr ) );
		$events = self::factory()->category->create( array( 'name' => 'Events' ) );
		pll_set_term_language( $events, 'en' );

		$post = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Open day', 'post_content' => '<p>Come in</p>' ) );
		$this->languages()->set_language( $post, 'en' );
		update_post_meta( $post, '_yoast_wpseo_primary_category', (string) $news_en );
		$out = $this->create( $post, $this->extract( $post ), array( 't' => 'Portes ouvertes' ) );
		$this->assertSame( (string) $news_fr, get_post_meta( $out['id'], '_yoast_wpseo_primary_category', true ) );

		$other = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Ride', 'post_content' => '<p>Ride</p>' ) );
		$this->languages()->set_language( $other, 'en' );
		update_post_meta( $other, '_yoast_wpseo_primary_category', (string) $events );
		$out2 = $this->create( $other, $this->extract( $other ), array( 't' => 'Balade' ) );
		$this->assertFalse( metadata_exists( 'post', $out2['id'], '_yoast_wpseo_primary_category' ) );
		$this->assertStringContainsString( "primary category 'Events'", self::warnings_text( $out2 ) );
		$this->assertSame( (string) $events, get_post_meta( $other, '_yoast_wpseo_primary_category', true ), 'source untouched' );
	}

	public function test_text_and_html_segments_sanitized_without_unfiltered_html() {
		$this->assertFalse( user_can( $this->author, 'unfiltered_html' ) );
		$content = '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"\u003cp\u003eHello\u003c/p\u003e"}}},"builderVersion":"5.11.0"} /-->' . "\n"
			. '<!-- wp:divi/button {"button":{"innerContent":{"desktop":{"value":{"text":"Welcome","linkUrl":"/x/"}}}},"builderVersion":"5.11.0"} /-->';
		$id = $this->insert( array( 'post_type' => 'post', 'post_title' => 'Hello', 'post_author' => $this->author, 'post_content' => $content ) );
		$this->languages()->set_language( $id, 'en' );

		$ex  = $this->extract( $id, $this->author );
		$out = $this->create(
			$id,
			$ex,
			array(
				'b0.content'     => '<p>ok</p><script>alert(1)</script>',
				'b1.button.text' => 'Hi<img src=x onerror=alert(1) ',
			),
			$this->author
		);
		$stored  = get_post_field( 'post_content', $out['id'], 'raw' );
		$decoded = str_replace( array( '\u003c', '\u003e', '\u0022' ), array( '<', '>', '"' ), $stored );
		$this->assertStringNotContainsString( '<img', $decoded );
		$this->assertStringNotContainsString( 'onerror', $decoded );
		$this->assertStringNotContainsString( '<script', $decoded );
		$this->assertStringStartsWith( 'Hi', self::attrs_of( $stored, 1 )['button']['innerContent']['desktop']['value']['text'] );
	}

	public function test_failed_create_removes_created_library_items() {
		$l     = $this->languages();
		$id    = $this->source();
		$ex    = $this->extract( $id );
		$fail  = static function ( $maybe_empty, $postarr ) {
			return 'page' === ( $postarr['post_type'] ?? '' ) ? true : $maybe_empty;
		};
		$count = count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '' ) ) );
		add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
		try {
			$e = $this->error(
				$this->admin,
				'i18n_create_translation',
				array( 'source_id' => $id, 'target_language' => 'fr', 'source_hash' => $ex['source_hash'], 'segments' => self::translate_all( $ex ) )
			);
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $fail, 10 );
		}
		$this->assertSame( 'create_failed', $e->error_code() );
		$this->assertNull( $l->translation_of( $this->library, 'fr' ), 'library item unlinked' );
		$this->assertSame( $count, count( get_posts( array( 'post_type' => 'et_pb_layout', 'post_status' => array( 'any', 'trash' ), 'numberposts' => -1, 'lang' => '' ) ) ), 'no created library post left, not even in the trash' );
		$this->assertNull( $this->extract( $id )['global_modules'][0]['translation'] );

		$out = $this->create( $id, $this->extract( $id ) );
		$this->assertCount( 1, $out['created_global_modules'] );
		$this->assertSame( 'publish', get_post_status( $out['created_global_modules'][0]['new_id'] ) );
	}

	public function test_trashed_global_translation_is_treated_as_missing() {
		$l      = $this->languages();
		$lib_fr = $this->insert(
			array(
				'post_type'    => 'et_pb_layout',
				'post_title'   => 'Pied de page',
				'post_content' => self::fixture( 'global-91-fr.html' ),
			)
		);
		$l->link( $this->library, $lib_fr, 'fr' );
		wp_trash_post( $lib_fr );

		$id = $this->source();
		$ex = $this->extract( $id );
		$this->assertNull( $ex['global_modules'][0]['translation'] );
		$out    = $this->create( $id, $ex );
		$new_id = $out['created_global_modules'][0]['new_id'];
		$this->assertNotSame( $lib_fr, $new_id );
		$this->assertSame( (string) $new_id, $this->global_layouts( get_post_field( 'post_content', $out['id'], 'raw' ) )[0]['globalModule'] );
		$this->assertSame( $new_id, $l->translation_of( $this->library, 'fr' ) );
	}

	// ----- JSON-LD ---------------------------------------------------------

	public function test_json_ld_is_translated_with_language_and_links() {
		$about = get_permalink( $this->about_en );
		$ld    = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Bike repair","inLanguage":"en-US","url":"' . $about . '","author":{"@type":"Organization","name":"Shop"}}</script>';
		$id    = $this->insert( array( 'post_title' => 'Schema', 'post_content' => self::code_block( $ld ) ) );
		$this->languages()->set_language( $id, 'en' );

		$extract = $this->extract( $id );
		$ids     = array_column( $extract['segments'], 'text', 'id' );
		$this->assertSame( 'Bike repair', $ids['b0.ld1.headline'] );

		$result  = $this->create( $id, $extract, array( 'b0.ld1.headline' => 'Réparation de vélos' ) );
		$content = get_post_field( 'post_content', $result['id'], 'raw' );
		$value   = self::attrs_of( $content, 0 )['content']['innerContent']['desktop']['value'];
		$this->assertSame( 1, preg_match( '/<script type="application\/ld\+json">(.*)<\/script>/s', $value, $m ) );
		$json = json_decode( $m[1], true );
		$this->assertSame( 'Réparation de vélos', $json['headline'] );
		$this->assertSame( 'fr-FR', $json['inLanguage'] );
		$this->assertSame( get_permalink( $this->about_fr ), $json['url'] );
		$this->assertSame( 'Shop', $json['author']['name'] );
	}
}
