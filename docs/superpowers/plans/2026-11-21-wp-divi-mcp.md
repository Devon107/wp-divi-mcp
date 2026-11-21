# WP Divi MCP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A self-contained WordPress plugin that serves an MCP endpoint, authenticated by per-connection token URLs, exposing WordPress management tools and Divi 5 page-building tools ported from the `divi5-layouts` Python skill.

**Architecture:** One plugin, namespace `WPDM\`. A JSON-RPC/MCP core (`Mcp/`) dispatches to tool classes registered in a `Registry`. A WP REST route is the HTTP transport; a token layer maps the request to a WP user. Divi work is split into a pure-PHP builder/validator (no WordPress needed, unit-testable) and a WordPress-side layer that calls Divi's own `divi/v1` routes in-process with `rest_do_request()`.

**Tech Stack:** PHP 7.4+, WordPress 6.2+, Divi 5.19. Dev only: Composer (PHPUnit 9, yoast/phpunit-polyfills), `@wordpress/env` (Docker) for the WP test suite, Node 24 (already installed). No PHP on the host: every PHP/Composer command runs in Docker.

**Spec:** `docs/superpowers/specs/2026-11-21-wp-divi-mcp-design.md`

## Global Constraints

- PHP 7.4+ syntax only (no enums, no `readonly`, no named args, no `match`). WordPress 6.2+.
- No runtime Composer dependencies. `vendor/` is dev-only and excluded from the release zip.
- Namespace `WPDM\`, autoload `includes/` PSR-4 (`WPDM\Mcp\Server` → `includes/Mcp/Server.php`). Options, transients, tables, CPT prefixed `wpdm_`.
- REST namespace `wp-divi-mcp/v1`, route `/mcp`. Connection URL: `https://site/wp-json/wp-divi-mcp/v1/mcp?key=<token>`.
- MCP protocol versions: negotiate `2025-06-18`, accept `2025-03-26`.
- Deletes are trash-only. No plugin/theme install or activation, no user create/delete, no file edits, no SQL/PHP execution.
- New Divi designs go to new draft pages. Applying to a published page requires `confirm_published=true`. Default Theme Builder template requires `confirm_default_template=true`. Theme Builder always `live=true` with explicit `template_id`.
- Globals: merge by id, never overwrite without `overwrite=true`; built-in colors (`gcid-primary-color`, `gcid-secondary-color`, `gcid-heading-color`, `gcid-body-color`, `gcid-link-color`) never touched.
- Composition defaults: `pill_link` padding 16px/30px; link-module icon color only set when it differs from text.
- Divi is commercial: never commit Divi theme files. `.wp-env.override.json` (gitignored) maps the owner's local Divi 5.19 folder.
- The Python skill at `~/.claude/skills/divi5-layouts/` is read-only for this project: never modify or delete it.
- HTTPS required for the endpoint unless `WP_DIVI_MCP_ALLOW_HTTP` is defined.

## Review Focus

1. **Notifications** (`notifications/initialized`, no `id`): must answer HTTP 202 with empty body, or Claude fails to connect. → Task 3 test `test_notification_returns_202_empty`.
2. **Spanish copy with accents, «», emoji, `&`, `<`, `--`**: markup must match WP `serialize_block_attributes()` exactly and round-trip through `parse_blocks()`. → Task 8 test `test_encode_matches_wordpress_serializer`.
3. **Token whose user was deleted or demoted**: deleted user → 401; demoted user → per-tool capability errors, never the old rights. → Task 4 tests `test_token_of_deleted_user_rejected`, `test_capabilities_checked_per_call`.
4. **Oversized payloads** (layouts with base64 images, several MB): clear tool error above 8 MB body / 5 MB per image, no PHP fatal. → Task 3 test `test_body_over_limit_returns_413`, Task 13 test `test_upload_media_base64_over_limit`.
5. **Page edited in the Visual Builder between dry run and apply**: backup must hold the content as it was immediately before the write, not at dry run. → Task 13 test `test_backup_taken_at_write_time`.

---

## Task 0 (blocking input from owner, not code)

Divi 5.19 theme folder and a fresh 5.19 Portability export. Needed by Tasks 12–14 (Divi-group tests) and Task 15 (catalog). Tasks 1–11 do not need them. Owner drops them in `/home/elmer/Downloads/Divi (1)/`; path goes into `.wp-env.override.json`.

---

### Task 1: Scaffold, dev environment, CI

**Files:**
- Create: `wp-divi-mcp.php`, `includes/Plugin.php`, `composer.json`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/Unit/PluginTest.php`, `.wp-env.json`, `package.json`, `bin/test`, `.github/workflows/ci.yml`
- Modify: `.gitignore` (add `.wp-env.override.json`)

**Interfaces:**
- Produces: constants `WPDM_VERSION = '0.1.0'`, `WPDM_FILE`, `WPDM_DIR`; `WPDM\Plugin::instance(): Plugin`, `Plugin::boot(): void` (hooks registered here by later tasks); PSR-4 autoloader via `spl_autoload_register` (no Composer at runtime).
- `bin/test [phpunit args]`: runs `npx wp-env run tests-cli --env-cwd=wp-content/plugins/wp-divi-mcp vendor/bin/phpunit "$@"`.
- Two PHPUnit suites: `unit` (`tests/Unit`, no WP needed but runs in same env) and `wp` (`tests/WP`). Group `divi` = tests needing the Divi theme; `tests/bootstrap.php` switches theme to Divi when `WPDM_TEST_DIVI=1`, else excludes group `divi`.

- [ ] **Step 1:** Plugin header: Name "WP Divi MCP", Requires at least 6.2, Requires PHP 7.4, License GPL-2.0-or-later, Version 0.1.0.
- [ ] **Step 2:** `composer.json` dev deps `phpunit/phpunit:^9.6`, `yoast/phpunit-polyfills:^2`; install with `docker run --rm -v "$PWD":/app -w /app composer:2 install`.
- [ ] **Step 3:** `.wp-env.json`: core `WordPress/WordPress#6.8`, phpVersion `7.4`, plugins `["."]`. `npx wp-env start`.
- [ ] **Step 4: Write failing test** `tests/Unit/PluginTest.php::test_plugin_boots` — asserts `defined('WPDM_VERSION')`, `WPDM_VERSION === '0.1.0'`, `Plugin::instance() === Plugin::instance()`.
- [ ] **Step 5:** `bin/test --testsuite unit` → FAIL; implement; → PASS.
- [ ] **Step 6:** CI workflow: `php -l` on all files with PHP 7.4 and 8.3 (matrix), then wp-env + `bin/test` on PHP 8.2 (no `divi` group).
- [ ] **Step 7: Commit** `chore: scaffold plugin, wp-env and CI`.

