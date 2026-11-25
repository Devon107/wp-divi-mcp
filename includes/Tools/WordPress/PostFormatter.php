<?php
/**
 * Shapes WP_Post objects into tool output.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Pure formatting: callers decide whether the user may see a post at all.
 */
final class PostFormatter {

	/**
	 * Whether a post is built with Divi.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function is_divi( \WP_Post $post ): bool {
		if ( 'on' === get_post_meta( $post->ID, '_et_pb_use_builder', true ) ) {
			return true;
		}
		return false !== strpos( (string) $post->post_content, '<!-- wp:divi/' );
	}

	/**
	 * Compact representation used in lists.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function summary( \WP_Post $post ): array {
		$divi = self::is_divi( $post );
		$link = (string) get_permalink( $post );
		$out  = array(
			'id'       => (int) $post->ID,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'link'     => $link,
			'date'     => self::published( $post ),
			'modified' => self::modified( $post ),
			'parent'   => (int) $post->post_parent,
			'divi'     => $divi,
		);
		if ( $divi ) {
			$out['edit_divi'] = add_query_arg( 'et_fb', '1', $link );
		}
		return $out;
	}

	/**
	 * Publication date in UTC, ISO 8601.
	 *
	 * @param \WP_Post $post Post.
	 * @return string Empty only when the post has no date at all.
	 */
	private static function published( \WP_Post $post ): string {
		return self::utc( $post, 'date' );
	}

	/**
	 * Last-modified time in UTC, ISO 8601.
	 *
	 * @param \WP_Post $post Post.
	 * @return string Empty only when the post has no modified date at all.
	 */
	private static function modified( \WP_Post $post ): string {
		return self::utc( $post, 'modified' );
	}

	/**
	 * A post date field in UTC, ISO 8601.
	 *
	 * New drafts keep a zeroed GMT date until they are published, so fall back to
	 * the local date (interpreted in the site timezone).
	 *
	 * @param \WP_Post $post  Post.
	 * @param string   $field 'date' or 'modified'.
	 * @return string Empty only when the post has no such date at all.
	 */
	private static function utc( \WP_Post $post, string $field ): string {
		$datetime = get_post_datetime( $post, $field, 'gmt' );
		if ( false === $datetime ) {
			$datetime = get_post_datetime( $post, $field, 'local' );
		}
		if ( false === $datetime ) {
			return '';
		}
		return $datetime->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'c' );
	}

	/**
	 * Schema property for write tools that may echo the content back.
	 *
	 * @return array
	 */
	public static function include_content_property(): array {
		return array(
			'type'        => 'boolean',
			'description' => 'Return content_raw in the response. Default false: Divi content is large and you already know what you wrote (content_length is always returned).',
		);
	}

	/**
	 * Full representation for a write tool: content_raw only when asked for.
	 *
	 * @param \WP_Post $post Post.
	 * @param array    $args Tool arguments (include_content).
	 * @return array
	 */
	public static function written( \WP_Post $post, array $args ): array {
		$out = self::full( $post, true );
		if ( empty( $args['include_content'] ) ) {
			unset( $out['content_raw'] );
		}
		return $out;
	}

	/**
	 * Full representation. Only for posts the caller may read.
	 *
	 * @param \WP_Post $post     Post.
	 * @param bool     $can_edit Whether the caller may edit the post; when false the
	 *                           custom fields are withheld.
	 * @return array
	 */
	public static function full( \WP_Post $post, bool $can_edit = true ): array {
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) && $ids ) {
				$terms[ $taxonomy ] = array_map( 'intval', $ids );
			}
		}

		$meta = array();
		if ( $can_edit ) {
			foreach ( get_post_meta( $post->ID ) as $key => $values ) {
				$key = (string) $key;
				if ( '' === $key || '_' === $key[0] || is_protected_meta( $key, 'post' ) ) {
					continue;
				}
				$values = array_map( 'maybe_unserialize', $values );
				// A JSON object must stay an object even when empty.
				$meta[ $key ] = 1 === count( $values ) ? $values[0] : $values;
			}
		}

		return array_merge(
			self::summary( $post ),
			array(
				'content_raw'    => $post->post_content,
				'content_length' => strlen( $post->post_content ),
				'excerpt'        => $post->post_excerpt,
				'terms'          => $terms ? $terms : new \stdClass(),
				'featured_media' => (int) get_post_thumbnail_id( $post->ID ),
				'meta'           => $meta ? $meta : new \stdClass(),
			)
		);
	}
}
