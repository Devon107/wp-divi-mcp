<?php
/**
 * Read-only views of Divi 5 block markup (port of inspect_layout.py).
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Outline, per-section attrs, real block examples, attribute paths and the
 * global ids a page references. Block comments are found with BlockScanner
 * (linear, no PCRE backtracking on large attributes).
 */
final class Inspector {

	/** Longest snippet in tree(). */
	const SNIPPET = 90;

	/** Longest sample string in paths(). */
	const SAMPLE = 70;

	/** Children names listed per example. */
	const EXAMPLE_CHILDREN = 20;

	/**
	 * Outline of sections, rows and modules with text snippets. Sections hidden
	 * on desktop, tablet and phone are marked [HIDDEN].
	 *
	 * @param string $content Block markup.
	 * @param int    $depth   Levels to show (1 = top-level sections only).
	 * @return string One line per block, indented by two spaces per level.
	 */
	public static function tree( string $content, int $depth = 6 ): string {
		$lines = array();
		self::tree_lines( self::parse( $content ), 0, max( 1, $depth ), $lines );
		return implode( "\n", $lines );
	}

	/**
	 * Top-level section number $n (1-based, inside divi/placeholder) with the
	 * full attrs of every block in it.
	 *
	 * @param string $content Block markup.
	 * @param int    $n       Section number.
	 * @return array{name: string, attrs: array, children: array}
	 * @throws \OutOfRangeException When there is no such section.
	 */
	public static function section( string $content, int $n ): array {
		$nodes = self::parse( $content );
		if ( $n < 1 || $n > count( $nodes ) ) {
			throw new \OutOfRangeException( 'Section ' . $n . ' does not exist; the content has ' . count( $nodes ) . ' section(s) at the top level.' );
		}
		return self::export_node( $nodes[ $n - 1 ] );
	}

	/**
	 * The first $n instances of a block, with their attrs and children names.
	 *
	 * @param string $content Block markup.
	 * @param string $block   Block name, e.g. "divi/tabs".
	 * @param int    $n       How many to return.
	 * @return array{count: int, examples: array}
	 */
	public static function example( string $content, string $block, int $n = 1 ): array {
		$found = array();
		foreach ( self::flatten( self::parse( $content ) ) as $node ) {
			if ( $node['name'] === $block ) {
				$found[] = $node;
			}
		}
		$examples = array();
		foreach ( array_slice( $found, 0, max( 0, $n ) ) as $node ) {
			$examples[] = array(
				'attrs'    => $node['attrs'],
				'children' => array_slice( array_column( $node['children'], 'name' ), 0, self::EXAMPLE_CHILDREN ),
			);
		}
		return array(
			'count'    => count( $found ),
			'examples' => $examples,
		);
	}

	/**
	 * Every attribute path used, per block type, with a sample value.
	 * Lists of objects appear as "[]" in the path.
	 *
	 * @param string $content Block markup.
	 * @param string $block   Only this block ('' = all).
	 * @return array<string, array<string, mixed>> Block name => path => sample, both sorted.
	 */
	public static function paths( string $content, string $block = '' ): array {
		$paths = array();
		foreach ( self::flatten( self::parse( $content ) ) as $node ) {
			if ( ( '' !== $block && $node['name'] !== $block ) || ! $node['attrs'] ) {
				continue;
			}
			$found = $paths[ $node['name'] ] ?? array();
			self::walk_paths( $node['attrs'], array(), $found );
			if ( $found ) {
				$paths[ $node['name'] ] = $found;
			}
		}
		ksort( $paths, SORT_STRING );
		foreach ( $paths as $name => $items ) {
			ksort( $items, SORT_STRING );
			$paths[ $name ] = $items;
		}
		return $paths;
	}

