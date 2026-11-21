<?php
/**
 * Tool: list_terms.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the terms of one taxonomy.
 */
final class ListTerms extends Tool {

	public function name(): string {
		return 'list_terms';
	}

	public function description(): string {
		return 'List the terms (categories, tags, custom) of one taxonomy, ordered by name, with optional search, parent and hide_empty filters. Use list_taxonomies to find taxonomy names. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'taxonomy'   => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Taxonomy name, e.g. category or post_tag.',
				),
				'search'     => array(
					'type'        => 'string',
					'description' => 'Search text (name or slug).',
				),
				'parent'     => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Only direct children of this term ID (0 for top level terms).',
				),
				'hide_empty' => array(
					'type'        => 'boolean',
					'description' => 'Hide terms without posts. Default false.',
				),
				'per_page'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => PostSupport::MAX_PER_PAGE,
					'description' => 'Items per page. Default 20, capped at 100.',
				),
				'page'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page number, starting at 1.',
				),
			),
			'required'             => array( 'taxonomy' ),
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
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$taxonomy = SiteSupport::taxonomy( $args['taxonomy'] );
		$per_page = PostSupport::per_page( $args );
		$page     = PostSupport::page( $args );

		// Terms of private taxonomies are for the people who manage or assign them.
		if ( ! $taxonomy->public && ! current_user_can( $taxonomy->cap->assign_terms ) && ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			throw new ToolError( 'You are not allowed to list the terms of "' . $taxonomy->name . '".', 'forbidden' );
		}

		$filter = array(
			'taxonomy'   => $taxonomy->name,
			'hide_empty' => ! empty( $args['hide_empty'] ),
		);
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$filter['search'] = $args['search'];
		}
		if ( isset( $args['parent'] ) ) {
			$filter['parent'] = $args['parent'];
		}

		$terms = get_terms(
			array_merge(
				$filter,
				array(
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
					'orderby' => 'name',
					'order'   => 'ASC',
				)
			)
		);
		if ( is_wp_error( $terms ) ) {
			throw new ToolError( 'Could not list terms: ' . $terms->get_error_message(), 'term_error' );
		}
		$total = wp_count_terms( $filter );
		$total = is_wp_error( $total ) ? count( $terms ) : (int) $total;

		$items = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$items[] = SiteSupport::term_data( $term );
			}
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}
}
