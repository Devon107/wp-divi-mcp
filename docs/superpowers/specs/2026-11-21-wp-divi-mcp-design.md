# WP Divi MCP — design

Date: 2026-11-21
Status: draft, waiting for approval

## Intent

A WordPress plugin that contains its own MCP server. You install it on a site, it generates a
connection URL, and you paste that URL into Claude (claude.ai custom connector, Claude Desktop or
Claude Code). From then on Claude can manage the site — posts, pages, media, menus, taxonomies —
and build and edit Divi 5 pages following the rules of the `divi5-layouts` skill.

Why: the current Divi 5 MCP (`~/.claude/skills/divi5-layouts/mcp/`) is a local Python process
that needs a `.env` file and an Application Password per site, and only works from this machine.
The owner wants "install plugin, connect, done" for their clients' sites and their work sites.

Audience: the owner only (private use on client and work sites). Not published on wordpress.org.

## Goals

1. One self-contained plugin (no Composer runtime dependencies, no external service).
2. Remote MCP endpoint over HTTP that Claude can reach directly.
3. Connection URLs generated and revoked from the WordPress admin, each tied to a WordPress user
   whose capabilities limit what Claude can do.
4. General WordPress management tools.
5. Divi 5 tools at parity with the existing Python MCP, plus building layouts server-side, so no
   local Python skill is needed.
6. The skill's knowledge (composition rules, attribute reference, module reference, icons)
   shipped inside the plugin and served to Claude through MCP.

## Non-goals (v1)

- OAuth 2.1 / dynamic client registration. Token URLs are enough for private use; OAuth can be
  added later behind the same tool layer.
- Installing/activating plugins or themes, creating/deleting users, editing PHP files, running
  arbitrary SQL or PHP. Too dangerous for a remote endpoint.
- Permanent deletion of content. Delete means "move to trash".
- WooCommerce-specific tools.
- wordpress.org packaging, translations, multisite network admin.

## Anti-goals (would make it a failure even if it works)

- A leaked URL that gives full admin control with no way to see or stop it.
- Silently overwriting a published page or the site's global colors/variables.
- Generated Divi pages that look worse than what the Python skill produces today (thin buttons,
  invisible CTAs, under-styled blurbs — the problems already solved in the skill).
- Breaking the Theme Builder (e.g. the empty auto-draft problem found with `live=false`).

## Constraints

- PHP 7.4+, WordPress 6.2+. Client sites are often behind on versions.
- Divi 5 optional: WordPress tools work on any site; Divi tools only appear when Divi 5's
  `GlobalData` class is present.
- The site must be publicly reachable over HTTPS. claude.ai connectors call the server from
  Anthropic's cloud, so `localhost` sites only work with Claude Code / Desktop.
- Some hosts (WP Engine among them) strip or rewrite the `Authorization` header, so auth cannot
  depend on it alone.
- Known Divi quirks that must be handled (from the existing MCP):
  - Divi REST nonces are created for the anonymous user before WP authenticates the request →
    re-create them for the authenticated user (companion plugin's `divi_visual_builder_rest_nonces`
    filter). Running inside WordPress we can also call Divi's PHP classes directly instead of
    going through `divi/v1` REST.
  - No read endpoint for globals; Divi's variables POST replaces the whole set → merge by id,
    report conflicts, never overwrite unless asked; built-in colors are never touched.
  - Theme Builder `live=false` starts an empty draft Theme Builder → always write live, with an
    explicit template id; the default template needs `confirm_default_template=true`.
  - Divi's set-layout rejects empty content → restore falls back to a plain post update.
  - Link module icons inherit the text color; never set an icon color unless it must differ.

## Approaches considered

- **A. Self-contained plugin with a hand-written MCP server in PHP — chosen.** Exposes MCP over
  HTTP from a WP REST route, token URL auth, no dependencies, works on WP 6.2+. The protocol layer
  is small (only `initialize`, `ping`, `tools/*`, `resources/*`, `prompts/*`) and is ours to keep
  current.
