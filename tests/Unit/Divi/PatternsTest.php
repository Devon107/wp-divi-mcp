<?php
/**
 * Unit tests for the composition patterns (WPDM\Divi\Patterns, WPDM\Divi\Theme).
 *
 * Output equality with patterns.py is pinned by GoldenTest (patterns_* cases);
 * these tests cover the PHP-only API and the composition defaults.
 *
 * @package WPDM
 */

use WPDM\Divi\Block;
use WPDM\Divi\D5;
use WPDM\Divi\Patterns;
use WPDM\Divi\Theme;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PatternsTest extends TestCase {

	public function set_up() {
		parent::set_up();
		D5::set_builder_version( '5.19.1' );
	}

	public function test_theme_defaults_match_python() {
		$theme = new Theme();
		$this->assertSame( '#161834', $theme->heading );
		$this->assertSame( '#0043ff', $theme->accent );
		$this->assertSame( '', $theme->heading_font );
		$this->assertSame( array( '4px', '4px', '22px', '22px' ), $theme->card_radius );
		$this->assertSame( '50px', $theme->pill_radius );
		$this->assertSame( 28, count( get_object_vars( $theme ) ) );
	}

	public function test_theme_from_array_overrides() {
		$theme = Theme::from_array(
			array(
				'accent'      => '#ff0000',
				'card_radius' => '16px',
			)
		);
		$this->assertSame( '#ff0000', $theme->accent );
		$this->assertSame( '16px', $theme->card_radius );
		$this->assertSame( '#161834', $theme->heading );
	}

	public function test_theme_unknown_key_throws() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'accent_color' );
		Theme::from_array( array( 'accent_color' => '#ff0000' ) );
	}

	public function test_unknown_option_throws() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'colour' );
		Patterns::pill_link( 'Go', '/go', null, array( 'colour' => '#000' ) );
	}

	public function test_pill_link_no_icon_color_by_default() {
		foreach ( array( 'primary', 'outline', 'text' ) as $variant ) {
			$link = Patterns::pill_link( 'Go', '/go', null, array( 'variant' => $variant ) );
			$this->assertSame( array( 'size' => D5::rv( '12px' ) ), $link->attrs['icon']['advanced'], $variant );
		}
		$link = Patterns::pill_link( 'Go', '/go', null, array( 'icon_hover_color' => '#00ff00' ) );
		$this->assertSame( D5::rv( null, null, null, array( 'hover' => '#00ff00' ) ), $link->attrs['icon']['advanced']['color'] );
	}

	public function test_pill_link_padding_and_on_dark_hover() {
		$link = Patterns::pill_link( 'Go', '/go', null, array( 'on_dark' => true ) );
		$deco = $link->attrs['module']['decoration'];
		$this->assertSame( D5::box( '16px', '30px', '16px', '30px' ), $deco['spacing']['desktop']['value']['padding'] );
		$this->assertSame( array( 'color' => '#ffffff' ), $deco['background']['desktop']['hover'] );
		$theme = new Theme();
		$this->assertSame( array( 'color' => $theme->button_bg ), $link->attrs['content']['decoration']['font']['font']['desktop']['hover'] );
	}

	public function test_section_header_returns_blocks() {
		$blocks = Patterns::section_header( 'Eyebrow', 'Title', null, array( 'intro' => 'Intro' ) );
		$this->assertCount( 2, $blocks );
		$this->assertContainsOnlyInstancesOf( Block::class, $blocks );
		$this->assertSame( 'divi/group', $blocks[0]->name );
		$this->assertCount( 2, $blocks[0]->children );
		$this->assertCount( 1, Patterns::section_header( '', 'Title' )[0]->children );
	}

	public function test_cards_return_columns() {
		$icon  = D5::fa_icon( '&#xf0e0;' );
		$cards = array(
			Patterns::icon_card( $icon, 'T' ),
			Patterns::media_card( 'https://example.com/a.jpg', 'T' ),
			Patterns::person_card( 'N', 'R' ),
			Patterns::testimonial_card( 'Q', 'N' ),
			Patterns::numbered_card( '01', 'B' ),
			Patterns::step_card( '1', 'T' ),
			Patterns::stat_card( '9', 'L' ),
		);
		foreach ( $cards as $card ) {
			$this->assertSame( 'divi/column', $card->name );
		}
	}

	public function test_body_text_requires_string() {
		$this->expectException( InvalidArgumentException::class );
		Patterns::body_text( 42 );
	}
}
