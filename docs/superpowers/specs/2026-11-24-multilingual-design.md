# Multilingual content (WPML and Polylang) — design

Date: 2026-11-24. Status: approved approach (A); builder check done, calls marked (call); pending the owner's review.

## Why

The owner runs Divi sites in more than one language. One site uses WPML and another uses Polylang. They want Claude to translate content: the page or post exists first in the main language, then Claude writes the translation and the plugin creates the other-language version, linked as its translation. For Divi pages the translated page must keep **exactly the same design**: same sections, modules and styles, with only the texts changed. The second language can be any language configured on the site.

## Goals

1. **Detect the language plugin.** Find out which one is active (Polylang or WPML), the configured languages and the default language.
2. **Read a post's language and translations.** Set a post's language, and link existing posts as translations.
3. **Translate a Divi page or a plain post in two steps.**
   - *Extract*: the plugin returns the translatable texts of the source as a numbered list.
   - *Create*: Claude sends the translated list back. The plugin copies the source exactly, swaps in the texts, and does the following:
     - switches global modules to their target-language version, or creates that version when it is missing;
     - rewrites internal links to their translations when they exist;
     - copies the Yoast title and description translated, when Yoast is active;
     - saves the result as a **draft** in the target language, linked to the source.
4. **Footer checks.**
   - When the site has a Theme Builder footer, report whether that footer layout has a version in the target language. This is reported only; the Theme Builder is not changed.
   - When the page carries its footer as a global module, it is handled like any other global module.
5. **Report what was created.** Every translated global module that was created is reported, with a note that other target-language pages still use the original until they are updated.

## Non-goals (for now)

- Translating categories, tags, menus, widgets, strings or options.
- Translating Theme Builder templates and layouts. The plugin only checks and reports on them.
- WPML's Advanced Translation Editor, translation jobs and memory.
- Machine translation services. Claude writes every translation.
- Media translation and attachment duplication. Images keep the same files; only the alt text is translated.
- Updating an existing translation when the source changes later.

## Anti-goals

- The translated page's block structure differs from the source. Same blocks, same attrs except the text fields, same order.
- The source page or any existing translation is changed. Both are read-only during create.
- An existing translation is silently overwritten. If one already exists for that language, create refuses with `conflict` and returns its id.
- The translation is published without review. It is always a draft. Claude publishes later with the usual tools when the owner says so.

## Constraints

- PHP 7.4+, WordPress 6.2+ (tests on 7.1.5 and 6.8).
- Polylang (free) is tested for real in wp-env.
- WPML is commercial and cannot be installed in tests or CI. Its adapter uses only WPML's documented public hooks:
  - `wpml_active_languages`, `wpml_default_language`, `wpml_post_language_details`, `wpml_element_trid`, `wpml_get_element_translations`, `wpml_object_id` (filters);
  - `wpml_set_element_language_details` (action).

  It is tested against a test double that implements those hooks in memory. The real check is an E2E run on the owner's WPML site.
- Server instructions are at 1498/1500 characters. The tools explain themselves through their descriptions and `knowledge/guide.md`, not the instructions.

## Approaches

- **A. Extract texts, then rebuild on the server (chosen).** Claude handles only the texts, so the structure is guaranteed identical and huge pages fit.
- B. Claude edits the exported layout JSON with the existing tools, plus a linking tool. Rejected: Claude would rewrite 100k+ characters of layout, which risks structural drift and does not fit large pages.
- C. Machine translation inside the plugin. Rejected: the owner wants Claude to write the translations.

## Design

### Language adapter

`WPDM\I18n\Languages` is a small interface with two implementations, `Polylang` and `Wpml`. `Languages::current()` returns the active one, or null when neither is active. Polylang wins if, unusually, both are active, and a warning is reported. The interface methods:

- `name(): string` returns `'polylang'` or `'wpml'`.
- `languages(): array<{code, name, locale, default: bool}>`
- `default_language(): string`
- `post_language( int $id ): ?string`
- `translations( int $id ): array<code => post_id>`, including the post itself.
- `set_language( int $id, string $code ): void`
- `link( int $source_id, int $translation_id, string $code ): void` adds the post to the source's translation group.
- `translation_of( int $id, string $code ): ?int` gives the translated post id for a code, or null.

For WPML the element type is `post_<post_type>`, and `link` uses the source's `trid` with `source_language_code`. Language codes are the plugin's own codes (`en`, `fr`, `es`, `pt-br`…). An unknown code gives `invalid_argument`, with the list of valid codes.

### Tools (registered only when a language plugin is active)

