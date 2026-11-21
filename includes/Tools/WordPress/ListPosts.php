<?php
/**
 * Tool: list_posts.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists posts of one post type.
 */
final class ListPosts extends Tool {

	public function name(): string {
		return 'list_posts';
	}

	public function description(): string {
		return 'List posts or pages of one post type (default: page), newest first, with optional search, status, author and taxonomy filters. Only returns content the current user may read. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to list. Default: page.',
				),
				'search'    => array(
					'type'        => 'string',
					'description' => 'Search text.',
				),
				'status'    => array(
					'type'        => 'string',
					'enum'        => PostSupport::LIST_STATUSES,
					'description' => 'Status filter: any (default, excludes trash), publish, draft, pending, private, future or trash.',
				),
				'author'    => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Only posts by this user ID.',
				),
				'tax_query' => array(
					'type'                 => 'object',
					'description'          => 'Filter by terms of one taxonomy: {"taxonomy": "category", "terms": [3, "News"]} (integers are term IDs, strings term names).',
					'properties'           => array(
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array(
							'type'  => 'array',
							'items' => array( 'type' => array( 'integer', 'string' ) ),
						),
					),
					'required'             => array( 'taxonomy', 'terms' ),
					'additionalProperties' => false,
				),
				'per_page'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => PostSupport::MAX_PER_PAGE,
					'description' => 'Items per page. Default 20, capped at 100.',
				),
				'page'      => array(
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
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );

		$type     = PostSupport::post_type( $args['post_type'] ?? 'page' );
		$per_page = PostSupport::per_page( $args );
		$page     = PostSupport::page( $args );

		$query = array(
			'post_type'   => $type->name,
			'post_status' => $args['status'] ?? 'any',
		);
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$query['s'] = $args['search'];
		}
		if ( isset( $args['author'] ) ) {
			$query['author'] = $args['author'];
		}
		if ( isset( $args['tax_query'] ) ) {
			$query['tax_query'] = $this->tax_query( $args['tax_query'] );
		}

		return PostSupport::query_readable( $query, $per_page, $page );
	}

	/**
	 * @param array $filter {taxonomy, terms}.
	 * @return array WP_Query tax_query.
	 * @throws ToolError On invalid input.
	 */
	private function tax_query( array $filter ): array {
		$taxonomy = $filter['taxonomy'];
		$terms    = $filter['terms'];
		if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			throw new ToolError( 'Unknown taxonomy "' . ( is_string( $taxonomy ) ? $taxonomy : '' ) . '".', 'invalid_argument' );
		}
		if ( ! is_array( $terms ) || array_values( $terms ) !== $terms || ! $terms ) {
			throw new ToolError( 'tax_query.terms must be a non-empty list.', 'invalid_argument' );
		}
		$ids   = array();
		$names = array();
		foreach ( $terms as $term ) {
			if ( is_int( $term ) ) {
				$ids[] = $term;
			} elseif ( is_string( $term ) && '' !== $term ) {
				$names[] = $term;
			} else {
				throw new ToolError( 'tax_query.terms entries must be integer IDs or names.', 'invalid_argument' );
			}
		}
		$clauses = array();
		if ( $ids ) {
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $ids,
			);
		}
		if ( $names ) {
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'name',
				'terms'    => $names,
			);
		}
		return count( $clauses ) > 1 ? array_merge( array( 'relation' => 'OR' ), $clauses ) : $clauses;
	}
}
