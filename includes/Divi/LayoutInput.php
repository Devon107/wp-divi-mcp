<?php
/**
 * The one way Divi tools accept a layout: a tree, raw markup or a Portability export.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves exactly one of `layout` (LayoutTree), `markup` (wp:divi/ block
 * markup) or `portability` (Divi export envelope) into post content plus a
 * Portability envelope, and validates it.
 */
final class LayoutInput {

	const SOURCES = array( 'layout', 'markup', 'portability' );

	/** Deepest nesting accepted in a layout tree. */
	const MAX_DEPTH = 64;

	/** Most arrays/objects accepted in a layout tree. */
	const MAX_NODES = 5000;

	/** Largest markup accepted, in bytes. */
	const MAX_BYTES = 8388608;

	/**
	 * Schema properties for the three sources (layout's own schema is added
	 * by the tool layer, which hoists its $defs).
	 *
	 * @return array
	 */
	public static function markup_properties(): array {
		return array(
			'markup'      => array(
				'type'        => 'string',
				'description' => 'Raw Divi 5 block markup (<!-- wp:divi/... -->), e.g. a page\'s post_content. Use instead of layout/portability.',
			),
			'portability' => array(
				'type'        => 'object',
				'description' => 'A Divi Portability export ({"context": "et_builder", "data": {...}, "global_colors": [...], "images": {...}}). Use instead of layout/markup.',
			),
		);
	}

	/**
	 * Resolve and validate the layout given in $args.
	 *
	 * @param array $args   Tool arguments; exactly one of layout, markup, portability.
	 * @param bool  $strict Throw validation_failed on validation errors.
	 * @return array{source: string, content: string, envelope: array, report: Report, images: string[]}
	 *         images = image URLs referenced by a layout tree (empty for the other sources).
	 * @throws ToolError invalid_input, too_complex, too_large, invalid_layout, validation_failed.
	 */
	public static function resolve( array $args, bool $strict = true ): array {
		$given = array();
		foreach ( self::SOURCES as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$given[] = $key;
			}
		}
		if ( 1 !== count( $given ) ) {
			throw new ToolError(
				'Pass exactly one of layout (a layout tree), markup (block markup) or portability (a Divi export); got ' . ( $given ? implode( ', ', $given ) : 'none' ) . '.',
				'invalid_input'
			);
		}
		$source = $given[0];
		$site   = self::site_globals();
		$images = array();

		if ( 'layout' === $source ) {
			$tree = $args['layout'];
			if ( ! is_array( $tree ) ) {
				throw new ToolError( 'layout must be a layout tree object.', 'invalid_input' );
			}
			self::check_complexity( $tree );
			if ( ! isset( $tree['builder_version'] ) && null !== Env::divi_version() ) {
				$tree['builder_version'] = Env::divi_version();
			}
			try {
				$built = LayoutTree::build( $tree );
			} catch ( LayoutError $e ) {
				throw new ToolError( $e->getMessage(), 'invalid_layout', array( 'path' => $e->path() ) );
			} catch ( \InvalidArgumentException $e ) {
				throw new ToolError( $e->getMessage(), 'invalid_layout', array( 'path' => '' ) );
			}
			$content  = $built['content'];
			$envelope = $built['envelope'];
			$images   = $built['images'];
			$report   = Validator::validate_doc( $envelope, null, true, $site );
		} elseif ( 'markup' === $source ) {
			if ( ! is_string( $args['markup'] ) ) {
				throw new ToolError( 'markup must be a string of block markup.', 'invalid_input' );
			}
			$content = $args['markup'];
			self::check_size( $content );
			$envelope = self::envelope( $content );
			$report   = Validator::validate_content( $content, null, false, $site );
		} else {
			$doc = $args['portability'];
			if ( ! is_array( $doc ) ) {
				throw new ToolError( 'portability must be a Divi export object.', 'invalid_input' );
			}
			$content = self::first_content( $doc );
			self::check_size( $content );
			$envelope = $doc;
			$report   = Validator::validate_doc( $doc, null, false, $site );
		}

		if ( $strict && ! $report->ok() ) {
			throw new ToolError(
				'The layout has ' . count( $report->errors ) . ' validation error(s); nothing was changed. First: ' . $report->errors[0],
				'validation_failed',
				array( 'report' => $report->to_array() )
			);
		}