	/**
	 * Global color and variable ids referenced anywhere in the content.
	 *
	 * @param string $content Block markup.
	 * @return array{colors: string[], variables: string[]} Sorted, unique.
	 */
	public static function globals( string $content ): array {
		$out = array(
			'colors'    => array(),
			'variables' => array(),
		);
		// One character class with a possessive repeat: linear, no backtracking.
		if ( preg_match_all( '/(?:gcid|gvid)-[A-Za-z0-9-]++/', $content, $m ) ) {
			foreach ( array_unique( $m[0] ) as $id ) {
				$out[ 0 === strpos( $id, 'gcid-' ) ? 'colors' : 'variables' ][] = $id;
			}
		}
		sort( $out['colors'], SORT_STRING );
		sort( $out['variables'], SORT_STRING );
		return $out;
	}

	// ----- internals ---------------------------------------------------------

	/**
	 * Top-level nodes {name, attrs, children}; the divi/placeholder wrapper is
	 * unwrapped when it is the only top-level block. Only divi/ blocks count.
	 *
	 * @param string $content Block markup.
	 * @return array[]
	 */
	private static function parse( string $content ): array {
		$content = Py::scrub( $content );
		$root    = array(
			'name'     => 'ROOT',
			'attrs'    => array(),
			'children' => array(),
		);
		// Stack of references to open nodes.
		$stack = array( &$root );
		foreach ( BlockScanner::scan( $content ) as $m ) {
			if ( 0 !== strpos( $m['name'], 'divi/' ) ) {
				continue;
			}
			if ( $m['closer'] ) {
				if ( count( $stack ) > 1 ) {
					array_pop( $stack );
				}
				continue;
			}
			$attrs = array();
			if ( null !== $m['raw'] ) {
				$decoded = json_decode( $m['raw'], true );
				$attrs   = is_array( $decoded ) ? $decoded : array( '_unparsed' => substr( trim( $m['raw'] ), 0, 80 ) );
			}
			$top                = count( $stack ) - 1;
			$stack[ $top ]['children'][] = array(
				'name'     => $m['name'],
				'attrs'    => $attrs,
				'children' => array(),
			);
			if ( ! $m['void'] ) {
				$last    = count( $stack[ $top ]['children'] ) - 1;
				$stack[] = &$stack[ $top ]['children'][ $last ];
			}
		}
		unset( $stack );
		$top = $root['children'];
		if ( 1 === count( $top ) && 'divi/placeholder' === $top[0]['name'] ) {
			return $top[0]['children'];
		}
		return $top;
	}

	/**
	 * @param array[] $nodes Nodes.
	 * @return array[] Every node, depth first.
	 */
	private static function flatten( array $nodes ): array {
		$out   = array();
		$queue = array_reverse( $nodes );
		while ( $queue ) {
			$node  = array_pop( $queue );
			$out[] = $node;
			foreach ( array_reverse( $node['children'] ) as $child ) {
				$queue[] = $child;
			}
		}
		return $out;
	}

	/**
	 * @param array[]  $nodes Nodes.
	 * @param int      $level Level.
	 * @param int      $depth Max levels.
	 * @param string[] $lines Output.
	 * @return void
	 */
	private static function tree_lines( array $nodes, int $level, int $depth, array &$lines ): void {
		$i = 0;
		foreach ( $nodes as $node ) {
			++$i;
			$label = str_replace( 'divi/', '', $node['name'] );
			if ( 'divi/section' === $node['name'] ) {
				$label = 'SECTION ' . $i;
				if ( self::hidden( $node['attrs'] ) ) {
					$label .= ' [HIDDEN]';
				}
			}
			$extra = '';
			if ( 'divi/row' === $node['name'] ) {
				$cs = self::dig( $node['attrs'], array( 'module', 'advanced', 'columnStructure', 'desktop', 'value' ) );
				if ( is_string( $cs ) && '' !== $cs ) {
					$extra = ' (' . $cs . ')';
				}
			}
			$lines[] = rtrim( str_repeat( '  ', $level ) . $label . $extra . '  ' . self::snippet( $node['attrs'] ) );
			if ( $level + 1 < $depth ) {
				self::tree_lines( $node['children'], $level + 1, $depth, $lines );
			}
		}
	}

