<?php
/**
 * Tool: upload_media.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Support\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an image to the media library from a public URL or base64 data.
 */
final class UploadMedia extends Tool {

	public function name(): string {
		return 'upload_media';
	}

	public function description(): string {
		return 'Add an image to the media library, from a public http(s) URL or from base64 data (not both). Images only (max 5 MB); the type is detected from the content. URLs pointing to private or internal addresses are refused. Returns the attachment id and url.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'url'      => array(
					'type'        => 'string',
					'description' => 'Public http(s) URL of an image. Use this or base64.',
				),
				'base64'   => array(
					'type'        => 'string',
					'description' => 'Standard base64 of the image bytes, up to 5 MB decoded. Requires filename.',
				),
				'filename' => array(
					'type'        => 'string',
					'description' => 'File name for base64 data, e.g. logo.png. The extension is corrected to the detected type.',
				),
				'alt'      => array(
					'type'        => 'string',
					'description' => 'Alternative text.',
				),
				'title'    => array(
					'type'        => 'string',
					'description' => 'Attachment title (defaults to the file name).',
				),
				'caption'  => array(
					'type'        => 'string',
					'description' => 'Caption.',
				),
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function capability(): string {
		return 'upload_files';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );

		if ( ! current_user_can( 'upload_files' ) ) {
			throw new ToolError( 'You are not allowed to upload files.', 'forbidden' );
		}

		$has_url    = isset( $args['url'] );
		$has_base64 = isset( $args['base64'] );
		if ( $has_url === $has_base64 ) {
			throw new ToolError( 'Provide exactly one of "url" or "base64".', 'invalid_argument' );
		}
		if ( $has_url && isset( $args['filename'] ) ) {
			throw new ToolError( '"filename" is only used with "base64".', 'invalid_argument' );
		}
		if ( $has_base64 && ( ! isset( $args['filename'] ) || '' === trim( $args['filename'] ) ) ) {
			throw new ToolError( '"base64" requires "filename".', 'invalid_argument' );
		}

		$alt   = $args['alt'] ?? '';
		$title = $args['title'] ?? '';
		$file  = $has_url
			? Media::sideload( $args['url'], $alt, $title )
			: Media::from_base64( $args['base64'], $args['filename'], $alt );

		$update = array( 'ID' => $file['id'] );
		if ( '' !== $title && $has_base64 ) {
			$update['post_title'] = sanitize_text_field( $title );
		}
		if ( isset( $args['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $args['caption'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}

		return SiteSupport::attachment_data( SiteSupport::find_attachment( $file['id'] ) );
	}
}
