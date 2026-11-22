<?php
/**
 * Builds the Divi 5 module catalog from a Divi theme folder.
 *
 * Port of the catalog part of the skill's generate_catalog.py: it reads every
 * `module.json` under includes/builder-5/visual-builder/packages/ and returns the
 * same structure as references/module-catalog.json (the bundled
 * knowledge/module-catalog.json). The Markdown docs and icons.json that
 * generate_catalog.py also writes are rendered by CatalogDocs.
 *
 * Divi 5.19.1 keeps the layout generate_catalog.py expects (checked against the
 * installed theme): packages/module-library/src/components/<slug>/module.json,
 * woocommerce modules one level deeper, and packages/shortcode-module/src/module/.
 * The catalog only needs module.json; conversion-outline.json and
 * module-default-render-attributes.json feed the Markdown docs only.
 *
 * Pure PHP, no WordPress dependency (also used by the Task 15 CLI tool).
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Reads module definitions from a Divi theme.
 */
final class CatalogBuilder {

	const PACKAGES = 'includes/builder-5/visual-builder/packages';

	/**
	 * Build the catalog from a theme folder.
	 *
	 * @param string $dir Theme root (contains style.css and includes/builder-5).
	 * @return array{decorationGroups: string[], diviVersion: string, modules: array<string, array>}
	 * @throws \RuntimeException When no module.json is found.
	 */
	public static function from_theme_dir( string $dir ): array {
		$files = self::module_files( rtrim( $dir, '/\\' ) . '/' . self::PACKAGES );
		if ( ! $files ) {
			throw new \RuntimeException( 'No module.json found under ' . rtrim( $dir, '/\\' ) . '/' . self::PACKAGES );
		}

		$modules = array();
		$groups  = array();
		foreach ( $files as $path ) {
			$meta = self::load( $path );
			if ( ! $meta instanceof \stdClass ) {
				continue;
			}
			$attrs = isset( $meta->attributes ) && $meta->attributes instanceof \stdClass ? get_object_vars( $meta->attributes ) : array();

			$elements = array();
			foreach ( $attrs as $key => $spec ) {
				if ( ! $spec instanceof \stdClass ) {
					continue;
				}
				$elements[] = (string) $key;
				$settings   = isset( $spec->settings ) && $spec->settings instanceof \stdClass ? $spec->settings : null;
				if ( $settings && isset( $settings->decoration ) && $settings->decoration instanceof \stdClass ) {
					foreach ( array_keys( get_object_vars( $settings->decoration ) ) as $group ) {
						$groups[ (string) $group ] = true;
					}
				}
			}

			if ( ! property_exists( $meta, 'name' ) || ! is_string( $meta->name ) ) {
				continue; // Python skips it too, but still counts its decoration groups.
			}
			usort( $elements, 'strcmp' );
			$children = property_exists( $meta, 'childrenName' ) && $meta->childrenName ? $meta->childrenName : array();

			// Keys in sorted order, as json.dump(..., sort_keys=True) writes them.
			$modules[ $meta->name ] = array(
				'category'    => property_exists( $meta, 'category' ) && $meta->category ? $meta->category : 'module',
				'children'    => is_array( $children ) ? array_values( $children ) : $children,
				'd4Shortcode' => property_exists( $meta, 'd4Shortcode' ) ? $meta->d4Shortcode : null,
				'elements'    => $elements,
				'title'       => property_exists( $meta, 'title' ) ? $meta->title : null,
			);
		}

		uksort( $modules, 'strcmp' );
		$groups = array_keys( $groups );
		usort( $groups, 'strcmp' );

		return array(
			'decorationGroups' => array_map( 'strval', $groups ),
			'diviVersion'      => self::theme_version( $dir ),
			'modules'          => $modules,
		);
	}

	/**
	 * Theme version from style.css ("unknown" when absent), like generate_catalog.py.
	 *
	 * @param string $dir Theme root.
	 * @return string
	 */
	public static function theme_version( string $dir ): string {
		$style = rtrim( $dir, '/\\' ) . '/style.css';
		if ( ! is_readable( $style ) ) {
			return 'unknown';
		}
		$fh = fopen( $style, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			return 'unknown';
		}
		$version = 'unknown';
		while ( false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line = trim( $line );
			if ( 0 === strpos( $line, 'Version:' ) ) {
				$version = trim( substr( $line, strlen( 'Version:' ) ) );
				break;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $version;
	}

	/**
	 * Every module.json below $root, sorted by path (Python's sorted(glob(..., recursive=True))).
	 * Hidden folders are skipped, as glob skips them. Also used by CatalogDocs.
	 *
	 * @param string $root Packages folder.
	 * @return string[]
	 */
	public static function module_files( string $root ): array {
		if ( ! is_dir( $root ) ) {
			return array();
		}
		$dirs  = new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS );
		$dirs  = new \RecursiveCallbackFilterIterator(
			$dirs,
			static function ( \SplFileInfo $file ) {
				return '.' !== substr( $file->getFilename(), 0, 1 );
			}
		);
		$files = array();
		foreach ( new \RecursiveIteratorIterator( $dirs ) as $file ) {
			if ( 'module.json' === $file->getFilename() && $file->isFile() ) {
				$files[] = $file->getPathname();
			}
		}
		usort( $files, 'strcmp' );
		return $files;
	}

	/**
	 * Decode a JSON file (objects as stdClass so {} and [] stay distinct); null on error
	 * or when the file is missing. Also used by CatalogDocs.
	 *
	 * @param string $path File.
	 * @return mixed
	 */
	public static function load( string $path ) {
		$json = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $json ) {
			return null;
		}
		$data = json_decode( $json );
		if ( $data instanceof \stdClass ) {
			unset( $data->_comment );
		}
		return $data;
	}
}
