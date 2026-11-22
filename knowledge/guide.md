# Building Divi 5 pages with WP Divi MCP

This guide is everything you need to build good Divi 5 pages on this WordPress site with
the tools of this server. You do not write Divi markup. You send a **layout tree**: a JSON
object that names sections, rows, columns, modules and composition patterns. The server
turns it into the block markup Divi itself writes, validates it against the module
catalog of the installed Divi, backs up the page, imports images, merges global colors,
and saves through Divi's own API. Read this guide once per conversation before you build.

Divi 5 stores a page as WordPress block markup (`<!-- wp:divi/section {...} -->`) whose
settings are JSON attributes. Everything below is about producing the right tree; the
attribute shapes are in `divi://attributes`, and each module's settings are in
`divi://modules/{name}`.

## 1. Tools and resources

| Tool | Use |
|---|---|
| `divi_status` | Divi active?, its version (stamped on new blocks), catalog version, what you may do (`can.publish_pages`, `can.edit_theme_options`, `can.upload_files`, `can.unfiltered_html`) |
| `divi_get_globals` | The site's global colors (`gcid-...`) and variables (`gvid-...`) with values: reuse them |
| `divi_inspect_page` | Look inside a page without downloading it: `mode` = `tree` (outline), `section` + `n` (full attrs of section n), `example` + `block` (attrs of the first instances of a module), `paths` (every attribute path used, with a sample), `globals` (ids it references) |
| `divi_export_page` | A page as a Divi Portability JSON (`portability`) plus a short outline: to copy a page or study it in full |
| `divi_find_icon` | Search Divi + Font Awesome icons by English keywords; each result's `value` goes straight into the tree |
| `divi_build_layout` | Build a tree into markup + Portability export and validate it; changes nothing |
| `divi_validate_layout` | Validate a tree (or markup / export): `{ok, report: {errors, warnings, info}}`; changes nothing |
| `divi_create_page` | New page, a **draft** unless `status` says otherwise; optionally applies a layout in the same call; returns `post_id`, `view`, `edit_divi` |
| `divi_apply_layout` | Replace a page's Divi design with a layout. `dry_run=true` previews; published pages need `confirm_published=true`; backs up first |
| `divi_merge_globals` | Add global colors/variables by id (existing values kept unless `overwrite`), `dry_run` to preview |
| `divi_set_page_css` | Replace a page's custom CSS (backed up first) |
| `divi_list_backups` / `divi_restore_backup` | Every Divi change is backed up first; restore by `backup_id` |
| `divi_list_theme_builder_templates`, `divi_create_theme_builder_template`, `divi_set_theme_builder_layout`, `divi_delete_theme_builder_template` | Theme Builder headers and footers |

WordPress tools: `site_info`, `list_posts` (default post type page), `get_post`, `create_post`,
`update_post`, `trash_post` / `restore_post` (deletes are trash-only), `search`, `list_media` /
`upload_media` / `update_media`, menus (`list_menus`, `get_menu`, `update_menu_items`), terms,
comments, options, users. Do not edit a Divi page's content with `update_post`; use the Divi tools.

| Resource | Content |
|---|---|
| `divi://guide` | This guide |
| `divi://attributes` | Attribute system: breakpoints, states, spacing, background, border, font, globals, rows/columns, icons |
| `divi://modules/index` | Every module of the installed Divi version |
| `divi://modules/{name}` | One module (name without `divi/`, e.g. `divi://modules/group`): elements, every Divi 5 setting path with value type/options, defaults. Its "Divi 5 settings" table is authoritative |
| `divi://schema/layout` | JSON Schema of the layout tree (every node, pattern, argument and value helper) |

## 2. Build by composition (default style)

Build cards and content blocks from **section → row → column → group → text / icon /
image / link**, not from the all-in-one modules. Blurb, Testimonial, Team Member (person),
Number Counter, Button and Call To Action lock their inner structure, so designs fight them.
The patterns below are built from the composition modules and are ready to use.

| Instead of | Use |
|---|---|
| blurb | `icon` or `image` + `text`, with the **column** styled as the card: patterns `icon_card`, `media_card` |
| testimonial | quote icon + `text` + author group: pattern `testimonial_card` |
| team member / person | avatar beside name + role in a 2-track grid: pattern `person_card` |
| button | `link` styled as a pill (16px/30px padding), icon optional: pattern `pill_link` (or a `link` node with your own attrs) |
| heading module | `text` with `<h1>`–`<h3>` styled through `headingFont`: pattern `heading_text` |
| number counter | pattern `stat_card` (static big number + label) |
| call to action | a row or column styled as the card (`{"$card": ...}`) holding `heading_text` + `body_text` + `pill_link` (example 3) |
| repeated card rows | `grid_row` with `cols: 3, tablet: 2, phone: 1`, or a wrapping `row` of `1_4` columns |

