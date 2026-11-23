# Claude Desktop extension (.mcpb) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a connection is created, Settings → Divi MCP offers a per-connection `.mcpb` file. The file installs a local stdio-to-HTTP proxy in Claude Desktop, so the plugin works on WP Engine.

**Architecture:** A dependency-free Node proxy (`assets/mcpb/server.js`) ships in the plugin. `WPDM\Admin\Mcpb` zips it with a generated `manifest.json` that embeds the connection URL. The shown-once box links to the zip as a `data:` URI, so the token is never stored or requested again.

**Tech Stack:** PHP 7.4+ (ZipArchive), WordPress admin, Node 18+ (global `fetch`, `node:test`), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-23-claude-desktop-mcpb-design.md`

## Global Constraints

- No npm dependencies in the bundle. The proxy uses only Node built-ins and global `fetch`.
- User-Agent sent by the proxy: `wp-divi-mcp-desktop/<version>`, where version is the `WPDM_VERSION` passed in env `WPDM_VERSION`. It must never start with `python`.
- Proxy logs go to stderr only and never contain the URL or the key.
- Request headers: `Content-Type: application/json`, `Accept: application/json, text/event-stream`, plus `Mcp-Session-Id` once known.
- Manifest: `manifest_version` "0.3". `server.type` "node". `entry_point` "server/index.js". `mcp_config` = `{command: "node", args: ["${__dirname}/server/index.js"], env: {WPDM_URL: <url>, WPDM_VERSION: <version>}}`. `compatibility.platforms` = `["darwin","win32","linux"]`.
- Extension `name`: `wp-divi-mcp-<host-slug>-<label-slug>`, at most 64 characters, made of `[a-z0-9-]`. `display_name`: `Divi MCP – <label> (<host>)`.
- The token never appears in a redirect, a log, or a new transient or option.
- PHP code must stay PHP 7.4 compatible (CI lints with 7.4).

## Review Focus

- Labels with accents, emoji or only symbols → the name still matches `^[a-z0-9-]{1,64}$`. Pinned in Task 2 (`test_name_is_safe_for_odd_labels`).
- Two connections on one site with the same label → each download still works. Uniqueness comes from the connection id suffix. Pinned in Task 2 (`test_names_differ_per_connection`).
- The server answers a request with a non-JSON body (an HTML error page from a proxy or WAF) → Claude receives a JSON-RPC error with the HTTP status and does not hang. Pinned in Task 1 (`html error page becomes json-rpc error`).
- A stdin line that is not JSON → stderr warning, no crash, and the next lines are still processed. Pinned in Task 1 (`bad stdin line is skipped`).
- ZipArchive is missing on the host → the page still renders, with a notice and no link. Pinned in Task 3 (`test_desktop_row_without_ziparchive`).

---

### Task 1: Node stdio-to-HTTP proxy

**Files:**
- Create: `assets/mcpb/server.js`
- Create: `tests/node/proxy.test.mjs`
- Modify: `.github/workflows/ci.yml` (lint job: add `actions/setup-node@v4` with node 20, then step `node --test tests/node/`)
- Modify: `package.json` (script `"test:node": "node --test tests/node/"`)

**Interfaces:**
- Produces: `assets/mcpb/server.js`, runnable as `node server.js` with env `WPDM_URL` (required) and `WPDM_VERSION` (optional, default `"dev"`). It also exports `{ start({ url, version, input, output, log }) }` when required as a module (`require.main !== module`), returning a Promise that resolves once input has ended and the shutdown DELETE was attempted. Tests use that.

- [ ] **Step 1: Write the failing tests** in `tests/node/proxy.test.mjs`. Use `node:test`, `node:assert/strict`, a `node:http` fake server on port 0, and `PassThrough` streams for input and output. Load the module with `createRequire`. Tests:
  - `initialize stores session id`: the fake replies 200 `{"jsonrpc":"2.0","id":1,"result":{}}` with header `Mcp-Session-Id: abc`. A second request (`tools/list`, id 2) arrives with header `mcp-session-id: abc`. Output has exactly 2 lines, which are the parsed responses.
  - `sends headers and user agent`: the fake records headers. Assert `user-agent === "wp-divi-mcp-desktop/9.9.9"` (version `9.9.9` passed), `content-type === "application/json"`, and `accept` contains `application/json`.
  - `notification gets 202 and no output`: input is `{"jsonrpc":"2.0","method":"notifications/initialized"}`, and the fake replies 202 with an empty body. Output is empty.
  - `batch response is written line per message`: the fake replies 200 with a JSON array of 2 responses. Output has 2 lines.
  - `http 401 becomes json-rpc error`: the fake replies 401 `{"jsonrpc":"2.0","id":null,"error":{"code":-32001,"message":"Unauthorized"}}`. Request id 7 gets an output line with `id === 7`, `error.code === -32001` and `error.message` containing `Unauthorized`.
  - `html error page becomes json-rpc error`: the fake replies 403 with `text/html` `<html>403 Forbidden</html>`. Request id 8 gets `error.code === -32000` and a message containing `HTTP 403`.
  - `network error becomes json-rpc error`: the URL points to a closed port. Request id 9 gets `error.code === -32000`.
  - `bad stdin line is skipped`: input `not json\n` then a valid request (id 3). Output has 1 line with id 3, and the log captured something.
  - `delete on end`: after initialize (session `abc`), end the input. `start()` resolves, and the fake saw `DELETE` with `mcp-session-id: abc`.
  - `url never logged`: run the `network error` scenario with `url` containing `key=SECRET`. The captured log never contains `SECRET`.

- [ ] **Step 2: Run to verify it fails.** Run `node --test tests/node/`. Expected: FAIL (cannot find module `assets/mcpb/server.js`).

- [ ] **Step 3: Implement `assets/mcpb/server.js`**, with `start({ url, version, input, output, log })`. Read lines with `readline.createInterface({ input })` and process messages sequentially, through a promise chain, so the session id from initialize is set before the next call. For each message, collect the ids of the requests in it: a request is any object with `id` and `method`. On a 2xx with a non-empty JSON body, write each response as a single line. On a non-2xx, write the server's JSON-RPC error when the body parses as one (keeping its code and message, with the id set to the request id), otherwise `{code:-32000, message:"HTTP <status> from WordPress"}`. On a fetch rejection, write `{code:-32000, message:"Cannot reach WordPress: <err.name>"}`. On input end, await the chain, send DELETE if a session exists (errors ignored), then resolve. When run as main: read env, exit 1 with a stderr message if `WPDM_URL` is missing, and call `start` with `process.stdin`, `process.stdout` and `console.error`.

- [ ] **Step 4: Run to verify it passes.** Run `node --test tests/node/`. Expected: all 10 tests pass.

- [ ] **Step 5: Wire CI and package.json** as listed in Files. Check with `python3 -c "import yaml;yaml.safe_load(open('.github/workflows/ci.yml'))"`.

- [ ] **Step 6: Commit.** `git add assets/mcpb/server.js tests/node package.json .github/workflows/ci.yml && git commit -m "feat: dependency-free Node stdio-to-HTTP proxy for Claude Desktop"`

### Task 2: .mcpb builder

**Files:**
- Create: `includes/Admin/Mcpb.php`
- Test: `tests/WP/McpbTest.php`

**Interfaces:**
- Consumes: `assets/mcpb/server.js` (Task 1), `WPDM_VERSION`, `WPDM_FILE`.
- Produces:
  - `WPDM\Admin\Mcpb::available(): bool`. True when `class_exists( 'ZipArchive' )`. Filterable via `wpdm_mcpb_available`, which tests use to force false.
  - `WPDM\Admin\Mcpb::name( string $label, string $connection_id ): string`. Returns the extension name.
  - `WPDM\Admin\Mcpb::filename( string $label, string $connection_id ): string`. Returns `<name>.mcpb`.
  - `WPDM\Admin\Mcpb::build( string $url, string $label, string $connection_id ): string`. Returns the zip bytes and throws `\RuntimeException` when it cannot.

- [ ] **Step 1: Write failing tests** in `tests/WP/McpbTest.php` (a `WP_UnitTestCase`; open the bytes by writing them to `wp_tempnam()` and using `ZipArchive`):
  - `test_zip_contains_manifest_and_server`: the entries are exactly `manifest.json` and `server/index.js`, and `server/index.js` equals `file_get_contents( WPDM_DIR . 'assets/mcpb/server.js' )` (use `plugin_dir_path( WPDM_FILE )` if `WPDM_DIR` does not exist).
  - `test_manifest_fields`: build with url `https://example.org/wp-json/wp-divi-mcp/v1/mcp?key=abc`, label `Immedex Dev` and id `a1b2c3`. Assert `manifest_version === '0.3'`, `version === WPDM_VERSION`, `server.type === 'node'`, `server.entry_point === 'server/index.js'`, `server.mcp_config.command === 'node'`, `server.mcp_config.args === ['${__dirname}/server/index.js']`, `server.mcp_config.env.WPDM_URL` equals the url, `server.mcp_config.env.WPDM_VERSION === WPDM_VERSION`, `compatibility.platforms === ['darwin','win32','linux']`, and that `display_name` contains `Immedex Dev` and the site host.
  - `test_name_is_safe_for_odd_labels`: for labels `Ñandú – café ☕`, `!!!`, an empty string and `str_repeat('x', 200)`, `name()` matches `/^[a-z0-9-]{1,64}$/` and starts with `wp-divi-mcp-`.
  - `test_names_differ_per_connection`: the same label with ids `aaa111` and `bbb222` gives different names. The same label and id gives the same name.
  - `test_filename_ends_with_mcpb`: `filename()` equals `name() . '.mcpb'`.

