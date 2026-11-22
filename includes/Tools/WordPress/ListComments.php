<?php
/**
 * Tool: list_comments.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists comments for moderation.
 */
final class ListComments extends Tool {

	const STATUSES = array( 'approve', 'hold', 'spam', 'trash', 'all' );

	public function name(): string {
		return 'list_comments';
	}

	public function description(): string {
		return 'List comments for moderation, newest first. status: approve, hold (pending), spam, trash or all (default: approved plus pending, as in the WordPress admin). Only returns comments on content the current user may edit. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Only comments on this post.',
				),
				'status'   => array(
					'type'        => 'string',
					'enum'        => self::STATUSES,
					'description' => 'approve, hold, spam, trash or all (approved plus pending). Default all.',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Search text (content, author, email).',
				),
				'per_page' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => PostSupport::MAX_PER_PAGE,
					'description' => 'Items per page. Default 20, capped at 100.',
				),
				'page'     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page number, starting at 1.',
				),
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'moderate_comments';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$per_page = PostSupport::per_page( $args );
		$page     = PostSupport::page( $args );

		$query = array(
			'status'    => $args['status'] ?? 'all',
			'post_type' => $this->editable_post_types(),
		);
		if ( isset( $args['post_id'] ) ) {
			$post = get_post( $args['post_id'] );
			if ( ! $post instanceof \WP_Post ) {
				throw new ToolError( 'Post ' . $args['post_id'] . ' not found.', 'not_found' );
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				throw new ToolError( 'You are not allowed to see the comments of post ' . $post->ID . '.', 'forbidden' );
			}
			$query['post_id'] = $post->ID;
		}
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$query['search'] = $args['search'];
		}

		if ( ! $query['post_type'] ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}

		$comments = get_comments(
			array_merge(
				$query,
				array(
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
					'orderby' => 'comment_date_gmt',
					'order'   => 'DESC',
				)
			)
		);
		$total    = (int) get_comments( array_merge( $query, array( 'count' => true ) ) );

		$items = array();
		foreach ( $comments as $comment ) {
			// Defence in depth: the post type scope above already implies this.
			if ( $comment instanceof \WP_Comment && current_user_can( 'edit_comment', $comment->comment_ID ) ) {
				$items[] = SiteSupport::comment_data( $comment );
			}
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Post types for which the current user may edit every post, so every
	 * comment on them is theirs to moderate (edit_comment follows edit_post).
	 * Restricting the query to these keeps total and paging exact.
	 *
	 * @return string[]
	 */
	private function editable_post_types(): array {
		$types = array();
		foreach ( get_post_types( array(), 'objects' ) as $name => $type ) {
			if ( ! post_type_supports( $name, 'comments' ) ) {
				continue;
			}
			if ( current_user_can( $type->cap->edit_others_posts )
				&& current_user_can( $type->cap->edit_published_posts )
				&& current_user_can( $type->cap->edit_private_posts ) ) {
				$types[] = $name;
			}
		}
		return $types;
	}
}
