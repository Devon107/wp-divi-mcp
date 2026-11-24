<?php
/**
 * Registers the language tools when a language plugin is active.
 *
 * @package WPDM
 */

namespace WPDM\Tools\I18n;

use WPDM\I18n\Languages;
use WPDM\Mcp\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Language tool registrar.
 */
final class Registrar {

	/**
	 * Add the language tools when Polylang or WPML is active.
	 *
	 * @param Registry $registry Registry.
	 * @return Registry
	 */
	public static function add( Registry $registry ): Registry {
		if ( null !== Languages::current() ) {
			$registry->add_tool( new I18nStatus() );
			$registry->add_tool( new I18nGetTranslations() );
			$registry->add_tool( new I18nSetLanguage() );
			$registry->add_tool( new I18nLinkTranslation() );
		}
		return $registry;
	}

	private function __construct() {}
}
