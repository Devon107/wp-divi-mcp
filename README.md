# WP Divi MCP

WordPress plugin with a built-in MCP server. Install it, create a connection URL in *Settings → Divi MCP*, and add that URL to Claude as a custom connector. Claude can then manage the site and build Divi 5 pages.

- Requires WordPress 6.2+ and PHP 7.4+. The Divi tools need the Divi 5 theme; everything else works without it.
- The endpoint is a Streamable HTTP MCP server at `/wp-json/wp-divi-mcp/v1/mcp`. Each connection is a URL with a secret token, tied to a WordPress user and a mode (read only or full access), with an optional expiry, instant revocation, a global disable switch and a 30-day activity log.
- HTTPS is required (set `WP_DIVI_MCP_ALLOW_HTTP` only for local development).

## Install

1. Download `wp-divi-mcp.zip` from the latest GitHub release.
2. In WordPress: *Plugins → Add New Plugin → Upload Plugin*, upload the zip and activate it. Deactivate the old "Divi 5 MCP Companion" plugin if it is installed.
3. Go to *Settings → Divi MCP*, create a connection and copy the URL (it is shown once).
4. In claude.ai: *Settings → Connectors → Add custom connector* and paste the URL. For Claude Code use the `claude mcp add --transport http ...` command shown with the URL.

Step-by-step guide for site owners (Spanish): [docs/connect.md](docs/connect.md).

## Uninstall

Deleting the plugin from *Plugins* runs `uninstall.php`, on every site of a multisite network. It removes the options `wpdm_connections` (all connections), `wpdm_disabled` and `wpdm_db_version`, every `wpdm_*` transient, the `{prefix}wpdm_log` activity table and the `wpdm_prune_log` cron event. The backups (`wpdm_backup` posts) are kept, so a reinstall can still restore them; to delete them too, add `define( 'WPDM_UNINSTALL_DELETE_BACKUPS', true );` to `wp-config.php` before deleting the plugin. Deactivating the plugin removes nothing except the cron event.

## Features

41 tools, 4 resources (plus one resource template) and 2 prompts.

WordPress tools (24):

- Content (9): `list_post_types`, `list_taxonomies`, `list_posts`, `get_post`, `search`, `create_post`, `update_post`, `trash_post`, `restore_post`
- Terms (3): `list_terms`, `create_term`, `update_term`
- Media (3): `list_media`, `upload_media`, `update_media`
- Menus (3): `list_menus`, `get_menu`, `update_menu_items`
- Comments (2): `list_comments`, `moderate_comment`
- Site (4): `site_info`, `list_users`, `get_options`, `update_options`

Divi 5 tools (17; 14 need the Divi theme, `divi_build_layout`, `divi_validate_layout` and `divi_find_icon` always work):

- Layouts (3): `divi_build_layout`, `divi_validate_layout`, `divi_find_icon`
- Pages (5): `divi_create_page`, `divi_apply_layout`, `divi_export_page`, `divi_inspect_page`, `divi_set_page_css`
- Backups (2): `divi_list_backups`, `divi_restore_backup`
- Globals and status (3): `divi_status`, `divi_get_globals`, `divi_merge_globals`
- Theme Builder (4): `divi_list_theme_builder_templates`, `divi_create_theme_builder_template`, `divi_delete_theme_builder_template`, `divi_set_theme_builder_layout`

Resources: `divi://guide`, `divi://attributes`, `divi://modules/index`, `divi://schema/layout` and the template `divi://modules/{name}`. Prompts: `build_divi_page`, `match_existing_site`.

Safety: posts and pages only go to the trash (restorable), Divi changes are backed up first, new designs go to a draft, and changing a published page or the default website template needs an explicit confirmation flag. No plugin, theme or user management. `update_post` backs up the current content whenever it changes it, and a published page's content needs `confirm_published`. Two things are not recoverable that way: `update_menu_items` permanently deletes the menu items it removes (its result lists their definitions so they can be recreated), and `divi_delete_theme_builder_template` moves a Theme Builder template to Divi's trash (its layouts' content stays in the backups).

## Development

Requirements: Docker, Node 20+, Composer (or the `composer:2` Docker image). The plugin has no runtime Composer dependencies; `vendor/` is dev-only.

```bash
composer install            # dev dependencies (PHPUnit, Yoast polyfills)
npm install                 # @wordpress/env
npx wp-env start            # WordPress 6.8 / PHP 8.2 test environment
bin/test                    # whole suite, runs inside wp-env
bin/test --testsuite unit   # one suite (unit | wp)
```

Tests that need the Divi theme are tagged `@group divi` and are skipped by default (`bin/test` passes `--exclude-group divi`). To run them, map the Divi theme with a local, gitignored `.wp-env.override.json` (`{"themes": ["../Divi"]}`), restart wp-env, and run `WPDM_TEST_DIVI=1 bin/test`; the bootstrap then switches the theme to Divi. Divi is never committed to this repository.

The wp-env test environment runs on PHP 8.2 because the `wordpress:php7.4` image no longer builds (Debian bullseye is archived). The PHP 7.4 compatibility floor is enforced in CI by running `php -l` on every file under PHP 7.4.

## Release

`bin/build-zip` builds `build/wp-divi-mcp.zip` from `git archive HEAD` minus the paths in `.distignore`, and refuses to run when files that ship have uncommitted changes. Pushing a tag `vX.Y.Z` runs `.github/workflows/release.yml`: PHP lint, a check that the tag matches the plugin header `Version`, `WPDM_VERSION` and `readme.txt` `Stable tag`, then the zip is attached to a new GitHub release.
