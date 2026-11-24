<?php
/**
 * Language plugin adapter contract: the only seam between the plugin and Polylang or WPML.
 *
 * An abstract class rather than an interface because it carries the static factory current().
 *
 * @package WPDM
 */

namespace WPDM\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * What the multilingual tools need from a language plugin.
 *
 * Language codes are the language plugin's own codes (en, fr, pt-br...).
 */
abstract class Languages {

	/**
	 * Adapter name: 'polylang' or 'wpml'.
	 *
	 * @return string
	 */
	abstract public function name(): string;

	/**
	 * Configured languages.
	 *
	 * @return array<int,array{code:string,name:string,locale:string,default:bool}>
	 */
	abstract public function languages(): array;

	/**
	 * Code of the default language.
	 *
	 * @return string
	 */
	abstract public function default_language(): string;

	/**
	 * Whether the language plugin translates a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	abstract public function is_translatable( string $post_type ): bool;

	/**
	 * Language code of a post, or null when it has none.
	 *
	 * @param int $id Post id.
	 * @return string|null
	 */
	abstract public function post_language( int $id ): ?string;

	/**
	 * Translation group of a post, including the post itself.
	 *
	 * @param int $id Post id.
	 * @return array<string,int> Language code => post id.
	 */
	abstract public function translations( int $id ): array;

	/**
	 * Set the language of a post.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return void
	 */
	abstract public function set_language( int $id, string $code ): void;

	/**
	 * Add a post to the translation group of a source post.
	 *
	 * @param int    $source_id      Source post id.
	 * @param int    $translation_id Translation post id.
	 * @param string $code           Language code of the translation.
	 * @return void
	 */
	abstract public function link( int $source_id, int $translation_id, string $code ): void;

	/**
	 * Id of the translation of a post in a language, or null.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return int|null
	 */
	abstract public function translation_of( int $id, string $code ): ?int;

	/**
	 * Whether Polylang is active.
	 *
	 * @return bool
	 */
	private static function polylang_active(): bool {
		return (bool) apply_filters( 'wpdm_polylang_active', function_exists( 'pll_languages_list' ) );
	}

	/**
	 * Whether WPML is active (its constant and the filter its hooks answer on).
	 *
	 * @return bool
	 */
	private static function wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) && has_filter( 'wpml_active_languages' );
	}

	/**
	 * The active adapter, or null when no language plugin is active.
	 *
	 * Polylang wins when both are present. Filter `wpdm_languages` can replace the result
	 * (tests use it to force null).
	 *
	 * @return Languages|null
	 */
	public static function current(): ?Languages {
		$current = null;
		if ( self::polylang_active() ) {
			$current = new Polylang();
		} elseif ( self::wpml_active() ) {
			$current = new Wpml();
		}
		$current = apply_filters( 'wpdm_languages', $current );
		return $current instanceof Languages ? $current : null;
	}
}
