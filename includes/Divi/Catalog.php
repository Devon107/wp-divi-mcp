<?php
/**
 * The Divi 5 module catalog the validator checks block names and attributes against.
 *
 * With WordPress and an active Divi 5 theme, the catalog is built from the theme's
 * own module.json files (CatalogBuilder) and cached in the transient
 * `wpdm_catalog_<divi_version>`. Otherwise (no WordPress, no Divi 5, build failure)
 * it is the bundled knowledge/module-catalog.json. WordPress functions are only
 * called behind function_exists() guards, so this also works in pure PHP.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Catalog loader.
 */
final class Catalog {

	const TRANSIENT_PREFIX = 'wpdm_catalog_';

	/**
	 * Cache lifetime: the key already changes with the Divi version.
	 */
	const TTL = 2592000; // 30 days.

	/**
	 * Loaded catalogs keyed by theme dir ('' = bundled).
	 *
	 * @var array<string, array>
	 */
	private static $memo = array();

	/**
	 * The catalog for this site: from the installed Divi theme, else bundled.
	 *
	 * @return array{decorationGroups: string[], diviVersion: string, modules: array<string, array>}
	 */
	public static function load(): array {
		$dir = self::theme_dir();
		if ( isset( self::$memo[ $dir ] ) ) {
			return self::$memo[ $dir ];
		}
		$catalog = '' === $dir ? null : self::from_theme( $dir );
		if ( null === $catalog ) {
			$catalog = self::bundled();
		}
		self::$memo[ $dir ] = $catalog;
		return $catalog;
	}

	/**
	 * The bundled catalog (knowledge/module-catalog.json).
	 *
	 * @return array
	 * @throws \RuntimeException When the file is missing or invalid.
	 */
	public static function bundled(): array {
		$file = dirname( __DIR__, 2 ) . '/knowledge/module-catalog.json';
		$json = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = false === $json ? null : json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['modules'] ) || ! is_array( $data['modules'] ) ) {
			throw new \RuntimeException( 'Bundled module catalog missing or invalid: ' . $file );
		}
		return $data;
	}

	/**
	 * The active (parent) theme folder when it ships Divi 5 module definitions, else ''.
	 * Filter `wpdm_catalog_theme_dir` overrides the folder ('' forces the bundled catalog).
	 *
	 * @return string
	 */
	public static function theme_dir(): string {
		$dir = function_exists( 'get_template_directory' ) ? (string) get_template_directory() : '';
		if ( function_exists( 'apply_filters' ) ) {
			$dir = (string) apply_filters( 'wpdm_catalog_theme_dir', $dir );
		}
		return '' !== $dir && is_dir( $dir . '/' . CatalogBuilder::PACKAGES ) ? $dir : '';
	}

	/**
	 * Forget loaded catalogs (tests, theme switches).
	 */
	public static function reset(): void {
		self::$memo = array();
	}

	/**
	 * Catalog from the theme, via the transient cache; null when it cannot be built.
	 *
	 * @param string $dir Theme folder.
	 * @return array|null
	 */
	private static function from_theme( string $dir ) {
		$key = self::TRANSIENT_PREFIX . CatalogBuilder::theme_version( $dir );
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) && ! empty( $cached['modules'] ) ) {
				return $cached;
			}
		}
		try {
			$catalog = CatalogBuilder::from_theme_dir( $dir );
		} catch ( \RuntimeException $e ) {
			return null;
		}
		if ( ! $catalog['modules'] ) {
			return null;
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $catalog, self::TTL );
		}
		return $catalog;
	}
}
