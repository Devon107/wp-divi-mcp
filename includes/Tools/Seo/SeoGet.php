<?php
/**
 * Tool: seo_get.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Seo;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Seo\Yoast;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the Yoast SEO title and meta description of a post.
 */
final class SeoGet extends Tool {

	public function name(): string {
		return 'seo_get';
	}

	public function description(): string {
		return "Get the Yoast SEO title and meta description of a post or page: the stored values ('' = Yoast's template) and the effective values the site renders.";
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID.',
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

		// Same visibility as get_post: editors of the post, or published and readable for others.
		if ( ! current_user_can( 'edit_post', $post->ID )
			&& ( 'publish' !== $post->post_status || ! current_user_can( 'read_post', $post->ID ) || post_password_required( $post ) ) ) {
			throw new ToolError( 'You are not allowed to read post ' . $post->ID . '.', 'forbidden' );
		}

		$stored    = Yoast::stored( $post->ID );
		$effective = Yoast::effective( $post->ID );
		return array(
			'id'                    => (int) $post->ID,
			'title'                 => $stored['title'],
			'description'           => $stored['description'],
			'effective_title'       => $effective['title'],
			'effective_description' => $effective['description'],
			'yoast_version'         => Yoast::version(),
		);
	}
}