- [ ] **Step 2: Run to verify they fail.** Run `bin/test --filter McpbTest`. Expected: FAIL (class `WPDM\Admin\Mcpb` not found).

- [ ] **Step 3: Implement `includes/Admin/Mcpb.php`** with the interfaces above. Build `name` as `wp-divi-mcp-` + `sanitize_title( host )`, then `sanitize_title( label )` when non-empty, then the connection id, joined with `-`. Replace anything outside `[a-z0-9-]` with `-` (accented letters may survive `sanitize_title`), collapse repeated hyphens, and trim the label part so the total is at most 64 characters while the id suffix is always kept. The host comes from `wp_parse_url( home_url(), PHP_URL_HOST )`. Write the zip to a `wp_tempnam()` file, read the bytes back, and delete the file. Use `wp_json_encode` with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`. Write the manifest `description` as: "Connects Claude Desktop to <host> through the WP Divi MCP plugin." Set `author` to `{"name": "WP Divi MCP"}`. Follow the existing class layout: `defined( 'ABSPATH' ) || exit;`, `final class`, docblocks.

- [ ] **Step 4: Run to verify they pass.** Run `bin/test --filter McpbTest`. Expected: OK (5 tests).

- [ ] **Step 5: Commit.** `git add includes/Admin/Mcpb.php tests/WP/McpbTest.php && git commit -m "feat: build a per-connection .mcpb bundle"`

### Task 3: Download in the shown-once box, docs

**Files:**
- Modify: `includes/Admin/SettingsPage.php`. In `handle_create()`, add `'id' => $created['id']` to the transient array. Add a "Claude Desktop" block in `render_new_connection()`, after the Claude Code row.
- Modify: `docs/connect.md` (new section "Claude Desktop (recomendado en WP Engine)")
- Modify: `readme.txt` (one line in the description about Claude Desktop)
- Test: `tests/WP/SettingsPageTest.php`

**Interfaces:**
- Consumes: `Mcpb::available()`, `Mcpb::build()` and `Mcpb::filename()` (Task 2). The transient now carries `{url, token, label, id}`.

- [ ] **Step 1: Write failing tests** in `tests/WP/SettingsPageTest.php`, reusing its create-and-render helpers:
  - `test_desktop_download_link_in_new_connection_box`: create a connection labelled `Desk`. The first render contains `download="` + `Mcpb::filename( 'Desk', <id> )` and `href="data:application/zip;base64,`. Base64-decoding the href payload gives a zip whose `manifest.json` env `WPDM_URL` equals the shown URL. The second render contains no `data:application/zip`. Get the id from `Tokens::all()`.
  - `test_desktop_row_without_ziparchive`: with `add_filter( 'wpdm_mcpb_available', '__return_false' )`, the render has no `data:application/zip` and contains the notice text `ZipArchive`.
  - `test_create_redirect_still_has_no_token`: the existing assertion still holds. Run the existing `test_create_connection_stores_it_and_redirects_without_token` unchanged.

- [ ] **Step 2: Run to verify they fail.** Run `bin/test --filter SettingsPageTest`. Expected: the 2 new tests FAIL.

- [ ] **Step 3: Implement the block.** Heading `Claude Desktop`, then a short paragraph: "Download this file and open it to install the connection in Claude Desktop. It works on any host, including WP Engine. The file contains the key: treat it like the URL, and revoke the connection if it leaks." Follow it with an `<a class="button button-primary">` that has `href` set to the data URI and `download` set to the filename, labelled "Download for Claude Desktop". Escape with `esc_attr` and translate with `__()` in the `wp-divi-mcp` domain. Build inside try/catch: on a `RuntimeException`, or when `available()` is false, show "Claude Desktop download needs the PHP ZipArchive extension on this server." instead.

- [ ] **Step 4: Run to verify they pass.** Run `bin/test --filter SettingsPageTest`. Expected: OK.

- [ ] **Step 5: Docs.** Write the `docs/connect.md` section in Spanish, in the same style as the rest of the guide. Steps: create the connection, click "Download for Claude Desktop", double-click the `.mcpb` (or Claude Desktop → Settings → Extensions → install from file), enable it, and start a new chat. Notes: one file per site, works on WP Engine, revoke if the file leaks, Linux support depends on the Claude Desktop build. Add a short line to `readme.txt`.

- [ ] **Step 6: Full suites, lint, zip.** Run `bin/test` (expect 0 failures), `WPDM_TEST_DIVI=1 bin/test --group divi` (expect OK), `node --test tests/node/`, `docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c 'find includes -name "*.php" -print0 | xargs -0 -n1 php -l' | grep -v "No syntax errors"` (expect no output), then `git stash -u` if dirty, `bin/build-zip` and `unzip -l build/wp-divi-mcp.zip | grep assets/mcpb/server.js` (expect one line).

- [ ] **Step 7: Commit.** `git add includes/Admin/SettingsPage.php tests/WP/SettingsPageTest.php docs/connect.md readme.txt && git commit -m "feat: Claude Desktop download in the new-connection box"`
