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
	 * Implementations call guard_link() first.
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
	 * Id of the translation of a term in a language, or null. A term of a taxonomy the language
	 * plugin does not translate is shared by every language, so it is its own translation.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $code     Language code.
	 * @return int|null
	 */
	abstract public function term_translation_of( int $term_id, string $taxonomy, string $code ): ?int;

	/**
	 * Throw unless the post exists.
	 *
	 * @param int $id Post id.
	 * @return string The post type.
	 * @throws \InvalidArgumentException When the post does not exist.
	 */
	protected function require_post( int $id ): string {
		$post_type = get_post_type( $id );
		if ( ! $post_type ) {
			throw new \InvalidArgumentException( sprintf( 'Post %d does not exist.', $id ) );
		}
		return $post_type;
	}

	/**
	 * Refuse a link() that cannot take effect or would corrupt the translation group.
	 *
	 * @param int    $source_id      Source post id.
	 * @param int    $translation_id Translation post id.
	 * @param string $code           Language code of the translation.
	 * @return void
	 * @throws \InvalidArgumentException When a post is missing, both ids are the same post, the source has no
	 *                                   language, the code is the source's language, the post types differ, or
	 *                                   the source's group already has a different post in that language.
	 */
	protected function guard_link( int $source_id, int $translation_id, string $code ): void {
		$source_type = $this->require_post( $source_id );
		$type        = $this->require_post( $translation_id );
		if ( $source_id === $translation_id ) {
			throw new \InvalidArgumentException( sprintf( 'Cannot link post %d to itself.', $source_id ) );
		}
		$source_lang = $this->post_language( $source_id );
		if ( null === $source_lang ) {
			throw new \InvalidArgumentException( sprintf( 'Source post %d has no language; set it first.', $source_id ) );
		}
		if ( $code === $source_lang ) {
			throw new \InvalidArgumentException( sprintf( 'Cannot link a translation in %s, the language of source post %d.', $code, $source_id ) );
		}
		if ( $type !== $source_type ) {
			throw new \InvalidArgumentException( sprintf( 'Post types differ: source is %s, translation is %s.', $source_type, $type ) );
		}
		$existing = $this->translations( $source_id )[ $code ] ?? null;
		if ( null !== $existing && $existing !== $translation_id ) {
			throw new \InvalidArgumentException( sprintf( 'Post %d already is the %s translation of post %d.', $existing, $code, $source_id ) );
		}
	}

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
