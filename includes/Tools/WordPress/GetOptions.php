<?php
/**
 * Tool: get_options.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the site settings the tools may manage.
 */
final class GetOptions extends Tool {

	public function name(): string {
		return 'get_options';
	}

	public function description(): string {
		return 'Read the site settings this plugin can manage: blogname, blogdescription, timezone_string, date_format, time_format, show_on_front, page_on_front, page_for_posts, posts_per_page. Also returns permalink_structure, which is read-only.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'manage_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		PostSupport::validate( $this->input_schema(), $args );
		return SiteSupport::options();
	}
}
