<?php
/**
 * Divi 5 layout builder library (PHP port of the divi5-layouts skill's divi5.py).
 *
 * Pure PHP, no WordPress dependency, so it also runs in CLI tooling.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Static helpers that build Divi 5 blocks and serialize them to block markup.
 *
 * This class is the source of truth for the plugin; it was ported from divi5.py
 * and is pinned against the Python output by tests/golden (byte for byte).
 *
 * Calling conventions (Python -> PHP):
 *
 * - Value helpers keep every Python parameter positional, in the same order:
 *   set_builder_version, block, rv (+ trailing `$more` for hover/sticky/extra
 *   breakpoints), box, radius, color_var, content_var, merge, deco (+ trailing
 *   `$groups`), module_deco($groups), html_attrs, admin_label, layout_attrs,
 *   fa_icon, divi_icon, encode_attrs, serialize, post_content, global_color,
 *   global_variable, find_image_urls.
 * - Every other helper takes its required Python parameters positionally and all
 *   optional ones (Python defaults, keyword-only args and **kwargs) in one
 *   trailing `array $opts` with the same key names, e.g.
 *   `D5::heading('Hi', array('level' => 'h1'))`. A Python parameter with a
 *   trailing underscore drops it: `radius_` -> `radius`, `open_` -> `open`,
 *   `type_` -> `type`. Unknown keys throw InvalidArgumentException (except in
 *   custom_css, where extra keys are per-element CSS, as **element_css in Python).
 * - Helpers with Python `*children` are variadic: pass the child blocks (null
 *   entries are dropped) and, optionally, the options array as the LAST argument:
 *   `D5::section($row1, $row2, array('css_id' => 'top'))`.
 *
 * JSON conventions:
 *
 * - Associative arrays are JSON objects in insertion order; lists (keys 0..n-1)
 *   are JSON arrays. An empty PHP array is therefore `[]`: use `new \stdClass()`
 *   for an empty JSON object. Helpers that can produce an empty object (rv, merge,
 *   deco, html_attrs, card, flex...) return a \stdClass in that case.
 * - Floats are encoded like Python's json.dumps / repr (1.0 -> "1.0",
 *   1e-7 -> "1e-07", 1e16 -> "1e+16"): the shortest digit string that round-trips,
 *   found with sprintf('%.Ne') for N = 0..16 plus its last-digit neighbours (for
 *   the asymmetric interval at powers of two). Checked against Python on ~66k
 *   floats (random bit patterns, every power of two and its neighbours) on PHP
 *   7.4 and 8.2; it relies on PHP's correctly rounded sprintf/strtod. NaN and
 *   Infinity are emitted like Python ("NaN", "Infinity"), which is not valid JSON.
 * - Truthiness follows Python ("0" is truthy, an empty \stdClass is falsy).
 *
 * Intentional differences from divi5.py: errors are InvalidArgumentException
 * instead of ValueError/TypeError; a "divi/column" made with block() (not column())
 * is treated as a full-width column by row() instead of raising AttributeError;
 * Block children must be Block objects. embed_image()/write_layout() are not ported
 * (file and network I/O belong to the WordPress side).
 */
class D5 {

	/**
	 * Default builderVersion stamped on blocks (the installed Divi version; Task 13
	 * overrides it with the live version via set_builder_version()).
	 */
	const BUILDER_VERSION = '5.19.1';

	const BREAKPOINTS = array( 'desktop', 'tablet', 'phone', 'phoneWide', 'tabletWide', 'widescreen', 'ultraWide' );

	const GRID_PRESETS = array(
		1 => 'css-grid-grids_1',
		2 => 'css-grid-grids_2',
		3 => 'css-grid-grids_5',
		4 => 'css-grid-grids_6',
		5 => 'css-grid-grids_9',
		6 => 'css-grid-grids_10',
	);

	/**
	 * uuid.NAMESPACE_URL (6ba7b811-9dad-11d1-80b4-00c04fd430c8) as hex.
	 */
	const NAMESPACE_URL_HEX = '6ba7b8119dad11d180b400c04fd430c8';

	/**
	 * @var string Current builder version.
	 */
	private static $builder_version = self::BUILDER_VERSION;

	/**
	 * @var array<string, string>|null Escape map for encode_attrs strings.
	 */
	private static $escape_map = null;

	// ------------------------------------------------------------------ //
	// Attribute helpers
	// ------------------------------------------------------------------ //

	/**
	 * Responsive value: {"desktop": {"value": ...}, "tablet": {...}, ...}.
	 *
	 * `$more['hover']` / `$more['sticky']` add states to desktop. Other `$more`
	 * keys are extra breakpoints (phoneWide, tabletWide, widescreen, ultraWide).
	 * Null values are skipped.
	 *
	 * @param mixed $desktop Desktop value.
	 * @param mixed $tablet  Tablet value.
	 * @param mixed $phone   Phone value.
	 * @param array $more    hover, sticky and extra breakpoints.
	 * @return array|\stdClass
	 */
	public static function rv( $desktop = null, $tablet = null, $phone = null, array $more = array() ) {
		$hover  = array_key_exists( 'hover', $more ) ? $more['hover'] : null;
		$sticky = array_key_exists( 'sticky', $more ) ? $more['sticky'] : null;
		unset( $more['hover'], $more['sticky'] );

		$out = array();
		if ( null !== $desktop || null !== $hover || null !== $sticky ) {
			$state = array();
			if ( null !== $desktop ) {
				$state['value'] = $desktop;
			}
			if ( null !== $hover ) {
				$state['hover'] = $hover;
			}
			if ( null !== $sticky ) {
				$state['sticky'] = $sticky;
			}
			$out['desktop'] = $state;
		}
		if ( null !== $tablet ) {
			$out['tablet'] = array( 'value' => $tablet );
		}
		if ( null !== $phone ) {
			$out['phone'] = array( 'value' => $phone );
		}
		foreach ( $more as $bp => $value ) {
			if ( ! in_array( $bp, self::BREAKPOINTS, true ) ) {
				throw new \InvalidArgumentException( 'Unknown breakpoint ' . self::py_repr( $bp ) );
			}
			if ( null !== $value ) {
				$out[ $bp ] = array( 'value' => $value );
			}
		}
		return self::obj( $out );
	}

	/**
	 * Spacing box for margin/padding with Divi's sync flags.
	 *
	 * @param mixed $top    Top.
	 * @param mixed $right  Right.
	 * @param mixed $bottom Bottom.
	 * @param mixed $left   Left.
	 * @return array
	 */
	public static function box( $top = '', $right = '', $bottom = '', $left = '' ) {
		return array(
			'top'            => $top,
			'right'          => $right,
			'bottom'         => $bottom,
			'left'           => $left,
			'syncVertical'   => ( self::py_eq( $top, $bottom ) && ! self::py_eq( $top, '' ) ) ? 'on' : 'off',
			'syncHorizontal' => ( self::py_eq( $left, $right ) && ! self::py_eq( $left, '' ) ) ? 'on' : 'off',
		);
	}

	/**
	 * Border radius. One value = uniform; four values = CSS order (TL, TR, BR, BL).
	 *
	 * @param mixed $top_left     Top left (or all corners).
	 * @param mixed $top_right    Top right.
	 * @param mixed $bottom_right Bottom right.
	 * @param mixed $bottom_left  Bottom left.
	 * @return array
	 */
	public static function radius( $top_left, $top_right = null, $bottom_right = null, $bottom_left = null ) {
		if ( null === $top_right ) {
			return array(
				'topLeft'     => $top_left,
				'topRight'    => $top_left,
				'bottomLeft'  => $top_left,
				'bottomRight' => $top_left,
				'sync'        => 'on',
			);
		}
		return array(
			'topLeft'     => $top_left,
			'topRight'    => $top_right,
			'bottomRight' => $bottom_right,
			'bottomLeft'  => $bottom_left,
			'sync'        => 'off',
		);
	}

	/**
	 * Reference a global color, e.g. color_var("gcid-primary-color").
	 *
	 * @param string $gcid    Global color id.
	 * @param mixed  $opacity Optional opacity.
	 * @return string
	 */
	public static function color_var( $gcid, $opacity = null ) {
		$settings = null !== $opacity ? array( 'opacity' => $opacity ) : new \stdClass();
		$payload  = self::py_dumps(
			array(
				'type'  => 'color',
				'value' => array(
					'name'     => $gcid,
					'settings' => $settings,
				),
			)
		);
		return '$variable(' . $payload . ')$';
	}

	/**
	 * Reference a global variable (number, text, font, link...), e.g. content_var("gvid-abc123").
	 *
	 * @param string $gvid Global variable id.
	 * @return string
	 */
	public static function content_var( $gvid ) {
		$payload = self::py_dumps(
			array(
				'type'  => 'content',
				'value' => array(
					'name'     => $gvid,
					'settings' => new \stdClass(),
				),
			)
		);
		return '$variable(' . $payload . ')$';
	}

	/**
	 * Deep-merge dicts left to right (later wins). Lists are replaced, not merged.
	 *
	 * Falsy arguments (null, [], empty \stdClass) are skipped. A result (or nested
	 * result) that ends up empty is a \stdClass, so it still encodes as {}.
	 *
	 * @param array|\stdClass|null ...$dicts Dicts.
	 * @return array|\stdClass
	 */
	public static function merge( ...$dicts ) {
		$out = array();
		foreach ( $dicts as $d ) {
			if ( ! self::truthy( $d ) ) {
				continue;
			}
			if ( ! self::is_dict( $d ) ) {
				throw new \InvalidArgumentException( 'merge() takes dicts (associative arrays or stdClass)' );
			}
			foreach ( self::to_array( $d ) as $key => $value ) {
				if ( self::is_dict( $value ) && array_key_exists( $key, $out ) && self::is_dict( $out[ $key ] ) ) {
					$out[ $key ] = self::merge( $out[ $key ], $value );
				} else {
					$out[ $key ] = $value;
				}
			}
		}
		return self::obj( $out );
	}

	/**
	 * Build {element: {"decoration": {group: value}}}.
	 *
	 * Example: deco("title", array("font" => array("font" => rv(array("size" => "32px")))))
	 *
	 * @param string $element Element name.
	 * @param array  $groups  Decoration groups.
	 * @return array
	 */
	public static function deco( $element = 'module', array $groups = array() ) {
		return array( $element => array( 'decoration' => self::obj( $groups ) ) );
	}

