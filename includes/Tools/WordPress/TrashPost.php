<?php
/**
 * Tool: trash_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Moves a post to the trash. There is deliberately no permanent delete.
 */
final class TrashPost extends Tool {

	public function name(): string {
		return 'trash_post';
	}

	public function description(): string {
		return 'Move a post or page to the trash. It can be brought back with restore_post. Permanent deletion is not available.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the post to trash.',
				),
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

		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to trash post ' . $post->ID . '.', 'forbidden' );
		}
		if ( 'trash' === $post->post_status ) {
			throw new ToolError( 'Post ' . $post->ID . ' is already in the trash.', 'invalid_state' );
		}
		// wp_trash_post() permanently deletes when the trash is disabled.
		if ( ! EMPTY_TRASH_DAYS ) {
			throw new ToolError( 'The WordPress trash is disabled on this site (EMPTY_TRASH_DAYS is 0), so trashing would delete permanently. Refusing.', 'trash_disabled' );
		}

		$result = wp_trash_post( $post->ID );
		if ( ! $result ) {
			throw new ToolError( 'WordPress refused to trash post ' . $post->ID . '.', 'trash_failed' );
		}
		return PostFormatter::summary( get_post( $post->ID ) );
	}
}
