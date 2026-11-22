<?php
/**
 * Tool: list_media.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Lists media library items.
 */
final class ListMedia extends Tool {

	public function name(): string {
		return 'list_media';
	}

	public function description(): string {
		return 'List media library items, newest first, with optional search and mime type filter (e.g. "image" or "application/pdf"). Users who cannot edit other people\'s content only see their own files. Paginated (max 100 per page).';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'search'    => array(
					'type'        => 'string',
					'description' => 'Search text (title, caption, description).',
				),
				'mime_type' => array(
					'type'        => 'string',
					'description' => 'A mime type ("image/png"), or a family ("image", "video", "audio", "application").',
				),
				'per_page'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => PostSupport::MAX_PER_PAGE,
					'description' => 'Items per page. Default 20, capped at 100.',
				),
				'page'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Page number, starting at 1.',
				),
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'upload_files';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args     = PostSupport::validate( $this->input_schema(), $args );
		$per_page = PostSupport::per_page( $args );
		$page     = PostSupport::page( $args );

		$query = array(
			'post_type'           => 'attachment',
			'post_status'         => 'inherit',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$query['s'] = $args['search'];
		}
		if ( isset( $args['mime_type'] ) ) {
			if ( ! preg_match( '#^[a-z0-9][a-z0-9.+-]*(/[a-z0-9][a-z0-9.+*-]*)?$#i', $args['mime_type'] ) ) {
				throw new ToolError( 'Argument "mime_type" is not a valid mime type or family.', 'invalid_argument' );
			}
			$query['post_mime_type'] = $args['mime_type'];
		}
		// Same rule as the media library: without edit_others_posts, own files only.
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query['author'] = get_current_user_id();
		}

		$wp    = new \WP_Query( $query );
		$items = array();
		foreach ( $wp->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$items[] = SiteSupport::attachment_data( $post );
			}
		}
		$total = (int) $wp->found_posts;
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}
}