	/**
	 * Shortcut for deco("module", ...).
	 *
	 * @param array $groups Decoration groups.
	 * @return array
	 */
	public static function module_deco( array $groups = array() ) {
		return self::deco( 'module', $groups );
	}

	/**
	 * Font settings for an element.
	 *
	 * group="font"        -> {element: {decoration: {font: {font: rv(...)}}}}  (titles, buttons)
	 * group="bodyFont"    -> needs sub="body"|"link"|"ul"|"ol"|"quote"       (rich text body)
	 * group="headingFont" -> needs sub="h1".."h6"                            (headings inside rich text)
	 *
	 * @param string $element Element.
	 * @param array  $opts    desktop, tablet, phone, hover, group ("font"), sub.
	 * @return array
	 */
	public static function font( $element = 'content', array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'desktop' => null,
				'tablet'  => null,
				'phone'   => null,
				'hover'   => null,
				'group'   => 'font',
				'sub'     => null,
			)
		);
		$value = array( 'font' => self::rv( $o['desktop'], $o['tablet'], $o['phone'], array( 'hover' => $o['hover'] ) ) );
		if ( self::py_eq( $o['group'], 'font' ) ) {
			return array( $element => array( 'decoration' => array( 'font' => $value ) ) );
		}
		if ( ! self::truthy( $o['sub'] ) ) {
			throw new \InvalidArgumentException( 'group=' . self::py_repr( $o['group'] ) . ' needs sub=' );
		}
		return array( $element => array( 'decoration' => array( $o['group'] => array( $o['sub'] => $value ) ) ) );
	}

	/**
	 * CSS ID / class on the module wrapper.
	 *
	 * @param string|null $css_id    CSS id.
	 * @param string|null $css_class CSS class(es).
	 * @return array
	 */
	public static function html_attrs( $css_id = null, $css_class = null ) {
		$value = array();
		if ( self::truthy( $css_id ) ) {
			$value['id'] = $css_id;
		}
		if ( self::truthy( $css_class ) ) {
			$value['class'] = $css_class;
		}
		return array( 'module' => array( 'advanced' => array( 'htmlAttributes' => self::rv( self::obj( $value ) ) ) ) );
	}

	/**
	 * Admin label shown in the Visual Builder layers.
	 *
	 * @param string $label Label.
	 * @return array
	 */
	public static function admin_label( $label ) {
		return array( 'module' => array( 'meta' => array( 'adminLabel' => self::rv( $label ) ) ) );
	}

	/**
	 * Per-module custom CSS (css.desktop.value.mainElement, etc.).
	 *
	 * hover_main -> css.desktop.hover.mainElement; tablet_main / phone_main ->
	 * css.tablet/phone.value.mainElement. Any other key is per-element CSS.
	 *
	 * @param array $opts main, before, after, free_form, hover_main, tablet_main, phone_main, + element CSS.
	 * @return array
	 */
	public static function custom_css( array $opts = array() ) {
		$known = array( 'main', 'before', 'after', 'free_form', 'hover_main', 'tablet_main', 'phone_main' );
		$o     = array();
		foreach ( $known as $key ) {
			$o[ $key ] = array_key_exists( $key, $opts ) ? $opts[ $key ] : null;
		}
		$value = array();
		if ( self::truthy( $o['main'] ) ) {
			$value['mainElement'] = $o['main'];
		}
		if ( self::truthy( $o['before'] ) ) {
			$value['before'] = $o['before'];
		}
		if ( self::truthy( $o['after'] ) ) {
			$value['after'] = $o['after'];
		}
		if ( self::truthy( $o['free_form'] ) ) {
			$value['freeForm'] = $o['free_form'];
		}
		foreach ( $opts as $key => $css ) {
			if ( ! in_array( $key, $known, true ) ) {
				$value[ $key ] = $css;
			}
		}
		return array(
			'css' => self::rv(
				$value ? $value : null,
				self::truthy( $o['tablet_main'] ) ? array( 'mainElement' => $o['tablet_main'] ) : null,
				self::truthy( $o['phone_main'] ) ? array( 'mainElement' => $o['phone_main'] ) : null,
				array( 'hover' => self::truthy( $o['hover_main'] ) ? array( 'mainElement' => $o['hover_main'] ) : null )
			),
		);
	}

	// ------------------------------------------------------------------ //
	// Blocks
	// ------------------------------------------------------------------ //

	/**
	 * Stamp blocks with the target site's Divi version (e.g. "5.14.0").
	 *
	 * @param string $version Version.
	 * @return void
	 */
	public static function set_builder_version( $version ) {
		self::$builder_version = $version;
	}

	/**
	 * Current builder version.
	 *
	 * @return string
	 */
	public static function builder_version() {
		return self::$builder_version;
	}

	/**
	 * Generic block. children=null renders self-closing; [] renders an empty container.
	 *
	 * @param string               $name            Block name ("divi/...").
	 * @param array|\stdClass|null $attrs           Attributes.
	 * @param array|null           $children        Children.
	 * @param bool                 $builder_version Add builderVersion unless present.
	 * @return Block
	 */
	public static function block( $name, $attrs = null, $children = null, $builder_version = true ) {
		$attrs = self::truthy( $attrs ) ? self::to_array( $attrs ) : array();
		if ( self::truthy( $builder_version ) && ! array_key_exists( 'builderVersion', $attrs ) ) {
			$attrs['builderVersion'] = self::$builder_version;
		}
		return new Block( $name, $attrs, $children );
	}

	// ----- layout values ------------------------------------------------ //

	/**
	 * Value for decoration.layout in flex mode (sections, rows, columns, groups, links, icons).
	 *
	 * direction: row | column | row-reverse | column-reverse
	 * justify:   start | center | end | space-between | space-around
	 * align:     flex-start | center | flex-end | stretch
	 * gap sets both rowGap and columnGap; row_gap/column_gap override.
	 * wrap: true -> "wrap", false -> "nowrap", or a CSS value.
	 *
	 * @param array $opts direction, justify, align, gap, row_gap, column_gap, wrap, display.
	 * @return array|\stdClass
	 */
	public static function flex( array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'direction'  => null,
				'justify'    => null,
				'align'      => null,
				'gap'        => null,
				'row_gap'    => null,
				'column_gap' => null,
				'wrap'       => null,
				'display'    => null,
			)
		);
		$value = self::truthy( $o['display'] ) ? array( 'display' => $o['display'] ) : array();
		if ( self::truthy( $o['direction'] ) ) {
			$value['flexDirection'] = $o['direction'];
		}
		if ( self::truthy( $o['justify'] ) ) {
			$value['justifyContent'] = $o['justify'];
		}
		if ( self::truthy( $o['align'] ) ) {
			$value['alignItems'] = $o['align'];
		}
		if ( null !== $o['gap'] ) {
			$value['rowGap']    = $o['gap'];
			$value['columnGap'] = $o['gap'];
		}
		if ( null !== $o['row_gap'] ) {
			$value['rowGap'] = $o['row_gap'];
		}
		if ( null !== $o['column_gap'] ) {
			$value['columnGap'] = $o['column_gap'];
		}
		if ( null !== $o['wrap'] ) {
			$value['flexWrap'] = true === $o['wrap'] ? 'wrap' : ( false === $o['wrap'] ? 'nowrap' : $o['wrap'] );
		}
		return self::obj( $value );
	}

	/**
	 * Value for decoration.layout in CSS grid mode.
	 *
	 * columns=3            -> equal columns (gridColumnWidths "equal", gridColumnCount "3")
	 * template="42px 1fr"  -> manual track sizes (gridColumnWidths "manual", gridTemplateColumns)
	 *
	 * @param array $opts columns, template, gap, row_gap, column_gap, align, justify.
	 * @return array
	 */
	public static function grid( array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'columns'    => null,
				'template'   => null,
				'gap'        => null,
				'row_gap'    => null,
				'column_gap' => null,
				'align'      => null,
				'justify'    => null,
			)
		);
		$value = array( 'display' => 'grid' );
		if ( self::truthy( $o['template'] ) ) {
			$value['gridColumnWidths']    = 'manual';
			$value['gridTemplateColumns'] = $o['template'];
		} elseif ( self::truthy( $o['columns'] ) ) {
			$value['gridColumnWidths'] = 'equal';
			$value['gridColumnCount']  = self::py_str( $o['columns'] );
			$value['gridAutoColumns']  = self::py_str( $o['columns'] );
		}
		if ( null !== $o['gap'] ) {
			$value['rowGap']    = $o['gap'];
			$value['columnGap'] = $o['gap'];
		}
		if ( null !== $o['row_gap'] ) {
			$value['rowGap'] = $o['row_gap'];
		}
		if ( null !== $o['column_gap'] ) {
			$value['columnGap'] = $o['column_gap'];
		}
		if ( self::truthy( $o['align'] ) ) {
			$value['alignItems'] = $o['align'];
		}
		if ( self::truthy( $o['justify'] ) ) {
			$value['justifyContent'] = $o['justify'];
		}
		return $value;
	}

	/**
	 * {element: {decoration: {layout: rv(desktop, tablet, phone)}}} — use with flex()/grid() values.
	 *
	 * @param mixed  $desktop Desktop layout.
	 * @param mixed  $tablet  Tablet layout.
	 * @param mixed  $phone   Phone layout.
	 * @param string $element Element.
	 * @return array
	 */
	public static function layout_attrs( $desktop = null, $tablet = null, $phone = null, $element = 'module' ) {
		return array( $element => array( 'decoration' => array( 'layout' => self::rv( $desktop, $tablet, $phone ) ) ) );
	}

	/**
	 * Card look for a column, group or row.
	 *
	 * radius: "16px" or a 4-item list (topLeft, topRight, bottomRight, bottomLeft).
	 * padding: "24px" or box(...) dict.
	 * shadow / hover_shadow: dicts of boxShadow keys, e.g. {"vertical": "10px", "blur": "28px"}.
	 *
	 * @param array $opts background, border_color, border_width ("1px"), radius, padding,
	 *                    shadow, hover_shadow, hover_border_color, element ("module").
	 * @return array
	 */
	public static function card( array $opts = array() ) {
		$o      = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'background'         => null,
				'border_color'       => null,
				'border_width'       => '1px',
				'radius'             => null,
				'padding'            => null,
				'shadow'             => null,
				'hover_shadow'       => null,
				'hover_border_color' => null,
				'element'            => 'module',
			)
		);
		$groups = array();
		if ( self::truthy( $o['background'] ) ) {
			$groups['background'] = self::rv( array( 'color' => $o['background'] ) );
		}
		$border = array();
		if ( self::truthy( $o['radius'] ) ) {
			if ( is_array( $o['radius'] ) && self::is_list( $o['radius'] ) ) {
				$count = count( $o['radius'] );
				if ( $count < 1 || $count > 4 ) {
					throw new \InvalidArgumentException( 'card() radius takes 1 to 4 values' );
				}
				$border['radius'] = self::radius( ...$o['radius'] );
			} else {
				$border['radius'] = self::radius( $o['radius'] );
			}
		}
		if ( self::truthy( $o['border_color'] ) ) {
			$border['styles'] = array(
				'all' => array(
					'width' => $o['border_width'],
					'color' => $o['border_color'],
				),
			);
		}
		if ( $border || self::truthy( $o['hover_border_color'] ) ) {
			$groups['border'] = self::rv(
				$border ? $border : null,
				null,
				null,
				array( 'hover' => self::truthy( $o['hover_border_color'] ) ? array( 'styles' => array( 'all' => array( 'color' => $o['hover_border_color'] ) ) ) : null )
			);
		}
		if ( self::truthy( $o['padding'] ) ) {
			$padding            = $o['padding'];
			$groups['spacing'] = self::rv( array( 'padding' => self::is_dict( $padding ) ? $padding : self::box( $padding, $padding, $padding, $padding ) ) );
		}
		if ( self::truthy( $o['shadow'] ) || self::truthy( $o['hover_shadow'] ) ) {
			$groups['boxShadow'] = self::rv(
				self::truthy( $o['shadow'] ) ? self::with_preset( $o['shadow'] ) : null,
				null,
				null,
				array( 'hover' => self::truthy( $o['hover_shadow'] ) ? self::with_preset( $o['hover_shadow'] ) : null )
			);
		}
		return array( $o['element'] => array( 'decoration' => self::obj( $groups ) ) );
	}

	// ----- structure ---------------------------------------------------- //

	/**
	 * Section. kind="fullwidth" for fullwidth modules. css_id for anchor links.
	 *
	 * @param Block|array|null ...$args Child blocks, then optional opts: attrs, kind, css_id.
	 * @return Block
	 */
	public static function section( ...$args ) {
		list( $children, $opts ) = self::split_args( __FUNCTION__, $args );
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'attrs'  => null,
				'kind'   => null,
				'css_id' => null,
			)
		);
		$base = array();
		if ( self::truthy( $o['kind'] ) ) {
			$base = array( 'module' => array( 'advanced' => array( 'type' => self::rv( $o['kind'] ) ) ) );
		}
		if ( self::truthy( $o['css_id'] ) ) {
			$base = self::merge( $base, self::html_attrs( $o['css_id'] ) );
		}
		return self::block( 'divi/section', self::merge( $base, $o['attrs'] ), $children );
	}

	/**
	 * Column. width like "1_2", "1_3", "2_3", "3_5" (or "1/2").
	 *
	 * tablet/phone override widths; row() fills defaults once it knows the column count.
	 *
	 * @param Block|array|null ...$args Child blocks, then optional opts: width ("1_1"), tablet, phone, attrs.
	 * @return Block
	 */
	public static function column( ...$args ) {
		list( $children, $opts ) = self::split_args( __FUNCTION__, $args );
		$o   = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'width'  => '1_1',
				'tablet' => null,
				'phone'  => null,
				'attrs'  => null,
			)
		);
		$col                 = self::block( 'divi/column', $o['attrs'], $children );
		$col->width_fraction = self::fraction( $o['width'] );
		$col->tablet_width   = $o['tablet'];
		$col->phone_width    = $o['phone'];
		return $col;
	}

	/**
	 * Flex row containing columns. Writes columnStructure/flexColumnStructure and
	 * each column's flexType so the Visual Builder shows the right layout.
	 *
	 * Widths must fill the row (sum = 1) or fill it several times (e.g. eight 1_4
	 * columns = two lines of four).
	 *
	 * @param Block|array|null ...$args Columns, then optional opts: attrs,
	 *                                  wrap_on (["tablet","phone"]), max_width, width, gap.
	 * @return Block
	 */
	public static function row( ...$args ) {
		list( $cols, $opts ) = self::split_args( __FUNCTION__, $args );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'attrs'     => null,
				'wrap_on'   => array( 'tablet', 'phone' ),
				'max_width' => null,
				'width'     => null,
				'gap'       => null,
			)
		);
		if ( ! $cols ) {
			throw new \InvalidArgumentException( 'row() needs at least one column' );
		}
		foreach ( $cols as $c ) {
			if ( 'divi/column' !== $c->name ) {
				throw new \InvalidArgumentException( 'row() children must be column() blocks' );
			}
		}
		$widths = array();
		foreach ( $cols as $c ) {
			$widths[] = null !== $c->width_fraction ? $c->width_fraction : array( 1, 1 );
		}
		$total = array( 0, 1 );
		foreach ( $widths as $w ) {
			$total = self::frac_add( $total, $w );
		}
		if ( 1 !== $total[1] ) {
			throw new \InvalidArgumentException(
				'Column widths add up to ' . self::frac_str( $total ) . '; they must fill the row (1, 2, 3... full lines). '
				. "For a narrower single column use column(..., attrs=module_deco(sizing=rv({'maxWidth': '700px'})))"
			);
		}
		$first_line = array();
		$acc        = array( 0, 1 );
		foreach ( $widths as $w ) {
			$first_line[] = $w;
			$acc          = self::frac_add( $acc, $w );
			if ( array( 1, 1 ) === $acc ) {
				break;
			}
		}
		$types = array();
		foreach ( $first_line as $w ) {
			$types[] = self::legacy_type( $w );
		}
		$structure = implode( ',', $types );
		$equal     = true;
		foreach ( $first_line as $w ) {
			if ( $w !== $first_line[0] ) {
				$equal = false;
			}
		}
		$flex_structure = $equal ? 'equal-columns_' . count( $first_line ) : null;

		$wrap_on = $o['wrap_on'];
		$multi   = count( $cols ) > 1;
		foreach ( $cols as $c ) {
			$frac   = null !== $c->width_fraction ? $c->width_fraction : array( 1, 1 );
			$tablet = $c->tablet_width;
			$phone  = $c->phone_width;
			if ( $multi && null === $phone && self::py_in( 'phone', $wrap_on ) ) {
				$phone = '1_1';
			}
			$sizing   = self::rv(
				array( 'flexType' => self::flex_type( $frac ) ),
				self::truthy( $tablet ) ? array( 'flexType' => self::flex_type( self::fraction( $tablet ) ) ) : null,
				self::truthy( $phone ) ? array( 'flexType' => self::flex_type( self::fraction( $phone ) ) ) : null
			);
			$c->attrs = self::merge(
				array(
					'module' => array(
						'advanced'   => array( 'type' => self::rv( self::legacy_type( $frac ) ) ),
						'decoration' => array( 'sizing' => $sizing ),
					),
				),
				$c->attrs
			);
		}

		$advanced = array( 'columnStructure' => self::rv( $structure ) );
		if ( null !== $flex_structure ) {
			$advanced['flexColumnStructure'] = self::rv( $flex_structure );
		}
		$desktop_layout = array( 'flexWrap' => self::frac_cmp( $total, array( 1, 1 ) ) > 0 ? 'wrap' : 'nowrap' );
		if ( self::truthy( $o['gap'] ) ) {
			$desktop_layout['columnGap'] = $o['gap'];
			$desktop_layout['rowGap']    = $o['gap'];
		}
		$layout = self::rv(
			$desktop_layout,
			( $multi && self::py_in( 'tablet', $wrap_on ) ) ? array( 'flexWrap' => 'wrap' ) : null,
			( $multi && self::py_in( 'phone', $wrap_on ) ) ? array( 'flexWrap' => 'wrap' ) : null
		);
		$base   = array(
			'module' => array(
				'advanced'   => $advanced,
				'decoration' => array( 'layout' => $layout ),
			),
		);
		$sizing_value = self::sizing_value( $o['width'], $o['max_width'] );
		if ( $sizing_value ) {
			$base['module']['decoration']['sizing'] = self::rv( $sizing_value );
		}
		return self::block( 'divi/row', self::merge( $base, $o['attrs'] ), $cols );
	}

	/**
	 * CSS-grid row: any number of columns (cards) flow into `cols` equal tracks
	 * (tablet/phone counts too). template="2fr 1fr" for manual tracks instead.
	 *
	 * @param Block|array|null ...$args Columns, then optional opts: cols (3), tablet (2),
	 *                                  phone (1), gap ("20px"), template, max_width, width, attrs.
	 * @return Block
	 */
	public static function grid_row( ...$args ) {
		list( $items, $opts ) = self::split_args( __FUNCTION__, $args );
		$o = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'cols'      => 3,
				'tablet'    => 2,
				'phone'     => 1,
				'gap'       => '20px',
				'template'  => null,
				'max_width' => null,
				'width'     => null,
				'attrs'     => null,
			)
		);
		foreach ( $items as $c ) {
			if ( 'divi/column' !== $c->name ) {
				throw new \InvalidArgumentException( 'grid_row() children must be column() blocks' );
			}
			$c->attrs = self::merge( array( 'module' => array( 'decoration' => array( 'sizing' => self::rv( array( 'flexType' => '24_24' ) ) ) ) ), $c->attrs );
		}
		$template = $o['template'];
		$tablet   = $o['tablet'];
		$phone    = $o['phone'];
		$desktop  = self::grid(
			array(
				'columns'  => self::truthy( $template ) ? null : $o['cols'],
				'template' => $template,
				'gap'      => $o['gap'],
			)
		);
		$tablet_v = ( self::truthy( $tablet ) && ! self::truthy( $template ) )
			? array(
				'gridColumnCount' => self::py_str( $tablet ),
				'gridAutoColumns' => self::py_str( $tablet ),
			)
			: null;
		$phone_v  = self::truthy( $phone )
			? array(
				'gridColumnCount'  => self::py_str( $phone ),
				'gridAutoColumns'  => self::py_str( $phone ),
				'gridColumnWidths' => self::truthy( $template ) ? 'equal' : 'auto',
			)
			: null;
		if ( self::truthy( $template ) && self::truthy( $phone ) ) {
			$phone_v = array(
				'gridColumnWidths' => 'equal',
				'gridColumnCount'  => self::py_str( $phone ),
				'gridAutoColumns'  => self::py_str( $phone ),
			);
		}
		$n            = count( $items );
		$base         = array(
			'module' => array(
				'advanced'   => array(
					'columnStructure'     => self::rv( $n > 1 ? implode( ',', array_fill( 0, $n, '1_' . $n ) ) : '4_4' ),
					'flexColumnStructure' => self::rv( self::grid_preset( $o['cols'] ) ),
				),
				'decoration' => array( 'layout' => self::rv( $desktop, $tablet_v, $phone_v ) ),
			),
		);
		$sizing_value = self::sizing_value( $o['width'], $o['max_width'] );
		if ( $sizing_value ) {
			$base['module']['decoration']['sizing'] = self::rv( $sizing_value );
		}
		return self::block( 'divi/row', self::merge( $base, $o['attrs'] ), $items );
	}

	/**
	 * Group: a flex (or grid) container for modules.
	 *
	 * layout = a full flex()/grid() value instead of the shortcut options.
	 * tablet / phone = layout overrides (e.g. {"flexDirection": "column"}).
	 *
	 * @param Block|array|null ...$args Children, then optional opts: attrs, direction, gap,
	 *                                  align, justify, wrap, width, layout, tablet, phone.
	 * @return Block
	 */
	public static function group( ...$args ) {
		list( $children, $opts ) = self::split_args( __FUNCTION__, $args );
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'attrs'     => null,
				'direction' => null,
				'gap'       => null,
				'align'     => null,
				'justify'   => null,
				'wrap'      => null,
				'width'     => null,
				'layout'    => null,
				'tablet'    => null,
				'phone'     => null,
			)
		);
		$value = self::truthy( $o['layout'] ) ? $o['layout'] : array();
		if ( ! self::truthy( $o['layout'] )
			&& ( null !== $o['direction'] || null !== $o['gap'] || null !== $o['align'] || null !== $o['justify'] || null !== $o['wrap'] ) ) {
			$value = self::flex(
				array(
					'direction' => $o['direction'],
					'justify'   => $o['justify'],
					'align'     => $o['align'],
					'gap'       => $o['gap'],
					'wrap'      => $o['wrap'],
				)
			);
		}
		$base = array();
		if ( self::truthy( $value ) || self::truthy( $o['tablet'] ) || self::truthy( $o['phone'] ) ) {
			$base = array( 'module' => array( 'decoration' => array( 'layout' => self::rv( self::truthy( $value ) ? $value : null, $o['tablet'], $o['phone'] ) ) ) );
		}
		if ( self::truthy( $o['width'] ) ) {
			$base = self::merge( $base, array( 'module' => array( 'decoration' => array( 'sizing' => self::rv( array( 'width' => $o['width'] ) ) ) ) ) );
		}
		return self::block( 'divi/group', self::merge( $base, $o['attrs'] ), $children );
	}

	// ----- leaf modules (self-closing) ---------------------------------- //

	/**
	 * Text module. `$html` is rich HTML (<p>, <h2>, <ul>, <a>...).
	 *
	 * @param string $html HTML.
	 * @param array  $opts attrs.
	 * @return Block
	 */
	public static function text( $html, array $opts = array() ) {
		$o = self::opts( __FUNCTION__, $opts, array( 'attrs' => null ) );
		return self::block( 'divi/text', self::merge( array( 'content' => array( 'innerContent' => self::rv( $html ) ) ), $o['attrs'] ) );
	}

	/**
	 * Heading module (plain text title, configurable tag).
	 *
	 * @param string $title Title.
	 * @param array  $opts  level ("h2"), attrs.
	 * @return Block
	 */
	public static function heading( $title, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'level' => 'h2',
				'attrs' => null,
			)
		);
		$base = array(
			'title' => array(
				'innerContent' => self::rv( $title ),
				'decoration'   => array( 'font' => array( 'font' => self::rv( array( 'headingLevel' => $o['level'] ) ) ) ),
			),
		);
		return self::block( 'divi/heading', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Button module.
	 *
	 * @param string $label Label.
	 * @param array  $opts  url (""), new_window (false), attrs.
	 * @return Block
	 */
	public static function button( $label, array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'url'        => '',
				'new_window' => false,
				'attrs'      => null,
			)
		);
		$inner = array( 'text' => $label );
		if ( self::truthy( $o['url'] ) ) {
			$inner['linkUrl'] = $o['url'];
		}
		if ( self::truthy( $o['new_window'] ) ) {
			$inner['linkTarget'] = 'on';
		}
		return self::block( 'divi/button', self::merge( array( 'button' => array( 'innerContent' => self::rv( $inner ) ) ), $o['attrs'] ) );
	}

	/**
	 * Image module.
	 *
	 * @param string $src  Image URL.
	 * @param array  $opts alt (""), title (""), url (""), attrs, attachment_id, width, height.
	 * @return Block
	 */
	public static function image( $src, array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'alt'           => '',
				'title'         => '',
				'url'           => '',
				'attrs'         => null,
				'attachment_id' => null,
				'width'         => null,
				'height'        => null,
			)
		);
		$inner = array(
			'src' => $src,
			'alt' => $o['alt'],
		);
		if ( self::truthy( $o['title'] ) ) {
			$inner['titleText'] = $o['title'];
		}
		if ( self::truthy( $o['url'] ) ) {
			$inner['linkUrl'] = $o['url'];
		}
		if ( self::truthy( $o['attachment_id'] ) ) {
			$inner['id'] = self::py_str( $o['attachment_id'] );
		}
		if ( self::truthy( $o['width'] ) ) {
			$inner['width'] = self::py_str( $o['width'] );
		}
		if ( self::truthy( $o['height'] ) ) {
			$inner['height'] = self::py_str( $o['height'] );
		}
		return self::block( 'divi/image', self::merge( array( 'image' => array( 'innerContent' => self::rv( $inner ) ) ), $o['attrs'] ) );
	}

	/**
	 * Blurb. icon = font-icon dict, e.g. fa_icon("&#xf0e0;"). Or image_src for an image.
	 *
	 * @param string $title Title.
	 * @param array  $opts  body_html (""), icon, image_src, url (""), attrs.
	 * @return Block
	 */
	public static function blurb( $title, array $opts = array() ) {
		$o           = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'body_html' => '',
				'icon'      => null,
				'image_src' => null,
				'url'       => '',
				'attrs'     => null,
			)
		);
		$title_inner = array( 'text' => $title );
		if ( self::truthy( $o['url'] ) ) {
			$title_inner['url'] = $o['url'];
		}
		$base = array( 'title' => array( 'innerContent' => self::rv( $title_inner ) ) );
		if ( self::truthy( $o['body_html'] ) ) {
			$base['content'] = array( 'innerContent' => self::rv( $o['body_html'] ) );
		}
		if ( self::truthy( $o['icon'] ) ) {
			$base['imageIcon'] = array(
				'innerContent' => self::rv(
					array(
						'useIcon' => 'on',
						'icon'    => $o['icon'],
					)
				),
			);
		} elseif ( self::truthy( $o['image_src'] ) ) {
			$base['imageIcon'] = array( 'innerContent' => self::rv( array( 'src' => $o['image_src'] ) ) );
		}
		return self::block( 'divi/blurb', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Link module: text + optional icon, styleable as a pill button.
	 *
	 * @param string $label Label.
	 * @param array  $opts  url (""), icon, icon_position ("after"), new_window (false), attrs.
	 * @return Block
	 */
	public static function link( $label, array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'url'           => '',
				'icon'          => null,
				'icon_position' => 'after',
				'new_window'    => false,
				'attrs'         => null,
			)
		);
		$inner = array( 'text' => $label );
		if ( self::truthy( $o['url'] ) ) {
			$inner['linkUrl'] = $o['url'];
		}
		$inner['linkTarget'] = self::truthy( $o['new_window'] ) ? 'on' : 'off';
		$base                = array( 'content' => array( 'innerContent' => self::rv( $inner ) ) );
		if ( self::truthy( $o['icon'] ) ) {
			$base['icon']   = array( 'innerContent' => self::rv( $o['icon'] ) );
			$base['module'] = array(
				'decoration' => array(
					'layout' => self::rv(
						array(
							'flexDirection'  => self::py_eq( $o['icon_position'], 'after' ) ? 'row-reverse' : 'row',
							'justifyContent' => 'start',
							'columnGap'      => '8px',
						)
					),
				),
			);
		}
		return self::block( 'divi/link', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Icon module. icon_value from fa_icon()/divi_icon().
	 *
	 * @param array|\stdClass $icon_value Icon reference.
	 * @param array           $opts       size, color, url (""), attrs.
	 * @return Block
	 */
	public static function icon( $icon_value, array $opts = array() ) {
		$o     = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'size'  => null,
				'color' => null,
				'url'   => '',
				'attrs' => null,
			)
		);
		$value = self::to_array( $icon_value );
		if ( self::truthy( $o['url'] ) ) {
			$value['url'] = $o['url'];
		}
		$base = array( 'icon' => array( 'innerContent' => self::rv( self::obj( $value ) ) ) );
		$adv  = array();
		if ( self::truthy( $o['size'] ) ) {
			$adv['size'] = self::rv( $o['size'] );
		}
		if ( self::truthy( $o['color'] ) ) {
			$adv['color'] = self::rv( $o['color'] );
		}
		if ( $adv ) {
			$base['icon']['advanced'] = $adv;
		}
		return self::block( 'divi/icon', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Breadcrumbs module (Home > parent > current page, resolved by WordPress).
	 *
	 * @param array $opts home_text ("Home"), separator (">"), home_url, color, link_color,
	 *                    hover_color, size ("13px"), attrs.
	 * @return Block
	 */
	public static function breadcrumbs( array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'home_text'   => 'Home',
				'separator'   => '>',
				'home_url'    => null,
				'color'       => null,
				'link_color'  => null,
				'hover_color' => null,
				'size'        => '13px',
				'attrs'       => null,
			)
		);
		$home = array( 'text' => $o['home_text'] );
		if ( self::truthy( $o['home_url'] ) ) {
			$home['url'] = $o['home_url'];
		}
		$base = array(
			'trail'     => array( 'advanced' => array( 'htmlTag' => self::rv( 'nav' ) ) ),
			'home'      => array( 'innerContent' => self::rv( $home ) ),
			'separator' => array( 'innerContent' => self::rv( array( 'text' => $o['separator'] ) ) ),
		);
		if ( self::truthy( $o['color'] ) ) {
			$base['breadcrumb'] = array(
				'decoration' => array(
					'font' => array(
						'font' => self::rv(
							array(
								'color' => $o['color'],
								'size'  => $o['size'],
							)
						),
					),
				),
			);
		}
		if ( self::truthy( $o['link_color'] ) ) {
			$link_font                         = array(
				'font' => array(
					'font' => self::rv(
						array(
							'color' => $o['link_color'],
							'size'  => $o['size'],
						),
						null,
						null,
						array( 'hover' => self::truthy( $o['hover_color'] ) ? array( 'color' => $o['hover_color'] ) : null )
					),
				),
			);
			$base['breadcrumbLink']            = array( 'decoration' => $link_font );
			$base['home']['decoration']        = $link_font;
			$base['separator']['decoration']   = array(
				'font' => array(
					'font' => self::rv(
						array(
							'color' => $o['link_color'],
							'size'  => $o['size'],
						)
					),
				),
			);
		}
		return self::block( 'divi/breadcrumbs', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Font Awesome icon reference. weight "900" = solid, "400" = regular.
	 *
	 * @param string $unicode HTML entity, e.g. "&#xf0e0;".
	 * @param string $weight  Weight.
	 * @return array
	 */
	public static function fa_icon( $unicode, $weight = '900' ) {
		return array(
			'unicode' => $unicode,
			'type'    => 'fa',
			'weight'  => $weight,
		);
	}

	/**
	 * Divi (ETmodules) icon reference.
	 *
	 * @param string $unicode HTML entity.
	 * @return array
	 */
	public static function divi_icon( $unicode ) {
		return array(
			'unicode' => $unicode,
			'type'    => 'divi',
			'weight'  => '400',
		);
	}

	/**
	 * Code module (raw HTML/shortcodes/<style>/<script>).
	 *
	 * @param string $html Code.
	 * @param array  $opts attrs.
	 * @return Block
	 */
	public static function code( $html, array $opts = array() ) {
		$o = self::opts( __FUNCTION__, $opts, array( 'attrs' => null ) );
		return self::block( 'divi/code', self::merge( array( 'content' => array( 'innerContent' => self::rv( $html ) ) ), $o['attrs'] ) );
	}

	/**
	 * Toggle module.
	 *
	 * @param string $title     Title.
	 * @param string $body_html Body HTML.
	 * @param array  $opts      open (false), level ("h3"), attrs.
	 * @return Block
	 */
	public static function toggle( $title, $body_html, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'open'  => false,
				'level' => 'h3',
				'attrs' => null,
			)
		);
		$base = array(
			'title'   => array(
				'innerContent' => self::rv( $title ),
				'decoration'   => array( 'font' => array( 'font' => self::rv( array( 'headingLevel' => $o['level'] ) ) ) ),
			),
			'content' => array( 'innerContent' => self::rv( $body_html ) ),
		);
		if ( self::truthy( $o['open'] ) ) {
			$base['module'] = array( 'advanced' => array( 'open' => self::rv( 'on' ) ) );
		}
		return self::block( 'divi/toggle', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Accordion with accordion_item() children.
	 *
	 * @param Block|array|null ...$args Items, then optional opts: attrs.
	 * @return Block
	 */
	public static function accordion( ...$args ) {
		list( $items, $opts ) = self::split_args( __FUNCTION__, $args );
		$o = self::opts( __FUNCTION__, $opts, array( 'attrs' => null ) );
		return self::block( 'divi/accordion', $o['attrs'], $items );
	}

	/**
	 * Accordion item.
	 *
	 * @param string $title     Title.
	 * @param string $body_html Body HTML.
	 * @param array  $opts      open (false), attrs.
	 * @return Block
	 */
	public static function accordion_item( $title, $body_html, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'open'  => false,
				'attrs' => null,
			)
		);
		$base = array(
			'title'   => array( 'innerContent' => self::rv( $title ) ),
			'content' => array( 'innerContent' => self::rv( $body_html ) ),
		);
		if ( self::truthy( $o['open'] ) ) {
			$base['module'] = array( 'advanced' => array( 'open' => self::rv( 'on' ) ) );
		}
		return self::block( 'divi/accordion-item', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Number counter module.
	 *
	 * @param mixed  $number Number (stringified like Python str()).
	 * @param string $title  Title.
	 * @param array  $opts   percent_sign (false), attrs.
	 * @return Block
	 */
	public static function number_counter( $number, $title, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'percent_sign' => false,
				'attrs'        => null,
			)
		);
		$base = array(
			'number' => array(
				'innerContent' => self::rv( self::py_str( $number ) ),
				'advanced'     => array( 'enablePercentSign' => self::rv( self::truthy( $o['percent_sign'] ) ? 'on' : 'off' ) ),
			),
			'title'  => array( 'innerContent' => self::rv( $title ) ),
		);
		return self::block( 'divi/number-counter', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Divider module.
	 *
	 * @param array $opts color, weight, attrs.
	 * @return Block
	 */
	public static function divider( array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'color'  => null,
				'weight' => null,
				'attrs'  => null,
			)
		);
		$line = array( 'show' => 'on' );
		if ( self::truthy( $o['color'] ) ) {
			$line['color'] = $o['color'];
		}
		if ( self::truthy( $o['weight'] ) ) {
			$line['weight'] = $o['weight'];
		}
		return self::block( 'divi/divider', self::merge( array( 'divider' => array( 'advanced' => array( 'line' => self::rv( $line ) ) ) ), $o['attrs'] ) );
	}

	/**
	 * Call To Action module (title, text, button in one box).
	 *
	 * @param string $title Title.
	 * @param array  $opts  body_html (""), button_label (""), button_url (""), attrs.
	 * @return Block
	 */
	public static function cta( $title, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'body_html'    => '',
				'button_label' => '',
				'button_url'   => '',
				'attrs'        => null,
			)
		);
		$base = array( 'title' => array( 'innerContent' => self::rv( $title ) ) );
		if ( self::truthy( $o['body_html'] ) ) {
			$base['content'] = array( 'innerContent' => self::rv( $o['body_html'] ) );
		}
		if ( self::truthy( $o['button_label'] ) ) {
			$inner = array( 'text' => $o['button_label'] );
			if ( self::truthy( $o['button_url'] ) ) {
				$inner['linkUrl'] = $o['button_url'];
			}
			$base['button'] = array( 'innerContent' => self::rv( $inner ) );
		}
		return self::block( 'divi/cta', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Testimonial. portrait is an image URL (stored as portrait.innerContent.src).
	 *
	 * @param string $author    Author.
	 * @param string $body_html Quote HTML.
	 * @param array  $opts      job_title, company, company_url, portrait (all ""), attrs.
	 * @return Block
	 */
	public static function testimonial( $author, $body_html, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'job_title'   => '',
				'company'     => '',
				'company_url' => '',
				'portrait'    => '',
				'attrs'       => null,
			)
		);
		$base = array(
			'author'  => array( 'innerContent' => self::rv( $author ) ),
			'content' => array( 'innerContent' => self::rv( $body_html ) ),
		);
		if ( self::truthy( $o['job_title'] ) ) {
			$base['jobTitle'] = array( 'innerContent' => self::rv( $o['job_title'] ) );
		}
		if ( self::truthy( $o['company'] ) ) {
			$inner = array( 'text' => $o['company'] );
			if ( self::truthy( $o['company_url'] ) ) {
				$inner['linkUrl'] = $o['company_url'];
			}
			$base['company'] = array( 'innerContent' => self::rv( $inner ) );
		}
		if ( self::truthy( $o['portrait'] ) ) {
			$base['portrait'] = array(
				'innerContent' => self::rv(
					array(
						'src' => $o['portrait'],
						'alt' => $author,
					)
				),
			);
		}
		return self::block( 'divi/testimonial', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Team member. social: {"facebookUrl": ..., "linkedinUrl": ..., "twitterUrl": ...}.
	 *
	 * @param string $name Name.
	 * @param array  $opts position, body_html, image_url (all ""), social, attrs.
	 * @return Block
	 */
	public static function team_member( $name, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'position'  => '',
				'body_html' => '',
				'image_url' => '',
				'social'    => null,
				'attrs'     => null,
			)
		);
		$base = array( 'name' => array( 'innerContent' => self::rv( $name ) ) );
		if ( self::truthy( $o['position'] ) ) {
			$base['position'] = array( 'innerContent' => self::rv( $o['position'] ) );
		}
		if ( self::truthy( $o['body_html'] ) ) {
			$base['content'] = array( 'innerContent' => self::rv( $o['body_html'] ) );
		}
		if ( self::truthy( $o['image_url'] ) ) {
			$base['image'] = array( 'innerContent' => self::rv( array( 'url' => $o['image_url'] ) ) );
		}
		if ( self::truthy( $o['social'] ) ) {
			$base['social'] = array( 'innerContent' => self::rv( self::to_array( $o['social'] ) ) );
		}
		return self::block( 'divi/team-member', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Contact form field. type: input | email | text (textarea) | checkbox | radio | select.
	 * half=true puts it at 50% width on tablet/desktop (full width on phone).
	 *
	 * @param string $field_id Field id.
	 * @param string $label    Label.
	 * @param array  $opts     type ("input"), required (true), half (false), attrs.
	 * @return Block
	 */
	public static function contact_field( $field_id, $label, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'type'     => 'input',
				'required' => true,
				'half'     => false,
				'attrs'    => null,
			)
		);
		$base = array(
			'fieldItem' => array(
				'innerContent' => self::rv( $label ),
				'advanced'     => array(
					'id'       => self::rv( $field_id ),
					'type'     => self::rv( $o['type'] ),
					'required' => self::rv( self::truthy( $o['required'] ) ? 'on' : 'off' ),
				),
			),
		);
		if ( self::truthy( $o['half'] ) ) {
			$base['module'] = array( 'decoration' => array( 'sizing' => self::rv( array( 'flexType' => '12_24' ), null, array( 'flexType' => '24_24' ) ) ) );
		}
		return self::block( 'divi/contact-field', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Contact form with contact_field() children. email = recipient (empty = site admin).
	 * unique_key makes the form's uniqueId stable (uuid5 in the URL namespace); without
	 * it a random uuid4 is used.
	 *
	 * @param Block|array|null ...$args Fields, then optional opts: email (""), title (""),
	 *                                  submit ("Submit"), success (""), unique_key, attrs.
	 * @return Block
	 */
	public static function contact_form( ...$args ) {
		list( $fields, $opts ) = self::split_args( __FUNCTION__, $args );
		$o          = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'email'      => '',
				'title'      => '',
				'submit'     => 'Submit',
				'success'    => '',
				'unique_key' => null,
				'attrs'      => null,
			)
		);
		$module_adv = array( 'uniqueId' => self::rv( self::truthy( $o['unique_key'] ) ? self::uuid5_url( $o['unique_key'] ) : self::uuid4() ) );
		if ( self::truthy( $o['success'] ) ) {
			$module_adv['successMessage'] = self::rv( $o['success'] );
		}
		$base = array(
			'module' => array( 'advanced' => $module_adv ),
			'button' => array( 'innerContent' => self::rv( array( 'text' => $o['submit'] ) ) ),
		);
		if ( self::truthy( $o['title'] ) ) {
			$base['title'] = array( 'innerContent' => self::rv( $o['title'] ) );
		}
		if ( self::truthy( $o['email'] ) ) {
			$base['email'] = array( 'advanced' => array( 'receiver' => self::rv( $o['email'] ) ) );
		}
		return self::block( 'divi/contact-form', self::merge( $base, $o['attrs'] ), $fields );
	}

	/**
	 * Timeline with timeline_item() children. position: right | left | alternating.
	 *
	 * @param Block|array|null ...$args Items, then optional opts: direction ("vertical"),
	 *                                  position ("alternating"), attrs.
	 * @return Block
	 */
	public static function timeline( ...$args ) {
		list( $items, $opts ) = self::split_args( __FUNCTION__, $args );
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'direction' => 'vertical',
				'position'  => 'alternating',
				'attrs'     => null,
			)
		);
		$base = array(
			'module' => array(
				'advanced' => array(
					'timeline' => self::rv(
						array(
							'direction' => $o['direction'],
							'position'  => $o['position'],
						)
					),
				),
			),
		);
		return self::block( 'divi/timeline', self::merge( $base, $o['attrs'] ), $items );
	}

	/**
	 * Timeline item.
	 *
	 * @param string $date  Date label.
	 * @param string $title Title.
	 * @param array  $opts  body_html (""), icon, attrs.
	 * @return Block
	 */
	public static function timeline_item( $date, $title, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'body_html' => '',
				'icon'      => null,
				'attrs'     => null,
			)
		);
		$base = array(
			'date'  => array( 'innerContent' => self::rv( $date ) ),
			'title' => array( 'innerContent' => self::rv( $title ) ),
		);
		if ( self::truthy( $o['body_html'] ) ) {
			$base['content'] = array( 'innerContent' => self::rv( $o['body_html'] ) );
		}
		if ( self::truthy( $o['icon'] ) ) {
			$base['marker'] = array( 'innerContent' => self::rv( $o['icon'] ) );
		}
		return self::block( 'divi/timeline-item', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Icon list (checklist-style) with icon_list_item() children.
	 *
	 * @param Block|array|null ...$args Items, then optional opts: color, size, attrs.
	 * @return Block
	 */
	public static function icon_list( ...$args ) {
		list( $items, $opts ) = self::split_args( __FUNCTION__, $args );
		$o   = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'color' => null,
				'size'  => null,
				'attrs' => null,
			)
		);
		$adv = array();
		if ( self::truthy( $o['color'] ) ) {
			$adv['color'] = self::rv( $o['color'] );
		}
		if ( self::truthy( $o['size'] ) ) {
			$adv['size'] = self::rv( $o['size'] );
		}
		$base = $adv ? array( 'icon' => array( 'advanced' => $adv ) ) : array();
		return self::block( 'divi/icon-list', self::merge( $base, $o['attrs'] ), $items );
	}

	/**
	 * Icon list item (default icon: Font Awesome check).
	 *
	 * @param string $label Label.
	 * @param array  $opts  icon, attrs.
	 * @return Block
	 */
	public static function icon_list_item( $label, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'icon'  => null,
				'attrs' => null,
			)
		);
		$base = array(
			'content' => array( 'innerContent' => self::rv( $label ) ),
			'icon'    => array( 'innerContent' => self::rv( self::truthy( $o['icon'] ) ? $o['icon'] : self::fa_icon( '&#xf00c;' ) ) ),
		);
		return self::block( 'divi/icon-list-item', self::merge( $base, $o['attrs'] ) );
	}

	/**
	 * Social Media Follow with social_network() children.
	 *
	 * @param Block|array|null ...$args Networks, then optional opts: attrs.
	 * @return Block
	 */
	public static function social_follow( ...$args ) {
		list( $networks, $opts ) = self::split_args( __FUNCTION__, $args );
		$o = self::opts( __FUNCTION__, $opts, array( 'attrs' => null ) );
		return self::block( 'divi/social-media-follow', $o['attrs'], $networks );
	}

	/**
	 * Social network. network: facebook, twitter, instagram, linkedin, youtube, tiktok, pinterest, ...
	 *
	 * @param string $network Network slug.
	 * @param string $url     Profile URL.
	 * @param array  $opts    label (defaults to the network), attrs.
	 * @return Block
	 */
	public static function social_network( $network, $url, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'label' => null,
				'attrs' => null,
			)
		);
		$base = array(
			'socialNetwork' => array(
				'innerContent' => self::rv(
					array(
						'title' => $network,
						'link'  => $url,
						'label' => self::truthy( $o['label'] ) ? $o['label'] : $network,
					)
				),
			),
		);
		return self::block( 'divi/social-media-follow-network', self::merge( $base, $o['attrs'] ) );
	}

	// ------------------------------------------------------------------ //
	// Serialization
	// ------------------------------------------------------------------ //

	/**
	 * Encode attrs exactly like WordPress serialize_block_attributes() / divi5.py:
	 * compact JSON, unicode kept, and < > & " -- escaped as \uXXXX inside strings
	 * so the JSON can never close the HTML comment that wraps it.
	 *
	 * @param mixed $value Value.
	 * @return string
	 * @throws \InvalidArgumentException For values that are not JSON-encodable.
	 */
	public static function encode_attrs( $value ) {
		if ( $value instanceof \stdClass ) {
			return self::encode_object( get_object_vars( $value ) );
		}
		if ( is_array( $value ) ) {
			if ( self::is_list( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					$parts[] = self::encode_attrs( $item );
				}
				return '[' . implode( ',', $parts ) . ']';
			}
			return self::encode_object( $value );
		}
		if ( is_string( $value ) ) {
			return self::encode_str( $value );
		}
		if ( true === $value ) {
			return 'true';
		}
		if ( false === $value ) {
			return 'false';
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) ) {
			return self::float_repr( $value, true );
		}
		throw new \InvalidArgumentException( 'Cannot encode ' . ( is_object( $value ) ? get_class( $value ) : gettype( $value ) ) . ' in block attributes' );
	}

	/**
	 * Serialize a block or list of blocks to Divi 5 block markup (no placeholder wrapper).
	 *
	 * @param Block|Block[] $blocks Blocks.
	 * @return string
	 */
	public static function serialize( $blocks ) {
		if ( $blocks instanceof Block ) {
			$blocks = array( $blocks );
		}
		if ( ! is_array( $blocks ) ) {
			throw new \InvalidArgumentException( 'serialize() takes a Block or a list of Blocks' );
		}
		$parts = array();
		foreach ( $blocks as $b ) {
			if ( ! $b instanceof Block ) {
				throw new \InvalidArgumentException( 'serialize() takes a Block or a list of Blocks' );
			}
			$attrs = $b->attrs ? ' ' . self::encode_object( $b->attrs ) : '';
			if ( null === $b->children ) {
				$parts[] = "<!-- wp:{$b->name}{$attrs} /-->";
			} else {
				$inner   = self::serialize( $b->children );
				$parts[] = '' !== $inner
					? "<!-- wp:{$b->name}{$attrs} -->\n{$inner}\n<!-- /wp:{$b->name} -->"
					: "<!-- wp:{$b->name}{$attrs} -->\n<!-- /wp:{$b->name} -->";
			}
		}
		return implode( "\n", $parts );
	}

	/**
	 * Full post_content string, wrapped in divi/placeholder like Divi 5 exports.
	 *
	 * @param Block|Block[] $blocks Blocks.
	 * @return string
	 */
	public static function post_content( $blocks ) {
		return '<!-- wp:divi/placeholder -->' . self::serialize( $blocks ) . '<!-- /wp:divi/placeholder -->';
	}

	// ------------------------------------------------------------------ //
	// Global colors / variables (for the export envelope)
	// ------------------------------------------------------------------ //

	/**
	 * Entry for the `global_colors` list. gcid must start with "gcid-".
	 *
	 * @param string $gcid  Id.
	 * @param string $color Color.
	 * @param string $label Label.
	 * @return array
	 */
	public static function global_color( $gcid, $color, $label ) {
		if ( ! is_string( $gcid ) || 0 !== strpos( $gcid, 'gcid-' ) ) {
			throw new \InvalidArgumentException( "Global color ids start with 'gcid-'" );
		}
		return array(
			$gcid,
			array(
				'color'  => $color,
				'status' => 'active',
				'label'  => $label,
			),
		);
	}

	/**
	 * Entry for `global_variables` (same keys Divi 5.13+ exports write).
	 * var_type: numbers | strings | images | links | fonts | colors.
	 *
	 * @param string $gvid     Id.
	 * @param mixed  $value    Value.
	 * @param string $label    Label.
	 * @param string $var_type Type.
	 * @return array
	 */
	public static function global_variable( $gvid, $value, $label, $var_type = 'numbers' ) {
		if ( ! is_string( $gvid ) || 0 !== strpos( $gvid, 'gvid-' ) ) {
			throw new \InvalidArgumentException( "Global variable ids start with 'gvid-'" );
		}
		return array(
			'id'     => $gvid,
			'label'  => $label,
			'value'  => $value,
			'status' => 'active',
			'type'   => $var_type,
		);
	}

	// ------------------------------------------------------------------ //
	// Images
	// ------------------------------------------------------------------ //

	/**
	 * Collect image URLs referenced in block attributes ("src"/"url" keys), in order, unique.
	 *
	 * @param Block|array $blocks Block or list of blocks.
	 * @return string[]
	 */
	public static function find_image_urls( $blocks ) {
		$found = array();
		$nodes = ( is_array( $blocks ) && self::is_list( $blocks ) ) ? $blocks : array( $blocks );
		foreach ( $nodes as $node ) {
			self::walk_images( $node, $found );
		}
		return array_values( array_unique( $found ) );
	}

	// ------------------------------------------------------------------ //
	// Export envelope
	// ------------------------------------------------------------------ //

	/**
	 * Build the array Divi's Portability importer expects (context "et_builder").
	 *
	 * @param Block|Block[] $blocks Blocks.
	 * @param array         $opts   post_id (1), global_colors, global_variables, images,
	 *                              custom_css (""), presets.
	 * @return array
	 */
	public static function layout_export( $blocks, array $opts = array() ) {
		$o    = self::opts(
			__FUNCTION__,
			$opts,
			array(
				'post_id'          => 1,
				'global_colors'    => null,
				'global_variables' => null,
				'images'           => null,
				'custom_css'       => '',
				'presets'          => null,
			)
		);
		$data = array( self::py_str( $o['post_id'] ) => self::post_content( $blocks ) );
		if ( self::is_list( $data ) ) {
			$data = (object) $data; // post_id 0 must still encode as a JSON object.
		}
		return array(
			'context'            => 'et_builder',
			'data'               => $data,
			'presets'            => $o['presets'],
			'global_colors'      => self::truthy( $o['global_colors'] ) ? $o['global_colors'] : array(),
			'global_variables'   => self::truthy( $o['global_variables'] ) ? $o['global_variables'] : array(),
			'page_settings_meta' => array( '_et_pb_custom_css' => array( $o['custom_css'] ) ),
			'canvases'           => array(
				'local'  => array(),
				'global' => array(),
			),
			'images'             => self::truthy( $o['images'] ) ? $o['images'] : array(),
			'thumbnails'         => array(),
		);
	}

	// ------------------------------------------------------------------ //
	// Ids
	// ------------------------------------------------------------------ //

	/**
	 * Python's str(uuid.uuid5(uuid.NAMESPACE_URL, $name)).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function uuid5_url( $name ) {
		if ( ! is_string( $name ) ) {
			throw new \InvalidArgumentException( 'unique_key must be a string' );
		}
		$hash = substr( sha1( hex2bin( self::NAMESPACE_URL_HEX ) . $name, true ), 0, 16 );
		return self::format_uuid( $hash, 5 );
	}

	/**
	 * Random version 4 UUID (same format as Python's str(uuid.uuid4())).
	 *
	 * @return string
	 */
	public static function uuid4() {
		return self::format_uuid( random_bytes( 16 ), 4 );
	}

	// ------------------------------------------------------------------ //
	// Internals
	// ------------------------------------------------------------------ //

	/**
	 * Set version and RFC 4122 variant bits on 16 bytes and format them.
	 *
	 * @param string $bytes   16 raw bytes.
	 * @param int    $version Version.
	 * @return string
	 */
	private static function format_uuid( $bytes, $version ) {
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | ( $version << 4 ) );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
	}

	/**
	 * Recursive helper for find_image_urls().
	 *
	 * @param mixed    $node  Node.
	 * @param string[] $found Collected URLs.
	 * @return void
	 */
	private static function walk_images( $node, array &$found ) {
		if ( $node instanceof Block ) {
			self::walk_images( $node->attrs, $found );
			foreach ( (array) $node->children as $child ) {
				self::walk_images( $child, $found );
			}
			return;
		}
		if ( $node instanceof \stdClass ) {
			$node = get_object_vars( $node );
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( ( 'src' === $key || 'url' === $key ) && is_string( $value )
				&& preg_match( '/^https?:\/\/.+\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/iu', $value ) ) {
				$found[] = $value;
			} else {
				self::walk_images( $value, $found );
			}
		}
	}

	/**
	 * Split variadic args into child blocks and a trailing options array.
	 *
	 * @param string $fn   Helper name.
	 * @param array  $args Args.
	 * @return array{0: Block[], 1: array}
	 */
	private static function split_args( $fn, array $args ) {
		$opts = array();
		if ( $args && is_array( end( $args ) ) ) {
			$opts = array_pop( $args );
		}
		$children = array();
		foreach ( $args as $arg ) {
			if ( null === $arg ) {
				continue;
			}
			if ( ! $arg instanceof Block ) {
				throw new \InvalidArgumentException( "D5::{$fn}(): children must be Block objects; options go in a trailing array" );
			}
			$children[] = $arg;
		}
		return array( $children, $opts );
	}

	/**
	 * Validate option keys and fill defaults.
	 *
	 * @param string $fn       Helper name.
	 * @param array  $opts     Given options.
	 * @param array  $defaults Defaults (also the allowed keys).
	 * @return array
	 */
	private static function opts( $fn, array $opts, array $defaults ) {
		foreach ( $opts as $key => $unused ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				throw new \InvalidArgumentException( "D5::{$fn}(): unknown option '{$key}'" );
			}
		}
		$out = array();
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
		}
		return $out;
	}

	/**
	 * {"style": "preset1", **$shadow}.
	 *
	 * @param array|\stdClass $shadow Shadow keys.
	 * @return array
	 */
	private static function with_preset( $shadow ) {
		$out = array( 'style' => 'preset1' );
		foreach ( self::to_array( $shadow ) as $key => $value ) {
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Row sizing value from width / max_width.
	 *
	 * @param mixed $width     Width.
	 * @param mixed $max_width Max width.
	 * @return array
	 */
	private static function sizing_value( $width, $max_width ) {
		$value = array();
		if ( self::truthy( $width ) ) {
			$value['width'] = $width;
		}
		if ( self::truthy( $max_width ) ) {
			$value['maxWidth'] = $max_width;
		}
		return $value;
	}

	/**
	 * _GRID_PRESETS.get(cols, "css-grid-grids_5").
	 *
	 * @param mixed $cols Column count.
	 * @return string
	 */
	private static function grid_preset( $cols ) {
		if ( is_bool( $cols ) ) {
			$cols = (int) $cols;
		} elseif ( is_float( $cols ) && floor( $cols ) === $cols && abs( $cols ) < PHP_INT_MAX ) {
			$cols = (int) $cols;
		}
		return ( is_int( $cols ) && isset( self::GRID_PRESETS[ $cols ] ) ) ? self::GRID_PRESETS[ $cols ] : 'css-grid-grids_5';
	}

	/**
	 * Python `needle in haystack` for wrap_on (list/tuple membership or substring).
	 *
	 * @param string $needle   Needle.
	 * @param mixed  $haystack Haystack.
	 * @return bool
	 */
	private static function py_in( $needle, $haystack ) {
		if ( is_string( $haystack ) ) {
			return false !== strpos( $haystack, $needle );
		}
		if ( is_array( $haystack ) ) {
			return in_array( $needle, $haystack, true );
		}
		throw new \InvalidArgumentException( 'wrap_on must be a list of breakpoints' );
	}

	// ----- fractions ---------------------------------------------------- //

	/**
	 * Parse "1_2" / "1/2" (or an [n, d] pair) into a reduced fraction.
	 *
	 * @param mixed $value Value.
	 * @return int[] [numerator, denominator], denominator > 0.
	 */
	private static function fraction( $value ) {
		if ( is_array( $value ) && 2 === count( $value ) && is_int( $value[0] ?? null ) && is_int( $value[1] ?? null ) ) {
			return self::frac_make( $value[0], $value[1] );
		}
		$text  = str_replace( '/', '_', self::py_str( $value ) );
		$parts = explode( '_', $text );
		if ( 2 !== count( $parts ) ) {
			throw new \InvalidArgumentException( 'Invalid column width ' . self::py_repr( $value ) . ' (use e.g. "1_2")' );
		}
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^\s*[+-]?\d+\s*$/', $part ) ) {
				throw new \InvalidArgumentException( 'Invalid column width ' . self::py_repr( $value ) . ' (use e.g. "1_2")' );
			}
		}
		return self::frac_make( (int) trim( $parts[0] ), (int) trim( $parts[1] ) );
	}

	/**
	 * @param int $num Numerator.
	 * @param int $den Denominator.
	 * @return int[]
	 */
	private static function frac_make( $num, $den ) {
		if ( 0 === $den ) {
			throw new \InvalidArgumentException( 'Column width denominator is zero' );
		}
		if ( $den < 0 ) {
			$num = -$num;
			$den = -$den;
		}
		$g = self::gcd( abs( $num ), $den );
		return array( intdiv( $num, $g ), intdiv( $den, $g ) );
	}

	/**
	 * @param int $a A.
	 * @param int $b B.
	 * @return int
	 */
	private static function gcd( $a, $b ) {
		while ( 0 !== $b ) {
			$t = $a % $b;
			$a = $b;
			$b = $t;
		}
		return 0 === $a ? 1 : $a;
	}

	/**
	 * @param int[] $a A.
	 * @param int[] $b B.
	 * @return int[]
	 */
	private static function frac_add( array $a, array $b ) {
		return self::frac_make( $a[0] * $b[1] + $b[0] * $a[1], $a[1] * $b[1] );
	}

	/**
	 * @param int[] $a A.
	 * @param int[] $b B.
	 * @return int -1, 0 or 1.
	 */
	private static function frac_cmp( array $a, array $b ) {
		return $a[0] * $b[1] <=> $b[0] * $a[1];
	}

	/**
	 * str(Fraction): "5/6" or "2".
	 *
	 * @param int[] $f Fraction.
	 * @return string
	 */
	private static function frac_str( array $f ) {
		return 1 === $f[1] ? (string) $f[0] : $f[0] . '/' . $f[1];
	}

	/**
	 * Flex column type in 24ths when exact, else "n_d" (Divi accepts e.g. "3_5").
	 *
	 * @param int[] $f Fraction.
	 * @return string
	 */
	private static function flex_type( array $f ) {
		$units = self::frac_make( $f[0] * 24, $f[1] );
		if ( 1 === $units[1] ) {
			return $units[0] . '_24';
		}
		return $f[0] . '_' . $f[1];
	}

	/**
	 * Legacy column type ("4_4" for full width).
	 *
	 * @param int[] $f Fraction.
	 * @return string
	 */
	private static function legacy_type( array $f ) {
		return array( 1, 1 ) === $f ? '4_4' : $f[0] . '_' . $f[1];
	}

	// ----- Python semantics -------------------------------------------- //

	/**
	 * array_is_list() polyfill (PHP < 8.1).
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	public static function is_list( array $value ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}
		$i = 0;
		foreach ( $value as $key => $unused ) {
			if ( $key !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	/**
	 * Is the value a JSON object (Python dict)? \stdClass, or a non-empty non-list array.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_dict( $value ) {
		return $value instanceof \stdClass || ( is_array( $value ) && array() !== $value && ! self::is_list( $value ) );
	}

	/**
	 * Dict as an array (stdClass -> array); other values unchanged.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function to_array( $value ) {
		return $value instanceof \stdClass ? get_object_vars( $value ) : $value;
	}

	/**
	 * Empty array -> \stdClass (an empty JSON object); anything else unchanged.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function obj( $value ) {
		return array() === $value ? new \stdClass() : $value;
	}

	/**
	 * Python truthiness.
	 *
	 * @internal Shared with Patterns; not part of the builder API.
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( '0' === $value ) {
			return true;
		}
		if ( $value instanceof \stdClass ) {
			return (bool) get_object_vars( $value );
		}
		if ( is_object( $value ) ) {
			return true;
		}
		return (bool) $value;
	}

	/**
	 * Python ==: numbers (and bools) compare by value, everything else strictly.
	 *
	 * @param mixed $a A.
	 * @param mixed $b B.
	 * @return bool
	 */
	private static function py_eq( $a, $b ) {
		$num_a = is_int( $a ) || is_float( $a ) || is_bool( $a );
		$num_b = is_int( $b ) || is_float( $b ) || is_bool( $b );
		if ( $num_a && $num_b ) {
			return (float) $a === (float) $b;
		}
		return $a === $b;
	}

	/**
	 * Python str() for scalars.
	 *
	 * @internal Shared with Patterns; not part of the builder API.
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function py_str( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( true === $value ) {
			return 'True';
		}
		if ( false === $value ) {
			return 'False';
		}
		if ( null === $value ) {
			return 'None';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) ) {
			return self::float_repr( $value, false );
		}
		throw new \InvalidArgumentException( 'Expected a scalar, got ' . ( is_object( $value ) ? get_class( $value ) : gettype( $value ) ) );
	}

	/**
	 * Short repr for error messages.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function py_repr( $value ) {
		return is_string( $value ) ? "'" . $value . "'" : ( is_scalar( $value ) || null === $value ? self::py_str( $value ) : gettype( $value ) );
	}

	/**
	 * Python float repr (json.dumps when $json): shortest round-trip digits,
	 * exponent form when exp < -4 or exp >= 16.
	 *
	 * @param float $value Value.
	 * @param bool  $json  JSON spelling for NaN/Infinity.
	 * @return string
	 */
	private static function float_repr( $value, $json ) {
		if ( is_nan( $value ) ) {
			return $json ? 'NaN' : 'nan';
		}
		if ( is_infinite( $value ) ) {
			if ( $json ) {
				return $value > 0 ? 'Infinity' : '-Infinity';
			}
			return $value > 0 ? 'inf' : '-inf';
		}
		$negative = ( ord( pack( 'E', $value )[0] ) & 0x80 ) !== 0;
		$abs      = abs( $value );
		if ( 0.0 === $abs ) {
			return $negative ? '-0.0' : '0.0';
		}
		// Shortest digit string that round-trips. The correctly rounded n-digit string
		// is tried first; at powers of two the round-trip interval is asymmetric, so
		// its neighbours (last digit +/- 1) can round-trip when it does not.
		$digits   = '';
		$exponent = 0;
		for ( $precision = 0; $precision <= 16; $precision++ ) {
			$s = sprintf( '%.' . $precision . 'e', $abs );
			if ( ! preg_match( '/^(\d)(?:\.(\d*))?e([+-]?\d+)$/', $s, $m ) ) {
				throw new \RuntimeException( 'Unexpected float format: ' . $s );
			}
			$candidate = $m[1] . ( isset( $m[2] ) ? $m[2] : '' );
			$exp       = (int) $m[3];
			$found     = null;
			if ( (float) $s === $abs || 16 === $precision ) {
				$found = array( $candidate, $exp );
			} else {
				foreach ( array( 1, -1 ) as $delta ) {
					$near = self::digits_step( $candidate, $exp, $delta );
					if ( $near && (float) ( $near[0][0] . '.' . substr( $near[0], 1 ) . 'e' . $near[1] ) === $abs ) {
						$found = $near;
						break;
					}
				}
			}
			if ( $found ) {
				$digits   = rtrim( $found[0], '0' );
				$digits   = '' === $digits ? '0' : $digits;
				$exponent = $found[1];
				break;
			}
		}
		$len = strlen( $digits );
		if ( $exponent >= -4 && $exponent < 16 ) {
			if ( $exponent >= 0 ) {
				if ( $len <= $exponent + 1 ) {
					$out = $digits . str_repeat( '0', $exponent + 1 - $len ) . '.0';
				} else {
					$out = substr( $digits, 0, $exponent + 1 ) . '.' . substr( $digits, $exponent + 1 );
				}
			} else {
				$out = '0.' . str_repeat( '0', -$exponent - 1 ) . $digits;
			}
		} else {
			$out = $digits[0] . ( $len > 1 ? '.' . substr( $digits, 1 ) : '' )
				. 'e' . ( $exponent < 0 ? '-' : '+' ) . sprintf( '%02d', abs( $exponent ) );
		}
		return ( $negative ? '-' : '' ) . $out;
	}

	/**
	 * Add $delta (+1/-1) to the last digit of a scientific digit string, keeping its length.
	 *
	 * @param string $digits   Digits (first one non-zero).
	 * @param int    $exponent Decimal exponent of the first digit.
	 * @param int    $delta    +1 or -1.
	 * @return array{0: string, 1: int}|null Null when the result would change length downwards.
	 */
	private static function digits_step( $digits, $exponent, $delta ) {
		$chars = str_split( $digits );
		$i     = count( $chars ) - 1;
		while ( $i >= 0 ) {
			$d = (int) $chars[ $i ] + $delta;
			if ( $d >= 0 && $d <= 9 ) {
				$chars[ $i ] = (string) $d;
				break;
			}
			$chars[ $i ] = $delta > 0 ? '0' : '9';
			--$i;
		}
		if ( $i < 0 ) {
			if ( $delta < 0 ) {
				return null;
			}
			// 99..9 + 1 = 100..0: one more power of ten, same digit count.
			array_unshift( $chars, '1' );
			array_pop( $chars );
			++$exponent;
		}
		if ( '0' === $chars[0] ) {
			return null;
		}
		return array( implode( '', $chars ), $exponent );
	}

	/**
	 * Encode an array as a JSON object (keys stringified).
	 *
	 * @param array $value Array.
	 * @return string
	 */
	private static function encode_object( array $value ) {
		$parts = array();
		foreach ( $value as $key => $item ) {
			$parts[] = self::encode_str( (string) $key ) . ':' . self::encode_attrs( $item );
		}
		return '{' . implode( ',', $parts ) . '}';
	}

	/**
	 * divi5.py _encode_str(): JSON string with " \ < > & and control chars escaped,
	 * then every "--" replaced by --. Non-ASCII is kept as is.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private static function encode_str( $s ) {
		if ( null === self::$escape_map ) {
			$map = array();
			for ( $i = 0; $i < 0x20; $i++ ) {
				$map[ chr( $i ) ] = sprintf( '\\u%04x', $i );
			}
			$map["\x08"]      = '\\b';
			$map["\x0c"]      = '\\f';
			$map["\n"]        = '\\n';
			$map["\r"]        = '\\r';
			$map["\t"]        = '\\t';
			$map['"']         = '\\u0022';
			$map['\\']        = '\\\\';
			$map['<']         = '\\u003c';
			$map['>']         = '\\u003e';
			$map['&']         = '\\u0026';
			self::$escape_map = $map;
		}
		return '"' . str_replace( '--', '\\u002d\\u002d', strtr( $s, self::$escape_map ) ) . '"';
	}

	/**
	 * Python json.dumps(value, separators=(",", ":")) with the default ensure_ascii=True.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function py_dumps( $value ) {
		if ( $value instanceof \stdClass ) {
			$value = get_object_vars( $value );
			if ( ! $value ) {
				return '{}';
			}
		}
		if ( is_array( $value ) ) {
			$parts = array();
			if ( self::is_list( $value ) ) {
				foreach ( $value as $item ) {
					$parts[] = self::py_dumps( $item );
				}
				return '[' . implode( ',', $parts ) . ']';
			}
			foreach ( $value as $key => $item ) {
				$parts[] = self::py_dumps( (string) $key ) . ':' . self::py_dumps( $item );
			}
			return '{' . implode( ',', $parts ) . '}';
		}
		if ( is_string( $value ) ) {
			$json = json_encode( $value, JSON_UNESCAPED_SLASHES );
			if ( false === $json ) {
				throw new \InvalidArgumentException( 'String is not valid UTF-8' );
			}
			return $json;
		}
		if ( is_float( $value ) ) {
			return self::float_repr( $value, true );
		}
		return self::encode_attrs( $value );
	}
}
