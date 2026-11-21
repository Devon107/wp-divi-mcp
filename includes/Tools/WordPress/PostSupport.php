<?php
/**
 * Shared validation and capability helpers for the content tools.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Argument validation, post type exposure, terms/meta handling and the
 * readable-posts query. Every capability decision in the content tools goes
 * through here or through an explicit current_user_can() in the tool.
 */
final class PostSupport {

	/**
	 * Statuses a tool may write.
	 */
	const STATUSES = array( 'draft', 'pending', 'private', 'publish', 'future' );

	/**
	 * Statuses a list may filter by.
	 */
	const LIST_STATUSES = array( 'any', 'publish', 'draft', 'pending', 'private', 'future', 'trash' );

	/**
	 * Post types never exposed (internal or handled elsewhere).
	 */
	const HIDDEN_TYPES = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_global_styles',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
	);

	const MAX_PER_PAGE = 100;

	/**
	 * Make the context user the current user, so WordPress APIs that consult
	 * the current user (WP_Query, current_user_can) agree with the Server.
	 *
	 * @param Context $ctx Context.
	 * @return void
	 */
	public static function bind_user( Context $ctx ): void {
		if ( get_current_user_id() !== $ctx->user_id ) {
			wp_set_current_user( $ctx->user_id );
		}
	}

	// ----- arguments -------------------------------------------------------

	/**
	 * Validate arguments against a tool's input schema (top level only).
	 *
	 * Enforces: no unknown keys, required keys, JSON types, enum, minimum and
	 * minLength. null values count as "not provided". `maximum` is advisory:
	 * tools clamp instead of rejecting.
	 *
	 * @param array $schema Input schema.
	 * @param array $args   Raw arguments.
	 * @return array Arguments without null values.
	 * @throws ToolError On invalid input.
	 */
	public static function validate( array $schema, array $args ): array {
		$props   = (array) $schema['properties'];
		$unknown = array();
		foreach ( array_keys( $args ) as $key ) {
			if ( ! array_key_exists( (string) $key, $props ) ) {
				$unknown[] = (string) $key;
			}
		}
		if ( $unknown ) {
			throw new ToolError( 'Unknown argument(s): ' . implode( ', ', $unknown ) . '. Allowed: ' . implode( ', ', array_keys( $props ) ) . '.', 'invalid_argument' );
		}

		foreach ( $args as $key => $value ) {
			if ( null === $value ) {
				unset( $args[ $key ] );
				continue;
			}
			self::check_value( (string) $key, $value, $props[ $key ] );
		}

		foreach ( $schema['required'] ?? array() as $required ) {
			if ( ! array_key_exists( $required, $args ) ) {
				throw new ToolError( 'Missing required argument: ' . $required . '.', 'invalid_argument' );
			}
		}
		return $args;
	}

	/**
	 * @param string $key   Argument name.
	 * @param mixed  $value Value.
	 * @param array  $def   Property schema.
	 * @return void
	 * @throws ToolError On mismatch.
	 */
	private static function check_value( string $key, $value, array $def ): void {
		$type = $def['type'] ?? '';
		$ok   = true;
		switch ( $type ) {
			case 'string':
				$ok = is_string( $value );
				break;
			case 'integer':
				$ok = is_int( $value );
				break;
			case 'boolean':
				$ok = is_bool( $value );
				break;
			case 'array':
				$ok = is_array( $value ) && self::is_list( $value );
				break;
			case 'object':
				$ok = is_array( $value ) && ( ! $value || ! self::is_list( $value ) );
				break;
		}
		if ( ! $ok ) {
			throw new ToolError( 'Argument "' . $key . '" must be of type ' . $type . '.', 'invalid_argument' );
		}
		if ( isset( $def['enum'] ) && ! in_array( $value, $def['enum'], true ) ) {
			throw new ToolError( 'Argument "' . $key . '" must be one of: ' . implode( ', ', $def['enum'] ) . '.', 'invalid_argument' );
		}
		if ( 'integer' === $type && isset( $def['minimum'] ) && $value < $def['minimum'] ) {
			throw new ToolError( 'Argument "' . $key . '" must be at least ' . $def['minimum'] . '.', 'invalid_argument' );
		}
		if ( 'string' === $type && isset( $def['minLength'] ) && strlen( $value ) < $def['minLength'] ) {
			throw new ToolError( 'Argument "' . $key . '" must not be empty.', 'invalid_argument' );
		}
	}

	/**
	 * @param array $value Array.
	 * @return bool Whether it is a JSON list.
	 */
	private static function is_list( array $value ): bool {
		return array_values( $value ) === $value;
	}

	/**
	 * Clamp a per_page argument: below 1 is an error, above 100 is capped.
	 *
	 * @param array $args Validated args.
	 * @return int
	 * @throws ToolError When below 1.
	 */
	public static function per_page( array $args ): int {
		$per_page = $args['per_page'] ?? 20;
		if ( $per_page < 1 ) {
			throw new ToolError( 'Argument "per_page" must be at least 1.', 'invalid_argument' );
		}
		return min( $per_page, self::MAX_PER_PAGE );
	}

	/**
	 * @param array $args Validated args.
	 * @return int
	 * @throws ToolError When below 1.
	 */
	public static function page( array $args ): int {
		$page = $args['page'] ?? 1;
		if ( $page < 1 ) {
			throw new ToolError( 'Argument "page" must be at least 1.', 'invalid_argument' );
		}
		return $page;
	}

	// ----- post types and posts -------------------------------------------

	/**
	 * Post types the tools may touch.
	 *
	 * @return \WP_Post_Type[] Keyed by name.
	 */
	public static function post_types(): array {
		$out = array();
		foreach ( get_post_types( array(), 'objects' ) as $name => $type ) {
			if ( in_array( $name, self::HIDDEN_TYPES, true ) || ( ! $type->public && ! $type->show_ui ) ) {
				continue;
			}
			$out[ $name ] = $type;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * @param string $name Post type name.
	 * @return \WP_Post_Type
	 * @throws ToolError When unknown or not exposed.
	 */
	public static function post_type( string $name ): \WP_Post_Type {
		$types = self::post_types();
		if ( ! isset( $types[ $name ] ) ) {
			throw new ToolError( 'Unknown post type "' . $name . '". Use list_post_types to see the available ones.', 'invalid_post_type' );
		}
		return $types[ $name ];
	}

	/**
	 * Load a post of an exposed type.
	 *
	 * @param int $id Post id.
	 * @return \WP_Post
	 * @throws ToolError not_found.
	 */
	public static function find_post( int $id ): \WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || ! isset( self::post_types()[ $post->post_type ] ) ) {
			throw new ToolError( 'Post ' . $id . ' not found.', 'not_found' );
		}
		return $post;
	}

	/**
	 * Whether the current user may see the post in a list or search result.
	 *
	 * @param \WP_Post $post       Post.
	 * @param bool     $text_match Whether a text search produced the match: password
	 *                             protected posts then need edit rights, otherwise
	 *                             search would reveal their content.
	 * @return bool
	 */
	public static function can_list( \WP_Post $post, bool $text_match ): bool {
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return false;
		}
		if ( $text_match && '' !== $post->post_password && ! current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Run a WP_Query as the current user and return only readable posts.
	 *
	 * @param array $query_args WP_Query args (post_type, post_status, paging, ...).
	 * @param int   $per_page   Page size.
	 * @param int   $page       Page number.
	 * @return array {items: summary[], total, pages}
	 */
	public static function query_readable( array $query_args, int $per_page, int $page ): array {
		$query_args = array_merge(
			$query_args,
			array(
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'perm'                => 'readable',
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'no_found_rows'       => false,
			)
		);
		$query = new \WP_Query( $query_args );
		$text  = ! empty( $query_args['s'] );

		$items   = array();
		$dropped = 0;
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! self::can_list( $post, $text ) ) {
				++$dropped;
				continue;
			}
			$items[] = PostFormatter::summary( $post );
		}

		$total = max( 0, (int) $query->found_posts - $dropped );
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	// ----- writes ----------------------------------------------------------

	/**
	 * Schema properties shared by create_post and update_post.
	 *
	 * @return array
	 */
	public static function write_properties(): array {
		return array(
			'title'          => array(
				'type'        => 'string',
				'description' => 'Post title.',
			),
			'content'        => array(
				'type'        => 'string',
				'description' => 'Post content (HTML or block markup).',
			),
			'excerpt'        => array(
				'type'        => 'string',
				'description' => 'Post excerpt.',
			),
			'status'         => array(
				'type'        => 'string',
				'enum'        => self::STATUSES,
				'description' => 'draft, pending, private, publish or future. Defaults to draft on create; unchanged on update. Publishing, private and future need the publish capability; future also needs date.',
			),
			'date'           => array(
				'type'        => 'string',
				'description' => 'Publication date for status=future, in the site timezone (e.g. 2030-01-31 09:00:00) or ISO 8601 with an offset. Must be in the future.',
			),
			'slug'           => array(
				'type'        => 'string',
				'description' => 'URL slug (post_name).',
			),
			'parent'         => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => 'Parent post ID for hierarchical post types (0 for none).',
			),
			'terms'          => array(
				'type'                 => 'object',
				'description'          => 'Terms to assign, per taxonomy: {"category": [3, "News"]}. Integers are term IDs, strings are term names (created if missing and the user may create terms). Replaces the post\'s terms in each listed taxonomy.',
				'additionalProperties' => array(
					'type'  => 'array',
					'items' => array( 'type' => array( 'integer', 'string' ) ),
				),
			),
			'featured_media' => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => 'Attachment ID of the featured image (0 removes it on update).',
			),
			'meta'           => array(
				'type'        => 'object',
				'description' => 'Custom fields as {key: value}. Keys starting with "_" are only allowed when registered with show_in_rest. On update, null deletes a key.',
			),
		);
	}

	/**
	 * Turn tool arguments into a validated write plan. Nothing is modified.
	 *
	 * @param \WP_Post_Type $type     Target post type.
	 * @param array         $args     Validated args.
	 * @param \WP_Post|null $existing Post being updated, null on create.
	 * @return array {post: array (unslashed), terms: array, meta: array, thumbnail: int|null}
	 * @throws ToolError On any invalid or forbidden input.
	 */
	public static function plan( \WP_Post_Type $type, array $args, ?\WP_Post $existing ): array {
		$post = array();
		foreach ( array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		) as $arg => $field ) {
			if ( isset( $args[ $arg ] ) ) {
				$post[ $field ] = $args[ $arg ];
			}
		}

		// Status and date.
		$status    = $args['status'] ?? null;
		$effective = $status ?? ( $existing ? $existing->post_status : 'draft' );
		if ( null !== $status ) {
			$changing = ! $existing || $existing->post_status !== $status;
			if ( $changing && in_array( $status, array( 'publish', 'future', 'private' ), true ) && ! current_user_can( $type->cap->publish_posts ) ) {
				throw new ToolError( 'You are not allowed to set the status "' . $status . '" on ' . $type->name . ' content.', 'forbidden' );
			}
			$post['post_status'] = $status;
		}
		if ( isset( $args['date'] ) ) {
			if ( 'future' !== $effective ) {
				throw new ToolError( 'The date argument is only used with status=future.', 'invalid_argument' );
			}
			$date = date_create( $args['date'], wp_timezone() );
			if ( false === $date ) {
				throw new ToolError( 'Could not parse date "' . $args['date'] . '".', 'invalid_argument' );
			}
			$timestamp = $date->getTimestamp();
			if ( $timestamp <= time() + MINUTE_IN_SECONDS ) {
				throw new ToolError( 'The date must be in the future for status=future.', 'invalid_argument' );
			}
			$post['post_date']     = wp_date( 'Y-m-d H:i:s', $timestamp );
			$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
			$post['edit_date']     = true;
		} elseif ( 'future' === $status && ( ! $existing || 'future' !== $existing->post_status ) ) {
			throw new ToolError( 'status=future requires a date.', 'invalid_argument' );
		}

		// Slug.
		if ( isset( $args['slug'] ) ) {
			$slug = sanitize_title( $args['slug'] );
			if ( '' === $slug ) {
				throw new ToolError( 'The slug is empty after sanitising.', 'invalid_argument' );
			}
			$post['post_name'] = $slug;
		}

		// Parent.
		if ( isset( $args['parent'] ) ) {
			$post['post_parent'] = self::check_parent( $type, $args['parent'], $existing );
		}

		return array(
			'post'      => $post,
			'terms'     => isset( $args['terms'] ) ? self::plan_terms( $type, $args['terms'] ) : array(),
			'meta'      => isset( $args['meta'] ) ? self::plan_meta( $type, $args['meta'], $existing ) : array(),
			'thumbnail' => isset( $args['featured_media'] ) ? self::check_thumbnail( $args['featured_media'], null !== $existing ) : null,
		);
	}

	/**
	 * @param \WP_Post_Type $type     Post type.
	 * @param int           $parent   Parent id.
	 * @param \WP_Post|null $existing Post being updated.
	 * @return int
	 * @throws ToolError On invalid parent.
	 */
	private static function check_parent( \WP_Post_Type $type, int $parent, ?\WP_Post $existing ): int {
		if ( 0 === $parent ) {
			return 0;
		}
		if ( ! $type->hierarchical ) {
			throw new ToolError( 'Post type "' . $type->name . '" is not hierarchical and cannot have a parent.', 'invalid_argument' );
		}
		$parent_post = get_post( $parent );
		if ( ! $parent_post instanceof \WP_Post || $parent_post->post_type !== $type->name ) {
			throw new ToolError( 'Parent ' . $parent . ' was not found among ' . $type->name . ' content.', 'invalid_argument' );
		}
		if ( ! current_user_can( 'read_post', $parent ) ) {
			throw new ToolError( 'You are not allowed to use parent ' . $parent . '.', 'forbidden' );
		}
		if ( $existing && ( $parent === $existing->ID || in_array( $existing->ID, get_post_ancestors( $parent ), true ) ) ) {
			throw new ToolError( 'A post cannot be its own ancestor.', 'invalid_argument' );
		}
		return $parent;
	}

	/**
	 * @param int  $media_id Attachment id (0 = none).
	 * @param bool $updating Whether this is an update (0 is then meaningful).
	 * @return int|null
	 * @throws ToolError On invalid attachment.
	 */
	private static function check_thumbnail( int $media_id, bool $updating ): ?int {
		if ( 0 === $media_id ) {
			return $updating ? 0 : null;
		}
		$attachment = get_post( $media_id );
		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || 0 !== strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
			throw new ToolError( 'featured_media ' . $media_id . ' is not an image attachment.', 'invalid_argument' );
		}
		if ( ! current_user_can( 'read_post', $media_id ) ) {
			throw new ToolError( 'You are not allowed to use attachment ' . $media_id . '.', 'forbidden' );
		}
		return $media_id;
	}

	/**
	 * Validate the terms argument without creating anything.
	 *
	 * @param \WP_Post_Type $type  Post type.
	 * @param array         $terms {taxonomy: [int|string]}.
	 * @return array List of {taxonomy: WP_Taxonomy, ids: int[], create: string[]}.
	 * @throws ToolError On unknown taxonomy, unknown id or missing capability.
	 */
	private static function plan_terms( \WP_Post_Type $type, array $terms ): array {
		$plan = array();
		foreach ( $terms as $name => $items ) {
			$name     = (string) $name;
			$taxonomy = get_taxonomy( $name );
			if ( ! $taxonomy ) {
				throw new ToolError( 'Unknown taxonomy "' . $name . '". Use list_taxonomies to see the available ones.', 'invalid_argument' );
			}
			if ( ! is_object_in_taxonomy( $type->name, $name ) ) {
				throw new ToolError( 'Taxonomy "' . $name . '" does not apply to post type "' . $type->name . '".', 'invalid_argument' );
			}
			if ( ! is_array( $items ) || array_values( $items ) !== $items ) {
				throw new ToolError( 'terms.' . $name . ' must be a list of term IDs or names.', 'invalid_argument' );
			}
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
				throw new ToolError( 'You are not allowed to assign terms in "' . $name . '".', 'forbidden' );
			}

			$ids    = array();
			$create = array();
			foreach ( $items as $item ) {
				if ( is_int( $item ) ) {
					$term = get_term( $item, $name );
					if ( ! $term instanceof \WP_Term ) {
						throw new ToolError( 'Term ' . $item . ' does not exist in "' . $name . '".', 'invalid_argument' );
					}
					$ids[] = (int) $term->term_id;
				} elseif ( is_string( $item ) && '' !== trim( $item ) ) {
					$label  = trim( $item );
					$exists = term_exists( wp_slash( $label ), $name );
					if ( $exists ) {
						$ids[] = (int) ( is_array( $exists ) ? $exists['term_id'] : $exists );
					} elseif ( current_user_can( $taxonomy->cap->edit_terms ) ) {
						$create[ strtolower( $label ) ] = $label;
					} else {
						throw new ToolError( 'The term "' . $label . '" does not exist in "' . $name . '" and you are not allowed to create terms there.', 'forbidden' );
					}
				} else {
					throw new ToolError( 'terms.' . $name . ' entries must be integer IDs or non-empty names.', 'invalid_argument' );
				}
			}
			$plan[] = array(
				'taxonomy' => $taxonomy,
				'ids'      => $ids,
				'create'   => array_values( $create ),
			);
		}
		return $plan;
	}

	/**
	 * Validate the meta argument without writing anything.
	 *
	 * @param \WP_Post_Type $type     Post type.
	 * @param array         $meta     {key: value}.
	 * @param \WP_Post|null $existing Post being updated (capability is checked per key).
	 * @return array {key: value} (null = delete, only when updating).
	 * @throws ToolError On refused keys.
	 */
	private static function plan_meta( \WP_Post_Type $type, array $meta, ?\WP_Post $existing ): array {
		$registered = array_merge( get_registered_meta_keys( 'post' ), get_registered_meta_keys( 'post', $type->name ) );
		$plan       = array();
		foreach ( $meta as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key ) {
				throw new ToolError( 'Meta keys must not be empty.', 'invalid_argument' );
			}
			$protected = '_' === $key[0] || is_protected_meta( $key, 'post' );
			if ( $protected && empty( $registered[ $key ]['show_in_rest'] ) ) {
				throw new ToolError( 'Meta key "' . $key . '" is protected. Only keys registered with show_in_rest may start with an underscore.', 'forbidden' );
			}
			if ( null === $value ) {
				if ( ! $existing ) {
					continue;
				}
			} elseif ( ! is_scalar( $value ) && ! is_array( $value ) ) {
				throw new ToolError( 'Meta "' . $key . '" must be a scalar, list or object.', 'invalid_argument' );
			}
			if ( $existing && ! self::can_write_meta( $existing->ID, $key, null === $value ) ) {
				throw new ToolError( 'You are not allowed to change meta "' . $key . '".', 'forbidden' );
			}
			$plan[ $key ] = $value;
		}
		return $plan;
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param bool   $delete  Whether the change deletes the key.
	 * @return bool
	 */
	private static function can_write_meta( int $post_id, string $key, bool $delete ): bool {
		return current_user_can( $delete ? 'delete_post_meta' : 'edit_post_meta', $post_id, $key );
	}

	/**
	 * Apply the non-post parts of a plan to an existing post.
	 *
	 * @param int   $post_id Post id.
	 * @param array $plan    From plan().
	 * @param bool  $created Whether the post was just created (meta caps are then checked here).
	 * @return void
	 * @throws ToolError On failure.
	 */
	public static function apply( int $post_id, array $plan, bool $created ): void {
		foreach ( $plan['meta'] as $key => $value ) {
			if ( $created && ! self::can_write_meta( $post_id, $key, false ) ) {
				throw new ToolError( 'You are not allowed to set meta "' . $key . '".', 'forbidden' );
			}
			if ( null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}

		foreach ( $plan['terms'] as $entry ) {
			$taxonomy = $entry['taxonomy'];
			$ids      = $entry['ids'];
			foreach ( $entry['create'] as $label ) {
				$created_term = wp_insert_term( wp_slash( $label ), $taxonomy->name );
				if ( is_wp_error( $created_term ) ) {
					$existing_id = $created_term->get_error_data( 'term_exists' );
					if ( ! $existing_id ) {
						throw new ToolError( 'Could not create term "' . $label . '": ' . $created_term->get_error_message(), 'term_error' );
					}
					$ids[] = (int) $existing_id;
					continue;
				}
				$ids[] = (int) $created_term['term_id'];
			}
			$result = wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy->name );
			if ( is_wp_error( $result ) ) {
				throw new ToolError( 'Could not assign terms in "' . $taxonomy->name . '": ' . $result->get_error_message(), 'term_error' );
			}
		}

		if ( null !== $plan['thumbnail'] ) {
			if ( 0 === $plan['thumbnail'] ) {
				delete_post_thumbnail( $post_id );
			} else {
				set_post_thumbnail( $post_id, $plan['thumbnail'] );
			}
		}
	}

	/**
	 * Best-effort undo of a create whose follow-up writes failed: move the new
	 * post to the trash (never a permanent delete).
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function discard_created( int $post_id ): void {
		if ( EMPTY_TRASH_DAYS ) {
			wp_trash_post( $post_id );
		}
	}
}