What real imports showed (the site owner reviewed generated pages):

- Composed pages (group/text/icon/link) imported with good structure and their styles applied.
- Pages using blurb, team member and testimonial modules came out under-styled: those modules
  expose few style hooks, so the result looked basic.
- Button modules came out thin (default padding is tiny). Use `pill_link`, which pads 16px/30px.
- A CTA button had the same color as its section, so it was invisible. Every clickable element
  needs a background that contrasts with its container, and text that contrasts with that
  background. The validator warns about both.
- Hero buttons "disappeared" on hover: their hover background (and the outline button's hover
  border) was the brand color of the section behind them. Hover states must contrast with the
  container too. On dark or brand-colored backgrounds pass `"on_dark": true` to `pill_link`
  (white hover with the button color as text). The validator checks hover backgrounds, borders
  and text.
- A link's icon inherits the link text color, normal and hover. Never set an icon color
  (`icon.advanced.color`, `pill_link` `icon_color` / `icon_hover_color`) unless the icon must
  differ from the text: a fixed icon color does not follow the text on hover.
- The Call To Action module is not part of this workflow: build CTAs as a styled row or column.

Groups nest, so any structure is reachable. The column is usually the card (background,
border, radius, padding, hover shadow via `{"$card": ...}`), and its layout switches between
flex and grid. Use the all-in-one modules only when the user asks for them, or for what
composition can't do: contact form, toggles / accordion, sliders, maps, menus, blog feeds.

## 3. Workflow

### 1. Understand the request
Find out (from the request, or ask briefly when it matters): purpose and sections, language
of the copy, brand colors/fonts, real images or placeholders, and whether to match an
existing page. Write real, specific copy in the user's language, never lorem ipsum.

### 2. Read the site
1. `divi_status`: Divi must be active; note `divi_version` and `can`. Without Divi only
   `divi_build_layout`, `divi_validate_layout` and `divi_find_icon` are available (and this
   knowledge), so you can still plan and check a layout.
2. `divi_get_globals`: reuse the site's real global colors and variables in the tree
   (`{"$color_var": "gcid-..."}`, `{"$content_var": "gvid-..."}`). Check which built-in color the
   site actually uses as its accent: often `gcid-secondary-color`, not `gcid-primary-color`.
3. Find a page that looks like what you are building (`list_posts`, `search`) and study it:
   `divi_inspect_page` with `mode: "tree"` for the outline (skip sections tagged `[HIDDEN]`, they
   are hidden on every device), then `mode: "section"` with `n` for the full attributes of a
   representative section, `mode: "paths"` with `block: "divi/link"` for the button style, and
   `mode: "globals"`. `divi_export_page` gives the whole page when you need it.
4. When pages or exports of the site disagree (row width, padding, `weight` vs
   `weightFineTune`), follow the most recent one, or the one closest to what you are building,
   and tell the user which one you followed.

### 3. Build the tree
Write the tree (section 4), starting from the examples in section 5. Set the `theme` tokens
from what you found on the site. Find icon codes with `divi_find_icon`.

### 4. Validate
`divi_validate_layout` with `layout: <tree>`. Fix every error (the page would break) and read
every warning: an unknown attribute key is usually a typo in an element name, a contrast
warning means a button or text is invisible somewhere, and a global id that is neither on the
site nor in `global_colors` would render blank. A tree mistake fails with `invalid_layout` and
a `path` like `blocks[0].section.children[1].row.children[0]`; fix that node.

### 5. Create the draft, preview, apply
New designs always go into a **new draft page**:
1. `divi_create_page` with `title` (and `slug`): a draft, returns `post_id`, `view`, `edit_divi`.
2. `divi_apply_layout` with `post_id`, `layout` and `dry_run: true`: check the report, the globals
   it would add (`globals_preview`) and `images_to_upload`.
3. The same call without `dry_run`. Images hosted elsewhere are imported into the media library
   (`upload_images`, default true; pass false to keep the URLs). New global ids are added;
   existing ids keep their values unless `overwrite_globals: true`.

(`divi_create_page` also accepts `layout` directly and applies it in the same call; an invalid
layout then creates nothing.)

### 6. Hand over
Give the user the `view` and `edit_divi` links, say what to replace (placeholder images,
links, phone numbers), and that the page is a draft until they publish it.

### Changing an existing page
Only when the user asks. `divi_apply_layout` replaces the whole design of the page (back up
first is automatic; the result has `backup_id`). A published, scheduled or private page needs
`confirm_published: true`: ask the user first, the change is visible at once. To change one
section: build the new section with `divi_build_layout`, replace the old section's markup in
the page markup (`divi_export_page` → the value inside `portability.data`; sections are the
`<!-- wp:divi/section ... -->` … `<!-- /wp:divi/section -->` blocks inside
`divi/placeholder`), and pass the whole result as `markup` to `divi_apply_layout` (dry run
first). Never hand-edit attribute JSON inside the markup; rebuild the section instead. The
safest way is to do this on a draft copy first (`divi_create_page`, then `divi_apply_layout`
with the export as `portability`) and let the user compare.

