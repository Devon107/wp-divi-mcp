<?php
/**
 * Yoast SEO adapter: the only code that talks to Yoast.
 *
 * Yoast renders titles and descriptions from its indexables table, which its
 * meta watcher only rebuilds at shutdown. refresh() rebuilds the post's
 * indexable right away, so effective() reflects a write in the same request.
 *
 * @package WPDM
 */

namespace WPDM\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the Yoast SEO title and meta description of a post.
 */
final class Yoast {

	const KEY_TITLE       = 'title';
	const KEY_DESCRIPTION = 'metadesc';

	/**
	 * Whether Yoast SEO is loaded.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		$active = defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' ) && class_exists( '\WPSEO_Meta' );
		return (bool) apply_filters( 'wpdm_yoast_active', $active );
	}

	/**
	 * Yoast version.
	 *
	 * @return string
	 */
	public static function version(): string {
		return defined( 'WPSEO_VERSION' ) ? (string) WPSEO_VERSION : '';
	}

	/**
	 * Stored values ('' when unset).
	 *
	 * @param int $post_id Post id.
	 * @return array{title:string, description:string}
	 */
	public static function stored( int $post_id ): array {
		return array(
			'title'       => (string) \WPSEO_Meta::get_value( self::KEY_TITLE, $post_id ),
			'description' => (string) \WPSEO_Meta::get_value( self::KEY_DESCRIPTION, $post_id ),
		);
	}

	/**
	 * Write the fields: null leaves a field unchanged, '' deletes it (Yoast falls back to its template).
	 *
	 * @param int         $post_id     Post id.
	 * @param string|null $title       Title.
	 * @param string|null $description Description.
	 * @return void
	 */
	public static function write( int $post_id, ?string $title, ?string $description ): void {
		foreach ( array( self::KEY_TITLE => $title, self::KEY_DESCRIPTION => $description ) as $key => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( '' === $value ) {
				\WPSEO_Meta::delete( $key, $post_id );
			} else {
				\WPSEO_Meta::set_value( $key, $value, $post_id );
			}
		}
	}

	/**
	 * Clean a title or description the way Yoast does: strip tags and line breaks, but keep
	 * Yoast variables such as %%date%% (WordPress' sanitize_text_field() would eat "%da").
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function clean( string $value ): string {
		if ( class_exists( '\WPSEO_Utils' ) && method_exists( '\WPSEO_Utils', 'sanitize_text_field' ) ) {
			return (string) \WPSEO_Utils::sanitize_text_field( $value );
		}
		return trim( (string) preg_replace( '`[\r\n\t ]+`', ' ', wp_strip_all_tags( $value, true ) ) );
	}

	/**
	 * Rebuild the post's indexable now and drop Yoast's per-request meta cache.
	 *
	 * @param int $post_id Post id.
	 * @return bool False when Yoast's API could not be used.
	 */
	public static function refresh( int $post_id ): bool {
		try {
			$watcher_class = (string) apply_filters( 'wpdm_yoast_watcher_class', '\Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher' );
			$memoizer_class = '\Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer';
			if ( ! class_exists( $watcher_class ) ) {
				return false;
			}
			$classes = YoastSEO()->classes;
			$classes->get( ltrim( $watcher_class, '\\' ) )->build_indexable( $post_id );
			if ( class_exists( $memoizer_class ) ) {
				$classes->get( ltrim( $memoizer_class, '\\' ) )->clear();
			}
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Values the site renders, with Yoast variables replaced ('' when unknown).
	 *
	 * @param int $post_id Post id.
	 * @return array{title:string, description:string}
	 */
	public static function effective( int $post_id ): array {
		$out = array( 'title' => '', 'description' => '' );
		try {
			$meta = YoastSEO()->meta->for_post( $post_id );
			if ( $meta ) {
				$out['title']       = is_string( $meta->title ) ? $meta->title : '';
				$out['description'] = is_string( $meta->description ) ? $meta->description : '';
			}
		} catch ( \Throwable $e ) {
			return $out;
		}
		return $out;
	}
}
