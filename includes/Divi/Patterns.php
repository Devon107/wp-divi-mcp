<?php
/**
 * Composition patterns (PHP port of the divi5-layouts skill's patterns.py).
 *
 * Pure PHP, no WordPress dependency, so it also runs in CLI tooling.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Cards and content blocks built only from column / group / text / icon / image /
 * link: no blurb, testimonial, team-member or button modules. The column (or a
 * group) is the card, groups nest to get any structure, and text carries headings
 * as <h2>/<h3> so the heading tag stays semantic.
 *
 * Pinned against patterns.py by tests/golden (patterns_* cases, byte for byte).
 *
 * Calling conventions (same as D5): the required Python parameters stay
 * positional, then `?Theme $theme = null` (null = default Theme()), then one
 * trailing `array $opts` with every other Python parameter under the same name,
 * e.g. `Patterns::pill_link('Go', '/go', $theme, array('variant' => 'outline'))`.
 * Unknown option keys throw InvalidArgumentException.
 *
 * Every pattern with `attrs` deep-merges it into its outer block. Card patterns
 * return a column(); pass width/tablet/phone when they go in a flex row(),
 * nothing when they go in a grid_row(). section_header() returns Block[] to
 * spread into a column: `D5::column(...Patterns::section_header('Team', 'Leaders'))`.
 *
 * Intentional differences from patterns.py: errors are InvalidArgumentException;
 * labels/numbers interpolated into HTML must be scalars (D5::py_str); body_text()
 * html must be a string.
 */
class Patterns {

	// ----- text pieces ------------------------------------------------- //

	/**
	 * Small uppercase label above a heading.
	 *
	 * @param mixed      $label Label (HTML allowed).
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  attrs.
	 * @return Block
	 */
	public static function eyebrow( $label, ?Theme $theme = null, array $opts = array() ) {
		$t     = self::theme( $theme );
		$o     = self::opts( __FUNCTION__, $opts, array( 'attrs' => null ) );
		$style = self::fam(
			array(
				'size'           => '11px',
				'weight'         => $t->eyebrow_weight,
				'color'          => $t->accent,
				'letterSpacing'  => '0.08em',
				'capitalization' => 'uppercase',
			),
			$t->eyebrow_font
		);
		return D5::text(
			'<p>' . D5::py_str( $label ) . '</p>',
			array( 'attrs' => D5::merge( self::body_font( $style ), $o['attrs'] ) )
		);
	}

