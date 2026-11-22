<?php
/**
 * Divi 5 layout validator (PHP port of the divi5-layouts skill's validate.py).
 *
 * Every layout goes through this before it is written to a site. It reports:
 *
 * - ERROR: things that break the import (envelope shape, unbalanced or misnested
 *   blocks, unknown Divi block names, invalid attribute JSON, malformed $variable
 *   references).
 * - WARNING: things that import but look wrong (unknown attribute keys, responsive
 *   shape, referenced-but-unshipped globals, buttons/links that vanish against their
 *   container or on hover, text the same color as its background).
 * - INFO: advice (thin buttons without padding, missing alt text, dynamic content,
 *   images that will be hot-linked).
 *
 * Messages, classification and order match validate.py exactly (pinned by
 * tests/golden/validator). Values are modelled the Python way (see PyJson / Py):
 * JSON objects are PHP arrays, JSON lists are PyList. Where validate.py would
 * crash on a malformed value (e.g. calling .get() on a string), the port treats the
 * value as absent and carries on.
 *
 * Pure PHP, no WordPress dependency at runtime (Catalog::load() is the only
 * WordPress-aware part, and it is guarded). Block markup is scanned with
 * BlockScanner, which reproduces validate.py's BLOCK_RE matches without PCRE
 * (not parse_blocks()).
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Validates Portability exports and raw Divi block markup.
 */
final class Validator {

	const BREAKPOINTS = array( 'desktop', 'tablet', 'phone', 'phoneWide', 'tabletWide', 'widescreen', 'ultraWide', 'desktopAbove', 'tabletOnly', 'phoneOnly' );

	const STATES = array( 'value', 'hover', 'sticky' );

	const META_KEYS = array( 'builderVersion', 'modulePreset', 'groupPreset', 'css', 'locked', 'globalParent', 'globalModule', 'blockName', 'localAttrs', 'uniqueId' );

	const CUSTOMIZER_COLORS = array( 'gcid-primary-color', 'gcid-secondary-color', 'gcid-heading-color', 'gcid-body-color', 'gcid-link-color' );

	const STRUCTURE = array( 'divi/section', 'divi/row', 'divi/row-inner', 'divi/column', 'divi/column-inner', 'divi/group' );

	/**
	 * Keys of an unprefixed LayoutTree value helper inside an icon value (strict hints).
	 */
	const TREE_HELPER_KEYS = array( 'fa', 'divi_icon', 'rv' );

	/**
	 * Report being filled.
	 *
	 * @var Report
	 */
	private $rep;

	/**
	 * Catalog modules: block name => entry.
	 *
	 * @var array<string, array>
	 */
	private $catalog;

	/**
	 * Known decoration group names (set).
	 *
	 * @var array<string, true>
	 */
	private $decoration_groups;

	/**
	 * Catalog Divi version ("?" when unknown).
	 *
	 * @var string
	 */
	private $catalog_version;

	/**
	 * gcid => color, from the export's global_colors (contrast checks).
	 *
	 * @var array<string, mixed>
	 */
	private $global_color_values = array();

	/**
	 * Extra layout-tree hints (beyond validate.py).
	 *
	 * @var bool
	 */
	private $strict_tree_hints;

	/**
	 * Global color ids that already exist on the target site (set).
	 *
	 * @var array<string, true>
	 */
	private $site_colors = array();

	/**
	 * Global variable ids that already exist on the target site (set).
	 *
	 * @var array<string, true>
	 */
	private $site_variables = array();

	/**
	 * @param array|null $catalog           Catalog document (module-catalog.json shape); null = Catalog::load().
	 * @param bool       $strict_tree_hints Flag unprefixed value helpers in icon values.
	 */
	private function __construct( $catalog, $strict_tree_hints ) {
		if ( null === $catalog ) {
			$catalog = Catalog::load();
		}
		$this->rep               = new Report();
		$this->catalog           = isset( $catalog['modules'] ) && is_array( $catalog['modules'] ) ? $catalog['modules'] : array();
		$this->catalog_version   = isset( $catalog['diviVersion'] ) ? Py::str( $catalog['diviVersion'] ) : '?';
		$this->decoration_groups = array();
		foreach ( isset( $catalog['decorationGroups'] ) && is_array( $catalog['decorationGroups'] ) ? $catalog['decorationGroups'] : array() as $group ) {
			$this->decoration_groups[ (string) $group ] = true;
		}
		$this->strict_tree_hints = $strict_tree_hints;
		if ( ! $this->catalog ) {
			$this->rep->warn( 'module catalog missing — run scripts/generate_catalog.py; block names not checked' );
		}
	}

