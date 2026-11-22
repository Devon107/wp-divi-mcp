<?php
/**
 * Tool: create_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Creates a post or page (draft by default).
 */
final class CreatePost extends Tool {

	public function name(): string {
		return 'create_post';
	}

	public function description(): string {
		return 'Create a post or page of any post type. New content is a draft unless you pass a status. Supports parent, terms, featured image and custom fields. To design a Divi page, create the page here and then apply a layout with the Divi tools.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				array(
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type. Default: page.',
					),
				),
				PostSupport::write_properties()
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$type = PostSupport::post_type( $args['post_type'] ?? 'page' );

		if ( ! current_user_can( $type->cap->create_posts ) ) {
			throw new ToolError( 'You are not allowed to create ' . $type->name . ' content.', 'forbidden' );
		}

		$plan    = PostSupport::plan( $type, $args, null );
		$postarr = array_merge(
			array(
				'post_status' => 'draft',
			),
			$plan['post'],
			array(
				'post_type'   => $type->name,
				'post_author' => $ctx->user_id,
			)
		);

		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			throw new ToolError( 'Could not create the post: ' . $id->get_error_message(), 'create_failed' );
		}

		try {
			PostSupport::apply( $id, $plan, true );
		} catch ( ToolError $e ) {
			PostSupport::discard_created( $id );
			throw $e;
		}

		return PostFormatter::full( get_post( $id ), true );
	}
}
