<?php
/**
 * Plugin bootstrap.
 *
 * @package WPDM
 */

namespace WPDM;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton. Later components register their hooks in boot().
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks. Idempotent.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		// Hooks are registered here by later tasks.
	}

	private function __construct() {}

	private function __clone() {}
}