### Headers and footers (Theme Builder)
Build them like any layout (one section; a header usually needs the `divi/menu` module, see
`divi://modules/menu`). Then `divi_create_theme_builder_template` (a new, unassigned template
that applies nowhere) → `divi_set_theme_builder_layout` with `template_id`, `layout_type`
(`header` or `footer` only) and `layout`. The user assigns it in Divi > Theme Builder.
The Default Website Template changes the whole site: touch it only after the user explicitly
agrees, with `confirm_default_template: true`.

## 4. The layout tree

### Top level

```
{
  "blocks": [ {"section": {...}}, ... ],      required: top-level nodes are sections
  "theme": { ...tokens for the patterns... }, optional (section "Theme tokens")
  "global_colors": [["gcid-x", {"color": "#hex", "status": "active", "label": "X"}]],
  "global_variables": [{"id": "gvid-x", "label": "X", "value": "12px", "status": "active", "type": "numbers"}],
  "custom_css": "page CSS",
  "builder_version": "5.19.1"                 leave it out: the site's Divi version is used
}
```

Only ship `global_colors` / `global_variables` for ids that are **new**. Reference the site's
existing ids directly; the five built-in colors (`gcid-primary-color`, `gcid-secondary-color`,
`gcid-heading-color`, `gcid-body-color`, `gcid-link-color`) exist on every site.

### Nodes

A node is one of three shapes:

- **Helper**: `{"<helper>": {...args, "children": [nodes]}}`, e.g. `{"text": {"html": "<p>Hi</p>"}}`.
- **Pattern**: `{"pattern": "<name>", "args": {...}}`, e.g.
  `{"pattern": "pill_link", "args": {"label": "Book now", "url": "#contact"}}`.
- **Raw block** (escape hatch for any module): `{"block": "divi/<name>", "attrs": {...},
  "children": [...]}`. Leave `children` out for a self-closing module. Read
  `divi://modules/<name>` first.

Every helper and pattern takes `attrs`: raw Divi attributes deep-merged over what the helper
writes (objects merge key by key, later wins, lists are replaced).

Structure helpers:

| Helper | Args | Notes |
|---|---|---|
| `section` | `css_id`, `kind`, `attrs`, `children` | Children: `row` / `grid_row`. `css_id` for anchors (`"#contacto"`); `kind: "fullwidth"` only for fullwidth modules |
| `row` | `width`, `max_width`, `gap`, `wrap_on`, `attrs`, `children` | Flex row of columns. Widths must fill the row (sum 1, or 2, 3 for several lines). Stacks on phone by default |
| `grid_row` | `cols` (3), `tablet` (2), `phone` (1), `gap` ("20px"), `template`, `width`, `max_width`, `attrs`, `children` | CSS grid: any number of columns/cards flow into `cols` equal tracks; `template: "2fr 1fr"` for manual tracks |
| `column` | `width` ("1_1"), `tablet`, `phone`, `attrs`, `children` | Width like `1_2`, `1_3`, `2_3`, `1_4`, `3_5`. Leave `width` out in a `grid_row` |
| `group` | `direction`, `gap`, `align`, `justify`, `wrap`, `width`, `layout`, `tablet`, `phone`, `attrs`, `children` | Flex container (column by default). `direction: "row"` puts items side by side; `tablet`/`phone` override the layout, e.g. `{"flexDirection": "column"}` |

Composition modules:

| Helper | Args |
|---|---|
| `text` | `html` (required: `<p>`, `<h2>`, `<ul>`, `<a>`...), `attrs` |
| `image` | `src` (required), `alt`, `title`, `url`, `attachment_id`, `width`, `height`, `attrs` |
| `link` | `label` (required), `url`, `icon` (`{"$fa": ...}`), `icon_position` (`after` / `before`), `new_window`, `attrs` |
| `icon` | `icon_value` (required, `{"$fa": ...}` or `{"$divi_icon": ...}`), `size`, `color`, `url`, `attrs` |
| `breadcrumbs` | `home_text`, `separator`, `home_url`, `color`, `link_color`, `hover_color`, `size`, `attrs` |

