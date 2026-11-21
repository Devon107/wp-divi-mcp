<?php
/**
 * Plugin Name:       WP Divi MCP
 * Plugin URI:        https://github.com/wp-divi-mcp/wp-divi-mcp
 * Description:       Built-in MCP server so Claude can manage a WordPress site and build Divi 5 pages.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WP Divi MCP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-divi-mcp
 *
 * @package WPDM
 */

defined( 'ABSPATH' ) || exit;

define( 'WPDM_VERSION', '0.1.0' );
define( 'WPDM_FILE', __FILE__ );
define( 'WPDM_DIR', plugin_dir_path( __FILE__ ) );

/**
 * PSR-4 autoloader: WPDM\Foo\Bar => includes/Foo/Bar.php (no Composer at runtime).
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPDM\\';
		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = WPDM_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

\WPDM\Plugin::instance()->boot();
