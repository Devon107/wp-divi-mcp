<?php
/**
 * Design tokens for the composition patterns (port of patterns.py's Theme).
 *
 * Pure PHP, no WordPress dependency, so it also runs in CLI tooling.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Theme tokens. Values may be plain colors or D5::color_var() / D5::content_var() refs.
 *
 * Property names and defaults are those of patterns.py's `Theme` dataclass.
 * card_radius is a 4-item list (TL, TR, BR, BL; Python's tuple) or a single string.
 */
class Theme {

	/** @var string Heading color. */
	public $heading = '#161834';

	/** @var string Body text color. */
	public $body = '#3a4060';

	/** @var string Muted text color. */
	public $muted = '#6e7490';

	/** @var string Accent color (eyebrows, links). */
	public $accent = '#0043ff';

	/** @var string Card border color. */
	public $border = '#e3e6ef';

	/** @var string Card background. */
	public $card_bg = '#ffffff';

	/** @var string Badge background. */
	public $badge_bg = '#e9edfa';

	/** @var string Badge foreground. */
	public $badge_fg = '#2f4ba3';

	/** @var string Button background. */
	public $button_bg = '#ffb600';

	/** @var string Button text color. */
	public $button_fg = '#161834';

	/** @var string Button hover background. */
	public $button_hover_bg = '#2f4ba3';

	/** @var string Button hover text color. */
	public $button_hover_fg = '#ffffff';

	/** @var string Heading font family ("" = site default), e.g. "Red Hat Display". */
	public $heading_font = '';

	/** @var string Body font family ("" = site default). */
	public $body_font = '';

	/** @var string Eyebrow font family ("" = site default), e.g. "Red Hat Mono". */
	public $eyebrow_font = '';

	/** @var string Eyebrow font weight. */
	public $eyebrow_weight = '700';

	/** @var string Heading font weight. */
	public $heading_weight = '700';

	/** @var string Card top border color ("" = none). */
	public $card_top_border = '';

	/** @var string Card top border width. */
	public $card_top_border_width = '4px';

	/** @var string Card hover CSS ("" = default hover shadow), e.g. "transform: translateY(-4px);". */
	public $card_hover_css = '';

	/** @var string H1 size. */
	public $h1_size = 'clamp(2.5rem, 5vw + 1rem, 4.125rem)';

	/** @var string H2 size. */
	public $h2_size = '38px';

	/** @var string H3 size. */
	public $h3_size = '19px';

	/** @var string Body size. */
	public $body_size = '16px';

	/** @var string[]|string Card radius: list (TL, TR, BR, BL) or a single value such as "16px". */
	public $card_radius = array( '4px', '4px', '22px', '22px' );

	/** @var string Card padding. */
	public $card_padding = '24px';

	/** @var string Card gap. */
	public $card_gap = '20px';

	/** @var string Pill (button) radius. */
	public $pill_radius = '50px';

	/**
	 * Theme with the given tokens overridden (Python `Theme(**overrides)`).
	 *
	 * @param array $overrides Token name => value.
	 * @return Theme
	 * @throws \InvalidArgumentException On an unknown token name.
	 */
	public static function from_array( array $overrides ) {
		$theme  = new self();
		$fields = get_class_vars( self::class );
		foreach ( $overrides as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $fields ) ) {
				throw new \InvalidArgumentException( "Theme: unknown token '{$key}'" );
			}
			$theme->$key = $value;
		}
		return $theme;
	}
}