	/**
	 * Validate a Portability export ({"context": "et_builder", "data": {...}, ...}).
	 *
	 * @param array|\stdClass|mixed $doc               Decoded export: json_decode() objects or assoc arrays.
	 * @param array|null            $catalog           Catalog document; null = Catalog::load().
	 * @param bool                  $strict_tree_hints Flag unprefixed LayoutTree helpers in icon values.
	 * @param array                 $site_globals      Ids the target site already has: {colors: [], variables: []}.
	 * @return Report Report with counts = block name => count.
	 */
	public static function validate_doc( $doc, ?array $catalog = null, bool $strict_tree_hints = false, array $site_globals = array() ): Report {
		$v = new self( $catalog, $strict_tree_hints );
		$v->site_globals( $site_globals );
		$v->doc( self::normalize( $doc, self::has_object( $doc ) ) );
		return $v->rep;
	}

	/**
	 * Validate raw block markup (a page's post_content). Runs the same block checks
	 * as one data entry of an export, with label "content" and no shipped globals.
	 *
	 * @param string     $content           Block markup.
	 * @param array|null $catalog           Catalog document; null = Catalog::load().
	 * @param bool       $strict_tree_hints Flag unprefixed LayoutTree helpers in icon values.
	 * @param array      $site_globals      Ids the target site already has: {colors: [], variables: []}.
	 * @return Report
	 */
	public static function validate_content( string $content, ?array $catalog = null, bool $strict_tree_hints = false, array $site_globals = array() ): Report {
		$v = new self( $catalog, $strict_tree_hints );
		$v->site_globals( $site_globals );
		$v->post( 'content', Py::scrub( $content ), array(), array(), array() );
		return $v->rep;
	}

	/**
	 * Remember the ids the target site already has, so references to them are
	 * not reported as missing.
	 *
	 * @param array $site_globals {colors?: string[], variables?: string[]} (ids).
	 * @return void
	 */
	private function site_globals( array $site_globals ): void {
		foreach ( (array) ( $site_globals['colors'] ?? array() ) as $id ) {
			$this->site_colors[ (string) $id ] = true;
		}
		foreach ( (array) ( $site_globals['variables'] ?? array() ) as $id ) {
			$this->site_variables[ (string) $id ] = true;
		}
	}

	// ---- envelope ----------------------------------------------------------------- //

	/**
	 * validate_doc body.
	 *
	 * @param mixed $doc Normalized document.
	 */
	private function doc( $doc ) {
		$rep = $this->rep;
		if ( ! Py::is_dict( $doc ) ) {
			$rep->err( 'top level must be a JSON object' );
			$doc = array();
		}
		$ctx = Py::get( $doc, 'context' );
		if ( 'et_builder' !== $ctx ) {
			$rep->err( "context must be 'et_builder' for page/layout imports (got " . Py::repr( $ctx ) . ')' );
		}
		$data = Py::get( $doc, 'data' );
		if ( ! Py::is_dict( $data ) || ! $data ) {
			$rep->err( "'data' must be a non-empty object {post_id: post_content}" );
			$data = array();
		}
		if ( count( $data ) > 1 ) {
			$rep->warn( "the Visual Builder imports only the first entry of 'data'" );
		}

		$gc_ids = array();
		foreach ( Py::iter( Py::get( $doc, 'global_colors' ) ) as $entry ) {
			if ( $entry instanceof PyList && 2 === count( $entry->items ) && Py::is_dict( $entry->items[1] )
				&& 0 === strpos( Py::str( $entry->items[0] ), 'gcid-' ) ) {
				$gc_ids[ $entry->items[0] ] = true;
				if ( ! Py::has( 'color', $entry->items[1] ) ) {
					$rep->err( 'global color ' . Py::str( $entry->items[0] ) . " has no 'color'" );
				}
			} else {
				$rep->err( 'global_colors entries must be ["gcid-...", {color, status, label}]: ' . Py::slice( Py::str( $entry ), 80 ) );
			}
		}
		$gv_ids = array();
		foreach ( Py::iter( Py::get( $doc, 'global_variables' ) ) as $entry ) {
			$id = Py::is_dict( $entry ) && array_key_exists( 'id', $entry ) ? $entry['id'] : '';
			if ( Py::is_dict( $entry ) && 0 === strpos( Py::str( $id ), 'gvid-' ) ) {
				$gv_ids[ $id ] = true;
				foreach ( array( 'label', 'value', 'type' ) as $k ) {
					if ( ! Py::has( $k, $entry ) ) {
						$rep->err( 'global variable ' . Py::str( $id ) . " missing '{$k}'" );
					}
				}
			} else {
				$rep->err( "global_variables entries must be objects with id 'gvid-...': " . Py::slice( Py::str( $entry ), 80 ) );
			}
		}

		$images = Py::get( $doc, 'images' );
		if ( ! Py::truthy( $images ) ) {
			$images = array();
		}
		if ( Py::is_dict( $images ) ) {
			foreach ( $images as $url => $img ) {
				if ( ! Py::is_dict( $img ) || ! Py::has( 'encoded', $img ) || ! Py::has( 'url', $img ) ) {
					$rep->err( 'images[' . Py::slice( (string) $url, 60 ) . "] needs 'encoded' (base64) and 'url'" );
				}
			}
		} else {
			$rep->err( "'images' must be an object keyed by URL (or an empty list)" );
		}

		$this->global_color_values = array();
		foreach ( Py::iter( Py::get( $doc, 'global_colors' ) ) as $entry ) {
			if ( $entry instanceof PyList && 2 === count( $entry->items ) && Py::is_dict( $entry->items[1] )
				&& Py::truthy( Py::get( $entry->items[1], 'color' ) ) && is_string( $entry->items[0] ) ) {
				$this->global_color_values[ $entry->items[0] ] = $entry->items[1]['color'];
			}
		}

		foreach ( $data as $post_id => $content ) {
			if ( Py::is_dict( $content ) ) {
				$content = array_key_exists( 'post_content', $content ) ? $content['post_content'] : '';
			}
			if ( ! is_string( $content ) ) {
				$rep->err( "data[{$post_id}] must be a string of block markup" );
				continue;
			}
			$this->post( "data[{$post_id}]", $content, $gc_ids, $gv_ids, $images );
		}
	}

