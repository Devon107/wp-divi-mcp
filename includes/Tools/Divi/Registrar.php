<?php
/**
 * Registers the Divi tools.
 *
 * @package WPDM
 */

namespace WPDM\Tools\Divi;

use WPDM\Divi\Env;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Hooked on the wpdm_registry filter. Build, validate and find_icon work
 * without Divi; the others are registered only when Divi 5 is active.
 */
final class Registrar {

	/** Tools that need no Divi theme. */
	const ALWAYS = array( 'divi_build_layout', 'divi_validate_layout', 'divi_find_icon' );

	/**
	 * Every Divi tool, regardless of the environment.
	 *
	 * @return Tool[]
	 */
	public static function tools(): array {
		return array(
			new DiviStatus(),
			new GetGlobals(),
			new MergeGlobals(),
			new BuildLayout(),
			new ValidateLayout(),
			new ApplyLayout(),
			new CreatePage(),
			new ExportPage(),
			new InspectPage(),
			new ListBackups(),
			new RestoreBackup(),
			new SetPageCss(),
			new FindIcon(),
			new ListThemeBuilderTemplates(),
			new CreateThemeBuilderTemplate(),
			new DeleteThemeBuilderTemplate(),
			new SetThemeBuilderLayout(),
		);
	}

	/**
	 * Add the Divi tools available on this site.
	 *
	 * @param Registry $registry Registry.
	 * @return Registry
	 */
	public static function add( Registry $registry ): Registry {
		$divi = Env::divi_active();
		foreach ( self::tools() as $tool ) {
			if ( $divi || in_array( $tool->name(), self::ALWAYS, true ) ) {
				$registry->add_tool( $tool );
			}
		}
		return $registry;
	}

	private function __construct() {}
}
