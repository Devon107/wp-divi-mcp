<?php
/**
 * The Theme Builder footer and its translations.
 *
 * @package WPDM
 */

namespace WPDM\I18n;

use WPDM\Divi\Env;
use WPDM\Mcp\ToolError;
use WPDM\Tools\Divi\ThemeBuilderSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Which layout is the site-wide footer, and which language versions of it exist. A footer in the
 * Default Website Template shows on every page, so a translated page needs a translated footer too.
 */
final class ThemeBuilderFooter {

	/**
	 * The footer layout of the Default Website Template and its translations.
	 *
	 * Reads the published Theme Builder through ThemeBuilderSupport, which needs the logged-in user
	 * to be allowed to use Divi's Theme Builder routes.
	 *
	 * @param Languages $languages Language adapter.
	 * @return array{layout_id:int,translations:array<string,int>}|null Null when Divi 5 is not active
	 *         or the Default Website Template has no footer.
	 * @throws ToolError From Divi's REST routes.
	 */
	public static function info( Languages $languages ): ?array {
		if ( ! Env::divi_active() ) {
			return null;
		}
		return self::from_templates( $languages, ThemeBuilderSupport::templates()['templates'] );
	}

	/**
	 * Same as info(), from a list of Theme Builder templates (as divi_list_theme_builder_templates
	 * returns them).
	 *
	 * @param Languages $languages Language adapter.
	 * @param array[]   $templates Templates.
	 * @return array{layout_id:int,translations:array<string,int>}|null
	 */
	public static function from_templates( Languages $languages, array $templates ): ?array {
		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) || ! ThemeBuilderSupport::is_default( $template ) ) {
				continue;
			}
			$layout_id = ThemeBuilderSupport::layout_id( $template, 'footer' );
			if ( $layout_id > 0 ) {
				return array(
					'layout_id'    => $layout_id,
					'translations' => $languages->translations( $layout_id ),
				);
			}
		}
		return null;
	}

	private function __construct() {}
}
