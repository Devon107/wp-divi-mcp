<?php
/**
 * Tool: get_post.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one post in full.
 */
final class GetPost extends Tool {

	public function name(): string {
		return 'get_post';
	}

	public function description(): string {
		return 'Get one post or page by ID: raw content, excerpt, terms, featured image, custom fields and whether it is built with Divi. Divi pages can be very large: pass include_content=false to get everything except content_raw (content_length is always returned), and use divi_inspect_page to look inside a Divi layout. Drafts, private and password-protected posts are only returned to users who may edit them.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post ID.',
				),
				'include_content' => array(
					'type'        => 'boolean',
					'description' => 'Include content_raw. Default true; false leaves it out (useful for large Divi pages).',
				),
			),
			'required'             => array( 'id' ),
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
		$args = PostSupport::validate( $this->input_schema(), $args );
		$post = PostSupport::find_post( $args['id'] );

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return self::shape( PostFormatter::full( $post, true ), $args );
		}

		// Everyone else only gets published, readable, non-password-protected posts.
		if ( 'publish' !== $post->post_status || ! current_user_can( 'read_post', $post->ID ) || post_password_required( $post ) ) {
			throw new ToolError( 'You are not allowed to read post ' . $post->ID . '.', 'forbidden' );
		}
		return self::shape( PostFormatter::full( $post, false ), $args );
	}

	/**
	 * Drop content_raw when the caller asked for include_content=false.
	 *
	 * @param array $data Full post representation.
	 * @param array $args Validated arguments.
	 * @return array
	 */
	private static function shape( array $data, array $args ): array {
		if ( isset( $args['include_content'] ) && false === $args['include_content'] ) {
			unset( $data['content_raw'] );
		}
		return $data;
	}
}
