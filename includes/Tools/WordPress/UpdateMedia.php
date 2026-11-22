<?php
/**
 * Tool: update_media.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Edits the text fields of a media item.
 */
final class UpdateMedia extends Tool {

	public function name(): string {
		return 'update_media';
	}

	public function description(): string {
		return 'Edit a media item\'s title, alt text, caption or description. Only items the current user may edit.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Attachment ID.',
				),
				'title'       => array(
					'type'        => 'string',
					'description' => 'Title.',
				),
				'alt'         => array(
					'type'        => 'string',
					'description' => 'Alternative text.',
				),
				'caption'     => array(
					'type'        => 'string',
					'description' => 'Caption.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Description.',
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'upload_files';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );

		$attachment = SiteSupport::find_attachment( $args['id'] );
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			throw new ToolError( 'You are not allowed to edit media item ' . $attachment->ID . '.', 'forbidden' );
		}
		if ( ! array_intersect( array( 'title', 'alt', 'caption', 'description' ), array_keys( $args ) ) ) {
			throw new ToolError( 'Provide at least one of: title, alt, caption, description.', 'invalid_argument' );
		}

		$update = array( 'ID' => $attachment->ID );
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $args['caption'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['post_content'] = wp_kses_post( $args['description'] );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				throw new ToolError( 'Could not update the media item: ' . $result->get_error_message(), 'update_failed' );
			}
		}
		if ( isset( $args['alt'] ) ) {
			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $args['alt'] ) ) );
		}

		return SiteSupport::attachment_data( SiteSupport::find_attachment( $attachment->ID ) );
	}
}
