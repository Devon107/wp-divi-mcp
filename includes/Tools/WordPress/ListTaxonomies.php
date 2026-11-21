<?php
/**
 * Tool: list_taxonomies.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists taxonomies.
 */
final class ListTaxonomies extends Tool {

	public function name(): string {
		return 'list_taxonomies';
	}

	public function description(): string {
		return 'List taxonomies (categories, tags, custom), optionally only those that apply to a post type, with the capabilities the current user has for them.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Only taxonomies that apply to this post type.',
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
		$args  = PostSupport::validate( $this->input_schema(), $args );
		$types = PostSupport::post_types();

		if ( isset( $args['post_type'] ) ) {
			PostSupport::post_type( $args['post_type'] );
			$names = get_object_taxonomies( $args['post_type'] );
		} else {
			$names = array_keys( get_taxonomies() );
		}

		$items = array();
		foreach ( $names as $name ) {
			$taxonomy = get_taxonomy( $name );
			if ( ! $taxonomy || ( ! $taxonomy->public && ! $taxonomy->show_ui ) ) {
				continue;
			}
			$items[] = array(
				'name'             => $taxonomy->name,
				'label'            => $taxonomy->label,
				'hierarchical'     => (bool) $taxonomy->hierarchical,
				'public'           => (bool) $taxonomy->public,
				'post_types'       => array_values( array_intersect( (array) $taxonomy->object_type, array_keys( $types ) ) ),
				'rest_base'        => $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name,
				'can_assign'       => current_user_can( $taxonomy->cap->assign_terms ),
				'can_create_terms' => current_user_can( $taxonomy->cap->edit_terms ),
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
		return array( 'items' => $items );
	}
}