		return array(
			'source'   => $source,
			'content'  => $content,
			'envelope' => $envelope,
			'report'   => $report,
			'images'   => $images,
		);
	}

	/**
	 * Portability envelope around raw markup (no globals, no images).
	 *
	 * @param string $content Block markup.
	 * @param string $post_id Key in data.
	 * @param array  $extra   Keys to override (global_colors, page_settings_meta, ...).
	 * @return array
	 */
	public static function envelope( string $content, string $post_id = '1', array $extra = array() ): array {
		return array_merge(
			array(
				'context'            => 'et_builder',
				'data'               => array( $post_id => $content ),
				'presets'            => null,
				'global_colors'      => array(),
				'global_variables'   => array(),
				'page_settings_meta' => array( '_et_pb_custom_css' => array( '' ) ),
				'canvases'           => array(
					'local'  => array(),
					'global' => array(),
				),
				'images'             => array(),
				'thumbnails'         => array(),
			),
			$extra
		);
	}

	/**
	 * Page custom CSS carried by an envelope ('' when none).
	 *
	 * @param array $envelope Envelope.
	 * @return string
	 */
	public static function page_css( array $envelope ): string {
		$css = $envelope['page_settings_meta']['_et_pb_custom_css'] ?? '';
		if ( is_array( $css ) ) {
			$css = $css[0] ?? '';
		}
		return is_string( $css ) ? $css : '';
	}

	/**
	 * Global color and variable ids the site already has (empty without Divi).
	 *
	 * @return array{colors: string[], variables: string[]}
	 */
	public static function site_globals(): array {
		$out = array(
			'colors'    => array(),
			'variables' => array(),
		);
		if ( ! Env::divi_active() ) {
			return $out;
		}
		$globals          = Globals::read();
		$out['colors']    = array_map( 'strval', array_keys( (array) $globals['colors'] ) );
		foreach ( (array) $globals['variables'] as $items ) {
			foreach ( array_keys( (array) $items ) as $id ) {
				$out['variables'][] = (string) $id;
			}
		}
		return $out;
	}

	// ----- internals ---------------------------------------------------------

	/**
	 * First data entry of an export, as a string ('' when missing).
	 *
	 * @param array $doc Export.
	 * @return string
	 */
	private static function first_content( array $doc ): string {
		$data = $doc['data'] ?? null;
		if ( ! is_array( $data ) || ! $data ) {
			return '';
		}
		$content = reset( $data );
		if ( is_array( $content ) ) {
			$content = $content['post_content'] ?? '';
		}
		return is_string( $content ) ? $content : '';
	}

	/**
	 * @param string $content Markup.
	 * @return void
	 * @throws ToolError too_large.
	 */
	private static function check_size( string $content ): void {
		if ( strlen( $content ) > self::MAX_BYTES ) {
			throw new ToolError( 'The markup is larger than 8 MB (' . strlen( $content ) . ' bytes).', 'too_large', array( 'bytes' => strlen( $content ) ) );
		}
	}

	/**
	 * Refuse trees nested deeper than MAX_DEPTH or with more than MAX_NODES
	 * arrays/objects, before any recursive processing. Iterative.
	 *
	 * @param array $tree Tree.
	 * @return void
	 * @throws ToolError too_complex with data.path.
	 */
	private static function check_complexity( array $tree ): void {
		$nodes = 0;
		$stack = array( array( $tree, '', 1 ) );
		while ( $stack ) {
			list( $node, $path, $depth ) = array_pop( $stack );
			++$nodes;
			if ( $nodes > self::MAX_NODES ) {
				throw new ToolError( 'The layout tree has more than ' . self::MAX_NODES . ' objects and lists; split the page or simplify it.', 'too_complex', array( 'path' => '' === $path ? '(root)' : $path ) );
			}
			if ( $depth > self::MAX_DEPTH ) {
				throw new ToolError( 'The layout tree is nested deeper than ' . self::MAX_DEPTH . ' levels.', 'too_complex', array( 'path' => $path ) );
			}
			$is_list = array_values( $node ) === $node;
			$items   = array();
			foreach ( $node as $key => $value ) {
				if ( is_array( $value ) ) {
					$child   = $is_list ? $path . '[' . $key . ']' : ( '' === $path ? (string) $key : $path . '.' . $key );
					$items[] = array( $value, $child, $depth + 1 );
				}
			}
			// Reverse so the walk is depth first in document order.
			foreach ( array_reverse( $items ) as $item ) {
				$stack[] = $item;
			}
		}
	}

	private function __construct() {}
}
