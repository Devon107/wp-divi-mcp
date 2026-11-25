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

	/** Statuses whose content change is visible at once (as DiviSupport::PUBLISHED). */
	const PUBLISHED = array( 'publish', 'future', 'private' );

	public function name(): string {
		return 'update_post';
	}

	public function description(): string {
		return 'Update a post or page by ID. Only the fields you pass change; terms listed per taxonomy replace that taxonomy\'s terms, meta null deletes a key. Overwrites existing values. Whenever content is passed, the current content is backed up first and backup_id is returned (divi_restore_backup restores it when Divi is active). The response leaves out content_raw unless include_content=true. To change a small part of the content, use edit_post_content instead of resending all of it. Changing the content of a published, scheduled or private post needs confirm_published=true (with or without force). Content of a Divi post is protected: use the Divi layout tools, or pass force=true to replace it anyway.';
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
					'force'             => array(
						'type'        => 'boolean',
						'description' => 'Replace the content of a Divi post anyway (the current content is backed up first).',
					),
					'confirm_published' => array(
						'type'        => 'boolean',
						'description' => 'Required (true) to change the content of a published, scheduled or private post: the change is visible at once.',
					),
					'include_content'   => PostFormatter::include_content_property(),
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

		$content_change = isset( $args['content'] );
		if ( $content_change && PostFormatter::is_divi( $post ) && empty( $args['force'] ) ) {
			throw new ToolError( 'This post is built with Divi; its content cannot be replaced here. Use divi_apply_layout to change the design, or pass force=true to overwrite it after a backup.', 'divi_content_protected' );
		}
		if ( $content_change && empty( $args['confirm_published'] ) && in_array( $post->post_status, self::PUBLISHED, true ) ) {
			throw new ToolError(
				'Post ' . $post->ID . ' is ' . $post->post_status . '; changing its content would be visible at once. Ask the user, then pass confirm_published=true (the current content is backed up first).',
				'confirmation_required',
				array(
					'post_id' => $post->ID,
					'status'  => $post->post_status,
				)
			);
		}

		$type          = PostSupport::post_type( $post->post_type );
		$plan          = PostSupport::plan( $type, $args, $post );
		$previous_date = PostFormatter::summary( $post )['date'];

		// Back up only once every check has passed, right before the write.
		$backup_id = null;
		if ( $content_change ) {
			clean_post_cache( $post->ID );
			$backup_id = Backups::save(
				'post_content',
				$post->ID,
				(string) get_post_field( 'post_content', $post->ID, 'raw' ),
				array(
					'post_type' => $post->post_type,
					'status'    => $post->post_status,
					'reason'    => 'update_post',
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

		$out = PostFormatter::written( get_post( $post->ID ), $args );
		if ( null !== $backup_id ) {
			$out['backup_id'] = $backup_id;
		}
		if ( $out['date'] !== $previous_date ) {
			$out['previous_date'] = $previous_date;
		}
		return $out;
	}
}