	/**
	 * One data entry: block checks, then the per-post global/dynamic/image notes.
	 *
	 * @param string $label   Label ("data[1]").
	 * @param string $content Block markup.
	 * @param array  $gc_ids  Shipped global color ids (set).
	 * @param array  $gv_ids  Shipped global variable ids (set).
	 * @param mixed  $images  Export images.
	 */
	private function post( $label, $content, array $gc_ids, array $gv_ids, $images ) {
		$rep                  = $this->rep;
		list( $counts, $used ) = $this->content( $content, $label );
		foreach ( $counts as $name => $n ) {
			$rep->counts[ $name ] = ( isset( $rep->counts[ $name ] ) ? $rep->counts[ $name ] : 0 ) + $n;
		}

		$colors = isset( $used['color'] ) ? $used['color'] : array();
		foreach ( Py::sorted( array_keys( $colors ) ) as $gcid ) {
			if ( ! isset( $gc_ids[ $gcid ] ) && ! isset( $this->site_colors[ $gcid ] ) && ! in_array( $gcid, self::CUSTOMIZER_COLORS, true ) ) {
				$rep->warn( "global color {$gcid} is referenced but not included in global_colors — it must already exist on the target site" );
			}
		}
		$vars = isset( $used['content'] ) ? $used['content'] : array();
		foreach ( Py::sorted( array_keys( $vars ) ) as $gvid ) {
			if ( ! isset( $gv_ids[ $gvid ] ) && ! isset( $this->site_variables[ $gvid ] ) && 0 === strpos( $gvid, 'gvid-' ) ) {
				$rep->warn( "global variable {$gvid} is referenced but not included in global_variables" );
			}
		}
		$urls = array();
		if ( preg_match_all( '/https?:\/\/[^"\\\\' . Py::WS . ']+?\.(?:jpe?g|png|gif|webp|avif|svg)(?:\?[^"\\\\' . Py::WS . ']*)?/iu', $content, $m ) ) {
			$urls = array_unique( $m[0] );
		}
		$dynamic = isset( $used['dynamic'] ) ? $used['dynamic'] : array();
		foreach ( Py::sorted( array_keys( $dynamic ) ) as $dyn ) {
			$rep->note( "dynamic content '{$dyn}' is used; if it points to a post/page ID it only resolves on the source site" );
		}
		$missing = 0;
		foreach ( $urls as $u ) {
			if ( ! ( Py::is_dict( $images ) && array_key_exists( $u, $images ) ) ) {
				++$missing;
			}
		}
		if ( $missing ) {
			$rep->note( "{$missing} image URL(s) are not embedded; they will load from their current host "
				. '(use write_layout(..., embed_images=True) to import them into the media library)' );
		}
	}

	// ---- block markup -------------------------------------------------------------- //

