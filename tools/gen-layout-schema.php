<?php
/**
 * Generates includes/Divi/schema/layout.schema.json (JSON Schema draft 2020-12 of the
 * LayoutTree input) from the code: node/pattern/value-helper names come from LayoutTree,
 * required args from ReflectionMethod, optional args and their defaults from the
 * `opts( __FUNCTION__, $opts, <defaults> )` call in each method, summaries from docblocks.
 * Arg descriptions not derivable from code are in the tables below.
 *
 * Usage (no PHP on the host):
 *   docker run --rm -v "$PWD":/app -w /app php:7.4-cli php tools/gen-layout-schema.php
 *
 * tests/Unit/Divi/LayoutTreeTest.php fails when the committed schema is stale.
 *
 * @package WPDM
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

foreach ( array( 'Block', 'D5', 'Theme', 'Patterns', 'LayoutError', 'LayoutTree' ) as $wpdm_ls_class ) {
	if ( ! class_exists( 'WPDM\\Divi\\' . $wpdm_ls_class ) ) {
		require_once dirname( __DIR__ ) . '/includes/Divi/' . $wpdm_ls_class . '.php';
	}
}

use WPDM\Divi\D5;
use WPDM\Divi\LayoutTree;
use WPDM\Divi\Patterns;
use WPDM\Divi\Theme;

if ( ! function_exists( 'wpdm_layout_schema_json' ) ) {

	/**
	 * Arg descriptions: "fn.key" first, then "key".
	 *
	 * @return array<string, string|array>  Description, or [description, schema override].
	 */
	function wpdm_ls_arg_table() {
		$str   = array( '$ref' => '#/$defs/string' );
		$obj   = array( '$ref' => '#/$defs/object' );
		$int   = array( 'type' => array( 'integer', 'string' ) );
		$num   = array( 'anyOf' => array( array( 'type' => array( 'string', 'number' ) ) ) );
		$bools = array( 'anyOf' => array( array( 'type' => 'boolean' ), $str ) );
		$width = 'Column width: "1_1", "1_2", "1_3", "2_3", "1_4", "3_4", "3_5"... (or "1/2"). Widths in a row must add up to whole lines.';
		$cardw = 'Column width when the card sits in a flex row ("1_3"...). Leave out in a grid_row.';
		return array(
			// Shared.
			'attrs'                        => array( null, array( '$ref' => '#/$defs/attrs' ) ),
			'children'                     => array( null, array( '$ref' => '#/$defs/children' ) ),
			'level'                        => 'Heading tag: "h1".."h6".',
			'url'                          => 'Link URL.',
			'new_window'                   => 'Open the link in a new tab.',
			'icon'                         => 'Icon: {"$fa": "&#xf0e0;"} or {"$divi_icon": "&#xe00b;"}.',
			'icon_value'                   => 'Icon: {"$fa": "&#xf19d;"} (Font Awesome, optional "weight") or {"$divi_icon": "&#xe00b;"}.',
			'body_html'                    => 'Body HTML.',
			'title'                        => 'Title text.',
			'label'                        => 'Label text.',
			'color'                        => 'Color (hex or {"$color_var": "gcid-..."}).',
			'size'                         => 'Size (CSS length, e.g. "16px").',
			'align'                        => 'Alignment.',
			'justify'                      => 'justify-content: start | center | end | space-between | space-around.',
			'direction'                    => 'Flex direction: row | column | row-reverse | column-reverse.',
			'gap'                          => 'Gap (CSS length) between items.',
			'row_gap'                      => 'Row gap (overrides gap).',
			'column_gap'                   => 'Column gap (overrides gap).',
			'max_width'                    => 'Max width (CSS), e.g. "1100px".',
			'body'                         => 'Body text (plain text is wrapped in <p>) or HTML.',
			'link_label'                   => 'Text link label (shown when link_url is set too).',
			'link_url'                     => 'Text link URL.',
			'number'                       => array( 'Number or short label, e.g. "01" or "98%".', $num ),
			'html'                         => 'Rich HTML (<p>, <h2>, <ul>, <a>...).',
			'tablet'                       => 'Tablet override.',
			'phone'                        => 'Phone override.',
			'width'                        => 'Width (CSS).',
			'open'                         => 'Open by default.',
			'photo'                        => 'Photo URL (falsy = icon badge instead).',
			'box_size'                     => 'Badge box size (CSS length).',
			'icon_size'                    => 'Icon size (CSS length).',
			'weight'                       => 'Weight.',
			// Structure.
			'section.kind'                 => 'Section type: "fullwidth" for fullwidth modules (leave out for regular sections).',
			'section.css_id'               => 'CSS id on the section for anchor links, e.g. "becas" (linked as "#becas").',
			'section.children'             => 'Rows: row / grid_row nodes.',
			'row.children'                 => 'Columns: column nodes or card patterns. Widths must fill the row (sum 1, or 2, 3... full lines).',
			'row.wrap_on'                  => array( 'Breakpoints where multi-column rows wrap (columns stack full width on phone).', array( 'type' => 'array', 'items' => array( 'enum' => array( 'tablet', 'phone' ) ) ) ),
			'row.width'                    => 'Row width (CSS), e.g. "90%".',
			'row.gap'                      => 'Gap between columns (CSS length).',
			'grid_row.children'            => 'Columns: column nodes or card patterns (any number; they flow into cols tracks).',
			'grid_row.cols'                => array( 'Equal columns on desktop.', $int ),
			'grid_row.tablet'              => array( 'Columns on tablet.', $int ),
			'grid_row.phone'               => array( 'Columns on phone.', $int ),
			'grid_row.gap'                 => 'Gap between cards (CSS length).',
			'grid_row.template'            => 'Manual track sizes instead of cols, e.g. "2fr 1fr".',
			'grid_row.width'               => 'Row width (CSS).',
			'column.children'              => 'Modules, groups and non-card patterns.',
			'column.width'                 => $width,
			'column.tablet'                => 'Tablet width override ("1_2"...).',
			'column.phone'                 => 'Phone width override ("1_1"...).',
			'group.children'               => 'Modules, groups and non-card patterns.',
			'group.align'                  => 'align-items: flex-start | center | flex-end | stretch.',
			'group.wrap'                   => array( 'Flex wrap: true, false or a CSS value.', $bools ),
			'group.width'                  => 'Group width (CSS), e.g. "100%".',
			'group.layout'                 => array( 'Full layout value instead of the shortcuts: {"$flex": {...}} or {"$grid": {...}}.', $obj ),
			'group.tablet'                 => array( 'Tablet layout override, e.g. {"flexDirection": "column"}.', $obj ),
			'group.phone'                  => array( 'Phone layout override.', $obj ),
			// Modules.
			'image.src'                    => 'Image URL.',
			'image.alt'                    => 'Alt text.',
			'image.title'                  => 'Title attribute.',
			'image.url'                    => 'Link URL when the image is clicked.',
			'image.attachment_id'          => array( 'Media library attachment id.', $int ),
			'image.width'                  => 'Image width (CSS).',
			'image.height'                 => 'Image height (CSS).',
			'link.icon_position'           => 'Icon position: "after" or "before".',
			'icon.url'                     => 'Link URL.',
			'heading.title'                => 'Heading text (plain).',
			'button.label'                 => 'Button text.',
			'blurb.image_src'              => 'Image URL instead of an icon.',
			'code.html'                    => 'Raw HTML/CSS/JS.',
			'toggle.level'                 => 'Title tag.',
			'number_counter.percent_sign'  => 'Show a % sign.',
			'divider.color'                => 'Line color.',
			'divider.weight'               => 'Line weight (CSS length).',
			'cta.button_label'             => 'Button text (empty = no button).',
			'cta.button_url'               => 'Button URL.',
			'testimonial.author'           => 'Author name.',
			'testimonial.body_html'        => 'Quote HTML.',
			'testimonial.job_title'        => 'Job title.',
			'testimonial.company'          => 'Company.',
			'testimonial.company_url'      => 'Company URL.',
			'testimonial.portrait'         => 'Portrait image URL.',
			'team_member.name'             => 'Name.',
			'team_member.position'         => 'Position.',
			'team_member.image_url'        => 'Photo URL.',
			'team_member.social'           => array( 'Social links object (Divi social innerContent).', $obj ),
			'contact_form.children'        => 'contact_field nodes.',
			'contact_form.email'           => 'Receiver email.',
			'contact_form.title'           => 'Form title.',
			'contact_form.submit'          => 'Submit button text.',
			'contact_form.success'         => 'Success message.',
			'contact_form.unique_key'      => 'Stable key for the form id (same key = same id on rebuild).',
			'contact_field.field_id'       => 'Field id, e.g. "name", "email".',
			'contact_field.type'           => 'Field type: input | email | text | checkbox | radio | select.',
			'contact_field.required'       => 'Required field.',
			'contact_field.half'           => 'Half width on desktop.',
			'timeline.children'            => 'timeline_item nodes.',
			'timeline.direction'           => 'vertical | horizontal.',
			'timeline.position'            => 'Item position: alternating | left | right.',
			'timeline_item.date'           => 'Date label.',
			'icon_list.children'           => 'icon_list_item nodes.',
			'icon_list.color'              => 'Icon color.',
			'icon_list.size'               => 'Icon size.',
			'icon_list_item.icon'          => 'Icon (default: check mark).',
			'social_follow.children'       => 'social_network nodes.',
			'social_network.network'       => 'Network slug: facebook, x, instagram, linkedin, youtube...',
			'social_network.url'           => 'Profile URL.',
			'social_network.label'         => 'Label (defaults to the network).',
			'accordion.children'           => 'accordion_item nodes.',
			'breadcrumbs.home_text'        => 'Home link text.',
			'breadcrumbs.separator'        => 'Separator.',
			'breadcrumbs.home_url'         => 'Home URL.',
			'breadcrumbs.link_color'       => 'Link color.',
			'breadcrumbs.hover_color'      => 'Link hover color.',
			// Patterns.
			'eyebrow.label'                => 'Label (HTML allowed).',
			'heading_text.title'           => 'Title (HTML allowed).',
			'heading_text.size'            => 'Font size (default: theme h1/h2/h3 size).',
			'heading_text.color'           => 'Color (default: theme heading).',
			'heading_text.align'           => 'Text align: left | center | right.',
			'body_text.html'               => 'Text (wrapped in <p>) or HTML.',
			'body_text.size'               => 'Font size (default: theme body_size).',
			'body_text.color'              => 'Color (default: theme body).',
			'body_text.align'              => 'Text align: left | center | right.',
			'section_header.eyebrow_label' => 'Eyebrow label above the title (empty string = none).',
			'section_header.title'         => 'Title.',
			'section_header.intro'         => 'Intro paragraph under the title.',
			'section_header.align'         => 'left | center | right.',
			'icon_badge.shape'             => 'circle | soft (card radius) | square.',
			'pill_link.label'              => 'Label.',
			'pill_link.url'                => 'URL.',
			'pill_link.icon_value'         => 'Icon (default arrow; false = no icon).',
			'pill_link.variant'            => 'primary (filled) | outline | text.',
			'pill_link.on_dark'            => 'Button sits on a dark/brand background: light hover (white fill).',
			'pill_link.bg'                 => 'Background color override.',
			'pill_link.fg'                 => 'Text color override.',
			'pill_link.hover_bg'           => 'Hover background override.',
			'pill_link.hover_fg'           => 'Hover text color override.',
			'pill_link.icon_color'         => 'Icon color, only when it must differ from the text.',
			'pill_link.icon_hover_color'   => 'Icon hover color, only when it must differ from the text.',
			'icon_card.badge_bg'           => 'Badge background override.',
			'icon_card.badge_fg'           => 'Badge icon color override.',
			'media_card.image_src'         => 'Image URL.',
			'media_card.alt'               => 'Alt text (default: title).',
			'person_card.name'             => 'Name.',
			'person_card.role'             => 'Role / job title.',
			'person_card.bio'              => 'Short bio.',
			'person_card.icon_value'       => 'Avatar icon when there is no photo (default: user icon).',
			'testimonial_card.quote'       => 'Quote (text or HTML).',
			'testimonial_card.name'        => 'Author name.',
			'testimonial_card.role'        => 'Author role.',
			'numbered_card.body'           => 'Body text or HTML.',
			'numbered_card.icon_value'     => 'Optional icon at the right of the number.',
			'numbered_card.title'          => 'Optional title.',
			'stat_card.label'              => 'Label text or HTML.',
			'cards.width'                  => $cardw,
			'cards.tablet'                 => 'Tablet width in a flex row.',
			'cards.phone'                  => 'Phone width in a flex row.',
			// Value helpers.
			'font.element'                 => 'Element key, e.g. "title", "content" (default), "button".',
			'font.desktop'                 => array( 'Font values: size, weight, color, lineHeight, letterSpacing, family, style...', $obj ),
			'font.tablet'                  => array( 'Tablet font values.', $obj ),
			'font.phone'                   => array( 'Phone font values.', $obj ),
			'font.hover'                   => array( 'Hover font values.', $obj ),
			'font.group'                   => '"font" (titles, buttons), "bodyFont" (rich text, needs sub) or "headingFont" (needs sub).',
			'font.sub'                     => 'bodyFont: body | link | ul | ol | quote; headingFont: h1..h6.',
			'flex.align'                   => 'align-items: flex-start | center | flex-end | stretch.',
			'flex.wrap'                    => array( 'Flex wrap: true, false or a CSS value.', $bools ),
			'flex.display'                 => 'CSS display, e.g. "flex".',
			'grid.columns'                 => array( 'Equal columns.', $int ),
			'grid.template'                => 'Manual tracks, e.g. "42px 1fr".',
			'grid.align'                   => 'align-items.',
			'card.background'              => 'Background color.',
			'card.border_color'            => 'Border color (adds a border).',
			'card.border_width'            => 'Border width.',
			'card.radius'                  => array( 'Radius: "16px" or 4 values [TL, TR, BR, BL].', array( 'anyOf' => array( $str, array( 'type' => 'array' ) ) ) ),
			'card.padding'                 => array( 'Padding: "24px" or a box ({"$box": [...]}).', array( 'anyOf' => array( $str, $obj ) ) ),
			'card.shadow'                  => array( 'Box shadow keys, e.g. {"vertical": "10px", "blur": "28px", "color": "rgba(0,0,0,.1)"}.', $obj ),
			'card.hover_shadow'            => array( 'Hover box shadow keys.', $obj ),
			'card.hover_border_color'      => 'Hover border color.',
			'card.element'                 => 'Element key (default "module").',
		);
	}

	/**
	 * Defaults array passed to opts() in a method, or [] when it has none.
	 *
	 * @param \ReflectionMethod $m Method.
	 * @return array
	 */
	function wpdm_ls_opts_defaults( \ReflectionMethod $m ) {
		$lines  = file( $m->getFileName() );
		$body   = implode( '', array_slice( $lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
		$tokens = token_get_all( "<?php\n" . $body );
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || 'opts' !== $tokens[ $i ][1] ) {
				continue;
			}
			$j = $i - 1;
			while ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				--$j;
			}
			if ( ! is_array( $tokens[ $j ] ) || T_DOUBLE_COLON !== $tokens[ $j ][0] ) {
				continue;
			}
			// Collect the third argument of opts( ... ).
			$k = $i + 1;
			while ( '(' !== $tokens[ $k ] ) {
				++$k;
			}
			$depth = 0;
			$args  = array( '' );
			for ( ; $k < $count; $k++ ) {
				$t    = $tokens[ $k ];
				$text = is_array( $t ) ? $t[1] : $t;
				if ( '(' === $t ) {
					++$depth;
					if ( 1 === $depth ) {
						continue;
					}
				} elseif ( ')' === $t ) {
					--$depth;
					if ( 0 === $depth ) {
						break;
					}
				} elseif ( ',' === $t && 1 === $depth ) {
					$args[] = '';
					continue;
				}
				$args[ count( $args ) - 1 ] .= $text;
			}
			$expr          = str_replace( 'self::card_defaults', '$card_defaults', trim( $args[2] ) );
			$card_defaults = static function ( array $own ) {
				$r = new \ReflectionMethod( Patterns::class, 'card_defaults' );
				$r->setAccessible( true );
				return $r->invoke( null, $own );
			};
			return eval( 'return ' . $expr . ';' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- dev tool, own source.
		}
		return array();
	}

	/**
	 * Docblock summary and @param descriptions.
	 *
	 * @param string $doc Doc comment.
	 * @return array{summary: string, params: array<string, string>}
	 */
	function wpdm_ls_doc( $doc ) {
		$summary = array();
		$params  = array();
		$current = null;
		$in_tags = false;
		foreach ( preg_split( '/\R/', (string) $doc ) as $line ) {
			$line = trim( preg_replace( '#^\s*(/\*\*|\*/|\*)#', '', $line ) );
			if ( preg_match( '/^@param\s+\S+\s+(?:\.\.\.)?\$(\w+)\s*(.*)$/', $line, $mm ) ) {
				$in_tags          = true;
				$current          = $mm[1];
				$params[ $current ] = $mm[2];
			} elseif ( 0 === strpos( $line, '@' ) ) {
				$in_tags = true;
				$current = null;
			} elseif ( $in_tags ) {
				if ( null !== $current && '' !== $line ) {
					$params[ $current ] .= ' ' . $line;
				}
			} else {
				$summary[] = $line;
			}
		}
		$text = trim( preg_replace( '/\s+/', ' ', implode( ' ', $summary ) ) );
		$text = preg_replace( array( '/`\$(\w+)`/', '/\bD5::/', '/\$(\w+)/' ), array( '`$1`', '', '$1' ), $text );
		return array(
			'summary' => $text,
			'params'  => $params,
		);
	}

	/**
	 * Schema of one arg.
	 *
	 * @param string $fn      Function (or "cards" for shared card keys).
	 * @param string $key     Arg.
	 * @param mixed  $default Default (null = none).
	 * @param string $docdesc Description from the docblock.
	 * @param string $group   Fallback table prefix ("cards").
	 * @return array
	 */
	function wpdm_ls_arg( $fn, $key, $default, $docdesc = '', $group = '' ) {
		$table = wpdm_ls_arg_table();
		$entry = null;
		foreach ( array( "{$fn}.{$key}", $group ? "{$group}.{$key}" : null, $key ) as $k ) {
			if ( null !== $k && array_key_exists( $k, $table ) ) {
				$entry = $table[ $k ];
				break;
			}
		}
		if ( null === $entry ) {
			if ( '' === $docdesc ) {
				throw new \RuntimeException( "No description for {$fn}.{$key}: add it to wpdm_ls_arg_table()" );
			}
			$entry = $docdesc;
		}
		list( $desc, $schema ) = is_array( $entry ) ? $entry : array( $entry, null );
		if ( null === $schema ) {
			if ( 'attrs' === $key ) {
				$schema = array( '$ref' => '#/$defs/attrs' );
			} elseif ( 'children' === $key ) {
				$schema = array( '$ref' => '#/$defs/children' );
			} elseif ( in_array( $key, array( 'icon', 'icon_value' ), true ) ) {
				$schema = array( '$ref' => '#/$defs/icon' );
			} elseif ( is_bool( $default ) ) {
				$schema = array( 'type' => 'boolean' );
			} elseif ( is_int( $default ) ) {
				$schema = array( 'type' => array( 'integer', 'string' ) );
			} else {
				$schema = array( '$ref' => '#/$defs/string' );
			}
		}
		if ( null !== $desc ) {
			$schema['description'] = $desc;
		}
		if ( null !== $default && ( is_scalar( $default ) || is_array( $default ) ) && '' !== $default ) {
			$schema['default'] = $default;
		}
		return $schema;
	}

	/**
	 * Properties and required list for a method: required params, then opts keys.
	 *
	 * @param \ReflectionMethod $m     Method.
	 * @param string            $group Table fallback prefix.
	 * @return array{0: array, 1: string[]}
	 */
	function wpdm_ls_params( \ReflectionMethod $m, $group = '' ) {
		$fn       = $m->getName();
		$doc      = wpdm_ls_doc( $m->getDocComment() );
		$props    = array();
		$required = array();
		$params   = $m->getParameters();
		foreach ( $params as $p ) {
			$type = $p->getType() instanceof \ReflectionNamedType ? $p->getType()->getName() : '';
			if ( $p->isVariadic() || Theme::class === $type || ( 'array' === $type && $p === end( $params ) ) ) {
				continue;
			}
			$name = $p->getName();
			if ( ! $p->isOptional() ) {
				$required[] = $name;
			}
			$desc           = isset( $doc['params'][ $name ] ) ? rtrim( $doc['params'][ $name ], '.' ) . '.' : '';
			$props[ $name ] = wpdm_ls_arg( $fn, $name, $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null, $desc, $group );
		}
		foreach ( wpdm_ls_opts_defaults( $m ) as $key => $default ) {
			$props[ $key ] = wpdm_ls_arg( $fn, $key, $default, '', $group );
		}
		if ( $params && end( $params )->isVariadic() ) {
			$props['children'] = wpdm_ls_arg( $fn, 'children', null );
		}
		return array( $props, $required );
	}

	/**
	 * The schema as an array.
	 *
	 * @return array
	 */
	function wpdm_layout_schema() {
		$defs = array();

		$helper_refs = array();
		foreach ( LayoutTree::helpers() as $name ) {
			$m                   = new \ReflectionMethod( D5::class, $name );
			list( $props, $req ) = wpdm_ls_params( $m );
			$inner               = array(
				'type'                 => 'object',
				'additionalProperties' => false,
			);
			if ( $req ) {
				$inner['required'] = $req;
			}
			$inner['properties']       = $props;
			$defs[ 'node_' . $name ] = array(
				'description'          => wpdm_ls_doc( $m->getDocComment() )['summary'],
				'type'                 => 'object',
				'required'             => array( $name ),
				'additionalProperties' => false,
				'properties'           => array( $name => $inner ),
			);
			$helper_refs[]           = array( '$ref' => '#/$defs/node_' . $name );
		}

		$pattern_refs = array();
		foreach ( LayoutTree::patterns() as $name ) {
			$m                   = new \ReflectionMethod( Patterns::class, $name );
			$card                = in_array( $name, LayoutTree::CARD_PATTERNS, true );
			list( $props, $req ) = wpdm_ls_params( $m, $card ? 'cards' : '' );
			$props['theme']      = array( '$ref' => '#/$defs/theme' );
			$summary             = wpdm_ls_doc( $m->getDocComment() )['summary'];
			if ( 'section_header' === $name ) {
				$summary = 'Eyebrow + heading (in a group) and an optional intro paragraph: 1-2 blocks, spread into the parent column/group.';
			} elseif ( $card ) {
				$summary .= ' Card: returns a column, so it goes directly in a row/grid_row (never inside a column or group).';
			}
			$defs[ 'pattern_' . $name ] = array(
				'description'          => $summary,
				'type'                 => 'object',
				'required'             => array( 'pattern', 'args' ),
				'additionalProperties' => false,
				'properties'           => array(
					'pattern' => array( 'const' => $name ),
					'args'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => $req,
						'properties'           => $props,
					),
				),
			);
			$pattern_refs[]             = array( '$ref' => '#/$defs/pattern_' . $name );
		}

		$str = array( '$ref' => '#/$defs/string' );
		$any = array( '$ref' => '#/$defs/value' );

		$rv_props = array(
			'desktop' => $any + array( 'description' => 'Desktop value.' ),
			'tablet'  => $any + array( 'description' => 'Tablet value.' ),
			'phone'   => $any + array( 'description' => 'Phone value.' ),
			'hover'   => $any + array( 'description' => 'Desktop hover value.' ),
			'sticky'  => $any + array( 'description' => 'Desktop sticky value.' ),
		);
		foreach ( D5::BREAKPOINTS as $bp ) {
			if ( ! isset( $rv_props[ $bp ] ) ) {
				$rv_props[ $bp ] = $any + array( 'description' => "Value at the {$bp} breakpoint." );
			}
		}
		$values = array(
			'rv'          => array(
				'Responsive value: {"$rv": {"desktop": "16px", "tablet": "14px"}} -> {"desktop": {"value": "16px"}, "tablet": {"value": "14px"}}. Most Divi attribute leaves need it.',
				array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => $rv_props,
				),
				array(),
			),
			'box'         => array(
				'Spacing box (margin/padding) with Divi sync flags. List in CSS shorthand order (1 to 4 values) or an object.',
				array(
					'anyOf' => array(
						array(
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 4,
							'items'    => $str,
						),
						array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'top'    => $str,
								'right'  => $str,
								'bottom' => $str,
								'left'   => $str,
							),
						),
					),
				),
				array(),
			),
			'radius'      => array(
				'Border radius: one value (uniform) or 4 values [top_left, top_right, bottom_right, bottom_left].',
				array(
					'anyOf' => array(
						$str,
						array(
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 4,
							'items'    => $str,
						),
					),
				),
				array(),
			),
			'color_var'   => array(
				'Reference to a global color: {"$color_var": "gcid-..."} (optional sibling "opacity") or {"$color_var": {"id": "gcid-...", "opacity": n}}.',
				array(
					'anyOf' => array(
						array(
							'type'    => 'string',
							'pattern' => '^gcid-',
						),
						array(
							'type'                 => 'object',
							'required'             => array( 'id' ),
							'additionalProperties' => false,
							'properties'           => array(
								'id'      => array( 'type' => 'string' ),
								'opacity' => array( 'type' => array( 'number', 'string' ) ),
							),
						),
					),
				),
				array(
					'opacity' => array(
						'type'        => array( 'number', 'string' ),
						'description' => 'Opacity (0-100).',
					),
				),
			),
			'content_var' => array(
				'Reference to a global variable (number, text, font, link...): {"$content_var": "gvid-..."}.',
				array(
					'type'    => 'string',
					'pattern' => '^gvid-',
				),
				array(),
			),
			'fa'          => array(
				'Font Awesome icon value, e.g. {"$fa": "&#xf19d;"} or {"$fa": "&#xf0e0;", "weight": "400"}.',
				array( 'type' => 'string' ),
				array(
					'weight' => array(
						'type'        => 'string',
						'description' => '"900" solid (default) or "400" regular.',
					),
				),
			),
			'divi_icon'   => array(
				'Divi icon font value, e.g. {"$divi_icon": "&#xe00b;"}.',
				array( 'type' => 'string' ),
				array(),
			),
			'merge'       => array(
				'Deep-merge objects left to right (later wins; lists are replaced), e.g. {"$merge": [{"$font": {...}}, {"$module_deco": {...}}]} as a whole attrs object.',
				array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => $any,
				),
				array(),
			),
			'module_deco' => array(
				'{"module": {"decoration": {groups}}}: decoration groups such as background, border, spacing, sizing, layout, boxShadow, font (values usually $rv).',
				array(
					'type'                 => 'object',
					'additionalProperties' => $any,
				),
				array(),
			),
		);
		foreach ( array( 'font', 'flex', 'grid', 'card' ) as $name ) {
			$m                   = new \ReflectionMethod( D5::class, $name );
			list( $props, $req ) = wpdm_ls_params( $m );
			$values[ $name ]     = array(
				wpdm_ls_doc( $m->getDocComment() )['summary'],
				array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => $props,
				),
				array(),
			);
		}
		$value_refs = array();
		foreach ( LayoutTree::value_helpers() as $name ) {
			if ( ! isset( $values[ $name ] ) ) {
				throw new \RuntimeException( "No schema for value helper {$name}" );
			}
			list( $desc, $arg, $siblings ) = $values[ $name ];
			$defs[ 'value_' . $name ]      = array(
				'description'          => $desc,
				'type'                 => 'object',
				'required'             => array( '$' . $name ),
				'additionalProperties' => false,
				'properties'           => array_merge( array( '$' . $name => $arg ), $siblings ),
			);
			$value_refs[]                  = array( '$ref' => '#/$defs/value_' . $name );
		}
		$defs['value_literal'] = array(
			'description'          => 'Literal value: X is returned as is and nothing inside it is resolved (only needed to pass a "$..." key through).',
			'type'                 => 'object',
			'required'             => array( '$literal' ),
			'additionalProperties' => false,
			'properties'           => array( '$literal' => new \stdClass() ),
		);
		$value_refs[]          = array( '$ref' => '#/$defs/value_literal' );

		$theme_props = array();
		foreach ( get_class_vars( Theme::class ) as $token => $default ) {
			$doc                   = ( new \ReflectionProperty( Theme::class, $token ) )->getDocComment();
			$desc                  = preg_match( '#@var\s+\S+\s+(.*?)\s*\*/#s', (string) $doc, $mm ) ? $mm[1] : $token;
			$theme_props[ $token ] = 'card_radius' === $token
				? array(
					'anyOf'       => array( $str, array( 'type' => 'array' ) ),
					'description' => $desc,
					'default'     => $default,
				)
				: $str + array(
					'description' => $desc,
					'default'     => $default,
				);
		}

		$defs = array_merge(
			array(
				'node'     => array(
					'description' => 'A node: a helper {"<helper>": {...args}}, a pattern {"pattern": "<name>", "args": {...}} or a raw block {"block": "divi/<name>", ...}.',
					'anyOf'       => array_merge( $helper_refs, array( array( '$ref' => '#/$defs/pattern' ), array( '$ref' => '#/$defs/block' ) ) ),
				),
				'pattern'  => array(
					'description' => 'Composition pattern (Patterns::<name>): cards and text pieces built from column/group/text/icon/image/link. Args are the parameter names.',
					'anyOf'       => $pattern_refs,
				),
				'block'    => array(
					'description'          => 'Escape hatch: any Divi 5 block. children absent = self-closing block; [] = empty container.',
					'type'                 => 'object',
					'required'             => array( 'block' ),
					'additionalProperties' => false,
					'properties'           => array(
						'block'    => array(
							'type'        => 'string',
							'pattern'     => '^divi/',
							'description' => 'Block name, e.g. "divi/text".',
						),
						'attrs'    => array( '$ref' => '#/$defs/attrs' ),
						'children' => array( '$ref' => '#/$defs/children' ),
					),
				),
				'children' => array(
					'type'  => 'array',
					'items' => array( '$ref' => '#/$defs/node' ),
				),
				'attrs'    => array(
					'description' => 'Divi attributes deep-merged into the block (raw Divi 5 shape, e.g. {"module": {"decoration": {...}}}). Value helpers are resolved anywhere inside, and may produce the whole object (e.g. {"$module_deco": {...}}).',
					'type'        => 'object',
				),
				'value'    => array(
					'description' => 'Any JSON value. Value helper keys start with "$" ($rv, $box, $radius, $color_var, $content_var, $fa, $divi_icon, $font, $flex, $grid, $card, $module_deco, $merge): an object is a helper call when exactly one of its keys is a $helper and its other keys are that helper\'s siblings ($fa: weight; $color_var: opacity); any other key next to a $helper, or two $helpers in one object, is an error (combine helpers with $merge). Keys without "$" are always plain data, so attrs copied from real Divi exports (decoration.font.font, border radius...) pass through; their values are still resolved recursively. {"$literal": X} yields X untouched.',
					'anyOf'       => array_merge( $value_refs, array( array( 'type' => array( 'string', 'number', 'boolean', 'null', 'array', 'object' ) ) ) ),
				),
				'string'   => array(
					'description' => 'A string (or number), or a helper that resolves to one ({"$color_var": ...}, {"$content_var": ...}).',
					'anyOf'       => array(
						array( 'type' => array( 'string', 'number' ) ),
						array( '$ref' => '#/$defs/value_color_var' ),
						array( '$ref' => '#/$defs/value_content_var' ),
						array( '$ref' => '#/$defs/value_literal' ),
					),
				),
				'object'   => array(
					'description' => 'An object (value helpers allowed inside, or a helper producing it: $rv, $flex, $grid, $box, $font, $merge...).',
					'type'        => 'object',
				),
				'icon'     => array(
					'description' => 'Icon value: {"$fa": "&#xf19d;", "weight"?: "900"} or {"$divi_icon": "&#xe00b;"}.',
					'anyOf'       => array(
						array( '$ref' => '#/$defs/value_fa' ),
						array( '$ref' => '#/$defs/value_divi_icon' ),
						array( '$ref' => '#/$defs/value_literal' ),
						array( 'type' => 'boolean' ),
					),
				),
				'theme'    => array(
					'description'          => 'Design tokens for the patterns (Theme). Values may be plain colors or {"$color_var": ...} / {"$content_var": ...}. In a pattern\'s args.theme: tokens for that pattern only, merged over the top-level theme.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => $theme_props,
				),
			),
			$defs
		);

		return array(
			'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
			'$id'                  => 'divi://schema/layout',
			'title'                => 'Divi 5 layout tree',
			'description'          => 'A page as a JSON tree mirroring the D5 builder and the composition patterns. Nesting: blocks are sections; a section holds rows (row = flex columns whose widths fill the row, grid_row = any number of cards in equal tracks); a row/grid_row holds columns or card patterns (icon_card, media_card, person_card, testimonial_card, numbered_card, step_card, stat_card return a column: put them directly in the row, never inside a column or group); columns and groups hold modules, groups and the other patterns. Args use the PHP/Python parameter names. Value helpers ({"$rv": ...}, {"$color_var": ...}, {"$fa": ...}...) are resolved anywhere in args/attrs; keys without "$" are plain Divi data. Prefer composition (group/icon/text/link and patterns) over blurb/testimonial/team_member/button.',
			'type'                 => 'object',
			'required'             => array( 'blocks' ),
			'additionalProperties' => false,
			'properties'           => array(
				'builder_version'  => array(
					'type'        => 'string',
					'description' => 'Divi version stamped on blocks (use the site\'s version, e.g. "5.14.0").',
				),
				'theme'            => array(
					'$ref'        => '#/$defs/theme',
					'description' => 'Theme tokens passed to every pattern.',
				),
				'global_colors'    => array(
					'type'        => 'array',
					'description' => 'Global colors for the export: [["gcid-x", {"color": "#hex", "status": "active", "label": "X"}], ...].',
				),
				'global_variables' => array(
					'type'        => 'array',
					'description' => 'Global variables for the export: [{"id": "gvid-x", "label": "X", "value": ..., "status": "active", "type": "numbers"}, ...].',
				),
				'custom_css'       => array(
					'type'        => 'string',
					'description' => 'Page custom CSS.',
				),
				'blocks'           => array(
					'type'        => 'array',
					'minItems'    => 1,
					'description' => 'Top-level nodes: sections (also for Theme Builder headers/footers).',
					'items'       => array( '$ref' => '#/$defs/node' ),
				),
			),
			'$defs'                => $defs,
		);
	}

	/**
	 * The schema file content: one line per top-level key and per $defs entry.
	 *
	 * @return string
	 */
	function wpdm_layout_schema_json() {
		$flags  = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$schema = wpdm_layout_schema();
		$defs   = $schema['$defs'];
		unset( $schema['$defs'] );
		$lines = array();
		foreach ( $schema as $key => $value ) {
			$lines[] = '  ' . json_encode( $key, $flags ) . ': ' . json_encode( $value, $flags );
		}
		$def_lines = array();
		foreach ( $defs as $key => $value ) {
			$def_lines[] = '    ' . json_encode( (string) $key, $flags ) . ': ' . json_encode( $value, $flags );
		}
		$lines[] = "  \"\$defs\": {\n" . implode( ",\n", $def_lines ) . "\n  }";
		return "{\n" . implode( ",\n", $lines ) . "\n}\n";
	}
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$wpdm_ls_out = dirname( __DIR__ ) . '/includes/Divi/schema/layout.schema.json';
	if ( ! is_dir( dirname( $wpdm_ls_out ) ) ) {
		mkdir( dirname( $wpdm_ls_out ), 0777, true );
	}
	$wpdm_ls_json = wpdm_layout_schema_json();
	file_put_contents( $wpdm_ls_out, $wpdm_ls_json );
	echo 'Wrote ' . $wpdm_ls_out . ' (' . strlen( $wpdm_ls_json ) . " bytes)\n";
}
