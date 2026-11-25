<?php
/**
 * WPML adapter: the only code that talks to WPML.
 *
 * Uses WPML's documented public hooks only (wpml_* filters and the
 * wpml_set_element_language_details action). WPML is commercial and not available in
 * tests; it is covered by a test double of those hooks (tests/WP/Support/WpmlDouble.php).
 *
 * @package WPDM
 */

namespace WPDM\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * Languages and translation groups through WPML.
 */
final class Wpml extends Languages {

	/**
	 * Adapter name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'wpml';
	}

	/**
	 * Configured languages.
	 *
	 * @return array<int,array{code:string,name:string,locale:string,default:bool}>
	 */
	public function languages(): array {
		$default = $this->default_language();
		$out     = array();
		foreach ( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) as $key => $language ) {
			$code  = (string) ( $language['code'] ?? $key );
			$out[] = array(
				'code'    => $code,
				'name'    => (string) ( $language['native_name'] ?? $language['translated_name'] ?? $code ),
				'locale'  => (string) ( $language['default_locale'] ?? '' ),
				'default' => $code === $default,
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
		return (string) apply_filters( 'wpml_default_language', null );
	}

	/**
	 * Whether WPML translates a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_translatable( string $post_type ): bool {
		return (bool) apply_filters( 'wpml_is_translated_post_type', false, $post_type );
	}

	/**
	 * Language of a post.
	 *
	 * @param int $id Post id.
	 * @return string|null
	 */
	public function post_language( int $id ): ?string {
		$details = apply_filters( 'wpml_post_language_details', null, $id );
		$code    = is_array( $details ) ? ( $details['language_code'] ?? '' ) : '';
		return is_string( $code ) && '' !== $code ? $code : null;
	}

	/**
	 * Translation group of a post (every status: drafts are part of it), in the order of the configured languages.
	 *
	 * @param int $id Post id.
	 * @return array<string,int>
	 */
	public function translations( int $id ): array {
		$type = $this->element_type( $id );
		$trid = null === $type ? null : apply_filters( 'wpml_element_trid', null, $id, $type );
		if ( ! $trid ) {
			return array();
		}
		$group = array();
		foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid, $type, false, true ) as $code => $element ) {
			$element_id = is_object( $element ) ? ( $element->element_id ?? 0 ) : ( $element['element_id'] ?? 0 );
			if ( $element_id && get_post_status( (int) $element_id ) ) {
				$group[ (string) $code ] = (int) $element_id;
			}
		}
		$out = array();
		foreach ( $this->languages() as $language ) {
			if ( isset( $group[ $language['code'] ] ) ) {
				$out[ $language['code'] ] = $group[ $language['code'] ];
			}
		}
		return $out;
	}

	/**
	 * Set the language of a post. A post already in a translation group keeps it.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return void
	 * @throws \InvalidArgumentException When the post does not exist.
	 */
	public function set_language( int $id, string $code ): void {
		$type = 'post_' . $this->require_post( $id );
		$trid = apply_filters( 'wpml_element_trid', null, $id, $type );
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $id,
				'element_type'         => $type,
				'trid'                 => $trid ? (int) $trid : false,
				'language_code'        => $code,
				'source_language_code' => null,
			)
		);
	}

	/**
	 * Add a post to the source's translation group (its trid), with the source's language as origin.
	 *
	 * @param int    $source_id      Source post id.
	 * @param int    $translation_id Translation post id.
	 * @param string $code           Language code of the translation.
	 * @return void
	 * @throws \InvalidArgumentException See Languages::guard_link().
	 */
	public function link( int $source_id, int $translation_id, string $code ): void {
		$this->guard_link( $source_id, $translation_id, $code );
		$source_type = $this->element_type( $source_id );
		$type        = $this->element_type( $translation_id );
		$trid        = apply_filters( 'wpml_element_trid', null, $source_id, $source_type );
		if ( ! $trid ) {
			throw new \InvalidArgumentException( sprintf( 'Source post %d is not in a WPML translation group; set its language first.', $source_id ) );
		}
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $translation_id,
				'element_type'         => $type,
				'trid'                 => (int) $trid,
				'language_code'        => $code,
				'source_language_code' => $this->post_language( $source_id ),
			)
		);
	}

	/**
	 * Translation of a post in a language.
	 *
	 * @param int    $id   Post id.
	 * @param string $code Language code.
	 * @return int|null
	 */
	public function translation_of( int $id, string $code ): ?int {
		$post_type = get_post_type( $id );
		if ( ! $post_type ) {
			return null;
		}
		$found = apply_filters( 'wpml_object_id', $id, $post_type, false, $code );
		return $found && get_post_status( (int) $found ) ? (int) $found : null;
	}

	/**
	 * Translation of a term in a language (the term itself when WPML does not translate the taxonomy).
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $code     Language code.
	 * @return int|null
	 */
	public function term_translation_of( int $term_id, string $taxonomy, string $code ): ?int {
		if ( ! apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy ) ) {
			return $term_id;
		}
		$found = apply_filters( 'wpml_object_id', $term_id, $taxonomy, false, $code );
		return $found ? (int) $found : null;
	}

	/**
	 * WPML element type of a post (post_<post type>), or null when the post does not exist.
	 *
	 * @param int $id Post id.
	 * @return string|null
	 */
	private function element_type( int $id ): ?string {
		$post_type = get_post_type( $id );
		return $post_type ? 'post_' . $post_type : null;
	}
}
