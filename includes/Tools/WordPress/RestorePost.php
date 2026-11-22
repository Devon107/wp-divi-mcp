<?php
/**
 * Tool: restore_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Brings a post back from the trash.
 */
final class RestorePost extends Tool {

	public function name(): string {
		return 'restore_post';
	}

	public function description(): string {
		return 'Restore a trashed post or page. WordPress restores it as a draft.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the trashed post.',
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'edit_posts';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = PostSupport::find_post( $args['id'] );

		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to restore post ' . $post->ID . '.', 'forbidden' );
		}
		if ( 'trash' !== $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is not in the trash.', 'invalid_state' );
		}

		$result = wp_untrash_post( $post->ID );
		if ( ! $result ) {
			throw new ToolError( 'WordPress refused to restore post ' . $post->ID . '.', 'restore_failed' );
		}
		return PostFormatter::summary( get_post( $post->ID ) );
	}
}