All-in-one modules (only when asked or needed): `heading` (`title`, `level`), `button`
(`label`, `url`, `new_window`), `blurb`, `cta`, `testimonial`, `team_member`, `number_counter`
(`number`, `title`, `percent_sign`), `code` (`html`), `divider` (`color`, `weight`), `toggle`
(`title`, `body_html`, `open`, `level`), `accordion` with `accordion_item` children (`title`,
`body_html`, `open`), `contact_form` (`email`, `title`, `submit`, `success`, `unique_key`) with
`contact_field` children (`field_id`, `label`, `type`, `required`, `half`), `timeline` with
`timeline_item` (`date`, `title`, `body_html`, `icon`), `icon_list` (`color`, `size`) with
`icon_list_item` (`label`, `icon`), `social_follow` with `social_network` (`network`, `url`,
`label`). Child modules only inside their parent. `divi://schema/layout` has every argument.

### Patterns

All patterns take `theme` (tokens for this pattern only, merged over the top-level `theme`).

| Pattern | Required args | Optional args | Returns |
|---|---|---|---|
| `eyebrow` | `label` | `attrs` | small uppercase label (text) |
| `heading_text` | `title` | `level` ("h2"), `size`, `color`, `align`, `attrs` | heading as a text module |
| `body_text` | `html` (plain text is wrapped in `<p>`) | `size`, `color`, `align`, `max_width`, `attrs` | paragraph text |
| `section_header` | `eyebrow_label` ("" for none), `title` | `intro`, `level`, `align` ("left"), `max_width` ("620px") | eyebrow + heading (+ intro), spread into the parent |
| `icon_badge` | `icon_value` | `box_size`, `icon_size`, `shape` (circle / soft / square), `attrs` | icon in a tinted shape |
| `number_badge` | `number` | `box_size`, `attrs` | "01" in a tinted circle |
| `pill_link` | `label`, `url` | `icon_value` (default arrow; `false` = none), `variant` (primary / outline / text), `on_dark`, `bg`, `fg`, `hover_bg`, `hover_fg`, `icon_color`, `icon_hover_color`, `new_window`, `attrs` | link styled as a pill button |
| `icon_card` | `icon_value`, `title` | `body`, `link_label`, `link_url`, `badge_bg`, `badge_fg` | **card** (replaces blurb) |
| `media_card` | `image_src`, `title` | `body`, `alt`, `link_label`, `link_url` | **card**, image on top |
| `person_card` | `name`, `role` | `photo`, `bio`, `icon_value` | **card** (replaces team member) |
| `testimonial_card` | `quote`, `name` | `role`, `photo` | **card** (replaces testimonial) |
| `numbered_card` | `number`, `body` | `icon_value`, `title` | **card**, big number on top |
| `step_card` | `number`, `title` | `body`, `link_label`, `link_url` | **card**, horizontal step |
| `stat_card` | `number`, `label` | | **card**, big number + label |

**Cards return a column.** Put them directly in a `row` (with `width`, `tablet`, `phone`, e.g.
`"width": "1_3", "tablet": "1_1"`) or in a `grid_row` (no width), never inside a column or group.
Every card also takes `width`, `tablet`, `phone`, `attrs`.

### Value helpers

Value helpers write Divi's attribute shapes for you. They work anywhere inside `attrs` and
args. Their keys start with `$`; keys without `$` are always plain Divi data, so attributes
copied from `divi_inspect_page` can be pasted as they are.

| Helper | Example | Produces |
|---|---|---|
| `$rv` | `{"$rv": {"desktop": "16px", "phone": "14px", "hover": "18px"}}` | `{"desktop": {"value": "16px", "hover": "18px"}, "phone": {"value": "14px"}}`: the breakpoint/state wrapper most attributes need |
| `$box` | `{"$box": ["96px", "24px"]}` (1–4 values, CSS order) | spacing box with Divi's sync flags |
| `$radius` | `{"$radius": "16px"}` or `{"$radius": ["4px", "4px", "22px", "22px"]}` (TL, TR, BR, BL) | border radius |
| `$color_var` | `{"$color_var": "gcid-secondary-color"}`, optional sibling `"opacity": 50` | a global color reference |
| `$content_var` | `{"$content_var": "gvid-h2-size"}` | a global variable reference (size, text, font...) |
| `$fa` | `{"$fa": "&#xf0e0;"}`, optional sibling `"weight": "400"` (900 = solid, default) | Font Awesome icon |
| `$divi_icon` | `{"$divi_icon": "&#xe00b;"}` | Divi font icon |
| `$font` | `{"$font": {"element": "content", "group": "headingFont", "sub": "h2", "desktop": {"size": "40px"}, "phone": {"size": "28px"}}}` | font settings: `group` = `font` (titles, links, buttons), `bodyFont` (+ `sub`: body, link, ul, ol, quote) or `headingFont` (+ `sub`: h1–h6) |
| `$module_deco` | `{"$module_deco": {"background": {"$rv": {"desktop": {"color": "#f5f7fa"}}}}}` | `{"module": {"decoration": {...}}}` for any decoration group: background, spacing, sizing, layout, border, boxShadow, position, ... |
| `$flex` | `{"$flex": {"direction": "row", "gap": "12px", "align": "center", "wrap": true}}` | a flex `layout` value (for `group.layout`) |
| `$grid` | `{"$grid": {"columns": 3, "gap": "20px"}}` or `{"$grid": {"template": "42px 1fr"}}` | a grid `layout` value |
| `$card` | `{"$card": {"background": "#fff", "border_color": "#e3e6ef", "radius": "16px", "padding": "24px", "hover_shadow": {"vertical": "10px", "blur": "28px", "color": "rgba(0,0,0,.1)"}}}` | card look for a column, group or row |
| `$merge` | `{"$merge": [{"$card": {...}}, {"$module_deco": {...}}]}` | deep-merges objects; use it to combine several helpers as one `attrs` |
| `$literal` | `{"$literal": X}` | X untouched (only to pass a `$` key through) |

