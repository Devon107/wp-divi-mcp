<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite provided by wp-env and the plugin.
 *
 * - WPDM_TEST_DIVI=1 switches the active theme to Divi (map it with .wp-env.override.json).
 * - Otherwise tests tagged @group divi are skipped by the exclusion below.
 *
 * @package WPDM
 */

$wpdm_plugin_dir = dirname( __DIR__ );

// Yoast PHPUnit Polyfills (required by the WP test suite).
require_once $wpdm_plugin_dir . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test suite not found in {$_tests_dir}. Run tests via bin/test (wp-env).\n" );
	exit( 1 );
}

// Point the WP test suite at the Yoast polyfills if it asks for the path.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wpdm_plugin_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

if ( '1' === getenv( 'WPDM_TEST_YOAST' ) ) {
	// Yoast SEO is installed in the tests env (.wp-env.json) but only loaded here.
	tests_add_filter(
		'muplugins_loaded',
		static function () {
			$yoast = WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php';
			if ( ! file_exists( $yoast ) ) {
				fwrite( STDERR, "WPDM_TEST_YOAST=1 but Yoast SEO is not installed in this wp-env (restart wp-env).\n" );
				exit( 1 );
			}
			require $yoast;
		}
	);
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wpdm_plugin_dir ) {
		require $wpdm_plugin_dir . '/wp-divi-mcp.php';
	}
);

if ( '1' === getenv( 'WPDM_TEST_DIVI' ) ) {
	// Switch to the Divi theme (mapped into the env via .wp-env.override.json).
	tests_add_filter(
		'setup_theme',
		static function () {
			if ( ! wp_get_theme( 'Divi' )->exists() ) {
				fwrite( STDERR, "WPDM_TEST_DIVI=1 but the Divi theme is not installed in this wp-env.\n" );
				exit( 1 );
			}
			switch_theme( 'Divi' );
		}
	);
}

require $_tests_dir . '/includes/bootstrap.php';