---

### Task 2: MCP protocol core

**Files:**
- Create: `includes/Mcp/Server.php`, `includes/Mcp/Registry.php`, `includes/Mcp/Tool.php`, `includes/Mcp/Context.php`, `includes/Mcp/ToolError.php`, `includes/Mcp/Resource.php`, `includes/Mcp/Prompt.php`
- Test: `tests/Unit/Mcp/ServerTest.php`

**Interfaces:**
- `abstract class Tool`: `abstract name(): string; abstract description(): string; abstract input_schema(): array; read_only(): bool` (default false); `destructive(): bool` (default false); `capability(): string` (default `'edit_posts'`); `abstract execute(array $args, Context $ctx): array`.
- `class ToolError extends \RuntimeException` with `__construct(string $message, string $code = 'tool_error', array $data = [])`.
- `class Context { int $user_id; array $connection; bool $read_only; ?string $session_id; }`
- `class Resource { __construct(string $uri, string $name, string $mime, callable $reader, string $description = '') }`; `class Prompt { __construct(string $name, string $description, array $arguments, callable $render) }` (`$render(array $args): array` of MCP messages).
- `Registry`: `add_tool(Tool)`, `add_resource(Resource)`, `add_resource_template(string $uri_template, string $name, string $mime, callable $reader)`, `add_prompt(Prompt)`, `tools(Context): Tool[]` (filters out non-read-only when `$ctx->read_only`, and tools whose capability the user lacks), `tool(string $name): ?Tool`.
- `Server`: `__construct(Registry $r, string $instructions)`, `handle(array $message, Context $ctx): ?array` (null for notifications), `handle_batch(array $messages, Context $ctx): array`.
- Methods handled: `initialize`, `ping`, `tools/list`, `tools/call`, `resources/list`, `resources/templates/list`, `resources/read`, `prompts/list`, `prompts/get`, `notifications/*` (ignored, return null). Unknown → `-32601`. Bad params → `-32602`. Parse errors handled by transport (`-32700`).
- `initialize` result: `protocolVersion` (client's if in `['2025-06-18','2025-03-26']`, else `'2025-06-18'`), `capabilities: {tools:{listChanged:false}, resources:{}, prompts:{}}`, `serverInfo: {name:'wp-divi-mcp', version: WPDM_VERSION}`, `instructions`.
- `tools/list` entries: `name, description, inputSchema, annotations{readOnlyHint, destructiveHint, openWorldHint:false}`.
- `tools/call`: before running, re-check `user_can($ctx->user_id, $tool->capability())` and read-only mode; on `ToolError`/exception return `{content:[{type:'text',text:message}], isError:true}`; on success `{content:[{type:'text',text: wp_json_encode($result)}], structuredContent:$result}`. Calls an action `wpdm_tool_called` (`$name, $args, $ok, $ms, $ctx`) for the log.

- [ ] **Step 1: Write failing tests**: `test_initialize_negotiates_version` (client `2025-03-26` → same; `1999-01-01` → `2025-06-18`), `test_unknown_method_is_32601`, `test_tools_call_tool_error_is_iserror_not_protocol_error`, `test_read_only_context_hides_write_tools`, `test_tools_call_rechecks_capability` (subscriber user → isError with "capability"), `test_notification_returns_null`, `test_batch_mixed_requests_and_notifications` (2 requests + 1 notification → 2 responses).
- [ ] **Step 2:** `bin/test --filter ServerTest` → FAIL.
- [ ] **Step 3:** Implement classes above.
- [ ] **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(mcp): JSON-RPC server, registry and tool base`.

---

### Task 3: HTTP transport (REST route)

**Files:**
- Create: `includes/Mcp/Transport.php`
- Modify: `includes/Plugin.php` (register route on `rest_api_init`)
- Test: `tests/WP/TransportTest.php`

**Interfaces:**
- Consumes: `Server::handle/handle_batch`, `Context`, `Auth\Tokens::authenticate(WP_REST_Request): array|WP_Error` (Task 4; until then a stub returning an admin context behind a test filter `wpdm_test_context`).
- Produces: `Transport::register(): void`, `Transport::dispatch(WP_REST_Request): WP_REST_Response`; filter `wpdm_server` (lets tasks add tools: `apply_filters('wpdm_registry', Registry)` called once per request).
- Behaviour: `POST` JSON (single or batch). Only notifications → 202, empty body. `initialize` → response header `Mcp-Session-Id` (32 hex), stored in transient `wpdm_sess_<id>` for 12 h (value: connection id); later requests with unknown session id → 404 with JSON-RPC error `-32001` "Session not found"; requests without the header are accepted (stateless clients). `GET` → 405. `DELETE` with session → deletes transient, 204. Body > 8 MB (`WPDM_MAX_BODY`, filterable) → 413. Non-HTTPS without `WP_DIVI_MCP_ALLOW_HTTP` → 403. Option `wpdm_disabled` true → 503. Invalid JSON → 400 with `-32700`.
- `permission_callback` returns true; auth is done inside `dispatch` so JSON-RPC errors are returned instead of WP's HTML-ish errors.

- [ ] **Step 1: Write failing tests**: `test_initialize_sets_session_header`, `test_notification_returns_202_empty`, `test_unknown_session_404`, `test_get_returns_405`, `test_delete_ends_session`, `test_body_over_limit_returns_413`, `test_invalid_json_32700`, `test_disabled_returns_503`, `test_http_rejected_without_constant`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(mcp): streamable HTTP transport over WP REST`.

---

### Task 4: Connection tokens and authentication

**Files:**
- Create: `includes/Auth/Tokens.php`
- Modify: `includes/Mcp/Transport.php` (replace stub)
- Test: `tests/WP/TokensTest.php`

**Interfaces:**
- Storage: option `wpdm_connections` (autoload no) = map `id => {id, label, user_id, mode:'read'|'full', hash, created, expires|null, last_used|null, last_ip|null}`. `id` = 12 hex.
- `Tokens::create(string $label, int $user_id, string $mode, ?int $expires): array` → `['id'=>..., 'token'=>..., 'url'=>...]`; token = `bin2hex(random_bytes(32))`; hash = `hash_hmac('sha256', $token, wp_salt('auth'))`. Token is never stored or returned again.
- `Tokens::verify(string $token): ?array` (constant-time `hash_equals`, checks expiry and that user exists and has `edit_posts`).
- `Tokens::revoke(string $id): bool`, `Tokens::all(): array` (without hash), `Tokens::url(string $token): string`.
- `Tokens::authenticate(WP_REST_Request $r): array|WP_Error`: token from `key` query param, else `Authorization: Bearer`, trimmed. Failure → `WP_Error('wpdm_unauthorized', ..., 401)` and increments transient `wpdm_fail_<md5(ip)>`; ≥ 10 within 60 s → 429 before verifying. Success → `wp_set_current_user($user_id)`, updates `last_used/last_ip` at most once per minute, returns `['connection'=>..., 'context'=>Context]`.

- [ ] **Step 1: Write failing tests**: `test_create_returns_token_once_and_stores_hash_only`, `test_verify_valid_and_invalid`, `test_expired_token_rejected`, `test_revoke`, `test_query_and_bearer_both_work`, `test_rate_limit_429_after_10_failures`, `test_token_of_deleted_user_rejected`, `test_capabilities_checked_per_call` (connection of editor; demote to subscriber; `tools/call create_post`-like dummy tool → isError), `test_read_mode_hides_write_tools`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(auth): per-connection token URLs`.

---

### Task 5: Activity log and admin screen

**Files:**
- Create: `includes/Log/ActivityLog.php`, `includes/Admin/SettingsPage.php`, `assets/admin.css`
- Modify: `includes/Plugin.php` (activation hook creates table; `admin_menu`; daily cron prune)
- Test: `tests/WP/ActivityLogTest.php`, `tests/WP/SettingsPageTest.php`

**Interfaces:**
- Table `{$wpdb->prefix}wpdm_log`: `id bigint PK, ts datetime, connection_id varchar(12), label varchar(190), tool varchar(100), target varchar(190), ok tinyint, ms int, args text`. Created with `dbDelta`; schema version option `wpdm_db_version = 1`.
- `ActivityLog::record(string $tool, array $args, bool $ok, int $ms, Context $ctx): void` (hooked to `wpdm_tool_called`); `args` JSON truncated to 2000 chars with keys `layout`, `blocks`, `content`, `data`, `base64` replaced by `"[omitted]"`; `target` = first of `post_id|id|template_id` present.
- `ActivityLog::recent(int $limit = 200): array`, `ActivityLog::prune(int $days = 30): int`.
- Settings → **Divi MCP** (`manage_options`), page slug `wp-divi-mcp`. Sections: Connections (table + "New connection" form: label, user select of users with `edit_posts`, mode, expiry none/30/90 days), shown-once notice with URL, a copy button and `claude mcp add --transport http <label-slug> "<url>"`; Revoke buttons (nonce `wpdm_revoke`); "Disable endpoint" checkbox (`wpdm_disabled`); Activity (last 200); notice if plugin `divi5-mcp-companion/divi5-mcp-companion.php` is active ("deactivate it, this plugin replaces it"); warning if site is not HTTPS.
- All form posts via `admin_post_wpdm_*` with `check_admin_referer` and `manage_options`.

- [ ] **Step 1: Write failing tests**: `test_record_omits_large_fields`, `test_prune_removes_older_than_30_days`, `test_create_connection_requires_manage_options`, `test_create_connection_shows_url_once` (second render has no token), `test_revoke_requires_nonce`, `test_companion_notice_when_active`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5:** Manual: `npx wp-env start`, open `http://localhost:8888/wp-admin/options-general.php?page=wp-divi-mcp`, create connection, `curl -X POST "<url>" -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}'` (with `WP_DIVI_MCP_ALLOW_HTTP` set in `.wp-env.json` config) → JSON with `serverInfo`.
- [ ] **Step 6: Commit** `feat(admin): connections screen and activity log`.

---

### Task 6: WordPress tools — content

**Files:**
- Create: `includes/Tools/WordPress/` `SiteInfo.php`, `ListPostTypes.php`, `ListTaxonomies.php`, `ListPosts.php`, `GetPost.php`, `CreatePost.php`, `UpdatePost.php`, `TrashPost.php`, `RestorePost.php`, `Search.php`, `PostFormatter.php`
- Modify: `includes/Plugin.php` (register via `wpdm_registry`)
- Test: `tests/WP/Tools/ContentToolsTest.php`

**Interfaces:**
- Tool names (exact): `site_info`, `list_post_types`, `list_taxonomies`, `list_posts`, `get_post`, `create_post`, `update_post`, `trash_post`, `restore_post`, `search`.
- `PostFormatter::summary(WP_Post): array` → `id, type, status, title, slug, link, modified, parent, divi` (`divi` = `get_post_meta($id,'_et_pb_use_builder',true)==='on'` or content contains `<!-- wp:divi/`), and `edit_divi` link (`add_query_arg('et_fb','1', get_permalink())`) when `divi`. `PostFormatter::full(WP_Post): array` adds `content_raw, excerpt, terms{tax:[ids]}, featured_media, meta` (non-protected keys only, i.e. not starting with `_`).
- `list_posts` args: `post_type` (default `page`), `search`, `status` (default `any`), `author`, `tax_query` `{taxonomy, terms[]}`, `per_page` (default 20, max 100), `page`. Returns `{items, total, pages}`.
- `create_post`/`update_post` args: `post_type, title, content, excerpt, status` (default `draft`), `slug, parent, terms{tax:[ids|names]}, featured_media, meta` (only keys registered with `show_in_rest` or not starting `_`). `update_post` with `content` on a Divi post → ToolError "use divi_apply_layout" unless `force=true`; with `force`, backup first (Backups, Task 12) — until Task 12 lands, `force` is refused.
- Capabilities: per post via `current_user_can('edit_post', $id)` / post type `cap->create_posts`; `publish` status requires `publish_posts` of the type.
- `site_info` returns: `wp_version, php_version, site_url, home_url, theme{name,stylesheet,version,parent}, divi{active:bool, version}, plugins_active[]` (names+versions, requires `activate_plugins` else omitted), `user{login, roles, caps:{edit_pages,publish_pages,edit_theme_options,upload_files,manage_options}}`, `connection{label, mode}`.

- [ ] **Step 1: Write failing tests**: `test_create_post_defaults_to_draft`, `test_create_page_with_parent_and_terms`, `test_update_refuses_divi_content_without_force`, `test_trash_and_restore`, `test_no_permanent_delete_tool_registered`, `test_author_cannot_edit_others_post`, `test_list_posts_pagination_and_max_100`, `test_site_info_hides_plugins_without_capability`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(tools): WordPress content tools`.

---

### Task 7: WordPress tools — media, terms, menus, comments, options, users

**Files:**
- Create: `includes/Tools/WordPress/` `ListTerms.php`, `CreateTerm.php`, `UpdateTerm.php`, `ListMedia.php`, `UploadMedia.php`, `UpdateMedia.php`, `ListMenus.php`, `GetMenu.php`, `UpdateMenuItems.php`, `ListComments.php`, `ModerateComment.php`, `GetOptions.php`, `UpdateOptions.php`, `ListUsers.php`, `includes/Support/Media.php`
- Test: `tests/WP/Tools/SiteToolsTest.php`

**Interfaces:**
- `Support\Media::sideload(string $url, string $alt = '', string $title = ''): array{id,url}` (uses `media_sideload_image`/`download_url`, image mime only); `Support\Media::from_base64(string $data, string $filename, string $alt = ''): array{id,url}` (max 5 MB decoded, mime check via `wp_check_filetype_and_ext`). Used again in Task 13.
- `upload_media` args: exactly one of `url` / `base64`+`filename`; plus `alt, title, caption`.
- `update_menu_items` args: `menu_id`, `items[]` each `{id?, title, url? | object_type+object_id, parent?, position}`; items not listed are left; `remove_ids[]` deletes menu items (menu items aren't content, this is allowed). Also `set_location {location, menu_id}`. Capability `edit_theme_options`.
- `moderate_comment` actions: `approve|unapprove|spam|trash|reply` (`reply` needs `content`). Capability `moderate_comments`.
- `get_options`/`update_options` allowlist: `blogname, blogdescription, timezone_string, date_format, time_format, show_on_front, page_on_front, page_for_posts, posts_per_page`; `permalink_structure` read-only. Capability `manage_options`.
- `list_users`: `id, login, display_name, roles`; `email` only if current user has `list_users`.

- [ ] **Step 1: Write failing tests**: `test_upload_media_from_base64`, `test_upload_media_base64_over_limit`, `test_upload_rejects_non_image`, `test_update_options_rejects_non_allowlisted_key`, `test_permalink_structure_read_only`, `test_menu_items_add_and_reorder`, `test_comment_reply`, `test_list_users_hides_email_without_cap`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(tools): media, terms, menus, comments, options, users`.

---

### Task 8: Divi builder port (core library)

**Files:**
- Create: `includes/Divi/Block.php`, `includes/Divi/D5.php`, `tests/golden/build_fixtures.py`, `tests/golden/fixtures/*.html`, `tests/golden/cases.json`
- Test: `tests/Unit/Divi/D5Test.php`, `tests/Unit/Divi/GoldenTest.php`

**Interfaces:**
- `Block { string $name; array $attrs; ?array $children; }` (constructor throws `InvalidArgumentException` unless name starts `divi/`; null children filtered).
- `D5` static methods mirroring `scripts/divi5.py` 1:1, Python kwargs → one trailing `array $opts` with the same keys: `set_builder_version(string)`, `block(string $name, ?array $attrs, ?array $children, bool $builder_version=true)`, `rv($desktop, $tablet=null, $phone=null, array $more=[])` (`$more` keys `hover`, `sticky`, others), `box`, `radius`, `color_var`, `content_var`, `merge(...$arrays)`, `deco`, `module_deco(array $groups)`, `font`, `html_attrs`, `admin_label`, `custom_css`, `flex`, `grid`, `layout_attrs`, `card`, `section`, `column`, `row`, `grid_row`, `group`, `text`, `heading`, `button`, `image`, `blurb`, `link`, `icon`, `breadcrumbs`, `fa_icon`, `divi_icon`, `code`, `toggle`, `accordion`, `accordion_item`, `number_counter`, `divider`, `cta`, `testimonial`, `team_member`, `contact_field`, `contact_form`, `timeline`, `timeline_item`, `icon_list`, `icon_list_item`, `social_follow`, `social_network`, `encode_attrs($value): string`, `serialize($blocks): string`, `post_content($blocks): string`, `global_color`, `global_variable`, `find_image_urls($blocks): array`, `layout_export($blocks, array $opts): array`.
- Defaults (`BUILDER_VERSION`, gaps, widths) copied verbatim from `divi5.py`; default builder version becomes the installed Divi version when called from WP (set by Task 13), `'5.19.0'` otherwise.
- PHP associative arrays must keep insertion order and distinguish `{}` from `[]`: represent empty JSON objects as `new \stdClass` or `(object)[]` inside attrs; `encode_attrs` treats a list (`array_is_list` polyfill for 7.4) as JSON array and any other array as object.
- `encode_attrs` escaping identical to `_encode_str` in `divi5.py` (`"`→`"`, `\`→`\\`, `<`→`<`, `>`→`>`, `&`→`&`, control chars, `--`→`--`, unicode kept unescaped). Floats encoded like Python `json.dumps` (e.g. `1.0` → `1.0`; avoid PHP's `1`): store as given string if needed — golden tests decide.
- Golden fixtures: `build_fixtures.py` imports the skill read-only via `sys.path.insert(0, os.path.expanduser('~/.claude/skills/divi5-layouts/scripts'))`, builds each case in `cases.json` (case = list of helper calls as `{fn, args, kwargs}`), writes `fixtures/<case>.html` (serialized markup). Run once with `python3 -I tests/golden/build_fixtures.py`; fixtures are committed and become the spec. Cases (at least): every structural helper; every module helper; `rv` with hover+sticky; `grid_row(cols=3,tablet=2,phone=1)`; `row` with `wrap_on`; strings with `Ñandú — «citas» & <b>x</b> -- 😀 \ "q"`; numbers `0`, `1.5`, `-2`; booleans; null; empty object; nested lists.
- `GoldenTest` runs each case through `D5` via a tiny interpreter (`call_user_func_array([D5::class, $fn], ...)`) and compares byte for byte.

- [ ] **Step 1:** Write `cases.json` and `build_fixtures.py`; run it; commit fixtures alone: `test: golden fixtures from Python divi5 library`.
- [ ] **Step 2: Write failing tests**: `GoldenTest::test_case_matches_python` (data provider over cases), `D5Test::test_encode_matches_wordpress_serializer` (`D5::encode_attrs($a) === serialize_block_attributes($a)` for the unicode string case; and `parse_blocks(D5::serialize($b))[0]['attrs'] == $a`), `test_block_name_must_start_with_divi`, `test_empty_object_stays_object`.
- [ ] **Step 3:** → FAIL. **Step 4:** implement `Block`, `D5`. **Step 5:** → PASS (all golden cases).
- [ ] **Step 6: Commit** `feat(divi): port divi5 builder library`.

---

### Task 9: Patterns port

**Files:**
- Create: `includes/Divi/Theme.php`, `includes/Divi/Patterns.php`, extend `tests/golden/cases.json` + fixtures
- Test: `tests/Unit/Divi/PatternsTest.php` (golden cases for patterns run through `GoldenTest`)

**Interfaces:**
- `Theme`: public properties with the exact names and defaults of `patterns.py` `class Theme`; `Theme::from_array(array $overrides): Theme` (unknown key → `InvalidArgumentException` naming the key).
- `Patterns` static methods, same names/params as `patterns.py`, last param `Theme $theme = null` (null → default Theme) and `array $opts` for kwargs: `eyebrow, heading_text, body_text, section_header` (returns `Block[]`), `icon_badge, number_badge, pill_link, icon_card, media_card, person_card, testimonial_card, numbered_card, step_card, stat_card` (card patterns return a `column` Block).
- `pill_link` padding 16px/30px; icon color attrs only when `icon_color`/`icon_hover_color` given; `on_dark=true` → white hover with brand text.

- [ ] **Step 1:** Add one golden case per pattern (default Theme, and one with a custom Theme incl. `on_dark=True`) via `build_fixtures.py`; commit fixtures.
- [ ] **Step 2: Write failing tests**: golden cases; `test_theme_unknown_key_throws`; `test_pill_link_no_icon_color_by_default`.
- [ ] **Step 3:** → FAIL. **Step 4:** implement. **Step 5:** → PASS.
- [ ] **Step 6: Commit** `feat(divi): port composition patterns`.

---

### Task 10: Layout tree (what Claude sends)

**Files:**
- Create: `includes/Divi/LayoutTree.php`, `includes/Divi/schema/layout.schema.json`
- Test: `tests/Unit/Divi/LayoutTreeTest.php`

**Interfaces:**
- Consumes: `D5`, `Patterns`, `Theme`.
- `LayoutTree::build(array $tree): array` → `['blocks'=>Block[], 'content'=>string (post_content with placeholder), 'envelope'=>array (layout_export), 'images'=>string[]]`. Throws `LayoutError extends \InvalidArgumentException` with a JSON-pointer-like `path` (e.g. `blocks[0].section.children[1]`) and message.
- Tree format exactly as in the spec: top-level `builder_version?, theme?, global_colors?, global_variables?, custom_css?, blocks[]`. Node = one-key object `{helper: {...args, children?}}` for D5 helpers (`section, row, grid_row, column, group, text, image, link, icon, heading, button, blurb, code, toggle, accordion, accordion_item, number_counter, divider, cta, testimonial, team_member, contact_form, contact_field, timeline, timeline_item, icon_list, icon_list_item, social_follow, social_network, breadcrumbs`), or `{pattern: name, args: {...}}`, or `{block: "divi/<name>", attrs, children?}`.
- Value helpers resolved anywhere inside args/attrs: `{"rv": {desktop, tablet?, phone?, hover?, sticky?}}`, `{"box": [t,r,b,l]}`, `{"radius": [...]}`, `{"color_var": id, "opacity"?}`, `{"content_var": id}`, `{"fa": code, "weight"?}`, `{"divi_icon": code}`, `{"font": {...}}`, `{"flex": {...}}`, `{"grid": {...}}`, `{"card": {...}}`, `{"module_deco": {...}}`. A one-key object whose key is one of these names is a helper; to pass a literal object with such a key, wrap it as `{"literal": {...}}`.
- `layout.schema.json`: JSON Schema draft 2020-12 of the above; used as `inputSchema.properties.layout` in Tasks 13–14 and served as resource `divi://schema/layout`.

- [ ] **Step 1: Write failing tests**: `test_tree_equals_direct_php_build` (spec's example tree vs same built with `D5`/`Patterns` directly), `test_unknown_helper_error_has_path`, `test_card_pattern_inside_column_rejected` (cards return columns; nesting a card in a column → LayoutError, mirroring the skill's rule), `test_literal_wrapper`, `test_builder_version_and_theme_applied`, `test_schema_is_valid_json_and_lists_all_helpers`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(divi): layout tree input format and schema`.

---

### Task 11: Validator port

**Files:**
- Create: `includes/Divi/Validator.php`, `includes/Divi/Report.php`, `includes/Divi/Catalog.php`, `knowledge/module-catalog.json` (copied from skill for now; regenerated in Task 16), `tests/golden/validator/*.json` + expected reports
- Test: `tests/Unit/Divi/ValidatorTest.php`

**Interfaces:**
- `Report { array $errors; array $warnings; array $info; array $counts; ok(): bool; to_array(): array }`.
- `Catalog::load(): array` — if WP + Divi active, read module definitions from the installed theme (`get_template_directory()` + the `module.json` locations used by the skill's `generate_catalog.py`), cache in transient `wpdm_catalog_<divi_version>`; else bundled `knowledge/module-catalog.json`.
- `Validator::validate_doc(array $doc, ?array $catalog = null): Report` (Portability envelope), `Validator::validate_content(string $content, ?array $catalog = null): Report` (raw markup). Same checks and messages as `validate.py`: envelope shape, block parse/balance, unknown modules (ERROR), unknown attribute keys (WARNING), responsive shape, referenced-but-unshipped global ids (WARNING), contrast of clickable bg vs container, text vs bg, hover bg/border/text vs container, thin-button padding.
- Parity fixtures: `build_fixtures.py --validator` runs `validate.py`'s `validate_doc` on each file in `tests/golden/validator/` and writes `<name>.expected.json` (`errors`, `warnings` as lists of strings). Include the skill-known bad cases: thin button, CTA same color as section, hover same as section, missing global, unknown module, unbalanced block.

- [ ] **Step 1:** Write validator fixtures + generate expected reports; commit.
- [ ] **Step 2: Write failing tests**: `test_parity_with_python_validator` (data provider; same errors and warnings sets), `test_validate_raw_markup`, `test_catalog_falls_back_to_bundled`.
- [ ] **Step 3:** → FAIL. **Step 4:** implement. **Step 5:** → PASS.
- [ ] **Step 6: Commit** `feat(divi): port layout validator`.

---

### Task 12: Divi WordPress layer — REST bridge, nonces, globals, backups

**Files:**
- Create: `includes/Divi/DiviRest.php`, `includes/Divi/Nonces.php`, `includes/Divi/Globals.php`, `includes/Divi/Backups.php`, `includes/Divi/Env.php`
- Test: `tests/WP/Divi/BackupsTest.php` (no Divi needed), `tests/WP/Divi/DiviLayerTest.php` (group `divi`)

**Interfaces:**
- `Env::divi_active(): bool` (`class_exists('\ET\Builder\Packages\GlobalData\GlobalData')`), `Env::divi_version(): ?string` (parent theme Version).
- `Nonces::register()`: the companion plugin's `divi_visual_builder_rest_nonces` filter, priority 99, verbatim behaviour.
- `DiviRest::call(string $route, array $body = [], string $method = 'POST'): array` — builds `WP_REST_Request($method, '/divi/v1' . $route)`, JSON body, header `X-ET-Nonce` = `wp_create_nonce('/divi/v1' . $route . '--' . $method)`, `rest_do_request`; non-2xx → `ToolError($message, $code)`. Routes used: `/outside-vb/posts/set-layout`, `/outside-vb/theme-builder/list-templates`, `/create-template`, `/delete-template`, `/upsert-layout`, `/outside-vb/theme-options/get`.
- `Globals::read(): array{colors: array, variables: array}`; `Globals::merge(array $colors, array $variables, bool $overwrite, bool $dry_run): array` (report shape identical to companion's `/globals/merge`). Before a non-dry-run merge that changes anything: `Backups::save('globals', 0, wp_json_encode(Globals::read()), [], $label)`.
- `Backups`: private CPT `wpdm_backup` (not public, no UI, `exclude_from_search`). `save(string $kind, int $source_id, string $content, array $meta, string $connection_label): int` with kinds `post_content|globals|theme_builder`; meta keys `_wpdm_kind`, `_wpdm_source`, `_wpdm_connection`, `_wpdm_meta` (JSON). `list(?int $source_id = null, ?string $kind = null, int $limit = 50): array`, `get(int $id): ?array`, `prune(int $source_id, string $kind, int $keep = 20): void` (called after save).
- `Globals::restore(array $snapshot)`: colors via `GlobalData::set_global_colors(..., true)`; variables replaced through `GlobalData` set method found in Divi 5.19 (confirm the method name in the 5.19 source during this task; record it in a code comment).

- [ ] **Step 1: Write failing tests**: `BackupsTest::test_save_list_get`, `test_prune_keeps_20`, `test_backup_cpt_not_public`; `DiviLayerTest::test_divi_rest_set_layout_with_in_process_nonce`, `test_globals_merge_reports_conflict_and_keeps_value`, `test_builtin_color_skipped_even_with_overwrite`, `test_globals_backup_and_restore`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** `bin/test` and `WPDM_TEST_DIVI=1 bin/test --group divi` → PASS.
- [ ] **Step 5:** Switch `UpdatePost` (Task 6) to back up and allow `force=true`; add `test_update_divi_post_with_force_backs_up`.
- [ ] **Step 6: Commit** `feat(divi): in-process Divi REST, globals merge, backups`.

---

### Task 13: Divi page tools

**Files:**
- Create: `includes/Tools/Divi/` `DiviStatus.php`, `GetGlobals.php`, `MergeGlobals.php`, `BuildLayout.php`, `ValidateLayout.php`, `ApplyLayout.php`, `CreatePage.php`, `ExportPage.php`, `InspectPage.php`, `ListBackups.php`, `RestoreBackup.php`, `SetPageCss.php`, `FindIcon.php`, `includes/Divi/Inspector.php`, `includes/Divi/LayoutInput.php`
- Modify: `includes/Plugin.php` (register Divi tools only when `Env::divi_active()`; `BuildLayout`, `ValidateLayout`, `FindIcon` always)
- Test: `tests/WP/Tools/DiviToolsTest.php` (group `divi` except build/validate/icon)

**Interfaces:**
- Tool names (exact): `divi_status, divi_get_globals, divi_merge_globals, divi_build_layout, divi_validate_layout, divi_apply_layout, divi_create_page, divi_export_page, divi_inspect_page, divi_list_backups, divi_restore_backup, divi_set_page_css, divi_find_icon`.
- `LayoutInput::resolve(array $args): array{content, envelope, report}` — accepts exactly one of `layout` (tree, Task 10), `markup` (raw `wp:divi/` string), `portability` (envelope object); runs Validator. Shared by build/validate/apply/create_page/theme builder.
- `divi_apply_layout` args: `post_id`, one layout input, `sync_globals=true`, `overwrite_globals=false`, `upload_images=true`, `dry_run=false`, `confirm_published=false`. Order: resolve+validate (ERROR → ToolError, nothing changed) → published without confirm → ToolError `confirmation_required` → dry run returns `{report, globals_preview, images_to_upload, content_bytes}` → **backup current content (read at this moment)** → images: embedded base64 (`envelope.images`) via `Media::from_base64`, and external image URLs from the tree via `Media::sideload` (skip URLs already on this site), rewrite URLs in content → globals merge → page CSS (`_et_pb_custom_css`, sanitized with `et_core_sanitize_custom_css_meta_value` when present) → `DiviRest::call('/outside-vb/posts/set-layout', ['post_id'=>..,'layout_content'=>..])` → result `{ok, view, edit_divi, backup_id, images_uploaded, globals, warnings}`.
- `divi_create_page` args: `title, slug?, parent?, status='draft'`, optional layout input (then applies as above in the same call).
- `divi_restore_backup` args: `backup_id`; post content backups → backup current content first, then set-layout (empty content → `wp_update_post` fallback); `globals` → `Globals::restore`; `theme_builder` → upsert-layout with stored content.
- `divi_export_page` returns `{portability, summary}` (summary = `Inspector::tree(content, depth=3)`); globals restricted to ids referenced in content (regex `(gcid-[A-Za-z0-9-]+|gvid-[A-Za-z0-9-]+)`).
- `Inspector` (port of `inspect_layout.py`): `tree(string $content, int $depth): string`, `section(string $content, int $n): array`, `example(string $content, string $block, int $n): array`, `paths(string $content, string $block): array`, `globals(string $content): array`. `divi_inspect_page` args: `post_id`, `mode` (`tree|section|example|paths|globals`), `n?`, `block?`, `depth?`.
- `divi_find_icon` args: `query`, `limit=20`; searches `knowledge/icons.json` (copied from skill) and returns `{name, unicode, family, weight, value}` where `value` is ready for `{"fa":..}`/`{"divi_icon":..}`.
- Default builder version for trees without `builder_version` = `Env::divi_version()`.

- [ ] **Step 1: Write failing tests**: `test_build_layout_returns_markup_and_report`, `test_apply_refuses_on_validation_error`, `test_apply_refuses_published_without_confirm`, `test_apply_dry_run_changes_nothing`, `test_backup_taken_at_write_time` (dry run → change content via `wp_update_post` → apply → backup equals changed content), `test_apply_uploads_embedded_image_and_rewrites_url`, `test_restore_empty_backup_uses_fallback`, `test_export_includes_only_used_globals`, `test_inspect_tree_marks_hidden_sections`, `test_find_icon_tooth`, `test_divi_tools_hidden_without_divi`.
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** both suites → PASS.
- [ ] **Step 5: Commit** `feat(tools): Divi page tools`.

---

### Task 14: Theme Builder tools

**Files:**
- Create: `includes/Tools/Divi/` `ListThemeBuilderTemplates.php`, `CreateThemeBuilderTemplate.php`, `DeleteThemeBuilderTemplate.php`, `SetThemeBuilderLayout.php`
- Test: `tests/WP/Tools/ThemeBuilderToolsTest.php` (group `divi`)

**Interfaces:**
- Tool names: `divi_list_theme_builder_templates`, `divi_create_theme_builder_template(title)`, `divi_delete_theme_builder_template(template_id)`, `divi_set_theme_builder_layout(layout_type: header|footer|body, template_id, <layout input>, confirm_default_template=false)`.
- All calls pass `live: true`. Delete refuses `default` template. Set: template must exist; default needs confirm; validate; back up previous layout content (`Backups::save('theme_builder', $template_id, $prev_content, ['layout_type'=>..,'layout_id'=>..])`) before `upsert-layout`. Capability `edit_theme_options`.

- [ ] **Step 1: Write failing tests**: `test_create_template_is_unassigned`, `test_delete_default_refused`, `test_set_layout_default_requires_confirm`, `test_set_layout_backs_up_previous`, `test_never_calls_with_live_false` (spy filter on `rest_pre_dispatch` asserts `live === true`).
- [ ] **Step 2:** → FAIL. **Step 3:** implement. **Step 4:** → PASS.
- [ ] **Step 5: Commit** `feat(tools): Theme Builder tools`.

---

### Task 15: Knowledge — guide, resources, prompts, Divi 5.19 catalog

Blocked on Task 0.

**Files:**
- Create: `tools/generate-catalog.php` (CLI, port of `generate_catalog.py`), `knowledge/guide.md`, `knowledge/attributes.md`, `knowledge/modules/*.md`, `knowledge/modules/INDEX.md`, `knowledge/module-catalog.json` (regenerated), `knowledge/icons.json`, `includes/Knowledge/Resources.php`, `includes/Knowledge/Prompts.php`, `includes/Knowledge/Instructions.php`
- Test: `tests/WP/KnowledgeTest.php`

**Interfaces:**
- `tools/generate-catalog.php <divi-theme-dir>` → writes `knowledge/modules/*.md`, `INDEX.md`, `module-catalog.json`; run in Docker: `docker run --rm -v "$PWD":/app -v "<divi dir>":/divi:ro -w /app php:8.2-cli php tools/generate-catalog.php /divi`.
- `knowledge/guide.md`: the skill's SKILL.md rewritten for the tool interface (tree JSON instead of Python, tool names instead of scripts), keeping the composition table, "what real imports showed" lessons, workflow, safety rules. `attributes.md` copied, then updated where the 5.19 export shows different shapes.
- Check against the owner's 5.19 export: run `divi_inspect_page`-equivalent `Inspector::paths` over it for `divi/section, row, column, group, text, link, icon, image`; any attribute path missing from the regenerated catalog → fix generator. Default builder version constant becomes `'5.19.0'`.
- Resources: `divi://guide`, `divi://attributes`, `divi://modules/index`, template `divi://modules/{name}`, `divi://schema/layout`. Prompts: `build_divi_page {purpose, sections?, language?, brand?}`, `match_existing_site {post_id}` (render: user message telling Claude to read `divi://guide`, call `divi_get_globals`, `divi_inspect_page`, build a tree, `divi_validate_layout`, `divi_create_page`).
- `Instructions::text(): string` (≤ 1500 chars) passed to `Server`: workflow steps, composition rule, "new designs → new draft page", confirm flags, read `divi://guide` first.

- [ ] **Step 1:** Port and run generator against Divi 5.19; commit generated knowledge separately: `chore(knowledge): regenerate module reference from Divi 5.19`.
- [ ] **Step 2: Write failing tests**: `test_resources_list_contains_guide_and_schema`, `test_module_template_reads_known_module` (`divi://modules/group`), `test_unknown_module_resource_error`, `test_prompts_render`, `test_instructions_under_1500_chars`, `test_catalog_has_5_19_modules` (`module-catalog.json` divi version field == `5.19.*`).
- [ ] **Step 3:** → FAIL. **Step 4:** implement. **Step 5:** → PASS; rerun golden + validator suites (catalog change may add warnings: regenerate validator expectations only if the difference is due to 5.19 modules, documented in commit message).
- [ ] **Step 6: Commit** `feat(knowledge): guide, resources and prompts`.

---

### Task 16: Release packaging and end-to-end check

**Files:**
- Create: `.github/workflows/release.yml`, `bin/build-zip`, `.distignore`, `docs/connect.md`
- Modify: `README.md`

**Interfaces:**
- `bin/build-zip` → `build/wp-divi-mcp.zip` containing `wp-divi-mcp/` with `wp-divi-mcp.php, includes/, knowledge/, assets/, readme`; excludes `tests, tools, vendor, docs, bin, node_modules, .github, .wp-env*`.
- `release.yml`: on tag `v*` → lint → build zip → GitHub release with zip attached.
- `docs/connect.md` (Spanish, for the owner): install zip, create connection, paste URL in claude.ai (*Settings → Connectors → Add custom connector*), Claude Code command, revoke, read-only mode, security notes (token in URL lands in logs; revoke when done).

- [ ] **Step 1:** Build zip; unzip listing contains no `tests/`/`vendor/` (`unzip -l build/wp-divi-mcp.zip | grep -E 'tests/|vendor/'` → no output).
- [ ] **Step 2:** MCP Inspector against wp-env: `npx @modelcontextprotocol/inspector` → Streamable HTTP → connection URL; `tools/list` shows all tools; call `site_info`.
- [ ] **Step 3:** Owner uploads zip to `immedexdev`, deactivates the companion plugin, creates a full connection, adds it to claude.ai. Then from claude.ai: `site_info` → `divi_get_globals` → `divi_create_page` with a composed tree (draft) → open `edit_divi` link → `divi_export_page` → `divi_apply_layout` on the same draft with a change → `divi_restore_backup` → `divi_create_theme_builder_template` + `divi_set_theme_builder_layout` (header) → `divi_merge_globals` with one new id and one conflicting id (dry run, then real) → check Activity log shows every call. Owner deletes test content.
- [ ] **Step 4:** Tag `v0.1.0`, push tag, release has the zip.
- [ ] **Step 5: Commit** `chore: release packaging and connection guide`.
