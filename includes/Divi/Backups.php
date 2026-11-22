<?php
/**
 * Backups taken before every overwrite.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Stores backups as posts of the private wpdm_backup type. Backups are
 * internal data, not user content: they are never exposed through the
 * front end, the admin UI or the core REST API, and old ones are pruned.
 */
final class Backups {

	const POST_TYPE = 'wpdm_backup';

	const KINDS = array( 'post_content', 'globals', 'theme_builder' );

	/** Backups kept per (source_id, kind). */
	const KEEP = 20;

	/**
	 * Hook the post type registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
	}

	/**
	 * @return void
	 */
	public static function register_post_type(): void {
		$caps = array();
		foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'read', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $cap ) {
			$caps[ $cap ] = 'manage_options';
		}
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => 'WP Divi MCP backups',
					'singular_name' => 'WP Divi MCP backup',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
				// Only manage_options users could ever edit these through core.
				'map_meta_cap'        => false,
				'capabilities'        => $caps,
			)
		);
	}

	/**
	 * Save a backup, then prune old ones of the same (source_id, kind).
	 *
	 * @param string $kind             post_content|globals|theme_builder.
	 * @param int    $source_id        Source post id (0 for site-wide data).
	 * @param string $content          Raw content, stored byte-exact.
	 * @param array  $meta             Extra data (stored as JSON).
	 * @param string $connection_label Label of the MCP connection that caused it.
	 * @return int Backup id.
	 * @throws ToolError On an unknown kind or a failed or inexact write.
	 */
	public static function save( string $kind, int $source_id, string $content, array $meta = array(), string $connection_label = '' ): int {
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			throw new ToolError( 'Unknown backup kind "' . $kind . '".', 'invalid_backup_kind' );
		}

		// kses (users without unfiltered_html) would rewrite the markup; a backup must be exact.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		try {
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => self::POST_TYPE,
						'post_status'  => 'private',
						'post_title'   => $kind . ' backup of ' . $source_id,
						'post_content' => $content,
						'post_author'  => get_current_user_id(),
					)
				),
				true
			);
		} finally {
			if ( $kses ) {
				kses_init_filters();
			}
		}
		if ( is_wp_error( $id ) || ! $id ) {
			throw new ToolError( 'Could not save the backup: ' . ( is_wp_error( $id ) ? $id->get_error_message() : 'unknown error' ), 'backup_failed' );
		}
		$id = (int) $id;

		if ( get_post_field( 'post_content', $id, 'raw' ) !== $content ) {
			wp_delete_post( $id, true );
			throw new ToolError( 'The backup could not be stored exactly (the database or a filter changed it); nothing was overwritten. The database table charset may not support some characters in the content (for example emoji on a utf8 rather than utf8mb4 table).', 'backup_failed' );
		}

		update_post_meta( $id, '_wpdm_kind', $kind );
		update_post_meta( $id, '_wpdm_source', (string) $source_id );
		update_post_meta( $id, '_wpdm_connection', wp_slash( $connection_label ) );
		update_post_meta( $id, '_wpdm_meta', wp_slash( (string) wp_json_encode( (object) $meta ) ) );

		self::prune( $source_id, $kind );
		return $id;
	}

	/**
	 * Backups, newest first, without content.
	 *
	 * @param int|null    $source_id Filter by source id.
	 * @param string|null $kind      Filter by kind.
	 * @param int         $limit     Maximum number (1-200).
	 * @return array[]
	 */
	public static function list( ?int $source_id = null, ?string $kind = null, int $limit = 50 ): array {
		$limit = max( 1, min( 200, $limit ) );
		$out   = array();
		foreach ( self::query( $source_id, $kind, $limit ) as $post ) {
			$out[] = self::summary( $post );
		}
		return $out;
	}

	/**
	 * One backup with its content.
	 *
	 * @param int $id Backup id.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$out            = self::summary( $post );
		$out['content'] = $post->post_content;
		return $out;
	}

	/**
	 * Keep the newest $keep backups of (source_id, kind); permanently delete the rest.
	 *
	 * @param int    $source_id Source id.
	 * @param string $kind      Kind.
	 * @param int    $keep      How many to keep.
	 * @return void
	 */
	public static function prune( int $source_id, string $kind, int $keep = self::KEEP ): void {
		$ids = get_posts(
			array_merge(
				self::query_args( $source_id, $kind ),
				array(
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
		foreach ( array_slice( $ids, max( 0, $keep ) ) as $old ) {
			wp_delete_post( (int) $old, true );
		}
	}

	/**
	 * @param int|null    $source_id Source id.
	 * @param string|null $kind      Kind.
	 * @param int         $limit     Limit.
	 * @return \WP_Post[]
	 */
	private static function query( ?int $source_id, ?string $kind, int $limit ): array {
		return get_posts( array_merge( self::query_args( $source_id, $kind ), array( 'posts_per_page' => $limit ) ) );
	}

	/**
	 * @param int|null    $source_id Source id.
	 * @param string|null $kind      Kind.
	 * @return array WP_Query args, newest first.
	 */
	private static function query_args( ?int $source_id, ?string $kind ): array {
		$meta = array();
		if ( null !== $source_id ) {
			$meta[] = array(
				'key'   => '_wpdm_source',
				'value' => (string) $source_id,
			);
		}
		if ( null !== $kind ) {
			$meta[] = array(
				'key'   => '_wpdm_kind',
				'value' => $kind,
			);
		}
		$args = array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => 'private',
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		);
		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return $args;
	}

	/**
	 * @param \WP_Post $post Backup post.
	 * @return array
	 */
	private static function summary( \WP_Post $post ): array {
		$meta = json_decode( (string) get_post_meta( $post->ID, '_wpdm_meta', true ), true );
		return array(
			'id'         => (int) $post->ID,
			'kind'       => (string) get_post_meta( $post->ID, '_wpdm_kind', true ),
			'source_id'  => (int) get_post_meta( $post->ID, '_wpdm_source', true ),
			'connection' => (string) get_post_meta( $post->ID, '_wpdm_connection', true ),
			'created'    => mysql2date( 'Y-m-d\TH:i:s\Z', $post->post_date_gmt, false ),
			'bytes'      => strlen( $post->post_content ),
			'meta'       => is_array( $meta ) ? $meta : array(),
		);
	}

	private function __construct() {}
}