	/**
	 * Heading as a Text module (<h1>..<h6> inside), styled via headingFont.
	 *
	 * @param mixed      $title Title (HTML allowed).
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  level ("h2"), size, color, align, attrs.
	 * @return Block
	 */
	public static function heading_text( $title, ?Theme $theme = null, array $opts = array() ) {
		$t       = self::theme( $theme );
		$o       = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'level' => 'h2',
				'size'  => null,
				'color' => null,
				'align' => null,
				'attrs' => null,
			)
		);
		$level   = $o['level'];
		$sizes   = array(
			'h1' => $t->h1_size,
			'h2' => $t->h2_size,
			'h3' => $t->h3_size,
		);
		$default = is_string( $level ) && array_key_exists( $level, $sizes ) ? $sizes[ $level ] : $t->h3_size;
		$big     = in_array( $level, array( 'h1', 'h2' ), true );
		$style   = self::fam(
			array(
				'size'       => self::pick( $o['size'], $default ),
				'weight'     => $t->heading_weight,
				'color'      => self::pick( $o['color'], $t->heading ),
				'lineHeight' => $big ? '1.1em' : '1.25em',
			),
			$t->heading_font
		);
		if ( $big ) {
			$style['letterSpacing'] = '-0.02em';
		}
		if ( D5::truthy( $o['align'] ) ) {
			$style['textAlign'] = $o['align'];
		}
		$tag = D5::py_str( $level );
		return D5::text(
			"<{$tag}>" . D5::py_str( $title ) . "</{$tag}>",
			array(
				'attrs' => D5::merge(
					D5::font(
						'content',
						array(
							'desktop' => $style,
							'group'   => 'headingFont',
							'sub'     => $level,
						)
					),
					$o['attrs']
				),
			)
		);
	}

	/**
	 * Paragraph text. `$html` may be plain text (wrapped in <p>) or HTML.
	 *
	 * @param string     $html  Text or HTML.
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  size, color, align, max_width, attrs.
	 * @return Block
	 * @throws \InvalidArgumentException When $html is not a string.
	 */
	public static function body_text( $html, ?Theme $theme = null, array $opts = array() ) {
		if ( ! is_string( $html ) ) {
			throw new \InvalidArgumentException( 'Patterns::body_text(): html must be a string' );
		}
		$t = self::theme( $theme );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'size'      => null,
				'color'     => null,
				'align'     => null,
				'max_width' => null,
				'attrs'     => null,
			)
		);
		if ( 0 !== strpos( self::py_lstrip( $html ), '<' ) ) {
			$html = "<p>{$html}</p>";
		}
		$style = self::fam(
			array(
				'size'       => self::pick( $o['size'], $t->body_size ),
				'lineHeight' => '1.6em',
				'color'      => self::pick( $o['color'], $t->body ),
			),
			$t->body_font
		);
		if ( D5::truthy( $o['align'] ) ) {
			$style['textAlign'] = $o['align'];
		}
		$extra = D5::truthy( $o['max_width'] ) ? D5::module_deco( array( 'sizing' => D5::rv( array( 'maxWidth' => $o['max_width'] ) ) ) ) : null;
		return D5::text( $html, array( 'attrs' => D5::merge( self::body_font( $style ), $extra, $o['attrs'] ) ) );
	}

	/**
	 * [group(eyebrow, heading), intro]: spread it into a column.
	 *
	 * @param mixed      $eyebrow_label Eyebrow label (falsy = none).
	 * @param mixed      $title         Title.
	 * @param Theme|null $theme         Theme.
	 * @param array      $opts          intro, level ("h2"), align ("left"), max_width ("620px").
	 * @return Block[]
	 */
	public static function section_header( $eyebrow_label, $title, ?Theme $theme = null, array $opts = array() ) {
		$t       = self::theme( $theme );
		$o       = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'intro'     => null,
				'level'     => 'h2',
				'align'     => 'left',
				'max_width' => '620px',
			)
		);
		$align   = $o['align'];
		$text_al = 'left' !== $align ? $align : null;
		$head    = D5::group(
			D5::truthy( $eyebrow_label ) ? self::eyebrow( $eyebrow_label, $t ) : null,
			self::heading_text(
				$title,
				$t,
				array(
					'level' => $o['level'],
					'align' => $text_al,
				)
			),
			array(
				'gap'   => '8px',
				'attrs' => D5::module_deco(
					array(
						'sizing' => D5::rv(
							array(
								'maxWidth'  => $o['max_width'],
								'alignment' => $align,
							)
						),
					)
				),
			)
		);
		$blocks  = array( $head );
		if ( D5::truthy( $o['intro'] ) ) {
			$blocks[] = self::body_text(
				$o['intro'],
				$t,
				array(
					'color'     => $t->muted,
					'align'     => $text_al,
					'max_width' => $o['max_width'],
					'attrs'     => D5::module_deco( array( 'sizing' => D5::rv( array( 'alignment' => $align ) ) ) ),
				)
			);
		}
		return $blocks;
	}

	// ----- badges ------------------------------------------------------ //

	/**
	 * Icon inside a tinted square/circle. shape: circle | soft (card radius) | square.
	 *
	 * @param array|\stdClass $icon_value Icon (D5::fa_icon() / D5::divi_icon()).
	 * @param Theme|null      $theme      Theme.
	 * @param array           $opts       box_size ("52px"), icon_size ("22px"), shape ("circle"), attrs.
	 * @return Block
	 */
	public static function icon_badge( $icon_value, ?Theme $theme = null, array $opts = array() ) {
		$t = self::theme( $theme );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'box_size'  => '52px',
				'icon_size' => '22px',
				'shape'     => 'circle',
				'attrs'     => null,
			)
		);
		if ( 'circle' === $o['shape'] ) {
			$rad = D5::radius( '100%' );
		} elseif ( 'square' === $o['shape'] ) {
			$rad = D5::radius( '8px' );
		} else {
			$rad = self::card_radius( $t->card_radius );
		}
		return D5::icon(
			$icon_value,
			array(
				'size'  => $o['icon_size'],
				'color' => $t->badge_fg,
				'attrs' => D5::merge(
					D5::module_deco(
						array(
							'background' => D5::rv( array( 'color' => $t->badge_bg ) ),
							'border'     => D5::rv( array( 'radius' => $rad ) ),
							'sizing'     => D5::rv(
								array(
									'width'  => $o['box_size'],
									'height' => $o['box_size'],
								)
							),
							'layout'     => D5::rv(
								array(
									'alignItems'     => 'center',
									'justifyContent' => 'center',
								)
							),
						)
					),
					$o['attrs']
				),
			)
		);
	}

	/**
	 * "01"-style number in a tinted circle (Text module).
	 *
	 * @param mixed      $number Number or label.
	 * @param Theme|null $theme  Theme.
	 * @param array      $opts   box_size ("52px"), attrs.
	 * @return Block
	 */
	public static function number_badge( $number, ?Theme $theme = null, array $opts = array() ) {
		$t     = self::theme( $theme );
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'box_size' => '52px',
				'attrs'    => null,
			)
		);
		$style = array(
			'size'      => '19px',
			'weight'    => '900',
			'color'     => $t->badge_fg,
			'textAlign' => 'center',
		);
		return D5::text(
			'<p>' . D5::py_str( $number ) . '</p>',
			array(
				'attrs' => D5::merge(
					self::body_font( $style ),
					D5::module_deco(
						array(
							'background' => D5::rv( array( 'color' => $t->badge_bg ) ),
							'border'     => D5::rv( array( 'radius' => D5::radius( '100px' ) ) ),
							'sizing'     => D5::rv(
								array(
									'width'  => $o['box_size'],
									'height' => $o['box_size'],
								)
							),
							'layout'     => D5::rv(
								array(
									'display'        => 'flex',
									'justifyContent' => 'center',
									'alignItems'     => 'center',
								)
							),
						)
					),
					$o['attrs']
				),
			)
		);
	}

	// ----- links ------------------------------------------------------- //

	/**
	 * Link styled as a pill button. variant: primary (filled) | outline | text.
	 *
	 * The hover state must contrast with the section behind the button, not only with
	 * the button itself. on_dark=true (button on a dark/brand background) switches to a
	 * light hover: white fill with the button color as text. bg/fg/hover_bg/hover_fg
	 * override any of the four colors explicitly.
	 *
	 * The link icon inherits the text color (normal and hover), so no icon color is
	 * written unless icon_color / icon_hover_color ask for a different one.
	 *
	 * @param mixed      $label Label.
	 * @param string     $url   URL.
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  icon_value (arrow; falsy = no icon), variant ("primary"),
	 *                          new_window (false), on_dark (false), bg, fg, hover_bg,
	 *                          hover_fg, icon_color, icon_hover_color, attrs.
	 * @return Block
	 */
	public static function pill_link( $label, $url, ?Theme $theme = null, array $opts = array() ) {
		$t          = self::theme( $theme );
		$o          = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'icon_value'       => null,
				'variant'          => 'primary',
				'new_window'       => false,
				'on_dark'          => false,
				'bg'               => null,
				'fg'               => null,
				'hover_bg'         => null,
				'hover_fg'         => null,
				'icon_color'       => null,
				'icon_hover_color' => null,
				'attrs'            => null,
			)
		);
		$icon_value = null !== $o['icon_value'] ? $o['icon_value'] : D5::fa_icon( '&#xf061;' );
		$link_opts  = array(
			'url'        => $url,
			'icon'       => D5::truthy( $icon_value ) ? $icon_value : null,
			'new_window' => $o['new_window'],
		);
		$on_dark    = D5::truthy( $o['on_dark'] );
		$bg         = $o['bg'];
		$fg         = $o['fg'];
		$hover_bg   = $o['hover_bg'];
		$hover_fg   = $o['hover_fg'];

		if ( 'text' === $o['variant'] ) {
			$base               = D5::merge(
				D5::font(
					'content',
					array(
						'desktop' => array(
							'size'   => '15px',
							'weight' => '700',
							'color'  => self::pick( $fg, $t->accent ),
						),
						'hover'   => D5::truthy( $hover_fg ) ? array( 'color' => $hover_fg ) : null,
					)
				),
				self::icon_attrs( $o['icon_color'], $o['icon_hover_color'] )
			);
			$link_opts['attrs'] = D5::merge( $base, $o['attrs'] );
			return D5::link( $label, $link_opts );
		}

		$filled = 'primary' === $o['variant'];
		if ( $filled ) {
			$bg = self::pick( $bg, $t->button_bg );
			$fg = self::pick( $fg, $t->button_fg );
			if ( $on_dark ) {
				$hover_bg = self::pick( $hover_bg, '#ffffff' );
				$hover_fg = self::pick( $hover_fg, $bg );
			} else {
				$hover_bg = self::pick( $hover_bg, $t->button_hover_bg );
				$hover_fg = self::pick( $hover_fg, $t->button_hover_fg );
			}
			$border_color = $bg;
		} else { // Outline: transparent fill, colored border; fills on hover.
			$line         = self::pick( $fg, $on_dark ? '#ffffff' : $t->button_bg );
			$fg           = $line;
			$bg           = self::pick( $bg, 'transparent' );
			$hover_bg     = self::pick( $hover_bg, $on_dark ? '#ffffff' : $t->button_bg );
			$hover_fg     = self::pick( $hover_fg, $on_dark ? $t->button_bg : $t->button_fg );
			$border_color = $line;
		}
		$hover_border = $hover_bg;

		$base               = D5::merge(
			D5::module_deco(
				array(
					'background' => D5::rv( array( 'color' => $bg ), null, null, array( 'hover' => array( 'color' => $hover_bg ) ) ),
					'border'     => D5::rv(
						array(
							'radius' => D5::radius( $t->pill_radius ),
							'styles' => array(
								'all' => array(
									'width' => $filled ? '0px' : '1px',
									'color' => $border_color,
								),
							),
						),
						null,
						null,
						array( 'hover' => array( 'styles' => array( 'all' => array( 'color' => $hover_border ) ) ) )
					),
					'spacing'    => D5::rv( array( 'padding' => D5::box( '16px', '30px', '16px', '30px' ) ) ),
					'sizing'     => D5::rv( array( 'width' => 'fit-content' ) ),
				)
			),
			D5::font(
				'content',
				array(
					'desktop' => array(
						'size'   => '16px',
						'weight' => '700',
						'color'  => $fg,
					),
					'hover'   => array( 'color' => $hover_fg ),
				)
			),
			self::icon_attrs( $o['icon_color'], $o['icon_hover_color'] )
		);
		$link_opts['attrs'] = D5::merge( $base, $o['attrs'] );
		return D5::link( $label, $link_opts );
	}

	// ----- cards (return columns) -------------------------------------- //

	/**
	 * Icon badge, title, text and optional text link, stacked (replaces blurb).
	 *
	 * @param array|\stdClass $icon_value Icon.
	 * @param mixed           $title      Title.
	 * @param Theme|null      $theme      Theme.
	 * @param array           $opts       body, link_label, link_url, badge_bg, badge_fg,
	 *                                    width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function icon_card( $icon_value, $title, ?Theme $theme = null, array $opts = array() ) {
		$t     = self::theme( $theme );
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'body'       => null,
					'link_label' => null,
					'link_url'   => null,
					'badge_bg'   => null,
					'badge_fg'   => null,
				)
			)
		);
		$badge = self::icon_badge( $icon_value, $t );
		if ( D5::truthy( $o['badge_bg'] ) || D5::truthy( $o['badge_fg'] ) ) {
			$badge = self::icon_badge(
				$icon_value,
				$t,
				array(
					'attrs' => D5::merge(
						D5::truthy( $o['badge_bg'] ) ? D5::module_deco( array( 'background' => D5::rv( array( 'color' => $o['badge_bg'] ) ) ) ) : null,
						D5::truthy( $o['badge_fg'] ) ? array( 'icon' => array( 'advanced' => array( 'color' => D5::rv( $o['badge_fg'] ) ) ) ) : null
					),
				)
			);
		}
		$kids = array( $badge, self::heading_text( $title, $t, array( 'level' => 'h3' ) ) );
		self::append_body_and_link( $kids, $t, $o );
		return self::card_column( $kids, $t, $o, self::stack( '12px' ) );
	}

	/**
	 * Image on top, then title / text / link (replaces blurb with image).
	 *
	 * @param string     $image_src Image URL.
	 * @param mixed      $title     Title.
	 * @param Theme|null $theme     Theme.
	 * @param array      $opts      body, alt (""; falls back to title), link_label, link_url,
	 *                              width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function media_card( $image_src, $title, ?Theme $theme = null, array $opts = array() ) {
		$t    = self::theme( $theme );
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'body'       => null,
					'alt'        => '',
					'link_label' => null,
					'link_url'   => null,
				)
			)
		);
		$kids = array(
			D5::image(
				$image_src,
				array(
					'alt'   => self::pick( $o['alt'], $title ),
					'attrs' => D5::module_deco( array( 'border' => D5::rv( array( 'radius' => D5::radius( '12px' ) ) ) ) ),
				)
			),
			self::heading_text( $title, $t, array( 'level' => 'h3' ) ),
		);
		self::append_body_and_link( $kids, $t, $o );
		return self::card_column( $kids, $t, $o, self::stack( '12px' ) );
	}

	/**
	 * Avatar (photo or icon badge) beside name + role, as a 2-track grid (replaces team-member).
	 *
	 * @param mixed      $name  Name.
	 * @param string     $role  Role.
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  photo, bio, icon_value, width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function person_card( $name, $role, ?Theme $theme = null, array $opts = array() ) {
		$t = self::theme( $theme );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'photo'      => null,
					'bio'        => null,
					'icon_value' => null,
				)
			)
		);
		if ( D5::truthy( $o['photo'] ) ) {
			$avatar = self::avatar_image( $o['photo'], $name, '56px' );
			$track  = '56px 1fr';
		} else {
			$avatar = self::icon_badge(
				self::pick( $o['icon_value'], D5::fa_icon( '&#xf007;' ) ),
				$t,
				array(
					'box_size'  => '42px',
					'icon_size' => '16px',
				)
			);
			$track  = '42px 1fr';
		}
		$info = array(
			self::heading_text(
				$name,
				$t,
				array(
					'level' => 'h3',
					'size'  => '17px',
				)
			),
			self::body_text(
				$role,
				$t,
				array(
					'color' => $t->muted,
					'size'  => '14.5px',
				)
			),
		);
		if ( D5::truthy( $o['bio'] ) ) {
			$info[] = self::body_text( $o['bio'], $t, array( 'size' => '14.5px' ) );
		}
		$info[] = array( 'layout' => array( 'rowGap' => '2px' ) );
		return self::card_column(
			array( $avatar, D5::group( ...$info ) ),
			$t,
			$o,
			D5::grid(
				array(
					'template'   => $track,
					'column_gap' => '20px',
					'align'      => 'flex-start',
				)
			)
		);
	}

	/**
	 * Quote icon, quote text, then author row (replaces testimonial).
	 *
	 * @param string     $quote Quote (text or HTML).
	 * @param mixed      $name  Author name.
	 * @param Theme|null $theme Theme.
	 * @param array      $opts  role (""), photo, width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function testimonial_card( $quote, $name, ?Theme $theme = null, array $opts = array() ) {
		$t = self::theme( $theme );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'role'  => '',
					'photo' => null,
				)
			)
		);
		if ( D5::truthy( $o['photo'] ) ) {
			$avatar = self::avatar_image( $o['photo'], $name, '48px' );
		} else {
			$avatar = self::icon_badge(
				D5::fa_icon( '&#xf007;' ),
				$t,
				array(
					'box_size'  => '48px',
					'icon_size' => '18px',
				)
			);
		}
		$author = D5::group(
			$avatar,
			D5::group(
				self::heading_text(
					$name,
					$t,
					array(
						'level' => 'h3',
						'size'  => '16px',
					)
				),
				D5::truthy( $o['role'] ) ? self::body_text(
					$o['role'],
					$t,
					array(
						'color' => $t->muted,
						'size'  => '14px',
					)
				) : null,
				array( 'layout' => array( 'rowGap' => '0px' ) )
			),
			array(
				'direction' => 'row',
				'align'     => 'center',
				'gap'       => '14px',
			)
		);
		$kids   = array(
			D5::icon(
				D5::fa_icon( '&#xf10d;' ),
				array(
					'size'  => '28px',
					'color' => $t->accent,
				)
			),
			self::body_text(
				$quote,
				$t,
				array(
					'size'  => '17px',
					'attrs' => D5::font(
						'content',
						array(
							'desktop' => array( 'style' => array( 'italic' ) ),
							'group'   => 'bodyFont',
							'sub'     => 'body',
						)
					),
				)
			),
			$author,
		);
		return self::card_column( $kids, $t, $o, self::stack( '18px' ) );
	}

	/**
	 * Top bar with big number (and optional icon at the right), then text.
	 *
	 * @param mixed      $number Number or label.
	 * @param string     $body   Body text or HTML.
	 * @param Theme|null $theme  Theme.
	 * @param array      $opts   icon_value, title, width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function numbered_card( $number, $body, ?Theme $theme = null, array $opts = array() ) {
		$t    = self::theme( $theme );
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'icon_value' => null,
					'title'      => null,
				)
			)
		);
		$num  = D5::text(
			'<p>' . D5::py_str( $number ) . '</p>',
			array(
				'attrs' => self::body_font(
					array(
						'size'   => '30px',
						'weight' => '800',
						'color'  => $t->badge_fg,
					)
				),
			)
		);
		$top  = D5::group(
			$num,
			D5::truthy( $o['icon_value'] ) ? D5::icon(
				$o['icon_value'],
				array(
					'size'  => '20px',
					'color' => $t->badge_fg,
				)
			) : null,
			array(
				'direction' => 'row',
				'justify'   => 'space-between',
				'align'     => 'center',
				'width'     => '100%',
			)
		);
		$kids = array( $top );
		if ( D5::truthy( $o['title'] ) ) {
			$kids[] = self::heading_text( $o['title'], $t, array( 'level' => 'h3' ) );
		}
		$kids[] = self::body_text( $body, $t, array( 'size' => '15px' ) );
		return self::card_column( $kids, $t, $o, self::stack( '12px' ) );
	}

	/**
	 * Horizontal step: number badge at left, title/text/link at right; stacks on tablet.
	 *
	 * @param mixed      $number Number or label.
	 * @param mixed      $title  Title.
	 * @param Theme|null $theme  Theme.
	 * @param array      $opts   body, link_label, link_url, width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function step_card( $number, $title, ?Theme $theme = null, array $opts = array() ) {
		$t    = self::theme( $theme );
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			self::card_defaults(
				array(
					'body'       => null,
					'link_label' => null,
					'link_url'   => null,
				)
			)
		);
		$info = array( self::heading_text( $title, $t, array( 'level' => 'h3' ) ) );
		self::append_body_and_link( $info, $t, $o );
		$info[] = array( 'layout' => array( 'rowGap' => '6px' ) );
		$col    = self::card_column( array( self::number_badge( $number, $t ), D5::group( ...$info ) ), $t, $o, null );
		$attrs  = D5::merge(
			D5::layout_attrs(
				D5::flex(
					array(
						'direction' => 'row',
						'gap'       => '20px',
						'align'     => 'flex-start',
					)
				),
				array( 'flexDirection' => 'column' )
			),
			$col->attrs
		);
		$col->attrs = $attrs instanceof \stdClass ? get_object_vars( $attrs ) : $attrs;
		return $col;
	}

	/**
	 * Big number + label (static, no counter animation).
	 *
	 * @param mixed      $number Number or label.
	 * @param string     $label  Label text or HTML.
	 * @param Theme|null $theme  Theme.
	 * @param array      $opts   width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function stat_card( $number, $label, ?Theme $theme = null, array $opts = array() ) {
		$t    = self::theme( $theme );
		$o    = self::opts( __FUNCTION__, $opts, self::card_defaults( array() ) );
		$kids = array(
			D5::text(
				'<p>' . D5::py_str( $number ) . '</p>',
				array(
					'attrs' => self::body_font(
						self::fam(
							array(
								'size'       => '44px',
								'weight'     => '800',
								'color'      => $t->heading,
								'lineHeight' => '1em',
							),
							$t->heading_font
						)
					),
				)
			),
			self::body_text(
				$label,
				$t,
				array(
					'color' => $t->muted,
					'size'  => '15px',
				)
			),
		);
		return self::card_column( $kids, $t, $o, self::stack( '8px' ) );
	}

	// ----- internals --------------------------------------------------- //

	/**
	 * The given theme, or a fresh default Theme.
	 *
	 * @param Theme|null $theme Theme.
	 * @return Theme
	 */
	private static function theme( ?Theme $theme ) {
		return null === $theme ? new Theme() : $theme;
	}

	/**
	 * Validate option keys and fill defaults.
	 *
	 * @param string $fn       Pattern name.
	 * @param array  $opts     Given options.
	 * @param array  $defaults Defaults (also the allowed keys).
	 * @return array
	 * @throws \InvalidArgumentException On an unknown key.
	 */
	private static function opts( $fn, array $opts, array $defaults ) {
		foreach ( $opts as $key => $unused ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				throw new \InvalidArgumentException( "Patterns::{$fn}(): unknown option '{$key}'" );
			}
		}
		return array_merge( $defaults, $opts );
	}

	/**
	 * Card option defaults plus the pattern's own.
	 *
	 * @param array $own Pattern-specific defaults (come first).
	 * @return array
	 */
	private static function card_defaults( array $own ) {
		return array_merge(
			$own,
			array(
				'width'  => '1_1',
				'tablet' => null,
				'phone'  => null,
				'attrs'  => null,
			)
		);
	}

	/**
	 * Python `$a or $b`.
	 *
	 * @param mixed $a Preferred value.
	 * @param mixed $b Fallback.
	 * @return mixed
	 */
	private static function pick( $a, $b ) {
		return D5::truthy( $a ) ? $a : $b;
	}

	/**
	 * Python str.lstrip(): strip leading Unicode whitespace (str.isspace()).
	 *
	 * @param string $s String.
	 * @return string
	 */
	private static function py_lstrip( $s ) {
		$out = preg_replace( '/^[\s\x{0B}\x{0C}\x{1C}-\x{1F}\x{85}\p{Z}]+/u', '', $s );
		return null === $out ? ltrim( $s, " \t\n\r\x0B\x0C\x1C\x1D\x1E\x1F" ) : $out;
	}

	/**
	 * Add "family" to a font style when a family is set.
	 *
	 * @param array $value  Font style.
	 * @param mixed $family Family.
	 * @return array
	 */
	private static function fam( array $value, $family ) {
		if ( D5::truthy( $family ) ) {
			$value['family'] = $family;
		}
		return $value;
	}

	/**
	 * font("content", desktop=$style, group="bodyFont", sub="body").
	 *
	 * @param array $style Desktop font style.
	 * @return array
	 */
	private static function body_font( array $style ) {
		return D5::font(
			'content',
			array(
				'desktop' => $style,
				'group'   => 'bodyFont',
				'sub'     => 'body',
			)
		);
	}

	/**
	 * Theme card_radius as a radius value: a list spreads (Python tuple), anything else is uniform.
	 *
	 * @param mixed $card_radius Theme card_radius.
	 * @return array
	 * @throws \InvalidArgumentException On a list with 0 or more than 4 values.
	 */
	private static function card_radius( $card_radius ) {
		if ( is_array( $card_radius ) && D5::is_list( $card_radius ) ) {
			$count = count( $card_radius );
			if ( $count < 1 || $count > 4 ) {
				throw new \InvalidArgumentException( 'Theme card_radius takes 1 to 4 values' );
			}
			return D5::radius( ...$card_radius );
		}
		return D5::radius( $card_radius );
	}

	/**
	 * Link icon size, plus a color only when it must differ from the text color.
	 *
	 * @param mixed  $color       Icon color.
	 * @param mixed  $hover_color Icon hover color.
	 * @param string $size        Icon size.
	 * @return array
	 */
	private static function icon_attrs( $color = null, $hover_color = null, $size = '12px' ) {
		$adv = array( 'size' => D5::rv( $size ) );
		if ( D5::truthy( $color ) || D5::truthy( $hover_color ) ) {
			$adv['color'] = D5::rv( $color, null, null, array( 'hover' => $hover_color ) );
		}
		return array( 'icon' => array( 'advanced' => $adv ) );
	}

	/**
	 * flex(direction="column", align="flex-start", gap=$gap).
	 *
	 * @param string $gap Gap.
	 * @return array|\stdClass
	 */
	private static function stack( $gap ) {
		return D5::flex(
			array(
				'direction' => 'column',
				'align'     => 'flex-start',
				'gap'       => $gap,
			)
		);
	}

	/**
	 * Round avatar image of the given width.
	 *
	 * @param string $src   Image URL.
	 * @param mixed  $alt   Alt text.
	 * @param string $width Width.
	 * @return Block
	 */
	private static function avatar_image( $src, $alt, $width ) {
		return D5::image(
			$src,
			array(
				'alt'   => $alt,
				'attrs' => D5::module_deco(
					array(
						'border' => D5::rv( array( 'radius' => D5::radius( '100%' ) ) ),
						'sizing' => D5::rv( array( 'width' => $width ) ),
					)
				),
			)
		);
	}

	/**
	 * Append the muted body text and the text link (both optional) shared by several cards.
	 *
	 * @param array $kids Blocks (appended to).
	 * @param Theme $t    Theme.
	 * @param array $o    Options with body, link_label, link_url.
	 * @return void
	 */
	private static function append_body_and_link( array &$kids, Theme $t, array $o ) {
		if ( D5::truthy( $o['body'] ) ) {
			$kids[] = self::body_text(
				$o['body'],
				$t,
				array(
					'color' => $t->muted,
					'size'  => '15px',
				)
			);
		}
		if ( D5::truthy( $o['link_label'] ) && D5::truthy( $o['link_url'] ) ) {
			$kids[] = self::pill_link( $o['link_label'], $o['link_url'], $t, array( 'variant' => 'text' ) );
		}
	}

	/**
	 * The card column (patterns.py _card_column).
	 *
	 * @param Block[]              $children     Children.
	 * @param Theme                $t            Theme.
	 * @param array                $o            Options with width, tablet, phone, attrs.
	 * @param array|\stdClass|null $extra_layout Layout value (flex()/grid()) or null.
	 * @return Block
	 */
	private static function card_column( array $children, Theme $t, array $o, $extra_layout ) {
		$top   = null;
		if ( D5::truthy( $t->card_top_border ) ) {
			$top = D5::module_deco(
				array(
					'border' => D5::rv(
						array(
							'styles' => array(
								'top' => array(
									'width' => $t->card_top_border_width,
									'color' => $t->card_top_border,
								),
							),
						)
					),
				)
			);
		}
		$hover = D5::truthy( $t->card_hover_css )
			? D5::custom_css( array( 'hover_main' => $t->card_hover_css ) )
			: D5::card(
				array(
					'hover_shadow' => array(
						'horizontal' => '0px',
						'vertical'   => '10px',
						'blur'       => '28px',
						'spread'     => '0px',
						'color'      => 'rgba(22,24,52,0.07)',
					),
				)
			);
		$attrs = D5::merge(
			D5::card(
				array(
					'background'   => $t->card_bg,
					'border_color' => $t->border,
					'radius'       => $t->card_radius,
					'padding'      => $t->card_padding,
				)
			),
			$top,
			$hover,
			D5::truthy( $extra_layout ) ? D5::layout_attrs( $extra_layout ) : null,
			$o['attrs']
		);
		$children[] = array(
			'width'  => $o['width'],
			'tablet' => $o['tablet'],
			'phone'  => $o['phone'],
			'attrs'  => $attrs,
		);
		return D5::column( ...$children );
	}
}