	/**
	 * validate_content(): scan the markup block by block.
	 *
	 * @param string $content Markup (valid UTF-8).
	 * @param string $label   Label.
	 * @return array{0: array<string, int>, 1: array<string, array<string, true>>} Counts and used variables by type.
	 */
	private function content( $content, $label ) {
		$rep = $this->rep;
		if ( '' === Py::strip( $content ) ) {
			$rep->err( "{$label}: empty post content" );
			return array( array(), array() );
		}
		if ( 0 !== strpos( Py::strip( $content ), '<!-- wp:divi/placeholder' ) ) {
			$rep->warn( "{$label}: content is not wrapped in <!-- wp:divi/placeholder --> (Divi 5 exports always are)" );
		}

		$matches = BlockScanner::scan( $content );

		$stack    = array(); // List of [name, attrs].
		$counts   = array();
		$used     = array(
			'color'   => array(),
			'content' => array(),
		);
		$last_end = 0;
		$line     = 1;
		$line_at  = 0;

		foreach ( $matches as $m ) {
			$start   = $m['start'];
			$between = Py::strip( substr( $content, $last_end, $start - $last_end ) );
			if ( '' !== $between && $stack ) {
				$rep->warn( "{$label}: stray text between blocks inside " . end( $stack )[0] . ': ' . Py::repr( Py::slice( $between, 60 ) ) );
			}
			$last_end = $m['end'];
			$closer   = $m['closer'];
			$name     = $m['name'];
			$raw      = $m['raw'];
			$void     = $m['void'];
			if ( false === strpos( $name, '/' ) ) {
				$name = 'core/' . $name;
			}
			$line   += substr_count( $content, "\n", $line_at, $start - $line_at );
			$line_at = $start;
			$where   = "{$label} line {$line} {$name}";

			if ( $closer ) {
				if ( ! $stack ) {
					$rep->err( "{$where}: closing tag without opener" );
					continue;
				}
				$open = array_pop( $stack )[0];
				if ( $open !== $name ) {
					$rep->err( "{$where}: closes {$name} but {$open} is open" );
				}
				continue;
			}

			$attrs = array();
			if ( null !== $raw ) {
				$raw = Py::strip( $raw );
				if ( false !== strpos( $raw, '--' ) ) {
					$rep->err( "{$where}: raw '--' inside attributes (must be \\u002d\\u002d) — breaks the HTML comment" );
				}
				if ( false !== strpos( $raw, '<' ) || false !== strpos( $raw, '>' ) ) {
					$rep->warn( "{$where}: raw '<' or '>' inside attributes (Divi escapes them as \\u003c / \\u003e)" );
				}
				try {
					$attrs = PyJson::loads( $raw );
				} catch ( PyJsonError $e ) {
					$rep->err( "{$where}: attributes are not valid JSON (" . $e->getMessage() . ')' );
					$attrs = array();
				}
				if ( ! Py::is_dict( $attrs ) ) {
					$rep->err( "{$where}: attributes must be a JSON object" );
					$attrs = array();
				}
			}

			$counts[ $name ] = ( isset( $counts[ $name ] ) ? $counts[ $name ] : 0 ) + 1;
			$parent          = $stack ? end( $stack )[0] : null;
			$parent_attrs    = $stack ? end( $stack )[1] : array();

			$this->block_rules( $name, $attrs, $void, $parent, $parent_attrs, $where );

			$filtered = array();
			foreach ( $attrs as $k => $v ) {
				if ( ! in_array( (string) $k, self::META_KEYS, true ) ) {
					$filtered[ $k ] = $v;
				}
			}
			$this->check_responsive( $filtered, $where, 0 );

			$bg = Py::dig( $attrs, 'module', 'decoration', 'background' );
			if ( Py::is_dict( $bg ) ) {
				foreach ( $bg as $bp => $states ) {
					$img = Py::is_dict( $states ) ? Py::dig( $states, 'value', 'image' ) : null;
					if ( Py::is_dict( $img ) && array_key_exists( 'url', $img ) && ! Py::truthy( $img['url'] ) && 'desktop' === (string) $bp ) {
						$rep->warn( "{$where}: empty background image url on desktop (remove the key or set an image)" );
					}
				}
			}

			$this->collect_variables( $attrs, $where, $used );
			$this->check_contrast( $name, $attrs, $stack, $where );
			if ( $this->strict_tree_hints ) {
				$this->check_tree_helpers( $attrs, $where );
			}

			if ( ! $void ) {
				$stack[] = array( $name, $attrs );
			}
		}

		$trailing = Py::strip( substr( $content, $last_end ) );
		if ( '' !== $trailing ) {
			$rep->warn( "{$label}: text after the last block: " . Py::repr( Py::slice( $trailing, 60 ) ) );
		}
		foreach ( array_reverse( $stack ) as $item ) {
			$rep->err( "{$label}: {$item[0]} is never closed" );
		}
		return array( $counts, $used );
	}