Common attrs, written with the helpers (combine several in one `attrs` with `$merge`, as the
examples do):

```
admin label:    {"module": {"meta": {"adminLabel": {"$rv": {"desktop": "Hero"}}}}}
section padding:{"$module_deco": {"spacing": {"$rv": {"desktop": {"padding": {"$box": ["96px", "24px"]}},
                                                     "phone":   {"padding": {"$box": ["64px", "20px"]}}}}}}
space children: {"$module_deco": {"layout": {"$rv": {"desktop": {"rowGap": "40px"}}}}}
background:     {"$module_deco": {"background": {"$rv": {"desktop": {"color": {"$color_var": "gcid-primary-color"}}}}}}
text color:     {"$font": {"element": "content", "group": "bodyFont", "sub": "body", "desktop": {"color": "#ffffff"}}}
link hover:     {"$font": {"element": "content", "desktop": {"color": "#0b1f3a"}, "hover": {"color": "#e4572e"}}}
hide on phone:  {"module": {"decoration": {"disabledOn": {"desktop": {"value": "off"}, "tablet": {"value": "off"}, "phone": {"value": "on"}}}}}
clickable card: {"module": {"advanced": {"link": {"$rv": {"desktop": {"url": "/servicios/", "target": "off"}}}}}}
custom CSS:     {"css": {"$rv": {"desktop": {"mainElement": "transition: all .3s;"}, "hover": {"mainElement": "transform: translateY(-4px);"}}}}
```

### Theme tokens

`theme` sets the look of every pattern. Values are colors (hex, rgba, or
`{"$color_var": ...}`), sizes or font names. Set them from the site (`divi_get_globals`,
`divi_inspect_page`); the defaults are a generic look.

| Token | Default | Token | Default |
|---|---|---|---|
| `heading` | `#161834` | `heading_font` | site default |
| `body` | `#3a4060` | `body_font` | site default |
| `muted` | `#6e7490` | `eyebrow_font` | site default |
| `accent` (eyebrows, text links) | `#0043ff` | `heading_weight` / `eyebrow_weight` | `700` |
| `border` | `#e3e6ef` | `h1_size` | `clamp(2.5rem, 5vw + 1rem, 4.125rem)` |
| `card_bg` | `#ffffff` | `h2_size` / `h3_size` | `38px` / `19px` |
| `badge_bg` / `badge_fg` | `#e9edfa` / `#2f4ba3` | `body_size` | `16px` |
| `button_bg` / `button_fg` | `#ffb600` / `#161834` | `card_radius` | `["4px", "4px", "22px", "22px"]` |
| `button_hover_bg` / `button_hover_fg` | `#2f4ba3` / `#ffffff` | `card_padding` / `card_gap` | `24px` / `20px` |
| `card_top_border` | none | `card_top_border_width` | `4px` |
| `card_hover_css` | default hover shadow | `pill_radius` | `50px` |

### Nesting rules

- `blocks` holds sections; a section holds `row` / `grid_row`; a row holds columns or cards;
  columns and groups hold modules, groups and the non-card patterns. Nested rows may go inside
  columns.
- Child modules only inside their parent (`accordion_item` in `accordion`, `contact_field` in
  `contact_form`, `social_network` in `social_follow`...). Fullwidth modules (raw blocks such as
  `divi/fullwidth-header`) go directly in a section with `"kind": "fullwidth"`.
- `section_header` spreads 1–2 modules into its parent; the other patterns produce one.

## 5. Complete examples

Each example is a complete, valid tree: pass it as `layout` to `divi_validate_layout`,
`divi_apply_layout` or `divi_create_page`. The `gcid-brand-*` colors stand for the site's own
brand colors: on a real site take the ids from `divi_get_globals` and only ship
`global_colors` for ids that are new. Images are placeholders.

### Example 1: hero with pill links on a dark background

The buttons sit on navy, so both pills use `on_dark` (white hover). The outline pill's hover
text is set to the navy so it reads on the white hover fill.

