=== WP Divi MCP ===
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Built-in MCP server so Claude can manage a WordPress site and build Divi 5 pages.

== Description ==

WP Divi MCP adds a Model Context Protocol (MCP) endpoint to your site. Create a connection URL under Settings > Divi MCP and add it to Claude as a custom connector. Claude can then read and manage content, media, menus and settings, and build Divi 5 pages (Divi theme required for the Divi tools).

Connection URLs contain a secret token. Treat them like passwords, revoke them when you are done, and serve the site over HTTPS.

== Installation ==

1. Upload the zip under Plugins > Add New Plugin > Upload Plugin and activate it.
2. Deactivate the "Divi 5 MCP Companion" plugin if it is installed.
3. Go to Settings > Divi MCP and create a connection.
4. Add the URL to Claude as a custom connector.

== Changelog ==

= 0.1.0 =
* First release.
