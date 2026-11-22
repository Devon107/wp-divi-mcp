<?php
/**
 * divi_find_icon.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword search over the Divi 5 icon list (port of find_icon.py).
 */
final class FindIcon extends Tool {

	const MAX_LIMIT = 100;

	/** @var array|null */
	private static $icons = null;

	public function name(): string {
		return 'divi_find_icon';
	}

	public function description(): string {
		return 'Search the Divi 5 icons (Divi icon font and Font Awesome) by keywords, e.g. "tooth", "arrow right", "phone". Each result has a "value" ready to use as an icon in a layout tree: {"$fa": "&#x...;", "weight": "900"} or {"$divi_icon": "&#x...;"}.';
	}

	public function input_schema(): array {
		return DiviSupport::schema(
			array(
				'query' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Keywords (English), space separated.',
				),
				'limit' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_LIMIT,
					'description' => 'Most results to return. Default 20.',
				),
			),
			array( 'query' )
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args  = PostSupport::validate( $this->input_schema(), $args );
		$limit = min( self::MAX_LIMIT, $args['limit'] ?? 20 );
		$words = preg_split( '/\s+/', strtolower( trim( $args['query'] ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			throw new ToolError( 'The query is empty.', 'invalid_argument' );
		}

		$hits = array();
		foreach ( self::icons() as $i => $icon ) {
			$name_tokens = preg_split( '/\s+/', str_replace( '-', ' ', strtolower( (string) ( $icon['n'] ?? '' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
			$tags        = preg_split( '/\s+/', strtolower( (string) ( $icon['t'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
			$tokens      = array_values( array_unique( array_merge( $name_tokens, $tags ) ) );
			$joined      = implode( ' ', $tokens );
			$score       = 0;
			foreach ( $words as $w ) {
				if ( in_array( $w, $name_tokens, true ) ) {
					$score += 4;
				} elseif ( in_array( $w, $tokens, true ) ) {
					$score += 3;
				} elseif ( self::any_prefix( $tokens, $w ) ) {
					$score += 2;
				} elseif ( false !== strpos( $joined, $w ) ) {
					$score += 1;
				}
			}
			if ( $score ) {
				$hits[] = array( $score, $i, $icon );
			}
		}
		// Highest score first; ties keep the list order (stable on PHP 7.4 too).
		usort(
			$hits,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0] ?: $a[1] <=> $b[1];
			}
		);

		$out = array();
		foreach ( array_slice( $hits, 0, $limit ) as $hit ) {
			$out[] = self::format( $hit[2], $hit[0] );
		}
		return array(
			'query' => $args['query'],
			'icons' => $out,
		);
	}

	/**
	 * @param array $icon  Raw entry {n, u, s, w, t}.
	 * @param int   $score Score.
	 * @return array
	 */
	private static function format( array $icon, int $score ): array {
		$styles  = array_values( (array) ( $icon['s'] ?? array() ) );
		$unicode = (string) ( $icon['u'] ?? '' );
		$divi    = in_array( 'divi', $styles, true );
		$weight  = (string) ( $icon['w'] ?? '900' );
		return array(
			'name'    => (string) ( $icon['n'] ?? '' ),
			'unicode' => $unicode,
			'family'  => $divi ? 'divi' : 'fa',
			'weight'  => $weight,
			'styles'  => $styles,
			'value'   => $divi ? array( '$divi_icon' => $unicode ) : array(
				'$fa'    => $unicode,
				'weight' => $weight,
			),
			'score'   => $score,
		);
	}

	/**
	 * @param string[] $tokens Tokens.
	 * @param string   $word   Word.
	 * @return bool
	 */
	private static function any_prefix( array $tokens, string $word ): bool {
		foreach ( $tokens as $t ) {
			if ( 0 === strpos( $t, $word ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array[]
	 * @throws ToolError When the bundled list cannot be read.
	 */
	private static function icons(): array {
		if ( null === self::$icons ) {
			$raw  = file_get_contents( dirname( __DIR__, 3 ) . '/knowledge/icons.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$list = false === $raw ? null : json_decode( $raw, true );
			if ( ! is_array( $list ) ) {
				throw new ToolError( 'The bundled icon list (knowledge/icons.json) cannot be read.', 'internal_error' );
			}
			self::$icons = $list;
		}
		return self::$icons;
	}
}
