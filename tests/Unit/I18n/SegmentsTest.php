<?php
/**
 * Unit tests for WPDM\I18n\Segments (extract / in-place apply of translatable strings).
 *
 * The fixtures in tests/fixtures/i18n are cut from real Divi 5 exports (same block shapes and
 * attrs), with neutral placeholder text and example.com URLs. Block indexes in page.html:
 * b0 placeholder, b1 section, b2 row, b3 column, b4 text, b5 button, b6 image, b7 link,
 * b8 code (style + html + script), b9 code (css + shortcode only), b10 number counter,
 * b11 text with only skipped values, b12 global layout 99 (its children are not counted).
 *
 * @package WPDM
 */

use WPDM\Divi\BlockScanner;
use WPDM\I18n\Protect;
use WPDM\I18n\Segments;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class SegmentsTest extends TestCase {

	/**
	 * @param string $name Fixture file name.
	 * @return string
	 */
	private static function fixture( $name ) {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/i18n/' . $name );
	}

	/**
	 * @param array $extract Segments::extract() result.
	 * @return array<string, array> id => segment
	 */
	private static function by_id( array $extract ) {
		$out = array();
		foreach ( $extract['segments'] as $seg ) {
			$out[ $seg['id'] ] = $seg;
		}
		return $out;
	}

	/**
	 * Decoded attrs of the $n-th divi/* opening or void block, counting like segment ids
	 * (global-layout children skipped).
	 *
	 * @param string $content Content.
	 * @param int    $n       Index.
	 * @return array|null
	 */
	private static function attrs_of( $content, $n ) {
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
	 * Inner markup of the first divi/global-layout block.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function global_children( $content ) {
		$open  = strpos( $content, '<!-- wp:divi/global-layout' );
		$start = strpos( $content, '-->', $open ) + 3;
		$end   = strpos( $content, '<!-- /wp:divi/global-layout -->' );
		return substr( $content, $start, $end - $start );
	}

	/**
	 * A "translation" that keeps tags and markers: prefixes each text run with "FR ".
	 *
	 * @param array $seg Segment.
	 * @return string
	 */
	private static function fake_translate( array $seg ) {
		if ( 'text' === $seg['kind'] ) {
			return 'FR ' . $seg['text'];
		}
		return (string) preg_replace( '/(^|>)([^<⟦]*[^<⟦\s][^<⟦]*)/u', '$1FR $2', $seg['text'] );
	}

	public function test_extracts_text_button_image_alt() {
		$ex  = Segments::extract( self::fixture( 'page.html' ) );
		$ids = self::by_id( $ex );

		$this->assertSame( 'html', $ids['b4.content']['kind'] );
		$this->assertSame( '<h1>Welcome to the Example Shop</h1><p>We fix bikes &amp; more. <a href="/about/">About us</a></p>', $ids['b4.content']['text'] );
		$this->assertStringContainsString( 'divi/text', $ids['b4.content']['context'] );
		$this->assertSame( array( 'id', 'kind', 'context', 'text' ), array_keys( $ids['b4.content'] ) );

		$this->assertSame( 'text', $ids['b5.button.text']['kind'] );
		$this->assertSame( 'Book a visit', $ids['b5.button.text']['text'] );
		$this->assertSame( 'Mechanic repairing a bicycle wheel', $ids['b6.image.alt']['text'] );
		$this->assertSame( 'shop-front', $ids['b6.image.titleText']['text'] );
		$this->assertSame( 'Get a free quote', $ids['b7.content.text']['text'] );
		$this->assertSame( 'Bikes repaired', $ids['b10.title']['text'] );

		$this->assertSame(
			array( 'b4.content', 'b4.content.phone', 'b5.button.text', 'b6.image.alt', 'b6.image.titleText', 'b7.content.text', 'b8.content', 'b10.title' ),
			array_keys( $ids )
		);
		foreach ( $ex['segments'] as $seg ) {
			$this->assertStringNotContainsString( 'src', $seg['id'] );
			$this->assertStringNotContainsString( 'linkUrl', $seg['id'] );
			$this->assertStringNotContainsString( 'example.com/wp-content', $seg['text'] );
		}
		$this->assertFalse( $ex['legacy'] );
		$this->assertSame( $ex, Segments::extract( self::fixture( 'page.html' ) ), 'Re-extracting gives the same ids.' );
	}

	public function test_skips_urls_numbers_colors_variables_empty() {
		$ids = self::by_id( Segments::extract( self::fixture( 'page.html' ) ) );
		foreach ( array_keys( $ids ) as $id ) {
			$this->assertStringStartsNotWith( 'b11.', $id );
			$this->assertStringStartsNotWith( 'b10.number', $id );
		}

		$content = '<!-- wp:divi/text {"a":{"innerContent":{"desktop":{"value":"/contact-us/"}}},"b":{"innerContent":{"desktop":{"value":"mailto:x@example.com"}}},"c":{"innerContent":{"desktop":{"value":"1,250.50"}}},"d":{"innerContent":{"desktop":{"value":"#FFF"}}},"e":{"innerContent":{"desktop":{"value":"hsl(10, 20%, 30%)"}}},"f":{"innerContent":{"desktop":{"value":" \n"}}},"g":{"innerContent":{"desktop":{"value":{"text":"Keep me","url":"Not prose","linkTarget":"on","icon":"x"}}}},"url":{"innerContent":{"desktop":{"value":"Never prose"}}}} /-->';
		$ids     = self::by_id( Segments::extract( $content ) );
		$this->assertSame( array( 'b0.g.text' ), array_keys( $ids ) );
	}

	public function test_responsive_variants() {
		$source = self::fixture( 'page.html' );
		$ids    = self::by_id( Segments::extract( $source ) );
		$this->assertArrayNotHasKey( 'b4.content.tablet', $ids, 'Equal tablet value is not a separate segment.' );
		$this->assertSame( '<p>Welcome!</p>', $ids['b4.content.phone']['text'] );
		$this->assertStringContainsString( 'phone', $ids['b4.content.phone']['context'] );

		$fr  = '<h1>Bienvenue</h1><p>Vélos &amp; plus. <a href="/about/">À propos</a></p>';
		$out = Segments::apply( $source, array( 'b4.content' => $fr ) );
		$a   = self::attrs_of( $out['content'], 4 );
		$this->assertSame( $fr, $a['content']['innerContent']['desktop']['value'] );
		$this->assertSame( $fr, $a['content']['innerContent']['tablet']['value'] );
		$this->assertSame( '<p>Welcome!</p>', $a['content']['innerContent']['phone']['value'] );
		$this->assertSame( array(), $out['fallbacks'] );
	}

	public function test_code_module_html_translated_and_script_kept() {
		$source = self::fixture( 'page.html' );
		$ids    = self::by_id( Segments::extract( $source ) );
		$this->assertSame( 'html', $ids['b8.content']['kind'] );
		$this->assertSame( "⟦1⟧\n<div class=\"hours\"><p>Open every day</p><a href=\"/visit/\">Plan your visit</a></div>\n⟦2⟧", $ids['b8.content']['text'] );

		$out = Segments::apply( $source, array( 'b8.content' => "⟦1⟧\n<div class=\"hours\"><p>Ouvert tous les jours</p><a href=\"/visit/\">Préparer votre visite</a></div>\n⟦2⟧" ) );
		$a   = self::attrs_of( $out['content'], 8 );
		$this->assertSame(
			"<style>.hours td{padding:4px}</style>\n<div class=\"hours\"><p>Ouvert tous les jours</p><a href=\"/visit/\">Préparer votre visite</a></div>\n<script>document.querySelector('.hours').dataset.ready='1';</script>",
			$a['content']['innerContent']['desktop']['value']
		);
	}

	public function test_code_only_css_has_no_segment() {
		$ex = Segments::extract( self::fixture( 'page.html' ) );
		$this->assertSame( 2, $ex['code_blocks'] );
		$this->assertArrayNotHasKey( 'b9.content', self::by_id( $ex ) );
	}

	public function test_global_layout_children_not_segments() {
		$ex = Segments::extract( self::fixture( 'page.html' ) );
		foreach ( $ex['segments'] as $seg ) {
			$this->assertStringNotContainsString( 'Footer', $seg['text'] );
		}
		$this->assertSame( array( 99 => array( 12 ) ), $ex['globals'] );
		$this->assertSame( array( 12 ), $ex['globals']['99'] );
	}

	public function test_apply_swaps_global_and_children() {
		$source = self::fixture( 'page.html' );
		$target = self::fixture( 'global-91-fr.html' );
		$out    = Segments::apply( $source, array(), array( '99' => array( '91', $target ) ) );

		$src_gl = self::attrs_of( $source, 12 );
		$new_gl = self::attrs_of( $out['content'], 12 );
		$this->assertSame( '91', $new_gl['globalModule'] );
		$this->assertSame( $src_gl['localAttrs'], $new_gl['localAttrs'] );
		$this->assertSame( 'divi/section', $new_gl['blockName'] );

		$children = self::global_children( $out['content'] );
		$this->assertStringContainsString( 'Texte de pied de page', $children );
		$this->assertStringNotContainsString( 'Footer placeholder text', $children );
		$this->assertStringNotContainsString( 'wp:divi/section', $children, 'The library item section wrapper is not nested.' );
		$this->assertStringNotContainsString( 'wp:divi/placeholder', $children );
		$names = array();
		foreach ( BlockScanner::scan( $children ) as $b ) {
			if ( ! $b['closer'] ) {
				$names[] = $b['name'];
				$this->assertSame( '91', json_decode( $b['raw'], true )['globalParent'] );
			}
		}
		$this->assertSame( array( 'divi/row', 'divi/column', 'divi/text' ), $names );
		$this->assertStringStartsWith( "\n<!-- wp:divi/row", $children );
		$this->assertStringEndsWith( "<!-- /wp:divi/row -->\n", $children );

		// Everything before the global layout is untouched.
		$open = strpos( $source, '<!-- wp:divi/global-layout' );
		$this->assertSame( substr( $source, 0, $open ), substr( $out['content'], 0, $open ) );
		$this->assertStringEndsWith( "<!-- /wp:divi/global-layout -->\n<!-- /wp:divi/placeholder -->\n", $out['content'] );
	}

	public function test_apply_in_place_keeps_other_bytes() {
		$source = "<!-- wp:divi/placeholder -->\n\n  <!-- wp:paragraph -->\n<p>Keep</p>\n<!-- /wp:paragraph -->\n"
			. '<!-- wp:divi/text  {"content":{"innerContent":{"desktop":{"value":"Hello"}}},"module":{"decoration":{}},"preset":[],"n":1.0} /-->' . "\t\n"
			. '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"Other é"}}},"x":{}} /-->' . "\n"
			. '<!-- /wp:divi/placeholder -->';
		$out    = Segments::apply( $source, array( 'b1.content' => 'Bonjour' ) );
		$this->assertSame( str_replace( '"value":"Hello"', '"value":"Bonjour"', $source ), $out['content'] );

		$fixture = self::fixture( 'page.html' );
		$out     = Segments::apply( $fixture, array( 'b6.image.alt' => 'Mécanicien' ) );
		$this->assertSame( str_replace( '"alt":"Mechanic repairing a bicycle wheel"', '"alt":"Mécanicien"', $fixture ), $out['content'] );
	}

	public function test_structure_identical() {
		$source = self::fixture( 'page.html' );
		$ex     = Segments::extract( $source );
		$tr     = array();
		foreach ( $ex['segments'] as $seg ) {
			$tr[ $seg['id'] ] = self::fake_translate( $seg );
		}
		$out = Segments::apply( $source, $tr );
		$this->assertSame( array(), $out['fallbacks'] );
		$this->assertSame( array(), $out['unknown'] );
		$this->assertNotSame( $source, $out['content'] );

		$src_blocks = BlockScanner::scan( $source );
		$new_blocks = BlockScanner::scan( $out['content'] );
		$this->assertSame( array_column( $src_blocks, 'name' ), array_column( $new_blocks, 'name' ) );
		$this->assertSame( array_column( $src_blocks, 'void' ), array_column( $new_blocks, 'void' ) );

		// Attrs minus the segment paths (all breakpoints of each segment's element/key) are equal.
		$by_block = array();
		foreach ( array_keys( $tr ) as $id ) {
			$parts                                    = explode( '.', $id );
			$by_block[ (int) substr( $parts[0], 1 ) ][] = array_slice( $parts, 1 );
		}
		for ( $n = 0; $n <= 12; $n++ ) {
			$a = self::attrs_of( $source, $n );
			$b = self::attrs_of( $out['content'], $n );
			foreach ( $by_block[ $n ] ?? array() as $tail ) {
				$a = self::without( $a, $tail );
				$b = self::without( $b, $tail );
			}
			$this->assertSame( $a, $b, "Block b$n" );
		}
		$this->assertSame( self::global_children( $source ), self::global_children( $out['content'] ) );

		// The translated texts are in place.
		$a = self::attrs_of( $out['content'], 5 );
		$this->assertSame( 'FR Book a visit', $a['button']['innerContent']['desktop']['value']['text'] );
	}

	/**
	 * Remove a segment's values (every breakpoint) from decoded attrs.
	 *
	 * @param array    $attrs Attrs.
	 * @param string[] $tail  Id parts after b<n>.
	 * @return array
	 */
	private static function without( array $attrs, array $tail ) {
		if ( in_array( end( $tail ), array( 'tablet', 'phone' ), true ) ) {
			array_pop( $tail );
		}
		$key = null;
		$el  = $tail;
		$ref = $attrs;
		foreach ( $tail as $k ) {
			$ref = $ref[ $k ] ?? null;
		}
		if ( ! isset( $ref['innerContent'] ) ) {
			$key = array_pop( $el );
		}
		$node = &$attrs;
		foreach ( $el as $k ) {
			$node = &$node[ $k ];
		}
		foreach ( $node['innerContent'] as $bp => $state ) {
			if ( null === $key ) {
				$node['innerContent'][ $bp ]['value'] = null;
			} else {
				$node['innerContent'][ $bp ]['value'][ $key ] = null;
			}
		}
		unset( $node );
		return $attrs;
	}

	public function test_broken_segment_falls_back() {
		$source = '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"<table><tr><td>A</td><td>B</td></tr></table>"}}}} /-->';
		$out    = Segments::apply( $source, array( 'b0.content' => '<table><tr><td>Un<td>Deux</td></tr></table>' ) );
		$this->assertSame( $source, $out['content'] );
		$this->assertSame( array( 'b0.content' ), $out['fallbacks'] );

		$fixture = self::fixture( 'page.html' );
		$out     = Segments::apply(
			$fixture,
			array(
				'b8.content'     => "⟦1⟧\n<div class=\"hours\"><p>Ouvert</p><a href=\"/visit/\">Visite</a></div>\n",
				'b5.button.text' => 'Réserver <b>vite</b>',
				'b6.image.alt'   => '  ',
				'b10.title'      => 'Vélos réparés',
			)
		);
		$this->assertSame( array( 'b5.button.text', 'b6.image.alt', 'b8.content' ), $out['fallbacks'] );
		$this->assertSame( str_replace( '"value":"Bikes repaired"', '"value":"Vélos réparés"', $fixture ), $out['content'] );
	}

	public function test_unknown_ids_reported() {
		$fixture = self::fixture( 'page.html' );
		$out     = Segments::apply( $fixture, array( 'nope' => 'x', 'b5.button.text' => 'Réserver', 'b9.content' => 'y' ) );
		$this->assertSame( array( 'nope', 'b9.content' ), $out['unknown'] );
		$this->assertSame( 'Réserver', self::attrs_of( $out['content'], 5 )['button']['innerContent']['desktop']['value']['text'] );
	}

	public function test_link_and_post_maps_applied() {
		$fixture = self::fixture( 'page.html' );
		$seen    = array();
		$links   = static function ( $url ) use ( &$seen ) {
			$seen[] = $url;
			$map    = array(
				'/about/'   => '/fr/a-propos/',
				'/contact/' => '/fr/contact/',
			);
			return $map[ $url ] ?? null;
		};
		$posts   = static function ( $id ) {
			return 67 === $id ? 167 : null;
		};
		$out     = Segments::apply(
			$fixture,
			array(
				'b4.content' => '<h1>Bienvenue</h1><p>Vélos &amp; plus. <a href="/about/">À propos</a></p>',
				'b8.content' => "⟦1⟧\n<div class=\"hours\"><p>Ouvert</p><a href=\"/visit/\">Visite</a></div>\n⟦2⟧",
			),
			array(),
			$links,
			$posts
		);
		$this->assertSame( array(), $out['fallbacks'] );
		$this->assertStringContainsString( 'href="/fr/a-propos/"', self::attrs_of( $out['content'], 4 )['content']['innerContent']['desktop']['value'] );
		$this->assertStringContainsString( 'href="/visit/"', self::attrs_of( $out['content'], 8 )['content']['innerContent']['desktop']['value'] );
		$this->assertSame( '/fr/contact/', self::attrs_of( $out['content'], 7 )['content']['innerContent']['desktop']['value']['linkUrl'], 'linkUrl is mapped without a translated segment.' );
		$this->assertSame(
			'$variable({"type":"content","value":{"name":"post_link_url_page","settings":{"post_id":"167"}}})$',
			self::attrs_of( $out['content'], 5 )['button']['innerContent']['desktop']['value']['linkUrl']
		);
		$this->assertNotContains( '/footer-link/', $seen, 'Global-layout children are not rewritten.' );
		$this->assertNotContains( 'https://example.com/wp-content/uploads/2026/11/shop-front.jpg', $seen, 'Image src is not a link.' );
		$this->assertSame( self::global_children( $fixture ), self::global_children( $out['content'] ) );

		// A post id without a translation keeps the original.
		$out = Segments::apply(
			$fixture,
			array(),
			array(),
			null,
			static function () {
				return null;
			}
		);
		$this->assertSame( $fixture, $out['content'] );
	}

	public function test_gutenberg_and_classic() {
		$ex = Segments::extract( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$this->assertSame( array( 'gb0' ), array_column( $ex['segments'], 'id' ) );
		$this->assertSame( 'html', $ex['segments'][0]['kind'] );
		$this->assertSame( '<p>Hi</p>', $ex['segments'][0]['text'] );

		$ex = Segments::extract( '<p>Hi</p>' );
		$this->assertSame( array( 'c' ), array_column( $ex['segments'], 'id' ) );
		$this->assertSame( '<p>Hi</p>', $ex['segments'][0]['text'] );

		$source = "<!-- wp:heading -->\n<h2>Title [x]</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->";
		$ex     = Segments::extract( $source );
		$this->assertSame( array( 'gb0', 'gb1' ), array_column( $ex['segments'], 'id' ) );
		$this->assertSame( '<h2>Title ⟦1⟧</h2>', $ex['segments'][0]['text'], 'Surrounding whitespace is not part of the segment.' );
		$out = Segments::apply( $source, array( 'gb0' => '<h2>Titre ⟦1⟧</h2>', 'gb1' => '<p>Salut</p>' ) );
		$this->assertSame( "<!-- wp:heading -->\n<h2>Titre [x]</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Salut</p>\n<!-- /wp:paragraph -->", $out['content'] );

		$out = Segments::apply( '<p>Hi <a href="/about/">us</a></p>', array( 'c' => '<p>Salut <a href="/about/">nous</a></p>' ), array(), static function () {
			return '/fr/a-propos/';
		} );
		$this->assertSame( '<p>Salut <a href="/fr/a-propos/">nous</a></p>', $out['content'] );
	}

	public function test_legacy_flag() {
		$this->assertTrue( Segments::extract( '[et_pb_section][et_pb_text]Hi[/et_pb_text][/et_pb_section]' )['legacy'] );
		$this->assertFalse( Segments::extract( '<p>Hi</p>' )['legacy'] );
	}

	public function test_custom_attribute_alt_title_extracted() {
		$ids = self::by_id( Segments::extract( self::fixture( 'image-attrs.html' ) ) );

		$alt = $ids['b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35'];
		$this->assertSame( 'text', $alt['kind'] );
		$this->assertSame( 'Mechanic repairing a bicycle wheel', $alt['text'] );
		$this->assertStringContainsString( 'divi/image', $alt['context'] );
		$this->assertStringContainsString( 'custom attribute alt', $alt['context'] );
		$this->assertSame( 'shop-front-photo', $ids['b0.attr.e6s5i73h29']['text'] );
		$this->assertStringContainsString( 'custom attribute title', $ids['b0.attr.e6s5i73h29']['context'] );

		// Tablet: equal title is filled by desktop, the different alt is its own segment.
		$this->assertArrayNotHasKey( 'b0.attr.e6s5i73h29.tablet', $ids );
		$this->assertSame( 'Mechanic at work', $ids['b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35.tablet']['text'] );

		// Entry without an id uses its index; a URL value is skipped like any other value.
		$this->assertSame( 'Workshop plan', $ids['b1.attr.2']['text'] );
		$this->assertStringContainsString( 'custom attribute aria-label', $ids['b1.attr.2']['context'] );
		$this->assertArrayNotHasKey( 'b1.attr.u1', $ids );

		$this->assertSame(
			array(
				'b0.image.titleText',
				'b0.attr.e6s5i73h29',
				'b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35',
				'b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35.tablet',
				'b1.image.titleText',
				'b1.attr.7jynqlletf',
				'b1.attr.9baebda1-e174-4328-828f-b06cd181b257',
				'b1.attr.2',
				'b1.attr.7jynqlletf.legacy',
			),
			array_keys( $ids )
		);
	}

	public function test_custom_attribute_legacy_copy_filled() {
		$source = self::fixture( 'image-attrs.html' );
		$ids    = self::by_id( Segments::extract( $source ) );
		$this->assertArrayNotHasKey( 'b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35.legacy', $ids, 'Equal legacy copy is not a segment.' );
		$this->assertSame( 'Old plan title', $ids['b1.attr.7jynqlletf.legacy']['text'], 'Different legacy copy is its own segment.' );

		$out = Segments::apply(
			$source,
			array(
				'b0.attr.0fca467f-6dd0-4a8c-9d4b-7f402e453a35' => 'Mécanicien réparant une roue',
				'b1.attr.7jynqlletf'                           => 'Plan de l\'atelier',
			)
		);
		$this->assertSame( array(), $out['fallbacks'] );
		$a = self::attrs_of( $out['content'], 0 )['module']['decoration']['attributes'];
		$this->assertSame( 'Mécanicien réparant une roue', $a['desktop']['value']['attributes'][1]['value'] );
		$this->assertSame( 'Mécanicien réparant une roue', $a['attributes'][1]['value'], 'Legacy copy filled by desktop.' );
		$this->assertSame( 'Mechanic at work', $a['tablet']['value']['attributes'][1]['value'], 'Different tablet value untouched.' );
		$b = self::attrs_of( $out['content'], 1 )['module']['decoration']['attributes'];
		$this->assertSame( 'Plan de l\'atelier', $b['desktop']['value']['attributes'][0]['value'] );
		$this->assertSame( 'Old plan title', $b['attributes'][0]['value'], 'Different legacy copy is not filled.' );
	}

	public function test_custom_attribute_non_prose_names_ignored() {
		$ex = Segments::extract( self::fixture( 'image-attrs.html' ) );
		foreach ( $ex['segments'] as $seg ) {
			$this->assertNotSame( 'hero-img', $seg['text'] );
			$this->assertNotSame( 'hero-photo', $seg['text'] );
			$this->assertStringNotContainsString( 'vy8tqoegre', $seg['id'] );
			$this->assertStringNotContainsString( 'k2l9x0', $seg['id'] );
		}
	}

	public function test_custom_attribute_apply_in_place() {
		$source = self::fixture( 'image-attrs.html' );
		$out    = Segments::apply( $source, array( 'b1.attr.9baebda1-e174-4328-828f-b06cd181b257' => 'Plan avec établis' ) );
		$this->assertSame( str_replace( '"value":"Floor plan with labeled work benches"', '"value":"Plan avec établis"', $source ), $out['content'] );

		$out = Segments::apply( $source, array( 'b0.attr.e6s5i73h29' => 'photo-devanture' ) );
		$this->assertSame( str_replace( '"value":"shop-front-photo"', '"value":"photo-devanture"', $source ), $out['content'], 'Desktop, tablet and legacy copies of the title, and nothing else.' );
	}

	public function test_protect_markers_match_extract() {
		$ids = self::by_id( Segments::extract( self::fixture( 'page.html' ) ) );
		$this->assertSame( array( 'div', 'p', '/p', 'a', '/a', '/div' ), Protect::tags( $ids['b8.content']['text'] ) );
	}
}
