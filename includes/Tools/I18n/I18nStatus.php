<?php
/**
 * Tool: i18n_status.
 *
 * @package WPDM
 */

namespace WPDM\Tools\I18n;

use WPDM\I18n\ThemeBuilderFooter;
use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;
use WPDM\Tools\WordPress\PostSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Reports the language plugin, the configured languages and the Theme Builder footer translations.
 */
final class I18nStatus extends Tool {

	public function name(): string {
		return 'i18n_status';
	}

	public function description(): string {
		return "Multilingual status of the site: which language plugin is active (polylang or wpml), the default language, and the configured languages (code, name, locale, default). Language codes are the plugin's own (en, fr, pt-br...): use these codes in every other i18n_* tool. With Divi, also theme_builder_footer: the layout id of the Default Website Template's footer and its translations by language code, which shows on every page; null when there is none or you may not read the Theme Builder.";
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
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
		PostSupport::validate( array( 'properties' => array() ), $args );
		$languages = I18nSupport::adapter();

		try {
			$footer = ThemeBuilderFooter::info( $languages );
		} catch ( ToolError $e ) {
			// The Theme Builder is only readable with Divi's own permissions; the rest of the status is still valid.
			$footer = null;
		}

		return array(
			'plugin'               => $languages->name(),
			'default_language'     => $languages->default_language(),
			'languages'            => $languages->languages(),
			'theme_builder_footer' => null === $footer ? null : array(
				'layout_id'    => $footer['layout_id'],
				'translations' => array() === $footer['translations'] ? new \stdClass() : $footer['translations'],
			),
		);
	}
}
