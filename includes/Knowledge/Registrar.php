<?php
/**
 * Registers the knowledge resources and prompts.
 *
 * @package WPDM
 */

namespace WPDM\Knowledge;

use WPDM\Mcp\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Hooked on the wpdm_registry filter. Always registers: the Divi knowledge is
 * useful before Divi is active too (planning a page, reading the reference).
 */
final class Registrar {

	/**
	 * Add the resources and prompts.
	 *
	 * @param Registry $registry Registry.
	 * @return Registry
	 */
	public static function add( Registry $registry ): Registry {
		Resources::register( $registry );
		Prompts::register( $registry );
		return $registry;
	}

	private function __construct() {}
}