```json
{
  "global_colors": [
    ["gcid-brand-navy", {"color": "#0b1f3a", "status": "active", "label": "Brand navy"}]
  ],
  "theme": {
    "heading": "#0b1f3a",
    "body": "#3a4060",
    "accent": {"$color_var": "gcid-secondary-color"},
    "button_bg": "#ffb600",
    "button_fg": "#0b1f3a",
    "heading_font": "Inter",
    "body_font": "Inter"
  },
  "blocks": [
    {"section": {
      "css_id": "inicio",
      "attrs": {"$merge": [
        {"module": {"meta": {"adminLabel": {"$rv": {"desktop": "Hero"}}}}},
        {"$module_deco": {
          "background": {"$rv": {"desktop": {
            "color": {"$color_var": "gcid-brand-navy"},
            "image": {"url": "https://placehold.co/1920x1080/0b1f3a/ffffff.png?text=Hero", "size": "cover", "position": "center center"},
            "gradient": {"enabled": "on", "type": "linear", "direction": "90deg", "overlaysImage": "on",
                         "stops": [{"position": "0", "color": "rgba(11,31,58,0.95)"}, {"position": "100", "color": "rgba(11,31,58,0.55)"}]}
          }}},
          "spacing": {"$rv": {
            "desktop": {"padding": {"$box": ["150px", "24px", "130px", "24px"]}},
            "phone": {"padding": {"$box": ["96px", "20px", "80px", "20px"]}}
          }}
        }}
      ]},
      "children": [
        {"row": {"width": "90%", "max_width": "1200px", "children": [
          {"column": {
            "attrs": {"$module_deco": {"layout": {"$rv": {"desktop": {"rowGap": "20px"}}}}},
            "children": [
              {"pattern": "eyebrow", "args": {"label": "Clínica dental en Valencia",
                "attrs": {"$font": {"element": "content", "group": "bodyFont", "sub": "body", "desktop": {"color": "#ffd166"}}}}},
              {"pattern": "heading_text", "args": {"title": "Sonríe sin miedo al dentista", "level": "h1", "color": "#ffffff",
                "attrs": {"$font": {"element": "content", "group": "headingFont", "sub": "h1", "phone": {"size": "36px"}}}}},
              {"pattern": "body_text", "args": {"html": "Primera visita y radiografía gratis. Financiación a 12 meses sin intereses.",
                "color": "rgba(255,255,255,0.85)", "size": "19px", "max_width": "620px"}},
              {"group": {"direction": "row", "gap": "12px", "wrap": true, "children": [
                {"pattern": "pill_link", "args": {"label": "Pide tu cita", "url": "#contacto", "on_dark": true}},
                {"pattern": "pill_link", "args": {"label": "Ver tratamientos", "url": "/tratamientos/", "variant": "outline", "on_dark": true, "hover_fg": "#0b1f3a"}}
              ]}}
            ]
          }}
        ]}}
      ]
    }}
  ]
}
```

### Example 2: three icon cards in a grid row

A centered section header in a one-column row, then a `grid_row` of `icon_card` (3 tracks on
desktop, 2 on tablet, 1 on phone). The theme uses the built-in global colors.

```json
{
  "theme": {
    "heading": {"$color_var": "gcid-heading-color"},
    "body": {"$color_var": "gcid-body-color"},
    "accent": {"$color_var": "gcid-secondary-color"},
    "badge_bg": "#e9edfa",
    "badge_fg": "#2f4ba3",
    "card_radius": "16px",
    "card_padding": "28px"
  },
  "blocks": [
    {"section": {
      "css_id": "tratamientos",
      "attrs": {"$merge": [
        {"module": {"meta": {"adminLabel": {"$rv": {"desktop": "Treatments"}}}}},
        {"$module_deco": {
          "background": {"$rv": {"desktop": {"color": "#f5f7fa"}}},
          "spacing": {"$rv": {
            "desktop": {"padding": {"$box": ["96px", "24px"]}},
            "phone": {"padding": {"$box": ["64px", "20px"]}}
          }},
          "layout": {"$rv": {"desktop": {"rowGap": "40px"}}}
        }}
      ]},
      "children": [
        {"row": {"width": "90%", "max_width": "1200px", "children": [
          {"column": {"children": [
            {"pattern": "section_header", "args": {
              "eyebrow_label": "Tratamientos",
              "title": "Todo lo que tu sonrisa necesita",
              "intro": "Un solo equipo para revisiones, ortodoncia e implantes.",
              "align": "center"
            }}
          ]}}
        ]}},
        {"grid_row": {"cols": 3, "tablet": 2, "phone": 1, "gap": "20px", "width": "90%", "max_width": "1200px", "children": [
          {"pattern": "icon_card", "args": {"icon_value": {"$fa": "&#xf5c9;"}, "title": "Odontología general",
            "body": "Revisiones, limpiezas y empastes sin esperas.", "link_label": "Más información", "link_url": "/odontologia-general/"}},
          {"pattern": "icon_card", "args": {"icon_value": {"$fa": "&#xf4be;"}, "title": "Ortodoncia invisible",
            "body": "Alineadores transparentes con seguimiento mensual.", "link_label": "Más información", "link_url": "/ortodoncia/"}},
          {"pattern": "icon_card", "args": {"icon_value": {"$fa": "&#xf0f1;"}, "title": "Implantes",
            "body": "Implantes de titanio con garantía de por vida.", "link_label": "Más información", "link_url": "/implantes/"}}
        ]}}
      ]
    }}
  ]
}
```

