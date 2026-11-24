<?php
/**
 * Polylang adapter: the only code that talks to Polylang.
 *
 * Uses Polylang's public functions (pll_*) only.
 *
 * @package WPDM
 */

namespace WPDM\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * Languages and translation groups through Polylang.
 */
final class Polylang extends Languages {

	/**
	 * Adapter name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'polylang';
	}

	/**
	 * Configured languages.
	 *
	 * @return array<int,array{code:string,name:string,locale:string,default:bool}>
	 */
	public function languages(): array {
		$default = $this->default_language();
		$out     = array();
		foreach ( (array) pll_languages_list( array( 'fields' => '' ) ) as $language ) {
			$out[] = array(
				'code'    => (string) $language->slug,
				'name'    => (string) $language->name,
				'locale'  => (string) $language->locale,
				'default' => (string) $language->slug === $default,
			);
		}
		return $out;
	}

	/**
	 * Default language code.
	 *
	 * @return string
	 */
	public function default_language(): string {
		return (string) pll_default_language();
	}

	/**
	 * Whether Polylang translates a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_translatable( string $post_type ): bool {
		return (bool) pll_is_translated_post_type( $post_type );
	}

	/**
	 * Language of a post.
	 *
	 * @param int $id Post id.
	 * @return string|null
	 */
	public function post_language( int $id ): ?string {
		$code = pll_get_post_language( $id );
		return is_string( $code ) && '' !== $code ? $code : null;
	}

	/**
	 * Translation group of a post, in the order of the configured languages.
	 *
	 * @param int $id Post id.
	 * @return array<string,int>
	 */
	public function translations( int $id ): array {
		$group = (array) pll_get_post_translations( $id );
		$out   = array();
		foreach ( $this->languages() as $language ) {
			if ( ! empty( $group[ $language['code'] ] ) ) {
				$out[ $language['code'] ] = (int) $group[ $language['code'] ];
			}
		}
		return $out;
	}

	/**
	 * Set the language of a post.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return void
	 * @throws \InvalidArgumentException When the post does not exist.
	 */
	public function set_language( int $id, string $code ): void {
		$this->require_post( $id );
		pll_set_post_language( $id, $code );
	}

	/**
	 * Add a post to the source's translation group. The translation gets the language first,
	 * because Polylang only links posts whose language matches their key.
	 *
	 * @param int    $source_id      Source post id.
	 * @param int    $translation_id Translation post id.
	 * @param string $code           Language code of the translation.
	 * @return void
	 * @throws \InvalidArgumentException See Languages::guard_link().
	 */
	public function link( int $source_id, int $translation_id, string $code ): void {
		$this->guard_link( $source_id, $translation_id, $code );
		if ( $this->post_language( $translation_id ) !== $code ) {
			$this->set_language( $translation_id, $code );
		}
		$group          = $this->translations( $source_id );
		$group[ $code ] = $translation_id;
		pll_save_post_translations( $group );
	}

	/**
	 * Translation of a post in a language.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return int|null
	 */
	public function translation_of( int $id, string $code ): ?int {
		return $this->translations( $id )[ $code ] ?? null;
	}
}