- B. Build on WordPress's `mcp-adapter` + Abilities API. Follows the emerging standard, but needs
  WP 6.9 or bundled Composer packages, the adapter still changes between versions, and token auth
  and all Divi work would still be ours.
- C. Host the existing Python server somewhere. Rejected: contradicts "install plugin and connect".

The owner picked A.

## Design

### Plugin layout

```
wp-divi-mcp/
  wp-divi-mcp.php            bootstrap, constants, autoloader
  includes/
    Mcp/Server.php           JSON-RPC 2.0 dispatch, MCP lifecycle, errors
    Mcp/Registry.php         tools, resources, prompts registration
    Auth/Tokens.php          create / verify / revoke connection tokens
    Admin/SettingsPage.php   Settings → Divi MCP screen
    Log/ActivityLog.php      per-call log (custom table)
    Tools/WordPress/*.php    general WP tools
    Tools/Divi/*.php         Divi tools
    Divi/Builder.php         port of divi5.py (blocks, rv, flex/grid, serialize, envelope)
    Divi/Patterns.php        port of patterns.py (Theme, cards, pill_link, section_header…)
    Divi/Validator.php       port of validate.py (catalog, responsive, contrast, hover checks)
    Divi/Globals.php         read + merge globals (from companion plugin)
    Divi/Nonces.php          nonce fix filter (from companion plugin)
    Divi/ThemeBuilder.php    templates, header/body/footer layouts
    Divi/Backups.php         backups before overwrite
  knowledge/                 SKILL guide, attributes.md, modules/*.md, module-catalog.json, icons.json
  tests/                     PHPUnit + golden files
  .github/workflows/         lint, tests, release zip on tag
```

### MCP transport

- Route: `POST /wp-json/wp-divi-mcp/v1/mcp` (Streamable HTTP, JSON responses, no SSE streaming).
  `GET` returns 405 (no server-initiated stream needed). `DELETE` ends the session.
- Protocol versions: negotiate `2025-06-18`, accept `2025-03-26`.
- Sessions: issue `Mcp-Session-Id` on `initialize`, stored as a short-lived transient; requests
  without a known session after initialize get the standard error so the client re-initializes.
- Batch requests and notifications handled per JSON-RPC 2.0.
- Tool results return `structuredContent` plus a text copy; tool errors return `isError: true`
  with a clear message (not a protocol error).
- Tool annotations (`readOnlyHint`, `destructiveHint`) on every tool, so Claude asks before
  destructive calls.

### Authentication and connections

- Admin screen *Settings → Divi MCP → Connections*: "New connection" with label, WordPress user
  (defaults to the current admin), mode (**read-only** or **full**), optional expiry.
- The plugin generates a 32-byte random token and shows the URL **once**:
  `https://site.com/wp-json/wp-divi-mcp/v1/mcp?key=<token>`
  Also shows a copy-ready `claude mcp add --transport http …` command for Claude Code.
- Only a SHA-256 HMAC of the token (keyed with `wp_salt('auth')`) is stored, in an option, with
  label, user id, mode, created, last used, last IP.
- Accepted from `?key=` or `Authorization: Bearer`. On match, the request runs as that user
  (`determine_current_user` filter, only for this route); capabilities are checked per tool.
- Read-only mode hides every tool without `readOnlyHint`.
- Revoke deletes the token instantly. Failed token attempts are rate-limited per IP (transient,
  e.g. 10/minute → 429).
- Requires HTTPS unless `WP_DIVI_MCP_ALLOW_HTTP` is defined (for local dev).
- Master switch: "Disable MCP endpoint" checkbox.

### Activity log

A custom table logs each call: time, connection label, tool, target ids, ok/error, duration.
Arguments are stored truncated, with layout bodies and media data left out. The admin
screen shows the last 200 calls, and older rows are pruned after 30 days. This is how
the owner sees what a connection did.

