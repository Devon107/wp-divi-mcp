=== WP Divi MCP ===
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Built-in MCP server so Claude can manage a WordPress site and build Divi 5 pages.

== Description ==

WP Divi MCP adds a Model Context Protocol (MCP) endpoint to your site. Create a connection URL under Settings > Divi MCP and add it to Claude as a custom connector. Claude can then read and manage content, media, menus and settings, and build Divi 5 pages (Divi theme required for the Divi tools).

Source code, releases and issues: https://github.com/Devon107/wp-divi-mcp (by the WP Divi MCP contributors).

Connection URLs contain a secret token. Treat them like passwords, revoke them when you are done, and serve the site over HTTPS.

== Installation ==

1. Upload the zip under Plugins > Add New Plugin > Upload Plugin and activate it.
2. Deactivate the "Divi 5 MCP Companion" plugin if it is installed.
3. Go to Settings > Divi MCP and create a connection.
4. Add the URL to Claude as a custom connector, or use "Download for Claude Desktop" to install a Claude Desktop extension for that connection. The extension runs on your computer, so it also works on hosts that block Anthropic's cloud connector (such as WP Engine).

== Changelog ==

= 0.2.0 =
* New: "Download for Claude Desktop" in the new-connection box. It installs a Claude Desktop extension (.mcpb) for that connection, which runs on your computer and works on hosts that block Anthropic's cloud connector, such as WP Engine.

= 0.1.0 =
* First release.
