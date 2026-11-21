<?php
/**
 * Tool: update_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Updates a post or page.
 */
final class UpdatePost extends Tool {

	public function name(): string {
		return 'update_post';
	}

	public function description(): string {
		return 'Update a post or page by ID. Only the fields you pass change; terms listed per taxonomy replace that taxonomy\'s terms, meta null deletes a key. Overwrites existing values. Content of a Divi post cannot be replaced here: use the Divi layout tools instead.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				array(
					'id'        => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'ID of the post to update.',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Optional sanity check: must match the post\'s type (it cannot be changed).',
					),
				),
				PostSupport::write_properties(),
				array(
					'force' => array(
						'type'        => 'boolean',
						'description' => 'Overwrite the content of a Divi post anyway. Not available until backups are implemented.',
					),
				)
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = PostSupport::find_post( $args['id'] );

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post->ID . '.', 'forbidden' );
		}
		if ( 'trash' === $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is in the trash. Restore it with restore_post first.', 'invalid_state' );
		}
		if ( isset( $args['post_type'] ) && $args['post_type'] !== $post->post_type ) {
			throw new ToolError( 'Post ' . $post->ID . ' is a "' . $post->post_type . '"; the post type cannot be changed.', 'invalid_argument' );
		}

		if ( PostFormatter::is_divi( $post ) ) {
			if ( ! empty( $args['force'] ) ) {
				// Task 12 replaces this with a backup followed by the write.
				throw new ToolError( 'force is not available until backups are implemented', 'force_unavailable' );
			}
			if ( isset( $args['content'] ) ) {
				throw new ToolError( 'This post is built with Divi; its content cannot be replaced here. Use divi_apply_layout to change the design.', 'divi_content_protected' );
			}
		}

		$type = PostSupport::post_type( $post->post_type );
		$plan = PostSupport::plan( $type, $args, $post );

		if ( $plan['post'] ) {
			$postarr = array_merge( $plan['post'], array( 'ID' => $post->ID ) );
			$result  = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) ) {
				throw new ToolError( 'Could not update the post: ' . $result->get_error_message(), 'update_failed' );
			}
		}
		PostSupport::apply( $post->ID, $plan, false );

		return PostFormatter::full( get_post( $post->ID ), true );
	}
}