### WordPress tools

All use WP core functions (not internal REST calls) and check capabilities.

| Tool | Notes |
|---|---|
| `site_info` | WP/PHP/theme/Divi versions, active plugins (read), user + capabilities, connection mode |
| `list_post_types`, `list_taxonomies` | public + registered types |
| `list_posts` | any post type; search, status, author, taxonomy filter, pagination |
| `get_post` | raw content, meta (non-protected), terms, featured image, Divi-built flag, links |
| `create_post` | any type; title, content, excerpt, status (default draft), parent, terms, featured image, meta allowlist |
| `update_post` | same fields; content updates refuse Divi-built posts unless `force=true` (use Divi tools) |
| `trash_post` / `restore_post` | trash only |
| `list_terms`, `create_term`, `update_term` | categories, tags, custom taxonomies |
| `list_media`, `upload_media` | upload from URL (sideload) or base64 (size cap), alt/title/caption |
| `update_media` | alt, title, caption |
| `list_menus`, `get_menu`, `update_menu_items` | classic menus and locations |
| `list_comments`, `moderate_comment` | approve, spam, trash, reply |
| `get_options`, `update_options` | allowlist only: blogname, blogdescription, timezone, date format, front page / posts page, permalink structure read-only |
| `list_users` | read-only, no emails unless `list_users` cap |
| `search` | across post types |

### Divi tools

| Tool | Notes |
|---|---|
| `divi_status` | Divi version, builder version, GlobalData available, nonce fix active |
| `divi_get_globals` | colors and variables (id, label, value) |
| `divi_merge_globals` | merge by id; reports added / same / conflicts / overwritten / skipped built-in; `overwrite`, `dry_run` |
| `divi_build_layout` | layout tree (below) → validated block markup + Portability JSON; returns report, nothing written |
| `divi_validate_layout` | validate a tree, raw markup or Portability JSON |
| `divi_apply_layout` | build/validate → backup → sideload images → merge globals → page CSS → save; `dry_run`; refuses on validation ERROR |
| `divi_create_page` | new draft page with Divi enabled, optionally applying a layout in the same call |
| `divi_export_page` | stored content + globals used, as Portability JSON and as a tree summary |
| `divi_inspect_page` | tree view, section N attrs, attribute examples (port of `inspect_layout.py`) so Claude can copy a site's real styles |
| `divi_list_backups`, `divi_restore_backup` | |
| `divi_list_theme_builder_templates`, `divi_create_theme_builder_template`, `divi_delete_theme_builder_template`, `divi_set_theme_builder_layout` | same safety rules as the Python MCP |
| `divi_find_icon` | search Divi + Font Awesome icons by keyword |
| `divi_set_page_css` | page custom CSS |

Saving uses Divi's PHP layer directly (the same code path `divi/v1/.../set-layout` uses) so the
content is stored exactly as the Visual Builder stores it; the REST nonce fix stays in the plugin
for any remaining REST use and for other tools that need it.

### Layout input (what Claude sends)

A JSON tree mirroring the Python library, so the skill's knowledge carries over 1:1:

```json
{
  "builder_version": "5.14.0",
  "theme": {"accent": {"color_var": "gcid-link-color"}, "card_radius": "16px"},
  "global_colors": [], "global_variables": [], "custom_css": "",
  "blocks": [
    {"section": {"css_id": "becas", "attrs": {}, "children": [
      {"row": {"children": [{"column": {"children": [
        {"pattern": "section_header", "args": {"eyebrow": "Becas", "title": "Una beca para cada talento"}}
      ]}}]}},
      {"grid_row": {"cols": 3, "tablet": 2, "phone": 1, "children": [
        {"pattern": "icon_card", "args": {"icon": {"fa": "&#xf19d;"}, "title": "Excelencia", "body": "…"}}
      ]}}
    ]}}
  ]
}
```