### Example 3: testimonials and a CTA row

Three `testimonial_card` in a flex row (`1_3` each, full width on tablet), then the CTA: the
**row** is the card (accent background, asymmetric radius) with the heading and text in a
`2_3` column and the pill in a `1_3` column. The pill is white with accent text so it contrasts
with the accent row, and its hover turns dark, which still contrasts with the row.

```json
{
  "global_colors": [
    ["gcid-brand-accent", {"color": "#e4572e", "status": "active", "label": "Brand accent"}]
  ],
  "theme": {
    "heading": "#161834",
    "body": "#3a4060",
    "accent": {"$color_var": "gcid-brand-accent"},
    "badge_bg": "#fdeee9",
    "badge_fg": {"$color_var": "gcid-brand-accent"}
  },
  "blocks": [
    {"section": {
      "css_id": "opiniones",
      "attrs": {"$merge": [
        {"module": {"meta": {"adminLabel": {"$rv": {"desktop": "Testimonials and CTA"}}}}},
        {"$module_deco": {
          "spacing": {"$rv": {
            "desktop": {"padding": {"$box": ["96px", "24px"]}},
            "phone": {"padding": {"$box": ["64px", "20px"]}}
          }},
          "layout": {"$rv": {"desktop": {"rowGap": "40px"}}}
        }}
      ]},
      "children": [
        {"row": {"width": "90%", "max_width": "1200px", "children": [
          {"column": {"children": [
            {"pattern": "section_header", "args": {"eyebrow_label": "Opiniones", "title": "Lo que dicen nuestros pacientes", "align": "center"}}
          ]}}
        ]}},
        {"row": {"width": "90%", "max_width": "1200px", "gap": "20px", "children": [
          {"pattern": "testimonial_card", "args": {"quote": "Me quitaron el miedo al dentista en la primera visita.", "name": "Lucía Martín", "role": "Paciente desde 2021", "width": "1_3", "tablet": "1_1"}},
          {"pattern": "testimonial_card", "args": {"quote": "Presupuesto claro y sin sorpresas. El implante quedó perfecto.", "name": "Jorge Pérez", "role": "Implante dental", "width": "1_3", "tablet": "1_1"}},
          {"pattern": "testimonial_card", "args": {"quote": "Mis hijos piden volver. Eso lo dice todo.", "name": "Ana Ruiz", "role": "Odontopediatría", "width": "1_3", "tablet": "1_1"}}
        ]}},
        {"row": {"width": "90%", "max_width": "1200px", "gap": "24px",
          "attrs": {"$merge": [
            {"$card": {"background": {"$color_var": "gcid-brand-accent"}, "radius": ["6px", "6px", "32px", "32px"], "padding": "48px"}},
            {"$module_deco": {"layout": {"$rv": {"desktop": {"alignItems": "center"}}}}}
          ]},
          "children": [
            {"column": {"width": "2_3", "tablet": "1_1", "children": [
              {"pattern": "heading_text", "args": {"title": "¿Hablamos de tu sonrisa?", "level": "h2", "color": "#ffffff"}},
              {"pattern": "body_text", "args": {"html": "Te llamamos hoy y te damos cita esta misma semana.", "color": "rgba(255,255,255,0.9)"}}
            ]}},
            {"column": {"width": "1_3", "tablet": "1_1",
              "attrs": {"$module_deco": {"layout": {"$rv": {
                "desktop": {"justifyContent": "center", "alignItems": "flex-end"},
                "tablet": {"alignItems": "flex-start"}
              }}}},
              "children": [
                {"pattern": "pill_link", "args": {"label": "Pedir cita", "url": "/contacto/",
                  "bg": "#ffffff", "fg": {"$color_var": "gcid-brand-accent"},
                  "hover_bg": "#161834", "hover_fg": "#ffffff"}}
              ]
            }}
          ]
        }}
      ]
    }}
  ]
}
```

A full page is several sections in one `blocks` list (hero, services, process, stats,
testimonials, FAQ with `toggle` modules, CTA), with one `theme` and the union of the
`global_colors`.

