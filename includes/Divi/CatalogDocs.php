<?php
/**
 * Renders the module reference (knowledge/modules/*.md, INDEX.md), the catalog JSON
 * and icons.json from a Divi theme folder.
 *
 * Port of the Markdown / file-writing part of the skill's generate_catalog.py; the
 * catalog data itself comes from CatalogBuilder. Output is byte-identical to the
 * Python script's (checked against Divi 5.19.1): same line order, same hints, and
 * py_dumps() reproduces json.dumps (indent, separators, sort_keys, ensure_ascii).
 *
 * Pure PHP, no WordPress dependency (used by tools/generate-catalog.php).
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Module reference renderer.
 */
final class CatalogDocs {

	const VALUE_HINTS = array(
		'divi/richtext'     => 'HTML string',
		'divi/text'         => 'string',
		'divi/textarea'     => 'string',
		'divi/upload'       => 'URL string',
		'divi/icon-picker'  => 'icon {"unicode","type","weight"}',
		'divi/toggle'       => '"on" or "off"',
		'divi/range'        => 'number or size string (e.g. "10", "24px")',
		'divi/color-picker' => 'color string',
		'divi/date-picker'  => 'date string',
	);

	const WRAPPER_KEYS = array( 'item', 'items', 'groups', 'component', 'props', 'features', 'fields' );

	const ICON_LIST = 'icon-library/src/components/icon-font/iconList.json';

	/**
	 * Render everything for a theme folder.
	 *
	 * @param string $dir Theme root.
	 * @return array{catalog: array, catalog_json: string, modules: array<string, string>, index: string, icons: string|null, icon_count: int}
	 */
	public static function render( string $dir ): array {
		$dir     = rtrim( $dir, '/\\' );
		$catalog = CatalogBuilder::from_theme_dir( $dir );
		$version = $catalog['diviVersion'];
		$modules = array();
		$rows    = array();

		foreach ( CatalogBuilder::module_files( $dir . '/' . CatalogBuilder::PACKAGES ) as $path ) {
			$meta = CatalogBuilder::load( $path );
			if ( ! $meta instanceof \stdClass || ! self::truthy( $meta ) || ! property_exists( $meta, 'name' ) || ! is_string( $meta->name ) || false === strpos( $meta->name, '/' ) ) {
				continue;
			}
			$folder   = dirname( $path );
			$outline  = self::or_obj( CatalogBuilder::load( $folder . '/conversion-outline.json' ) );
			$defaults = CatalogBuilder::load( $folder . '/module-default-render-attributes.json' );
			$defaults = self::truthy( $defaults ) ? $defaults : new \stdClass();

			$name     = $meta->name;
			$slug     = explode( '/', $name, 2 )[1];
			$title    = self::str( self::get( $meta, 'title', '' ) );
			$children = self::get( $meta, 'childrenName' );
			$children = self::truthy( $children ) ? self::items( $children ) : array();
			$category = self::get( $meta, 'category' );
			$category = self::truthy( $category ) ? self::str( $category ) : 'module';
			$d4       = self::get( $meta, 'd4Shortcode' );

			$modules[ $slug ] = self::module_markdown( $meta, $outline, $defaults, $title, $category, $children, $d4 );

			$short = array();
			foreach ( $children as $c ) {
				$parts   = explode( '/', (string) $c, 2 );
				$short[] = $parts[1] ?? '';
			}
			$rows[] = '| `' . $name . '` | ' . $title . ' | ' . $category . ' | '
				. ( $short ? implode( ', ', $short ) : '-' ) . ' | [' . $slug . '.md](' . $slug . '.md) |';
		}

		usort( $rows, 'strcmp' );
		$index = '# Divi 5 module index (generated from Divi ' . $version . ")\n\n"
			. "| Block name | Title | Category | Children | Details |\n|---|---|---|---|---|\n"
			. implode( "\n", $rows ) . "\n";

		$icons      = null;
		$icon_count = 0;
		$list       = CatalogBuilder::load( $dir . '/' . CatalogBuilder::PACKAGES . '/' . self::ICON_LIST );
		if ( is_array( $list ) ) {
			$compact = array();
			foreach ( $list as $i ) {
				if ( $i instanceof \stdClass ) {
					$compact[] = array(
						'n' => self::get( $i, 'name' ),
						'u' => self::get( $i, 'unicode' ),
						's' => self::get( $i, 'styles' ),
						'w' => self::get( $i, 'fontWeight' ),
						't' => self::get( $i, 'searchTerms', '' ),
					);
				}
			}
			$icons      = self::py_dumps( $compact, null, false, false, array( ',', ':' ) );
			$icon_count = count( $compact );
		}

		return array(
			'catalog'      => $catalog,
			'catalog_json' => self::py_dumps( $catalog, 1, true ),
			'modules'      => $modules,
			'index'        => $index,
			'icons'        => $icons,
			'icon_count'   => $icon_count,
		);
	}