- Node kinds: every helper in `divi5.py` (`section`, `row`, `grid_row`, `column`, `group`, `text`,
  `image`, `link`, `icon`, all-in-one modules…), every pattern in `patterns.py`, and a generic
  `{"block": "divi/<name>", "attrs": {…}, "children": […]}` escape hatch.
- Helper values: `{"rv": {...}}`, `{"box": [...]}`, `{"color_var": "gcid-…"}`, `{"content_var": "gvid-…"}`,
  `{"fa": "&#xf19d;", "weight": "900"}`, `{"divi_icon": "&#xe00b;"}`.
- The exact JSON schema is published as an MCP resource and in the tool's `inputSchema`.
- Raw `wp:divi/` markup is also accepted by validate/apply, for edits of exported content.

### Port of the Python library

- `Builder.php`, `Patterns.php`, `Validator.php` are direct ports. Behaviour is pinned by golden
  tests: a Python script in `tests/golden/` builds a set of layouts with the skill's library and
  saves the serialized markup; PHPUnit builds the same trees in PHP and compares byte for byte
  (attribute encoding and escaping are where ports usually drift).
- Validator parity: same ERROR/WARNING set on the same fixtures, including the contrast and hover
  checks and thin-button warning.
- Defaults follow the skill: composition over blurb/testimonial/person/button; `pill_link` padding
  16px/30px; no icon color unless it differs from the text.

### Knowledge served to Claude

- `instructions` in `initialize`: short version of the skill workflow (inspect site → reuse globals →
  compose → validate → draft page → apply → share links), composition table, safety rules.
- Resources: `divi://guide` (adapted SKILL.md), `divi://attributes`, `divi://modules/index`,
  `divi://modules/{name}` (116 files, ~850 KB total, read on demand), `divi://schema/layout`.
- Prompts: `build_divi_page` (purpose, sections, language, brand) and `match_existing_site`
  (page id to copy styles from).
- The plugin's `knowledge/` folder is generated from the skill by a script, so both stay in sync.

### Backups and safety

- Before any overwrite (`divi_apply_layout`, `update_post` on content, Theme Builder layouts) the
  previous content is stored in a private `wpdm_backup` post type (content, meta it touches,
  source post id, connection label). WordPress revisions are kept as usual. Keep the last 20 per
  post.
- New designs default to new draft pages. Applying to a published page requires
  `confirm_published=true`.
- Globals never overwritten unless `overwrite=true`; built-in colors never.

### Distribution and updates

- GitHub Actions builds `wp-divi-mcp.zip` on every tag and attaches it to a release.
- v1 installs by uploading the zip. Auto-updates from the private repo are a later addition.

### Testing

- PHPUnit (WP test suite via `wp-env` or `@wordpress/env`) for auth, protocol, tools, builder,
  validator, golden parity.
- Protocol check with the MCP Inspector against a local site.
- End-to-end on the staging site `immedexdev` (Divi 5.14): connect from claude.ai with a token URL,
  create a draft page, apply a composed layout, export, restore, Theme Builder sandbox template,
  globals merge with a conflict. Test content is drafts only and deleted afterwards.

## Left to the builder

- Internal class structure, naming, and how the tree DSL maps to PHP helpers.
- Exact rate-limit numbers, log retention, backup count (values above are defaults).
- Whether to call Divi's PHP save layer directly or loop back through `divi/v1` internally, as long
  as stored content matches what the Visual Builder writes.
- Admin screen styling.

## Calls made by Claude (owner to check)

- Repo name `wp-divi-mcp`, private on GitHub account `Devon107`.
- Token in the URL query (`?key=`) as the primary auth, Bearer header as alternative.
- Read-only vs full connection modes.
- Trash-only deletes; no plugin/theme/user management.
- Backups in a private post type instead of local files.
- Applying to a published page needs an explicit confirm flag.
