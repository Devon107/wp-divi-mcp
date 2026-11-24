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
		add_action( 'rest_api_init', array( Mcp\Transport::class, 'register' ) );
		Log\ActivityLog::register();
		Admin\SettingsPage::register();
		add_filter( 'wpdm_registry', array( Tools\WordPress\Registrar::class, 'add' ) );
		add_filter( 'wpdm_registry', array( Tools\Divi\Registrar::class, 'add' ) );
		add_filter( 'wpdm_registry', array( Tools\Seo\Registrar::class, 'add' ) );
		add_filter( 'wpdm_registry', array( Tools\I18n\Registrar::class, 'add' ) );
		add_filter( 'wpdm_registry', array( Knowledge\Registrar::class, 'add' ) );
		add_filter( 'wpdm_instructions', array( Knowledge\Instructions::class, 'filter' ) );
		Divi\Backups::register();
		Divi\Nonces::register();
		// Further hooks are registered here by later tasks.
	}

	/**
	 * Activation: create the log table and schedule the daily prune.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Log\ActivityLog::install();
	}

	/**
	 * Deactivation: stop the daily prune. Data is kept.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Log\ActivityLog::unschedule_prune();
	}

	private function __construct() {}

	private function __clone() {}
}