| Tool | Read-only | Input | Output |
|---|---|---|---|
| `i18n_status` | yes | — | `{plugin, default_language, languages[], theme_builder_footer: {layout_id, translations: {code: id}} \| null}` |
| `i18n_get_translations` | yes | `{id}` | `{id, language, translations: {code: {id, title, status, link}}}` |
| `i18n_set_language` | no | `{id, language}` | `{id, language}`. Fails with `conflict` if the post is already linked to others in a different language. |
| `i18n_link_translation` | no | `{source_id, translation_id}` | `{translations}`. Both posts must have languages set, they must differ, and the source's group must not already have that language. |
| `i18n_extract` | yes | `{id, target_language}` | see below |
| `i18n_create_translation` | no | see below | see below |

Permissions follow the existing tools. Read tools follow `get_post` visibility. Write tools need `edit_post` on every post they change, and `publish_posts` is not needed because the output is a draft. Read-only connections are blocked by `read_only: false`.

### Segments (the extract/create contract)

`i18n_extract {id, target_language}` walks the source and returns:

```json
{
  "source_id": 123,
  "source_language": "en",
  "target_language": "fr",
  "source_hash": "<sha256, see below>",
  "segments": [
    {"id": "t", "kind": "text", "context": "post title", "text": "Home"},
    {"id": "b3.content", "kind": "html", "context": "divi/text in section 2", "text": "<p>Hello <a href=\"/about\">us</a></p>"},
    {"id": "b7.button.text", "kind": "text", "context": "divi/button", "text": "Book now"},
    {"id": "b9.image.alt", "kind": "text", "context": "divi/image alt", "text": "Clinic front"},
    {"id": "seo.title", "kind": "text", "context": "Yoast SEO title", "text": "..."},
    {"id": "g45.b2.content", "kind": "html", "context": "global module 45 'Footer' (no fr version yet)", "text": "..."}
  ],
  "global_modules": [{"id": 45, "title": "Footer", "translation": null}],
  "warnings": ["Theme Builder footer (layout 28) has no fr version."]
}
```

- **What is a segment.** Any string at `<element>.innerContent.<breakpoint>.value` of a block. When that value is an object, its `text`, `title`, `alt`, `caption`, `label` and `placeholder` keys each become a segment. Also the post title, the excerpt, and (with Yoast) the stored SEO title and description.
  - Skipped: values that are empty, whitespace only, a URL, a number, a color or a Divi global variable reference.
  - Skipped: values of keys that are never prose: `src`, `linkUrl`, `url`, `id`, `width`, `height`, `sizes`, `srcset`, `icon`, `linkTarget`.
  - Extra prose keys seen in real exports are included: `titleText` (image), and `title` / `label` (social network).
  - **`divi/code` blocks are skipped (call).** They hold CSS, scripts and shortcodes (`[ssjr_service_checker map="yes"]`), are copied unchanged, and each one is listed in a warning ("N code modules were not translated; review them"). Shortcode attributes are never translated.
  - Responsive variants (`tablet`, `phone`) that equal `desktop` are not separate segments: the desktop translation fills them. Variants that differ are their own segments.
  - `adminLabel` is not translated.
- **Segment ids are stable paths.** `b<n>` is the index of the block's **opening** tag among `divi/*` opening and self-closing blocks in document order. Closing tags, non-divi blocks and global-layout children are not counted. It is followed by the attr path. `t` is the title, `x` the excerpt, `seo.*` the Yoast fields, and `g<libraryId>.<…>` marks segments of global module library items that have no target version. Re-extracting an unchanged source gives the same ids.
- **`kind`.** `html` means inline HTML may appear and must be kept as valid HTML with the same tags. `text` means plain text.
- **Global modules (call, from the real export).** In Divi 5 a global module is a `divi/global-layout` block with `{"globalModule": "<et_pb_layout id as string>", "blockName": ..., "localAttrs": {...}}`. Its full content is inlined as child blocks carrying `"globalParent": "<id>"`.
  - The inline children are **not** page segments; the library item is the source of truth.
  - In the translation, the global-layout block keeps its attrs, with `globalModule` set to the target library id and `localAttrs` unchanged. Its inline children are replaced by the target library item's content, with `globalParent` set to the target id, so the page is right whichever copy Divi renders.
  - The structure test treats each global-layout block as one opaque unit.
  - The E2E run checks what Divi renders.

  Lookup of the library item:
  - If the library item already has a target-language translation, it is listed with that id and contributes no segments.
  - If it has none, its own content's segments are included, prefixed `g<id>.`.

`i18n_create_translation` input:

```json
{
  "source_id": 123,
  "target_language": "fr",
  "source_hash": "...",
  "segments": {"t": "Accueil", "b3.content": "<p>Bonjour ...</p>", "...": "..."},
  "slug": "accueil"
}
```

`slug` is optional. Behaviour:

- **Validation:**
  - `source_hash` (call) covers the post content, title and excerpt, the stored Yoast fields, and the content of every library item that contributed `g<id>.` segments. It must match the current source, otherwise `conflict` ("the source changed since extract; extract again").
  - Every segment id must exist. Unknown ids give `invalid_argument`.
  - Missing ids keep the source text, and a warning lists them.
  - `html` segments go through `wp_kses_post`. When the user has `unfiltered_html`, they are kept as is, the same as the Divi tools do today.
- **Global modules.** For each one without a target version, the plugin creates the target library item: it copies the source item, applies its `g<id>.` segments, sets the language and links it. Then it uses the new id in the page.
  - The output lists each one created: `{source_id, new_id, title, used_in_pages_count}`, where `used_in_pages_count` counts the other target-language pages that still reference the source item.
  - The warning names them: "Created fr version of global module 'Footer' (#91). N other fr pages still use the original; update them when you are ready."
- **Page content: in-place rewrite (call).** The plugin does not re-serialize the whole document. For each block with segments, it decodes that block's attribute JSON, sets the translated strings at their paths, re-encodes only that block's attributes with `D5::encode_attrs`, and splices them back at the same offsets (from `BlockScanner`). Everything else stays byte for byte: other blocks, text between blocks, non-divi blocks, `{}` vs `[]`. Global-layout blocks are rewritten as described above.
  - The structure test compares the block names and the non-text attrs of the source and the translation.
  - **Other content (call).**
    - Core Gutenberg blocks: each block's inner HTML is one `html` segment.
    - Classic (non-block) HTML: one `html` segment `c`.
    - Legacy Divi 4 shortcode content (`[et_pb_`) is refused with `invalid_argument` ("convert the page to Divi 5 first").
- **Dynamic-content links (call).** Divi stores some links as `$variable({"type":"content","value":{"name":"post_link_url_page","settings":{"post_id":N}}})$`. Their `post_id` is mapped to its target-language translation when one exists; otherwise it is left as is and reported with the untranslated links.
- **Internal links.** In translated `html` segments and in `linkUrl` / `url` values, an href to a post on this site is rewritten to that post's target-language translation's permalink, when one exists (resolved with `url_to_postid`). Otherwise it is left as is and reported as a warning listing the untranslated link targets.
- **The new post:**
  - It has the same post type, author, template and page attributes, a parent mapped to the parent's translation (else the source parent), and the same featured image.
  - Taxonomy terms are mapped to their target translations when they exist, otherwise left out with a warning.
  - Meta is copied, except plugin-internal and language-plugin meta. Divi meta (`_et_pb_use_builder`, `_et_pb_page_layout`…) is copied. The Yoast title and description come from the `seo.*` segments (via `WPDM\Seo\Yoast`).
  - Status is `draft`. Language is set and the post is linked to the source. No backup is taken (call): nothing existing is modified, since the source and the library items are only read.
- **Existing translation.** If the source already has a translation in that language, the call refuses with `conflict` and returns `{existing_id}`. Updating existing translations is a non-goal.
- **Output:** `{id, language, status: "draft", view, edit_divi, translations, created_global_modules[], warnings[]}`.

### Theme Builder footer check

`i18n_status` and `i18n_extract` look up the Default Website Template's footer layout. With WPML that is the `et_footer_layout` post; with Polylang, the same post, through Polylang's translations if the type is translatable. The check reports whether a target-language translation exists. Nothing is created or assigned.

## Tests

- **Polylang, real, in wp-env.** Group `polylang`, `WPDM_TEST_POLYLANG=1`, Polylang installed in the tests env like Yoast:
  - adapter methods;
  - `i18n_set_language` / link;
  - extract → create on a Divi page with:
    - text, link, button, image alt and a responsive variant;
    - a global module with and without a target version;
    - an internal link to a translated and to an untranslated page;
    - Yoast fields (with `WPDM_TEST_YOAST=1` too, in a combined job);
  - the structure check: the block names and non-text attrs of the source and the translation are identical;
  - `source_hash` conflict, unknown segment id, existing translation conflict, permissions, read-only.
- **WPML, test double.** Group `wpml`, `tests/WP/Support/WpmlDouble.php` implements the listed hooks over an in-memory table. The same adapter contract tests run against it.
- **Without a language plugin.** The tools are not registered.

## Left to the builder

- The exact list of skipped keys and value detectors, refined against real exports (`plantilla5-19.json`, `homepage 2026.json`).
- How to discover Polylang's translatable post types (`pll_is_translated_post_type`) and WPML's (`wpml_is_translated_post_type`). An untranslatable type gives `invalid_argument`.
- Batching: whether `i18n_extract` needs pagination for very large pages.
- Exact warning texts.
