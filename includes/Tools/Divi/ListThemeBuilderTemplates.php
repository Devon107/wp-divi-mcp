<?php
/**
 * divi_list_theme_builder_templates.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * List the templates of the published Theme Builder.
 */
final class ListThemeBuilderTemplates extends Tool {

	public function name(): string {
		return 'divi_list_theme_builder_templates';
	}

	public function description(): string {
		return 'List the templates of the published Divi Theme Builder: id, title, default, enabled, where they apply (use_on / exclude_from) and their header/body/footer layout ids. The template with default=true is the Default Website Template: its header and footer show on the whole site.';
	}

	public function input_schema(): array {
		return DiviSupport::schema( array() );
	}

	public function read_only(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		PostSupport::validate( $this->input_schema(), $args );
		return array_merge( array( 'ok' => true ), ThemeBuilderSupport::templates() );
	}
}