	/**
	 * One module's Markdown file.
	 *
	 * @param \stdClass $meta     module.json.
	 * @param \stdClass $outline  conversion-outline.json ({} when absent).
	 * @param mixed     $defaults module-default-render-attributes.json ({} when absent).
	 * @param string    $title    Title.
	 * @param string    $category Category.
	 * @param array     $children Child block names.
	 * @param mixed     $d4       D4 shortcode.
	 * @return string
	 */
	private static function module_markdown( \stdClass $meta, \stdClass $outline, $defaults, string $title, string $category, array $children, $d4 ): string {
		$attrs    = self::or_obj( self::get( $meta, 'attributes', new \stdClass() ) );
		$elements = array();
		foreach ( get_object_vars( $attrs ) as $key => $spec ) {
			if ( ! $spec instanceof \stdClass ) {
				continue;
			}
			$settings                  = self::or_obj( self::get( $spec, 'settings', new \stdClass() ) );
			$elements[ (string) $key ] = array(
				'innerContent' => property_exists( $settings, 'innerContent' ),
				'advanced'     => self::sorted_keys( self::get( $settings, 'advanced' ) ),
				'decoration'   => self::sorted_keys( self::get( $settings, 'decoration' ) ),
			);
		}

		$fields = array();
		$seen   = array();
		foreach ( get_object_vars( $attrs ) as $key => $spec ) {
			if ( ! $spec instanceof \stdClass ) {
				continue;
			}
			$key      = (string) $key;
			$settings = self::or_obj( self::get( $spec, 'settings' ) );
			foreach ( array( 'innerContent', 'advanced' ) as $group ) {
				self::collect( self::get( $settings, $group ), $fields, $seen, $key . '.' . $group, null );
			}
			if ( property_exists( $settings, 'innerContent' ) ) {
				$found = false;
				foreach ( $fields as $f ) {
					if ( 0 === strpos( $f[0], $key . '.innerContent' ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$fields[] = array( $key . '.innerContent', '', 'content' === $key ? 'HTML string' : 'see legacy mapping' );
				}
			}
		}

		$content_map = array();
		self::flatten_outline( self::get( $outline, 'module', new \stdClass() ), '', $content_map );
		$font_map = array();
		$advanced = self::get( $outline, 'advanced' );
		self::flatten_outline( self::get( self::or_obj( $advanced ), 'fonts', new \stdClass() ), '', $font_map );

		$child_list = array();
		foreach ( $children as $c ) {
			$child_list[] = '`' . self::str( $c ) . '`';
		}
		$lines = array(
			'# ' . $meta->name . ' — ' . $title,
			'',
			'- Category: `' . $category . '`',
			'- Divi 4 shortcode: `' . ( self::truthy( $d4 ) ? self::str( $d4 ) : '-' ) . '`',
			'- Allowed children: ' . ( $child_list ? implode( ', ', $child_list ) : 'none' ),
			'',
			'## Elements (top-level attribute keys)',
			'',
			'Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.',
			'',
		);
		foreach ( $elements as $key => $info ) {
			$parts = array();
			if ( $info['innerContent'] ) {
				$parts[] = 'innerContent';
			}
			if ( $info['advanced'] ) {
				$parts[] = 'advanced: ' . implode( ', ', $info['advanced'] );
			}
			if ( $info['decoration'] ) {
				$parts[] = 'decoration: ' . implode( ', ', $info['decoration'] );
			}
			$lines[] = '- `' . $key . '` — ' . ( $parts ? implode( '; ', $parts ) : '(no settings)' );
		}
		if ( $fields ) {
			array_push(
				$lines,
				'',
				'## Divi 5 settings (authoritative)',
				'',
				'Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the',
				'last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);',
				'otherwise the value sits directly at `<path>.desktop.value`.',
				'',
				'| Path | Label | Value |',
				'|---|---|---|'
			);
			foreach ( $fields as $f ) {
				$lines[] = '| `' . $f[0] . '` | ' . $f[1] . ' | ' . $f[2] . ' |';
			}
		}
		if ( $content_map ) {
			array_push(
				$lines,
				'',
				'## Legacy Divi 4 → Divi 5 mapping',
				'',
				"From Divi's D4 conversion file: tells what each classic option means, but where it",
				'disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).',
				'`*` is the breakpoint + state, e.g. `desktop.value`.',
				''
			);
			foreach ( $content_map as $k => $v ) {
				$lines[] = '- `' . $k . '` → `' . $v . '`';
			}
		}
		if ( $font_map ) {
			array_push( $lines, '', '## Font groups', '' );
			foreach ( $font_map as $k => $v ) {
				$lines[] = '- `' . $k . '` → `' . $v . '`';
			}
		}
		if ( self::truthy( $defaults ) ) {
			array_push(
				$lines,
				'',
				'## Defaults applied when an attribute is omitted',
				'',
				'```json',
				substr( self::py_dumps( $defaults, null, false, true, array( ',', ':' ) ), 0, 3000 ),
				'```'
			);
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * collect_settings(): every field item of a settings subtree with the path it saves to.
	 *
	 * @param mixed       $node         Subtree.
	 * @param array       $out          [path, label, hint] rows (appended).
	 * @param array       $seen         Paths already added.
	 * @param string      $default_attr Path for items without attrName.
	 * @param string|null $key_hint     Nearest non-wrapper key.
	 * @return void
	 */
	private static function collect( $node, array &$out, array &$seen, string $default_attr, $key_hint ): void {
		$add = static function ( string $path, string $label, string $hint ) use ( &$out, &$seen ) {
			if ( ! isset( $seen[ $path ] ) ) {
				$seen[ $path ] = true;
				$out[]         = array( $path, $label, str_replace( '|', '/', $hint ) );
			}
		};

		if ( $node instanceof \stdClass ) {
			$comp = self::get( $node, 'component' );
			if ( $comp instanceof \stdClass && self::truthy( $comp ) ) {
				$props     = self::or_obj( self::get( $comp, 'props' ) );
				$node_attr = self::get( $node, 'attrName' );
				$prop_attr = self::get( $props, 'attrName' );
				$base      = self::str( self::truthy( $node_attr ) ? $node_attr : ( self::truthy( $prop_attr ) ? $prop_attr : $default_attr ) );
				$name      = self::str( self::get( $comp, 'name', '' ) );
				$label     = self::str( self::get( $node, 'label', '' ) );
				if ( 'field' === self::get( $comp, 'type' ) && false === strpos( $base, '.decoration.' ) ) {
					$sub = self::get( $node, 'subName' );
					if ( self::truthy( $sub ) ) {
						$path = $base . '.' . self::str( $sub );
					} elseif ( ! self::truthy( $node_attr ) && ! self::truthy( $prop_attr ) && '.advanced' === substr( $base, -9 ) && self::truthy( $key_hint ) ) {
						$path = $base . '.' . $key_hint;
					} else {
						$path = $base;
					}
					$opts = self::get( $props, 'options' );
					$hint = self::VALUE_HINTS[ $name ] ?? str_replace( 'divi/', '', $name ) . ' field';
					if ( $opts instanceof \stdClass && self::truthy( $opts ) ) {
						$keys = array();
						foreach ( array_slice( array_keys( get_object_vars( $opts ) ), 0, 12 ) as $k ) {
							$keys[] = '"' . $k . '"';
						}
						$hint = 'one of: ' . implode( ', ', $keys );
					}
					$features = self::or_obj( self::get( $node, 'features' ) );
					if ( ! self::truthy( self::get( $features, 'responsive', true ) ) ) {
						$hint .= ' (not responsive)';
					}
					$add( $path, $label, $hint );
					$sync = self::get( $props, 'syncImageData' );
					if ( $sync instanceof \stdClass ) {
						foreach ( get_object_vars( $sync ) as $k => $v ) {
							if ( self::truthy( $v ) && 'src' !== (string) $k ) {
								$add( $base . '.' . $k, '(synced from ' . self::str( self::get( $node, 'label', 'image' ) ) . ')', 'string' );
							}
						}
					}
				} elseif ( 'divi/button-link' === $name ) {
					$add( $base . '.linkUrl', 'Link URL', 'string (not responsive)' );
					$add( $base . '.linkTarget', 'Link Target', 'one of: "off", "on" (not responsive)' );
				}
			}
			foreach ( get_object_vars( $node ) as $key => $value ) {
				$key = (string) $key;
				self::collect( $value, $out, $seen, $default_attr, in_array( $key, self::WRAPPER_KEYS, true ) ? $key_hint : $key );
			}
		} elseif ( is_array( $node ) ) {
			foreach ( $node as $value ) {
				self::collect( $value, $out, $seen, $default_attr, $key_hint );
			}
		}
	}

	/**
	 * flatten_outline(): (d4 key, d5 path) pairs into $out (dict() semantics).
	 *
	 * @param mixed  $node   Outline subtree.
	 * @param string $prefix Key prefix.
	 * @param array  $out    Map (appended).
	 * @return void
	 */
	private static function flatten_outline( $node, string $prefix, array &$out ): void {
		if ( is_string( $node ) ) {
			$out[ $prefix ] = $node;
		} elseif ( $node instanceof \stdClass ) {
			foreach ( get_object_vars( $node ) as $key => $value ) {
				self::flatten_outline( $value, '' !== $prefix ? $prefix . '.' . $key : (string) $key, $out );
			}
		}
	}

	// ----- Python semantics -----------------------------------------------------

	/**
	 * json.dumps(value, indent=, sort_keys=, ensure_ascii=, separators=).
	 * PHP arrays with keys 0..n-1 are lists; stdClass and other arrays are objects.
	 *
	 * @param mixed         $value        Value.
	 * @param int|null      $indent       Indent (null = one line).
	 * @param bool          $sort_keys    Sort object keys.
	 * @param bool          $ensure_ascii Escape non-ASCII.
	 * @param string[]|null $separators   [item, key] separators (default like Python).
	 * @param int           $level        Nesting level (internal).
	 * @return string
	 */
	public static function py_dumps( $value, ?int $indent = null, bool $sort_keys = false, bool $ensure_ascii = true, ?array $separators = null, int $level = 0 ): string {
		if ( null === $separators ) {
			$separators = null === $indent ? array( ', ', ': ' ) : array( ',', ': ' );
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( true === $value ) {
			return 'true';
		}
		if ( false === $value ) {
			return 'false';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) ) {
			return self::float_repr( $value );
		}
		if ( is_string( $value ) ) {
			return self::py_string( $value, $ensure_ascii );
		}
		$is_obj = $value instanceof \stdClass;
		$items   = $is_obj ? get_object_vars( $value ) : (array) $value;
		$is_list = ! $is_obj && array_keys( $items ) === range( 0, count( $items ) - 1 );
		if ( ! $items ) {
			return $is_obj ? '{}' : '[]';
		}
		if ( ! $is_list && $sort_keys ) {
			uksort(
				$items,
				static function ( $a, $b ) {
					return strcmp( (string) $a, (string) $b );
				}
			);
		}
		$parts = array();
		foreach ( $items as $k => $v ) {
			$enc     = self::py_dumps( $v, $indent, $sort_keys, $ensure_ascii, $separators, $level + 1 );
			$parts[] = $is_list ? $enc : self::py_string( (string) $k, $ensure_ascii ) . $separators[1] . $enc;
		}
		list( $open, $close ) = $is_list ? array( '[', ']' ) : array( '{', '}' );
		if ( null === $indent ) {
			return $open . implode( $separators[0], $parts ) . $close;
		}
		$inner = "\n" . str_repeat( ' ', $indent * ( $level + 1 ) );
		return $open . $inner . implode( $separators[0] . $inner, $parts ) . "\n" . str_repeat( ' ', $indent * $level ) . $close;
	}

	/**
	 * A JSON string literal the way Python writes it.
	 *
	 * @param string $s            UTF-8 string.
	 * @param bool   $ensure_ascii Escape everything outside printable ASCII.
	 * @return string
	 */
	private static function py_string( string $s, bool $ensure_ascii ): string {
		$pattern = $ensure_ascii ? '/[^\x20\x21\x23-\x5b\x5d-\x7e]/u' : '/[\x00-\x1f\\\\"]/u';
		$out     = preg_replace_callback(
			$pattern,
			static function ( $m ) {
				$c   = $m[0];
				$map = array(
					'"'  => '\\"',
					'\\' => '\\\\',
					"\n" => '\\n',
					"\r" => '\\r',
					"\t" => '\\t',
					"\x08" => '\\b',
					"\x0c" => '\\f',
				);
				if ( isset( $map[ $c ] ) ) {
					return $map[ $c ];
				}
				$cp = self::ord( $c );
				if ( $cp > 0xFFFF ) {
					$cp -= 0x10000;
					return sprintf( '\\u%04x\\u%04x', 0xD800 | ( $cp >> 10 ), 0xDC00 | ( $cp & 0x3FF ) );
				}
				return sprintf( '\\u%04x', $cp );
			},
			$s
		);
		return '"' . ( null === $out ? $s : $out ) . '"';
	}

	/**
	 * Code point of one UTF-8 character.
	 *
	 * @param string $c Character.
	 * @return int
	 */
	private static function ord( string $c ): int {
		$b = array_values( unpack( 'C*', $c ) );
		$n = count( $b );
		if ( 1 === $n ) {
			return $b[0];
		}
		if ( 2 === $n ) {
			return ( ( $b[0] & 0x1F ) << 6 ) | ( $b[1] & 0x3F );
		}
		if ( 3 === $n ) {
			return ( ( $b[0] & 0x0F ) << 12 ) | ( ( $b[1] & 0x3F ) << 6 ) | ( $b[2] & 0x3F );
		}
		return ( ( $b[0] & 0x07 ) << 18 ) | ( ( $b[1] & 0x3F ) << 12 ) | ( ( $b[2] & 0x3F ) << 6 ) | ( $b[3] & 0x3F );
	}

	/**
	 * float.__repr__: shortest round-trip digits, scientific below 1e-4 and from 1e16.
	 *
	 * @param float $f Float.
	 * @return string
	 */
	private static function float_repr( float $f ): string {
		if ( is_nan( $f ) ) {
			return 'NaN';
		}
		if ( is_infinite( $f ) ) {
			return $f > 0 ? 'Infinity' : '-Infinity';
		}
		if ( 0.0 === $f ) {
			return '-' === sprintf( '%.1f', $f )[0] ? '-0.0' : '0.0';
		}
		for ( $p = 0; $p < 17; $p++ ) {
			$s = sprintf( '%.' . $p . 'e', $f );
			if ( (float) $s === $f ) {
				break;
			}
		}
		list( $mant, $exp ) = explode( 'e', $s );
		$exp                = (int) $exp;
		$neg                = '-' === $mant[0];
		$digits             = str_replace( array( '-', '.' ), '', $mant );
		$digits             = rtrim( $digits, '0' );
		$digits             = '' === $digits ? '0' : $digits;
		$sign               = $neg ? '-' : '';
		if ( $exp < -4 || $exp >= 16 ) {
			$m = $digits[0] . ( strlen( $digits ) > 1 ? '.' . substr( $digits, 1 ) : '' );
			return $sign . $m . 'e' . ( $exp < 0 ? '-' : '+' ) . sprintf( '%02d', abs( $exp ) );
		}
		if ( $exp < 0 ) {
			return $sign . '0.' . str_repeat( '0', -$exp - 1 ) . $digits;
		}
		$int  = substr( str_pad( $digits, $exp + 1, '0' ), 0, $exp + 1 );
		$frac = substr( $digits, $exp + 1 );
		return $sign . $int . '.' . ( '' === $frac ? '0' : $frac );
	}

	/**
	 * Python truthiness of a decoded JSON value.
	 *
	 * @param mixed $v Value.
	 * @return bool
	 */
	public static function truthy( $v ): bool {
		if ( $v instanceof \stdClass ) {
			return (bool) get_object_vars( $v );
		}
		if ( is_string( $v ) ) {
			return '' !== $v;
		}
		return (bool) $v;
	}

	/**
	 * str(value) for scalars as Python prints them in an f-string.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function str( $v ): string {
		if ( null === $v ) {
			return 'None';
		}
		if ( is_bool( $v ) ) {
			return $v ? 'True' : 'False';
		}
		if ( is_float( $v ) ) {
			return self::float_repr( $v );
		}
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * dict.get().
	 *
	 * @param mixed  $obj     Object.
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private static function get( $obj, string $key, $default = null ) {
		return $obj instanceof \stdClass && property_exists( $obj, $key ) ? $obj->$key : $default;
	}

	/**
	 * `value or {}`: the value when it is a non-empty object, else an empty object.
	 *
	 * @param mixed $v Value.
	 * @return \stdClass
	 */
	private static function or_obj( $v ): \stdClass {
		return $v instanceof \stdClass ? $v : new \stdClass();
	}

	/**
	 * sorted((value or {}).keys()).
	 *
	 * @param mixed $v Value.
	 * @return string[]
	 */
	private static function sorted_keys( $v ): array {
		if ( ! $v instanceof \stdClass ) {
			return array();
		}
		$keys = array_map( 'strval', array_keys( get_object_vars( $v ) ) );
		usort( $keys, 'strcmp' );
		return $keys;
	}

	/**
	 * Iterate a list (or a dict's keys, as Python iterates dicts).
	 *
	 * @param mixed $v Value.
	 * @return array
	 */
	private static function items( $v ): array {
		if ( $v instanceof \stdClass ) {
			return array_map( 'strval', array_keys( get_object_vars( $v ) ) );
		}
		return is_array( $v ) ? array_values( $v ) : array();
	}

	private function __construct() {}
}