	/**
	 * Name / nesting rules, attribute keys, builderVersion and content sanity.
	 *
	 * @param string      $name         Block name.
	 * @param array       $attrs        Attributes.
	 * @param bool        $void         Self-closing.
	 * @param string|null $parent       Parent block name.
	 * @param array       $parent_attrs Parent attributes.
	 * @param string      $where        Message prefix.
	 */
	private function block_rules( $name, array $attrs, $void, $parent, array $parent_attrs, $where ) {
		$rep     = $this->rep;
		$catalog = $this->catalog;

		if ( 'divi/placeholder' === $name ) {
			if ( null !== $parent ) {
				$rep->err( "{$where}: divi/placeholder must be the outermost block" );
			}
			return;
		}
		if ( 0 !== strpos( $name, 'divi/' ) ) {
			$rep->warn( "{$where}: non-Divi block; the Visual Builder may not edit it" );
			return;
		}
		if ( $catalog && ! isset( $catalog[ $name ] ) ) {
			$rep->err( "{$where}: unknown Divi block name (not in Divi {$this->catalog_version} catalog)" );
			return;
		}

		$info     = isset( $catalog[ $name ] ) && is_array( $catalog[ $name ] ) ? $catalog[ $name ] : array();
		$cat      = isset( $info['category'] ) ? $info['category'] : null;
		$children = isset( $info['children'] ) && is_array( $info['children'] ) ? $info['children'] : array();

		if ( 'divi/section' === $name ) {
			if ( ! in_array( $parent, array( null, 'divi/placeholder' ), true ) ) {
				$rep->err( "{$where}: sections must be top-level (parent is {$parent})" );
			}
		} elseif ( 'divi/global-layout' === $name ) {
			$rep->warn( "{$where}: global-layout points to a Divi Library item on the source site; it will not exist on another site" );
		} elseif ( 'divi/row' === $name || 'divi/row-inner' === $name ) {
			if ( ! in_array( $parent, array( 'divi/section', 'divi/column', 'divi/column-inner', 'divi/global-layout', 'divi/group' ), true ) ) {
				$rep->err( "{$where}: rows go inside a section (or a column for nested rows), not " . Py::str( $parent ) );
			}
		} elseif ( 'divi/column' === $name || 'divi/column-inner' === $name ) {
			if ( ! in_array( $parent, array( 'divi/row', 'divi/row-inner', 'divi/section' ), true ) ) {
				$rep->err( "{$where}: columns go inside a row, not " . Py::str( $parent ) );
			}
		} elseif ( 'child-module' === $cat ) {
			$parents = array();
			foreach ( $catalog as $n => $i ) {
				if ( isset( $i['children'] ) && is_array( $i['children'] ) && in_array( $name, $i['children'], true ) ) {
					$parents[] = (string) $n;
				}
			}
			if ( ! in_array( $parent, $parents, true ) ) {
				$rep->err( "{$where}: child module must be inside " . ( $parents ? implode( ' / ', $parents ) : 'its parent module' ) . ', not ' . Py::str( $parent ) );
			}
		} elseif ( 'fullwidth-module' === $cat ) {
			$kind = 'divi/section' === $parent ? Py::dig( $parent_attrs, 'module', 'advanced', 'type', 'desktop', 'value' ) : null;
			if ( 'divi/section' !== $parent ) {
				$rep->err( "{$where}: fullwidth modules go directly inside a fullwidth section, not " . Py::str( $parent ) );
			} elseif ( 'fullwidth' !== $kind ) {
				$rep->err( "{$where}: parent section needs module.advanced.type.desktop.value = 'fullwidth'" );
			}
		} elseif ( in_array( $parent, array( 'divi/section', 'divi/row', 'divi/row-inner', null, 'divi/placeholder' ), true ) ) {
			$rep->err( "{$where}: modules go inside a column or group, not " . ( null === $parent ? 'the top level' : $parent ) );
		} elseif ( 0 === strpos( $parent, 'divi/' ) && ! in_array( $parent, self::STRUCTURE, true ) && 'divi/group-carousel' !== $parent ) {
			$allowed = isset( $catalog[ $parent ]['children'] ) && is_array( $catalog[ $parent ]['children'] ) ? $catalog[ $parent ]['children'] : array();
			if ( ! in_array( $name, $allowed, true ) ) {
				$rep->err( "{$where}: {$parent} does not accept {$name} as a child" );
			}
		}

		if ( $void && in_array( $name, self::STRUCTURE, true ) ) {
			$rep->warn( "{$where}: empty {$name} (self-closing) — fine if intended, otherwise it has lost its content" );
		}
		if ( $children && $void && 'divi/section' !== $name ) {
			$rep->warn( "{$where}: container module is self-closing (it will have no items)" );
		}

		if ( $catalog && isset( $catalog[ $name ] ) ) {
			$elements = isset( $info['elements'] ) && is_array( $info['elements'] ) ? array_map( 'strval', $info['elements'] ) : array();
			foreach ( $attrs as $key => $val ) {
				$key = (string) $key;
				if ( ! in_array( $key, $elements, true ) && ! in_array( $key, self::META_KEYS, true ) ) {
					$rep->warn( "{$where}: unknown top-level attribute '{$key}' (known: " . implode( ', ', Py::sorted( $elements ) ) . ')' );
				} elseif ( ! in_array( $key, self::META_KEYS, true ) && Py::is_dict( $val ) ) {
					foreach ( $val as $sub => $subval ) {
						$sub = (string) $sub;
						if ( ! in_array( $sub, array( 'innerContent', 'advanced', 'decoration', 'meta' ), true ) ) {
							$rep->warn( "{$where}: '{$key}.{$sub}' — elements only hold innerContent/advanced/decoration/meta" );
						} elseif ( 'decoration' === $sub && Py::is_dict( $subval ) && $this->decoration_groups ) {
							foreach ( Py::keys( $subval ) as $group ) {
								if ( ! isset( $this->decoration_groups[ $group ] ) ) {
									$rep->warn( "{$where}: unknown decoration group '{$key}.decoration.{$group}'" );
								}
							}
						}
					}
				}
			}
		}
		if ( 'divi/global-layout' !== $name && ! array_key_exists( 'builderVersion', $attrs ) ) {
			$rep->warn( "{$where}: no builderVersion (Divi will run legacy migrations on it)" );
		}

		if ( 'divi/text' === $name ) {
			if ( ! Py::truthy( Py::dig( $attrs, 'content', 'innerContent', 'desktop', 'value' ) ) ) {
				$rep->warn( "{$where}: text module without content.innerContent.desktop.value" );
			}
		}
		if ( 'divi/button' === $name ) {
			$val = Py::dig( $attrs, 'button', 'innerContent', 'desktop', 'value' );
			if ( ! Py::is_dict( $val ) || ! Py::truthy( Py::get( $val, 'text' ) ) ) {
				$rep->warn( "{$where}: button without button.innerContent.desktop.value.text" );
			}
		}
		if ( 'divi/image' === $name ) {
			$val = Py::dig( $attrs, 'image', 'innerContent', 'desktop', 'value' );
			if ( ! Py::is_dict( $val ) || ! Py::truthy( Py::get( $val, 'src' ) ) ) {
				$rep->warn( "{$where}: image without image.innerContent.desktop.value.src" );
			} elseif ( ! Py::truthy( Py::get( $val, 'alt' ) ) && ! self::has_alt_attribute( $attrs ) ) {
				$rep->note( "{$where}: image has no alt text" );
			}
		}
	}