	/**
	 * Hidden on desktop, tablet and phone.
	 *
	 * @param array $attrs Attrs.
	 * @return bool
	 */
	private static function hidden( array $attrs ): bool {
		$dis = self::dig( $attrs, array( 'module', 'decoration', 'disabledOn' ) );
		if ( ! is_array( $dis ) ) {
			return false;
		}
		foreach ( array( 'desktop', 'tablet', 'phone' ) as $bp ) {
			if ( ! isset( $dis[ $bp ] ) || ! is_array( $dis[ $bp ] ) || ( $dis[ $bp ]['value'] ?? null ) !== 'on' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Text of a block: admin label, then innerContent values.
	 *
	 * @param array $attrs Attrs.
	 * @return string
	 */
	private static function snippet( array $attrs ): string {
		$texts = array();
		self::walk_snippet( $attrs, $texts );
		$text = implode( ' ', $texts );
		$text = html_entity_decode( (string) preg_replace( '/<[^>]+>/', ' ', $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = Py::strip( (string) preg_replace( '/[' . Py::WS . ']+/u', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::SNIPPET, 'UTF-8' ) : substr( $text, 0, self::SNIPPET );
	}

	/**
	 * Admin labels go to the front, like inspect_layout.py.
	 *
	 * @param mixed    $node  Value.
	 * @param string[] $texts Collected texts.
	 * @return void
	 */
	private static function walk_snippet( $node, array &$texts ): void {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( 'innerContent' === $key ) {
				$val = is_array( $value ) && isset( $value['desktop'] ) && is_array( $value['desktop'] ) ? ( $value['desktop']['value'] ?? null ) : null;
				if ( is_string( $val ) ) {
					$texts[] = $val;
				} elseif ( is_array( $val ) ) {
					foreach ( $val as $k => $x ) {
						if ( in_array( $k, array( 'text', 'title', 'src', 'linkUrl' ), true ) && is_scalar( $x ) ) {
							$texts[] = (string) $x;
						}
					}
				}
			} elseif ( 'adminLabel' === $key ) {
				$val = is_array( $value ) && isset( $value['desktop'] ) && is_array( $value['desktop'] ) ? ( $value['desktop']['value'] ?? null ) : null;
				if ( is_scalar( $val ) && '' !== (string) $val ) {
					array_unshift( $texts, '[' . $val . ']' );
				}
			} else {
				self::walk_snippet( $value, $texts );
			}
		}
	}

	/**
	 * @param mixed $value  Value.
	 * @param array $path   Path so far.
	 * @param array $out    path => sample (first seen wins).
	 * @return void
	 */
	private static function walk_paths( $value, array $path, array &$out ): void {
		if ( is_array( $value ) && $value && ! self::is_list( $value ) ) {
			foreach ( $value as $k => $v ) {
				$next   = $path;
				$next[] = (string) $k;
				self::walk_paths( $v, $next, $out );
			}
			return;
		}
		if ( is_array( $value ) && $value && is_array( $value[0] ) && ! self::is_list( $value[0] ) ) {
			$next   = $path;
			$next[] = '[]';
			foreach ( $value as $item ) {
				self::walk_paths( $item, $next, $out );
			}
			return;
		}
		$key = implode( '.', $path );
		if ( ! array_key_exists( $key, $out ) ) {
			if ( is_string( $value ) && strlen( $value ) > self::SAMPLE ) {
				$value = ( function_exists( 'mb_strcut' ) ? mb_strcut( $value, 0, self::SAMPLE, 'UTF-8' ) : substr( $value, 0, self::SAMPLE ) ) . '…';
			}
			$out[ $key ] = $value;
		}
	}

	/**
	 * @param array $node Parsed node.
	 * @return array{name: string, attrs: array, children: array}
	 */
	private static function export_node( array $node ): array {
		return array(
			'name'     => $node['name'],
			'attrs'    => $node['attrs'],
			'children' => array_map( array( self::class, 'export_node' ), $node['children'] ),
		);
	}

	/**
	 * @param array    $value Array.
	 * @param string[] $keys  Keys.
	 * @return mixed|null
	 */
	private static function dig( array $value, array $keys ) {
		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return null;
			}
			$value = $value[ $k ];
		}
		return $value;
	}

	/**
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ): bool {
		return array_values( $value ) === $value;
	}

	private function __construct() {}
}
