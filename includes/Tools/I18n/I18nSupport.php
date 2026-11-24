<?php
/**
 * Shared validation for the language tools.
 *
 * @package WPDM
 */

namespace WPDM\Tools\I18n;

use WPDM\I18n\Languages;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Not a tool. The adapter lookup, language code and post checks every language tool shares.
 */
final class I18nSupport {

	/**
	 * The active language adapter.
	 *
	 * @return Languages
	 * @throws ToolError not_available.
	 */
	public static function adapter(): Languages {
		$languages = Languages::current();
		if ( null === $languages ) {
			throw new ToolError( 'No language plugin (Polylang or WPML) is active on this site.', 'not_available' );
		}
		return $languages;
	}

	/**
	 * Validate a language code against the configured languages.
	 *
	 * @param Languages $languages Language adapter.
	 * @param string    $code      Language code.
	 * @return string The code.
	 * @throws ToolError invalid_argument, with the valid codes.
	 */
	public static function code( Languages $languages, string $code ): string {
		$valid = array_column( $languages->languages(), 'code' );
		if ( ! in_array( $code, $valid, true ) ) {
			throw new ToolError( 'Unknown language "' . $code . '". Valid codes: ' . implode( ', ', $valid ) . '.', 'invalid_argument' );
		}
		return $code;
	}

	/**
	 * Require that the language plugin translates the post's type.
	 *
	 * @param Languages $languages Language adapter.
	 * @param \WP_Post  $post      Post.
	 * @return void
	 * @throws ToolError invalid_argument.
	 */
	public static function translatable( Languages $languages, \WP_Post $post ): void {
		if ( ! $languages->is_translatable( $post->post_type ) ) {
			throw new ToolError( 'Post type "' . $post->post_type . '" is not translatable in ' . $languages->name() . ': enable it in the language plugin settings first.', 'invalid_argument' );
		}
	}

	/**
	 * Require that the current user may edit the post.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 * @throws ToolError forbidden.
	 */
	public static function require_edit( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post->ID . '.', 'forbidden' );
		}
	}

	/**
	 * Whether the current user may read the post: the same visibility as get_post (editors of the post,
	 * or published and readable for others).
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_read( \WP_Post $post ): bool {
		return current_user_can( 'edit_post', $post->ID )
			|| ( 'publish' === $post->post_status && current_user_can( 'read_post', $post->ID ) && ! post_password_required( $post ) );
	}

	/**
	 * The translation group of a post as tools return it: language code => id, title, status, link.
	 * Posts the current user may not read are left out.
	 *
	 * @param Languages $languages Language adapter.
	 * @param int       $id        Any post of the group.
	 * @return array|\stdClass An empty group is an object, so it encodes as {}.
	 */
	public static function group( Languages $languages, int $id ) {
		$out = array();
		foreach ( $languages->translations( $id ) as $code => $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || ! self::can_read( $post ) ) {
				continue;
			}
			$out[ $code ] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post ),
				'status' => $post->post_status,
				'link'   => (string) get_permalink( $post ),
			);
		}
		return array() === $out ? new \stdClass() : $out;
	}

	private function __construct() {}
}