	/**
	 * validate.py checks `'"name":"alt"' in json.dumps(attrs, separators=(",", ":"))`.
	 * In compact JSON that substring can only be a "name" key holding the string "alt".
	 *
	 * @param mixed $node Value.
	 * @return bool
	 */
	private static function has_alt_attribute( $node ) {
		if ( $node instanceof PyList ) {
			$node = $node->items;
		} elseif ( Py::is_dict( $node ) && array_key_exists( 'name', $node ) && 'alt' === $node['name'] ) {
			return true;
		}
		if ( is_array( $node ) ) {
			foreach ( $node as $child ) {
				if ( is_array( $child ) || $child instanceof PyList ) {
					if ( self::has_alt_attribute( $child ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Warn when a breakpoint key holds something other than {value|hover|sticky: ...}.
	 *
	 * @param mixed  $node  Value.
	 * @param string $path  Path for messages.
	 * @param int    $depth Depth.
	 */
	private function check_responsive( $node, $path, $depth ) {
		if ( $depth > 40 || ! Py::is_dict( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, self::BREAKPOINTS, true ) && Py::is_dict( $value ) && $value
				&& ! array_intersect( Py::keys( $value ), self::STATES ) ) {
				$keys = array_slice( Py::sorted( Py::keys( $value ) ), 0, 4 );
				$this->rep->warn( "{$path}.{$key}: breakpoint object without 'value'/'hover'/'sticky' (got keys " . Py::repr( new PyList( $keys ) ) . ')' );
			} elseif ( Py::is_dict( $value ) ) {
				$this->check_responsive( $value, "{$path}.{$key}", $depth + 1 );
			} elseif ( $value instanceof PyList ) {
				foreach ( $value->items as $i => $item ) {
					$this->check_responsive( $item, "{$path}.{$key}[{$i}]", $depth + 1 );
				}
			}
		}
	}

	/**
	 * Collect $variable(...)$ references from every string value.
	 *
	 * @param mixed  $node  Value.
	 * @param string $where Message prefix.
	 * @param array  $used  Type => set of names (by reference).
	 */
	private function collect_variables( $node, $where, array &$used ) {
		if ( is_string( $node ) ) {
			if ( false === strpos( $node, '$variable(' ) || ! preg_match_all( '/\$variable\((\{.*?\})\)\$/u', $node, $all, PREG_SET_ORDER ) ) {
				return;
			}
			foreach ( $all as $vm ) {
				$ok = false;
				try {
					$var = PyJson::loads( $vm[1] );
					if ( Py::is_dict( $var ) && Py::is_dict( Py::get( $var, 'value' ) ) && array_key_exists( 'value', $var ) && array_key_exists( 'name', $var['value'] ) ) {
						$vname = $var['value']['name'];
						$type  = Py::get( $var, 'type' );
						// Python raises TypeError (caught) for an unhashable type or name.
						if ( ! is_array( $type ) && ! $type instanceof PyList && ! is_array( $vname ) && ! $vname instanceof PyList ) {
							$ok = true;
							if ( is_string( $type ) ) {
								$used[ $type ][ Py::str( $vname ) ] = true;
							}
							if ( 'content' === $type && 0 !== strpos( Py::str( $vname ), 'gvid-' ) ) {
								$used['dynamic'][ Py::str( $vname ) ] = true;
							}
						}
					}
				} catch ( PyJsonError $e ) {
					$ok = false;
				}
				if ( ! $ok ) {
					$this->rep->err( "{$where}: malformed \$variable(...)\$ reference: " . Py::slice( $vm[0], 80 ) );
				}
			}
			return;
		}
		if ( $node instanceof PyList ) {
			$node = $node->items;
		}
		if ( is_array( $node ) ) {
			foreach ( $node as $child ) {
				$this->collect_variables( $child, $where, $used );
			}
		}
	}

	// ---- contrast ------------------------------------------------------------------ //

	/**
	 * Normalize a color for comparison; resolve $variable gcid refs via the export's global_colors.
	 *
	 * @param mixed $value Color value.
	 * @return string|null
	 */
	private function resolve_color( $value ) {
		if ( ! is_string( $value ) || '' === Py::strip( $value ) ) {
			return null;
		}
		if ( 0 === strpos( $value, '$variable(' ) ) {
			if ( ! preg_match( '/"name"[' . Py::WS . ']*:[' . Py::WS . ']*"([^"]+)"/u', $value, $m ) ) {
				return null;
			}
			if ( false !== strpos( $value, '"opacity"' ) ) {
				return null; // Tinted variant, can't compare reliably.
			}
			$value = array_key_exists( $m[1], $this->global_color_values ) ? Py::str( $this->global_color_values[ $m[1] ] ) : $m[1];
		}
		return str_replace( ' ', '', Py::lower( $value ) );
	}

	/**
	 * <element>.decoration.background.desktop.<state>.color
	 *
	 * @param array  $attrs   Attributes.
	 * @param string $element Element.
	 * @param string $state   State.
	 * @return string|null
	 */
	private function bg_color( array $attrs, $element = 'module', $state = 'value' ) {
		return $this->resolve_color( Py::dig( $attrs, $element, 'decoration', 'background', 'desktop', $state, 'color' ) );
	}

	/**
	 * <element>.decoration.font.font.desktop.<state>.color
	 *
	 * @param array  $attrs   Attributes.
	 * @param string $element Element.
	 * @param string $state   State.
	 * @return string|null
	 */
	private function font_color( array $attrs, $element, $state = 'value' ) {
		return $this->resolve_color( Py::dig( $attrs, $element, 'decoration', 'font', 'font', 'desktop', $state, 'color' ) );
	}

	/**
	 * <element>.decoration.border.desktop.<state>.styles.all.color
	 *
	 * @param array  $attrs   Attributes.
	 * @param string $element Element.
	 * @param string $state   State.
	 * @return string|null
	 */
	private function border_color( array $attrs, $element = 'module', $state = 'value' ) {
		return $this->resolve_color( Py::dig( $attrs, $element, 'decoration', 'border', 'desktop', $state, 'styles', 'all', 'color' ) );
	}

	/**
	 * Flag clickable elements that disappear: same background as their container,
	 * or text color equal to their own background (also on hover), and thin buttons.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes.
	 * @param array  $stack Open blocks: list of [name, attrs].
	 * @param string $where Message prefix.
	 */
	private function check_contrast( $name, array $attrs, array $stack, $where ) {
		$rep = $this->rep;
		if ( 'divi/link' === $name ) {
			$own_bg   = $this->bg_color( $attrs );
			$text_col = $this->font_color( $attrs, 'content' );
		} elseif ( 'divi/button' === $name ) {
			$own_bg   = $this->bg_color( $attrs, 'button' );
			$text_col = $this->font_color( $attrs, 'button' );
		} else {
			return;
		}
		$parent_bg = null;
		foreach ( array_reverse( $stack ) as $item ) {
			$parent_bg = $this->bg_color( $item[1] );
			if ( Py::truthy( $parent_bg ) ) {
				break;
			}
		}
		$own   = Py::truthy( $own_bg );
		$pbg   = Py::truthy( $parent_bg );
		$el    = 'divi/link' === $name ? 'content' : 'button';
		$bg_el = 'divi/link' === $name ? 'module' : 'button';

		if ( $own && $pbg && $own_bg === $parent_bg ) {
			$rep->warn( "{$where}: button/link background ({$own_bg}) is the same as its container's — it won't be visible" );
		}
		if ( $own && Py::truthy( $text_col ) && $own_bg === $text_col ) {
			$rep->warn( "{$where}: button/link text color equals its background ({$own_bg})" );
		}

		$hover_bg     = $this->bg_color( $attrs, $bg_el, 'hover' );
		$hover_text   = $this->font_color( $attrs, $el, 'hover' );
		$hover_text   = Py::truthy( $hover_text ) ? $hover_text : $text_col;
		$hover_border = $this->border_color( $attrs, $bg_el, 'hover' );
		$hover_border = Py::truthy( $hover_border ) ? $hover_border : $this->border_color( $attrs, $bg_el );

		if ( Py::truthy( $hover_bg ) && $pbg && $hover_bg === $parent_bg ) {
			if ( ! Py::truthy( $hover_border ) || $hover_border === $parent_bg ) {
				$rep->warn( "{$where}: on hover the button background ({$hover_bg}) matches its container and no "
					. 'contrasting border remains — the button seems to disappear (only text is left)' );
			}
		} elseif ( ( ! Py::truthy( $hover_bg ) || 'transparent' === $hover_bg ) && in_array( $own_bg, array( null, 'transparent' ), true )
			&& Py::truthy( $hover_border ) && $hover_border === $parent_bg ) {
			$rep->warn( "{$where}: on hover the outline color ({$hover_border}) matches the container background — the outline disappears" );
		}

		if ( 'divi/link' === $name ) {
			$icon_col = Py::dig( $attrs, 'icon', 'advanced', 'color', 'desktop' );
			if ( Py::is_dict( $icon_col ) && Py::truthy( Py::get( $icon_col, 'value' ) ) ) {
				$icon_v     = $this->resolve_color( $icon_col['value'] );
				$text_hover = $this->font_color( $attrs, 'content', 'hover' );
				if ( Py::truthy( $icon_v ) && $icon_v === $text_col ) {
					$rep->warn( "{$where}: link icon color repeats the text color — remove it; the icon inherits "
						. 'the text color (and its hover) automatically' );
				} elseif ( Py::truthy( $text_hover ) && ! Py::truthy( Py::get( $icon_col, 'hover' ) ) ) {
					$rep->warn( "{$where}: link icon has a fixed color but no hover color while the text changes on "
						. "hover — the icon won't follow the text" );
				}
			}
		}
		if ( Py::truthy( $hover_bg ) && Py::truthy( $hover_text ) && $hover_bg === $hover_text ) {
			$rep->warn( "{$where}: on hover the text color equals the button background ({$hover_bg})" );
		}
		if ( 'divi/button' === $name ) {
			$pad = Py::dig( $attrs, 'button', 'decoration', 'spacing', 'desktop', 'value', 'padding' );
			if ( ! Py::truthy( $pad ) ) {
				$rep->note( "{$where}: button without explicit padding renders thin; prefer link() / patterns.pill_link" );
			}
		}
	}

	// ---- strict layout-tree hints (beyond validate.py) ----------------------------- //

	/**
	 * Flag icon values that look like an unprefixed LayoutTree value helper
	 * (`{"fa": ...}` instead of `{"$fa": ...}`): LayoutTree passes unprefixed keys
	 * through as plain data, which gives Divi an invalid icon value.
	 *
	 * Looks at `<icon element>.innerContent`, each breakpoint and state value under it
	 * (elements whose name contains "icon" or "marker"), and `<any element>.innerContent.<bp>.<state>.icon`.
	 *
	 * @param array  $attrs Attributes.
	 * @param string $where Message prefix.
	 */
	private function check_tree_helpers( array $attrs, $where ) {
		foreach ( $attrs as $element => $spec ) {
			$element = (string) $element;
			$inner   = Py::get( $spec, 'innerContent' );
			if ( in_array( $element, self::META_KEYS, true ) || ! Py::is_dict( $inner ) ) {
				continue;
			}
			$is_icon    = (bool) preg_match( '/icon|marker/i', $element );
			$candidates = array();
			if ( $is_icon ) {
				$candidates[ "{$element}.innerContent" ] = $inner;
			}
			foreach ( $inner as $bp => $states ) {
				if ( ! Py::is_dict( $states ) ) {
					continue;
				}
				if ( $is_icon ) {
					$candidates[ "{$element}.innerContent.{$bp}" ] = $states;
				}
				foreach ( $states as $state => $value ) {
					if ( $is_icon ) {
						$candidates[ "{$element}.innerContent.{$bp}.{$state}" ] = $value;
					}
					if ( Py::is_dict( $value ) && array_key_exists( 'icon', $value ) ) {
						$candidates[ "{$element}.innerContent.{$bp}.{$state}.icon" ] = $value['icon'];
					}
				}
			}
			foreach ( $candidates as $path => $value ) {
				if ( ! Py::is_dict( $value ) ) {
					continue;
				}
				foreach ( self::TREE_HELPER_KEYS as $helper ) {
					if ( array_key_exists( $helper, $value ) ) {
						$this->rep->warn( "{$where}: icon value '{$path}' has the key '{$helper}' — that is an unprefixed value helper, write {\"\${$helper}\": ...} in the layout tree" );
						break;
					}
				}
			}
		}
	}

	// ---- input normalization ------------------------------------------------------- //

	/**
	 * Whether the decoded input uses objects (json_decode without assoc).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function has_object( $value ) {
		if ( $value instanceof \stdClass ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $child ) {
				if ( ( is_array( $child ) || is_object( $child ) ) && self::has_object( $child ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Convert decoded JSON to the PyJson model (dict = PHP array, list = PyList).
	 *
	 * With json_decode() objects every PHP array is a list. With assoc arrays a
	 * non-empty 0..n-1 array is a list and anything else (including []) a dict,
	 * since assoc decoding cannot tell {} from [].
	 *
	 * @param mixed $value   Value.
	 * @param bool  $objects Input uses stdClass for objects.
	 * @return mixed
	 */
	private static function normalize( $value, $objects ) {
		if ( $value instanceof \stdClass ) {
			$out = array();
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$out[ (string) $k ] = self::normalize( $v, $objects );
			}
			return $out;
		}
		if ( $value instanceof PyList ) {
			return new PyList( array_map( static function ( $v ) use ( $objects ) {
				return self::normalize( $v, $objects );
			}, $value->items ) );
		}
		if ( is_array( $value ) ) {
			$is_list = $objects || ( array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 ) );
			$out     = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::normalize( $v, $objects );
			}
			return $is_list ? new PyList( $out ) : $out;
		}
		return is_string( $value ) ? Py::scrub( $value ) : $value;
	}
}