## 6. Matching an existing site

The pattern defaults are generic. To match a site:

1. Read one real card, header and button with `divi_inspect_page` (`mode: "section"`, and
   `mode: "paths"` with `block: "divi/link"` or `"divi/button"`).
2. Set the `theme` tokens from them: `card_radius`, `card_padding`, `card_top_border`,
   `card_hover_css`, `h2_size` (can be `{"$content_var": "gvid-..."}`), `heading_weight`,
   `heading_font`, `eyebrow_font`, `button_bg` / `button_fg` / hover colors, `pill_radius`.
3. Reuse the site's row width and max width (`row.width`, `row.max_width`) and section padding.
4. If a pattern still can't reproduce a component, copy the component's attributes from the
   inspect output into a `column` / `group` / `link` node's `attrs`, or a raw `block`. That is
   expected and better than approximating.
5. When the site's pages disagree, follow the most recent one and tell the user which.

## 7. Rules that keep pages working

- **Responsive**: set desktop, then only what changes on tablet/phone (values cascade down).
  Multi-column rows stack on phone by default; shrink big headings and section padding on phone.
- **Global colors and variables**: reference them with `$color_var` / `$content_var`. Never
  reuse an existing id with a different value. New ids need a fresh slug (`gcid-brand-navy`).
- **Contrast**: every clickable element (pill, link, clickable card) needs a background that
  differs from its container, normal and hover, and text that differs from its own background.
- **Images**: use the site's media (`list_media`) or `upload_media` when you have the files;
  placeholders are fine for drafts. Always give images a meaningful `alt`. Hot-linked images
  are imported into the media library on apply unless `upload_images: false`.
- **Site-specific references**: dynamic content with a `post_id` and `divi/global-layout`
  only work on the site they came from. Use plain URLs.
- **HTML**: `text.html` is rich HTML. Without the `unfiltered_html` capability
  (`divi_status` → `can`) WordPress may filter scripts and unusual tags; keep to plain markup and
  use the `code` module only when needed.
- **Accessibility / SEO**: one `h1` per page, headings in order, descriptive link text.
- **Anchors**: `"css_id": "contacto"` on the target section, `"url": "#contacto"` on the link.
- **Unclear module**: if a module's attribute shape isn't clear from `divi://modules/<name>`
  and no page on the site uses it, say so and suggest the user add one in the Visual Builder
  so you can inspect it; that is quicker than guessing wrong.

## 8. Safety rules

- **New designs go into a new draft page** (`divi_create_page`). Change an existing page only
  when the user asks.
- **`confirm_published: true`** is required to change a published, scheduled or private page
  (`divi_apply_layout`, `divi_set_page_css`, `divi_restore_backup`). Ask the user before you set
  it: the change is visible at once.
- **`confirm_default_template: true`** is required to change the Default Website Template
  (`divi_set_theme_builder_layout`, `divi_restore_backup`): it changes the header/footer of the
  whole site. Ask first; prefer a new template (`divi_create_theme_builder_template`).
- **Theme Builder**: only `header` and `footer` layouts can be set; deleting a template backs up
  its layouts, and the Default Website Template cannot be deleted.
- **Backups**: page content, page CSS, globals and Theme Builder layouts are backed up before
  every change. `divi_list_backups` lists them; `divi_restore_backup` with `backup_id` restores
  one (after backing up the current state). Tell the user the `backup_id` when you change an
  existing page.
- **Deletes are trash-only**: `trash_post` moves to the trash, `restore_post` brings it back.
  Nothing is deleted permanently.
- **Globals**: `divi_apply_layout` never overwrites an existing global value unless you pass
  `overwrite_globals: true`; do that only when the user asked to change a site color.

## 9. When something fails

| Error | Meaning |
|---|---|
| `invalid_layout` (`data.path`) | The tree is malformed at that path: unknown helper or argument, wrong nesting, a card inside a column |
| `validation_failed` (`data.report`) | The built layout has validation errors; nothing was changed |
| `confirmation_required` | Published page or Default Website Template: ask the user, then retry with the confirm flag |
| `forbidden` | The user's role can't do this (see `divi_status` → `can`) |
| `divi_not_active` | Divi 5 is not the active theme |
| `not_found` | Wrong `post_id`, `template_id` or `backup_id` |

The info line "image URL(s) are not embedded" is expected: `divi_apply_layout` imports those
images into the media library (`upload_images`); its mention of `write_layout` refers to the
offline scripts and does not apply here.

Validator warnings to take seriously: `unknown attribute` (typo in an element or key name),
contrast warnings on buttons/links (normal or hover), a global id that is missing everywhere,
and `unprefixed value helper` (`{"fa": ...}` written instead of `{"$fa": ...}`).
