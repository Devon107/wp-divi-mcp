<?php
/**
 * Registers the Yoast SEO tools when Yoast is active.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Seo;

use WPDM\Mcp\Registry;
use WPDM\Seo\Yoast;

defined( 'ABSPATH' ) || exit;

/**
 * SEO tool registrar.
 */
final class Registrar {

	/**
	 * Add the SEO tools available on this site.
	 *
	 * @param Registry $registry Registry.
	 * @return Registry
	 */
	public static function add( Registry $registry ): Registry {
		if ( Yoast::active() ) {
			$registry->add_tool( new SeoGet() );
			$registry->add_tool( new SeoUpdate() );
		}
		return $registry;
	}

	private function __construct() {}
}
