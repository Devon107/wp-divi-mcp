<?php
/**
 * Tool: update_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Divi\Backups;
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
		return 'Update a post or page by ID. Only the fields you pass change; terms listed per taxonomy replace that taxonomy\'s terms, meta null deletes a key. Overwrites existing values. Content of a Divi post is protected: use the Divi layout tools, or pass force=true to replace it anyway (the current content is backed up first and backup_id is returned).';
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
						'description' => 'Replace the content of a Divi post anyway. The current content is backed up first; the result includes backup_id.',
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

		$backup_needed = false;
		if ( isset( $args['content'] ) && PostFormatter::is_divi( $post ) ) {
			if ( empty( $args['force'] ) ) {
				throw new ToolError( 'This post is built with Divi; its content cannot be replaced here. Use divi_apply_layout to change the design, or pass force=true to overwrite it after a backup.', 'divi_content_protected' );
			}
			$backup_needed = true;
		}

		$type = PostSupport::post_type( $post->post_type );
		$plan = PostSupport::plan( $type, $args, $post );

		// Back up only once every check has passed, right before the write.
		$backup_id = null;
		if ( $backup_needed ) {
			$backup_id = Backups::save(
				'post_content',
				$post->ID,
				$post->post_content,
				array(
					'post_title'  => $post->post_title,
					'post_status' => $post->post_status,
				),
				(string) ( $ctx->connection['label'] ?? '' )
			);
		}

		if ( $plan['post'] ) {
			$postarr = array_merge( $plan['post'], array( 'ID' => $post->ID ) );
			$result  = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) ) {
				throw new ToolError( 'Could not update the post: ' . $result->get_error_message(), 'update_failed' );
			}
		}
		PostSupport::apply( $post->ID, $plan, false );

		$out = PostFormatter::full( get_post( $post->ID ), true );
		if ( null !== $backup_id ) {
			$out['backup_id'] = $backup_id;
		}
		return $out;
	}
}
