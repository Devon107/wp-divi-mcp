<?php
/**
 * Shared lookups and formatters for the site tools (media, terms, menus,
 * comments, options).
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lookups throw ToolError when something does not exist or is not exposed;
 * capability decisions stay in the tools, next to the write they protect.
 */
final class SiteSupport {

	/**
	 * Options the tools may read and write, in output order.
	 */
	const OPTIONS = array(
		'blogname',
		'blogdescription',
		'timezone_string',
		'date_format',
		'time_format',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'posts_per_page',
	);

	// ----- media -----------------------------------------------------------

	/**
	 * Load an attachment.
	 *
	 * @param int $id Attachment id.
	 * @return \WP_Post
	 * @throws ToolError not_found.
	 */
	public static function find_attachment( int $id ): \WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			throw new ToolError( 'Media item ' . $id . ' not found.', 'not_found' );
		}
		return $post;
	}

	/**
	 * @param \WP_Post $post Attachment.
	 * @return array
	 */
	public static function attachment_data( \WP_Post $post ): array {
		$meta = wp_get_attachment_metadata( $post->ID );
		return array(
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'url'         => (string) wp_get_attachment_url( $post->ID ),
			'mime_type'   => $post->post_mime_type,
			'alt'         => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'width'       => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'      => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'author'      => (int) $post->post_author,
			'date'        => (string) get_post_time( 'c', true, $post ),
		);
	}

	// ----- terms -----------------------------------------------------------

	/**
	 * A taxonomy the tools may work with.
	 *
	 * @param string $name Taxonomy name.
	 * @return \WP_Taxonomy
	 * @throws ToolError invalid_argument when unknown or internal.
	 */
	public static function taxonomy( string $name ): \WP_Taxonomy {
		$taxonomy = taxonomy_exists( $name ) ? get_taxonomy( $name ) : false;
		if ( ! $taxonomy instanceof \WP_Taxonomy || ( ! $taxonomy->public && ! $taxonomy->show_ui ) ) {
			throw new ToolError( 'Unknown taxonomy "' . $name . '". Use list_taxonomies to see the available ones.', 'invalid_argument' );
		}
		return $taxonomy;
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return array
	 */
	public static function term_data( \WP_Term $term ): array {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'taxonomy'    => $term->taxonomy,
		);
	}

	/**
	 * Load a term of a taxonomy.
	 *
	 * @param int    $id       Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term
	 * @throws ToolError not_found.
	 */
	public static function find_term( int $id, string $taxonomy ): \WP_Term {
		$term = $id > 0 ? get_term( $id, $taxonomy ) : null;
		if ( ! $term instanceof \WP_Term ) {
			throw new ToolError( 'Term ' . $id . ' not found in "' . $taxonomy . '".', 'not_found' );
		}
		return $term;
	}

	// ----- menus -----------------------------------------------------------

	/**
	 * @param int $id Menu (term) id.
	 * @return \WP_Term
	 * @throws ToolError not_found.
	 */
	public static function menu( int $id ): \WP_Term {
		$menu = $id > 0 ? wp_get_nav_menu_object( $id ) : false;
		if ( ! $menu instanceof \WP_Term ) {
			throw new ToolError( 'Menu ' . $id . ' not found. Use list_menus to see the menus.', 'not_found' );
		}
		return $menu;
	}

	/**
	 * Ids of the menu items that belong to a menu (any status).
	 *
	 * @param int $menu_id Menu id.
	 * @return int[]
	 */
	public static function menu_item_ids( int $menu_id ): array {
		$ids = get_objects_in_term( $menu_id, 'nav_menu' );
		if ( is_wp_error( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( 'nav_menu_item' === get_post_type( (int) $id ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * The items of a menu in display order.
	 *
	 * @param int $menu_id Menu id.
	 * @return array[]
	 */
	public static function menu_items( int $menu_id ): array {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		$out   = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$out[] = array(
				'id'        => (int) $item->ID,
				'title'     => (string) $item->title,
				'url'       => (string) $item->url,
				'type'      => (string) $item->type,
				'object'    => (string) $item->object,
				'object_id' => (int) $item->object_id,
				'parent'    => (int) $item->menu_item_parent,
				'position'  => (int) $item->menu_order,
			);
		}
		return $out;
	}

	// ----- comments --------------------------------------------------------

	/**
	 * Wire status of a comment.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return string approve|hold|spam|trash (or the raw value for custom statuses).
	 */
	public static function comment_status( \WP_Comment $comment ): string {
		$map = array(
			'1'     => 'approve',
			'0'     => 'hold',
			'spam'  => 'spam',
			'trash' => 'trash',
		);
		return $map[ $comment->comment_approved ] ?? (string) $comment->comment_approved;
	}

	/**
	 * @param \WP_Comment $comment Comment.
	 * @return array
	 */
	public static function comment_data( \WP_Comment $comment ): array {
		$post = get_post( (int) $comment->comment_post_ID );
		return array(
			'id'           => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'post_title'   => $post ? $post->post_title : '',
			'author'       => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'content'      => $comment->comment_content,
			'status'       => self::comment_status( $comment ),
			'date_gmt'     => gmdate( 'c', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) ),
			'parent'       => (int) $comment->comment_parent,
			'type'         => '' === $comment->comment_type ? 'comment' : $comment->comment_type,
		);
	}

	// ----- options ---------------------------------------------------------

	/**
	 * Current values of the allowlisted options, plus the read-only
	 * permalink_structure.
	 *
	 * @return array
	 */
	public static function options(): array {
		$out = array();
		foreach ( self::OPTIONS as $key ) {
			$value = get_option( $key );
			if ( in_array( $key, array( 'page_on_front', 'page_for_posts', 'posts_per_page' ), true ) ) {
				$value = (int) $value;
			} else {
				$value = (string) $value;
			}
			$out[ $key ] = $value;
		}
		$out['permalink_structure'] = (string) get_option( 'permalink_structure' );
		return $out;
	}
}
