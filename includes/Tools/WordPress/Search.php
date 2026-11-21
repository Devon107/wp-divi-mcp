<?php
/**
 * Tool: search.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Text search across post types.
 */
final class Search extends Tool {

	public function name(): string {
		return 'search';
	}

	public function description(): string {
		return 'Search titles and content across post types (default: all public ones). Only returns content the current user may read. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'query'      => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Text to search for.',
				),
				'post_types' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Post types to search. Default: all public post types.',
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
			'required'             => array( 'query' ),
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

		$types = array();
		if ( ! empty( $args['post_types'] ) ) {
			foreach ( $args['post_types'] as $name ) {
				if ( ! is_string( $name ) ) {
					throw new ToolError( 'post_types must be a list of post type names.', 'invalid_argument' );
				}
				$types[] = PostSupport::post_type( $name )->name;
			}
		} else {
			foreach ( PostSupport::post_types() as $name => $type ) {
				if ( $type->public ) {
					$types[] = $name;
				}
			}
		}

		return PostSupport::query_readable(
			array(
				'post_type'   => $types,
				'post_status' => 'any',
				's'           => $args['query'],
			),
			PostSupport::per_page( $args ),
			PostSupport::page( $args )
		);
	}
}
