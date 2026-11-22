<?php
/**
 * Regenerates the bundled Divi knowledge from an installed Divi 5 theme folder:
 *
 *   knowledge/module-catalog.json   catalog used by the validator (CatalogBuilder)
 *   knowledge/modules/<slug>.md     attribute map of each module (CatalogDocs)
 *   knowledge/modules/INDEX.md      one line per module
 *   knowledge/icons.json            Divi + Font Awesome icon list (divi_find_icon)
 *
 * Port of the skill's generate_catalog.py; the output is byte-identical to it.
 * Module files of modules that no longer exist are removed.
 *
 * Usage (no PHP on the host):
 *   docker run --rm -v "$PWD":/app -v "/path/to/themes/Divi":/divi:ro -w /app php:8.2-cli php tools/generate-catalog.php /divi
 *
 * @package WPDM
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

foreach ( array( 'CatalogBuilder', 'CatalogDocs' ) as $wpdm_gc_class ) {
	if ( ! class_exists( 'WPDM\\Divi\\' . $wpdm_gc_class ) ) {
		require_once dirname( __DIR__ ) . '/includes/Divi/' . $wpdm_gc_class . '.php';
	}
}

use WPDM\Divi\CatalogDocs;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php tools/generate-catalog.php /path/to/wp-content/themes/Divi\n" );
	exit( 1 );
}

try {
	$wpdm_gc = CatalogDocs::render( $argv[1] );
} catch ( RuntimeException $e ) {
	fwrite( STDERR, $e->getMessage() . "\n" );
	exit( 1 );
}

$wpdm_gc_out = dirname( __DIR__ ) . '/knowledge';
$wpdm_gc_mod = $wpdm_gc_out . '/modules';
if ( ! is_dir( $wpdm_gc_mod ) && ! mkdir( $wpdm_gc_mod, 0755, true ) ) {
	fwrite( STDERR, "Cannot create {$wpdm_gc_mod}\n" );
	exit( 1 );
}

foreach ( glob( $wpdm_gc_mod . '/*.md' ) as $wpdm_gc_file ) {
	$wpdm_gc_slug = basename( $wpdm_gc_file, '.md' );
	if ( 'INDEX' !== $wpdm_gc_slug && ! isset( $wpdm_gc['modules'][ $wpdm_gc_slug ] ) ) {
		unlink( $wpdm_gc_file );
	}
}
foreach ( $wpdm_gc['modules'] as $wpdm_gc_slug => $wpdm_gc_md ) {
	file_put_contents( $wpdm_gc_mod . '/' . $wpdm_gc_slug . '.md', $wpdm_gc_md );
}
file_put_contents( $wpdm_gc_mod . '/INDEX.md', $wpdm_gc['index'] );
file_put_contents( $wpdm_gc_out . '/module-catalog.json', $wpdm_gc['catalog_json'] );
if ( null !== $wpdm_gc['icons'] ) {
	file_put_contents( $wpdm_gc_out . '/icons.json', $wpdm_gc['icons'] );
	echo 'Wrote ' . $wpdm_gc['icon_count'] . " icons\n";
}

echo 'Wrote ' . count( $wpdm_gc['modules'] ) . ' modules from Divi ' . $wpdm_gc['catalog']['diviVersion'] . ' to ' . $wpdm_gc_mod . "\n";
