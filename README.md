# WP Divi MCP

WordPress plugin with a built-in MCP server. Install it, create a connection URL in *Settings → Divi MCP*, and add that URL to Claude as a custom connector. Claude can then manage the site and build Divi 5 pages.

Status: design phase. See `docs/superpowers/specs/`.

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
