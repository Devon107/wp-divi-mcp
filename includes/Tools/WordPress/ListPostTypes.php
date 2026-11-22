<?php
/**
 * Tool: list_post_types.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the post types the content tools can work with.
 */
final class ListPostTypes extends Tool {

	public function name(): string {
		return 'list_post_types';
	}

	public function description(): string {
		return 'List the post types (pages, posts, custom types) that the content tools can read and write, with their taxonomies and whether the current user may create them.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
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
		PostSupport::validate( $this->input_schema(), $args );

		$items = array();
		foreach ( PostSupport::post_types() as $name => $type ) {
			$items[] = array(
				'name'         => $name,
				'label'        => $type->label,
				'public'       => (bool) $type->public,
				'hierarchical' => (bool) $type->hierarchical,
				'taxonomies'   => array_values( get_object_taxonomies( $name ) ),
				'supports'     => array_keys( get_all_post_type_supports( $name ) ),
				'rest_base'    => $type->rest_base ? $type->rest_base : $name,
				'can_create'   => current_user_can( $type->cap->create_posts ),
			);
		}
		return array( 'items' => $items );
	}
}
