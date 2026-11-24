<?php
/**
 * Tool: seo_update.
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
 * Sets the Yoast SEO title and/or meta description of a post.
 */
final class SeoUpdate extends Tool {

	const TITLE_MAX = 60;
	const DESC_MAX  = 155;
	const DESC_MIN  = 50;

	public function name(): string {
		return 'seo_update';
	}

	public function description(): string {
		return "Set the Yoast SEO title and/or meta description of a post or page (any status; content is not touched). Empty string resets to Yoast's template; Yoast variables such as %%title%% and %%sep%% are allowed. Returns the previous values for undo.";
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Post or page ID.',
				),
				'title'       => array(
					'type'        => 'string',
					'description' => "SEO title (plain text; Yoast variables allowed). '' resets to Yoast's template.",
				),
				'description' => array(
					'type'        => 'string',
					'description' => "Meta description (plain text). '' resets to Yoast's template.",
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
		if ( ! array_key_exists( 'title', $args ) && ! array_key_exists( 'description', $args ) ) {
			throw new ToolError( 'Pass title and/or description.', 'invalid_argument' );
		}
		$post = PostSupport::find_post( $args['id'] );
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new ToolError( 'You are not allowed to edit post ' . $post->ID . '.', 'forbidden' );
		}

		$title       = array_key_exists( 'title', $args ) ? Yoast::clean( (string) $args['title'] ) : null;
		$description = array_key_exists( 'description', $args ) ? Yoast::clean( (string) $args['description'] ) : null;

		$previous = Yoast::stored( $post->ID );
		Yoast::write( $post->ID, $title, $description );

		$warnings = array();
		$refreshed = Yoast::refresh( $post->ID );
		if ( ! $refreshed ) {
			$warnings[] = 'Yoast could not refresh its cache; the change shows on the site after the next page save.';
		}

		$stored    = Yoast::stored( $post->ID );
		$effective = Yoast::effective( $post->ID );

		if ( $refreshed && '' === $effective['title'] ) {
			$warnings[] = 'Yoast returned no SEO title for this post: Yoast does not index this post type, so the values are saved but have no effect on the site.';
		}
		foreach ( array( 'title', 'description' ) as $field ) {
			$requested = array_key_exists( $field, $args ) ? trim( (string) $args[ $field ] ) : '';
			if ( '' !== $requested && $stored[ $field ] !== $requested ) {
				$warnings[] = sprintf( 'Yoast cleaned the %s when saving it; stored: "%s".', $field, $stored[ $field ] );
			}
		}

		$title_len = mb_strlen( $effective['title'] );
		if ( $title_len > self::TITLE_MAX ) {
			$warnings[] = sprintf( 'The SEO title is %d characters; search engines usually show about %d.', $title_len, self::TITLE_MAX );
		}
		$desc_len = mb_strlen( $effective['description'] );
		if ( $desc_len > self::DESC_MAX ) {
			$warnings[] = sprintf( 'The meta description is %d characters; search engines usually show about %d.', $desc_len, self::DESC_MAX );
		} elseif ( $desc_len > 0 && $desc_len < self::DESC_MIN ) {
			$warnings[] = sprintf( 'The meta description is %d characters; aim for at least %d.', $desc_len, self::DESC_MIN );
		}

		return array(
			'id'                    => (int) $post->ID,
			'previous'              => $previous,
			'title'                 => $stored['title'],
			'description'           => $stored['description'],
			'effective_title'       => $effective['title'],
			'effective_description' => $effective['description'],
			'warnings'              => $warnings,
		);
	}
}
