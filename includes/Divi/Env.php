<?php
/**
 * Divi environment detection.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

defined( 'ABSPATH' ) || exit;

/**
 * Whether Divi 5 is loaded, and which version.
 */
final class Env {

	const GLOBAL_DATA = '\ET\Builder\Packages\GlobalData\GlobalData';

	/**
	 * Divi 5 is loaded (its GlobalData package is available).
	 *
	 * @return bool
	 */
	public static function divi_active(): bool {
		return class_exists( self::GLOBAL_DATA );
	}

	/**
	 * Version of the parent theme (Divi), or null when Divi 5 is not active.
	 *
	 * @return string|null
	 */
	public static function divi_version(): ?string {
		if ( ! self::divi_active() ) {
			return null;
		}
		$theme   = wp_get_theme();
		$parent  = $theme->parent() ? $theme->parent() : $theme;
		$version = (string) $parent->get( 'Version' );
		return '' === $version ? null : $version;
	}

	private function __construct() {}
}
