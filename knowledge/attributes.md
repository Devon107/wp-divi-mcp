# Divi 5 attribute reference

Taken from Divi 5.11 source (style declarations, conversion outlines) and real exports,
checked against Divi 5.19.1 and a 5.19 export. Use this file when you write `attrs` in a
layout tree by hand. In the tree, value helpers (`{"$rv": ...}`, `{"$box": [...]}`,
`{"$color_var": "gcid-..."}`, `{"$font": {...}}`, `{"$module_deco": {...}}`) write these shapes
for you; everything without a `$` key is passed to Divi exactly as written. The per-module
settings are in `divi://modules/{name}` (e.g. `divi://modules/link`).

## Changes for Divi 5.19

What differs from the 5.11 version of this file (catalog regenerated from the installed
Divi 5.19.1, shapes checked against a fresh 5.19 export):

- **Version stamp.** New blocks carry the site's Divi version (`divi_status` → `divi_version`,
  e.g. `5.19.1`); the tools stamp it for you when the tree has no `builder_version`. Blocks in
  a 5.19 export can still say `"builderVersion":"5.11.0"`: Divi keeps a block's version until
  the block is edited, so an old stamp in an export is normal and is not a reason to use it.
- **Modules.** 116 modules (was 115): new `divi/global-slot` (structure, no children). The
  composition modules (section, row, column, group, text, link, icon, image) and their default
  layouts did not change.
- **New settings** (see each module's reference): `divi/menu` gains the elements
  `collapsibleMenu`, `collapsibleMenuOpenIcon`, `collapsibleMenuCloseIcon` and
  `menuMobile.advanced.collapsible` / `parentLinksActive`; `divi/slider` and `divi/post-slider`
  pagination gains `style` (`"dot"` / `"swipe"`), `swipeText`, `showCounter`,
  `showSwipeLabel`, `color`; `divi/shop` gains a `pagination` element; `divi/contact-form`,
  `divi/signup` and `divi/comments` gain `module.advanced.showLabels`.
- **Confirmed in the 5.19 export** (now listed below): `divi/link` content is
  `content.innerContent` = `{text, linkUrl, linkTarget}`; link and icon modules keep the icon in
  `icon.innerContent` and its color/size in `icon.advanced.color` / `icon.advanced.size`; Divi
  writes `id` (a number), `width`, `height`, `sizes` and `srcset` into `image.innerContent`;
  a clickable column uses `module.advanced.link` = `{url, target}`; custom CSS has states too
  (`css.desktop.hover.mainElement`).
- **Envelope.** 5.19 Portability exports add top-level `canvases` (`{"local":[],"global":[]}`),
  `components` (`{"items":[],"order":[]}`), `presets` and `thumbnails`. The tools accept
  exports with or without them (pass an export as `portability`).
- **Globals.** With these tools, globals are merged by `divi_apply_layout` / `divi_create_page`
  (`sync_globals`), never overwriting an existing id unless `overwrite_globals=true`
  (section 6).

## Contents
1. Shape of every attribute
2. Breakpoints and states
3. Elements
4. Decoration groups (spacing, sizing, layout, background, border, boxShadow, font, ...)
5. Module-level `advanced` settings
6. Global colors and variables
7. Custom CSS
8. Rows and columns (flex)
9. Icons

---

## 1. Shape of every attribute

```
{ <element>: { "innerContent" | "advanced" | "decoration": { <group>: { <breakpoint>: { <state>: VALUE } } } } }
```

Example: padding on the module wrapper at desktop:

```json
{"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"80px","right":"","bottom":"80px","left":"","syncVertical":"on","syncHorizontal":"off"}}}}}}}
```

Every value is a string (`"80px"`, `"on"`, `"700"`), except arrays (gradient stops,
font `style`, HTML attributes). Booleans are `"on"` / `"off"`.

Font settings have one extra level: `decoration.font.font.<bp>.<state>`, and rich-text
modules use `decoration.bodyFont.<body|link|ul|ol|quote>.font.<bp>.<state>` and
`decoration.headingFont.<h1..h6>.font.<bp>.<state>`.

Top-level keys besides elements: `builderVersion` (the tools set it to the site's Divi
version), `modulePreset` (e.g. `["default"]`, optional), `css` (custom CSS), `locked`.

## 2. Breakpoints and states

Breakpoints: `desktop` (always present), `tablet`, `phone`. Divi 5 also supports
`phoneWide`, `tabletWide`, `widescreen`, `ultraWide` if the site enabled them in
Customizable Breakpoints, so stick to desktop/tablet/phone unless asked.

Values cascade down: tablet inherits desktop, phone inherits tablet. Only write a
smaller breakpoint when it differs.

States, inside a breakpoint: `value` (normal), `hover`, `sticky`.

```json
"background":{"desktop":{"value":{"color":"#000"},"hover":{"color":"#333"}},"phone":{"value":{"color":"#111"}}}
```

`disabledOn` (hide on a device): `{"desktop":{"value":"off"},"tablet":{"value":"on"},"phone":{"value":"on"}}`.

## 3. Elements

`module` is the wrapper of every block. Other elements are the module's parts
(`title`, `content`, `button`, `image`, `imageIcon`, `number`...). Each module's
reference (`divi://modules/{name}`) lists its elements and where its content lives
(`title.innerContent.*` and so on).

Content locations for the most used modules:

| Module | Content |
|---|---|
| divi/text | `content.innerContent` = HTML string |
| divi/link | `content.innerContent` = `{text, linkUrl, linkTarget:"off"\|"on"}`; `icon.innerContent` = icon (section 9), `icon.advanced.color` / `size` |
| divi/icon | `icon.innerContent` = icon (section 9); `icon.advanced.color`, `icon.advanced.size` |
| divi/heading | `title.innerContent` = plain string; tag via `title.decoration.font.font.*.headingLevel` |
| divi/button | `button.innerContent` = `{text, linkUrl, linkTarget:"on"}` |
| divi/image | `image.innerContent` = `{src, alt, titleText, linkUrl, linkTarget, id, width, height}`; Divi also writes `sizes` / `srcset` for media-library images (`id` is a number) |
| divi/blurb | `title.innerContent` = `{text, url}`, `content.innerContent` = HTML, `imageIcon.innerContent` = `{useIcon:"on", icon:{unicode,type,weight}}` or `{src, alt}` |
| divi/code | `content.innerContent` = raw HTML / shortcode |
| divi/toggle, divi/accordion-item | `title.innerContent` = string, `content.innerContent` = HTML |
| divi/cta | `title.innerContent`, `content.innerContent`, `button.innerContent` = `{text, linkUrl}` |
| divi/number-counter | `number.innerContent` = "820+", `title.innerContent` = string |
| divi/testimonial | `author/jobTitle.innerContent` strings, `company.innerContent` = `{text, linkUrl}`, `portrait.innerContent` = `{url}` |
| divi/team-member | `name/position.innerContent` strings, `image.innerContent` = `{url}`, `social.innerContent` = `{facebookUrl, linkedinUrl, ...}` |
| divi/social-media-follow-network | `socialNetwork.innerContent` = `{title:"facebook", link, label}` |

## 4. Decoration groups

Values below go inside `<bp>.<state>`.

### spacing
```json
{"margin":{"top":"","right":"","bottom":"20px","left":"","syncVertical":"off","syncHorizontal":"off"},
 "padding":{"top":"40px","right":"24px","bottom":"40px","left":"24px","syncVertical":"on","syncHorizontal":"on"}}
```
`{"$box": ["40px", "24px"]}` (CSS shorthand order, 1–4 values) sets the sync flags for you.
Margins accept `auto`.

### sizing
`width`, `maxWidth`, `minWidth`, `height`, `minHeight`, `maxHeight`,
`alignment` (`left|center|right`, centers the block itself), `alignSelf`,
`flexGrow`, `flexShrink`, `flexType` (column/child width in a flex parent, `"12_24"` = half),
`aspectRatio`. Grid children: `gridColumnSpan`, `gridRowSpan`, `gridColumnStart`, ...

### layout (flex/grid container: section, row, column, group)
`display` (`flex` | `grid` | `block`), `flexDirection` (`row` | `column`), `flexWrap`
(`wrap` | `nowrap`), `justifyContent`, `alignItems`, `alignContent`,
`columnGap`, `rowGap`. Grid: `gridColumnCount`, `gridTemplateColumns`,
`gridRowCount`, `gridAutoFlow`, ...

Defaults in Divi 5.11 and 5.19 (applied for any key you leave out):

| Block | Default layout |
|---|---|
| section | flex, `flexDirection: column`, `alignItems: center` |
| row | flex, `flexDirection: row`, `alignItems: stretch` |
| column | flex, `flexDirection: column`, `alignItems: stretch` |
| group | flex, `flexDirection: column`, `alignItems: flex-start` |
| text | flex column |
| link | flex row, `alignItems: center`, gaps 10px (icon before text; `row-reverse` puts it after) |

So a group only needs `rowGap` to space stacked items, and `flexDirection: "row"` to
put items side by side. Any of these can switch to `display: "grid"` (see the Grid
section below) — e.g. a card column with `gridTemplateColumns: "42px 1fr"` for an
avatar beside a name.

### Grid rows
A row in grid mode takes any number of columns and flows them into tracks:

```json
{"module":{"advanced":{"columnStructure":{"desktop":{"value":"1_6,1_6,1_6,1_6,1_6,1_6"}},
                       "flexColumnStructure":{"desktop":{"value":"css-grid-grids_5"}}},
           "decoration":{"layout":{"desktop":{"value":{"display":"grid","gridColumnWidths":"equal","gridColumnCount":"3","gridAutoColumns":"3","columnGap":"18px","rowGap":"18px"}},
                                   "tablet":{"value":{"gridColumnCount":"2","gridAutoColumns":"2"}},
                                   "phone":{"value":{"gridColumnCount":"1","gridAutoColumns":"1","gridColumnWidths":"auto"}}}}}}
```
Columns inside get `flexType: "24_24"`. `flexColumnStructure` is only the picker preset the
layout started from (`css-grid-grids_1/2/5/6/9/10` = 1/2/3/4/5/6 equal tracks,
`_3` = 3fr 1fr, `_4` = 1fr 3fr, `_7` = 1fr 2fr 1fr, `_8` = 2fr 1fr 1fr); the `layout`
values are what render. Manual tracks: `"gridColumnWidths":"manual","gridTemplateColumns":"2fr 1fr"`.
The `grid_row` node writes all of this.

A flex row can also hold more columns than one line: eight `1_4` columns with
`flexWrap: "wrap"` on desktop give two lines of four.

### background
```json
{"color":"#0b1f3a",
 "image":{"url":"https://.../hero.jpg","size":"cover","position":"center center","repeat":"no-repeat","parallax":{"enabled":"off"}},
 "gradient":{"enabled":"on","type":"linear","direction":"90deg",
             "stops":[{"position":"0","color":"rgba(0,0,0,0.7)"},{"position":"100","color":"rgba(0,0,0,0)"}],
             "overlaysImage":"on"},
 "video":{"mp4":"https://.../bg.mp4"}}
```
Radial gradient: `"type":"circular"` or `"elliptical"`, `"directionRadial":"center"`.
To turn off an inherited color on a smaller breakpoint: `"enableColor":"off"`.

### border
```json
{"radius":{"topLeft":"12px","topRight":"12px","bottomLeft":"12px","bottomRight":"12px","sync":"on"},
 "styles":{"all":{"width":"1px","color":"#e5e5e5","style":"solid"}}}
```
Per side: `styles.top|right|bottom|left` instead of `all`.

### boxShadow
```json
{"style":"preset1","horizontal":"0px","vertical":"12px","blur":"30px","spread":"-6px","color":"rgba(0,0,0,0.15)","position":"outer"}
```
`style` must be a preset name (`preset1` … `preset7`) or the shadow is ignored; the
other keys override the preset. `"position":"inner"` for inset.

### font  (under `decoration.font.font`, or `bodyFont.body.font`, `headingFont.h2.font`, ...)
`family` (e.g. `"Inter"`, or `"var(--et_global_heading_font)"` / `"var(--et_global_body_font)"`),
`weight` (`"400"`, `"600"`, `"700"` — use this), `weightFineTune` (variable-font weight that newer
builder versions write alongside or instead of `weight`; both render, `weightFineTune` wins when set), `style` (array, e.g. `["italic"]`, `["uppercase"]`, `["underline"]`),
`size`, `lineHeight`, `letterSpacing`, `color`, `textAlign` (`left|center|right|justify`),
`capitalization`, `headingLevel` (`h1`..`h6`, titles only).

Google fonts load automatically when used by family name.

### Others
- `boxShadow`, `border`, `background` and most groups also take a `hover` state (e.g. a card's
  hover shadow: `"boxShadow":{"desktop":{"value":{...},"hover":{"style":"preset1","vertical":"10px","blur":"30px","color":"rgba(0,0,0,0.10)"}}}`).
- `animation`: `{"style":"fade|slide|zoom|bounce|flip|fold|roll","direction":"top|bottom|left|right|center","duration":"600ms","delay":"0ms","intensity":{"slide":"10"}}`
- `transform`: `{"scale":{"x":"105%","y":"105%"},"translate":{"x":"0px","y":"-4px"},"rotate":{"z":"0deg"}}` (often under `hover`)
- `transition`: `{"duration":"300ms","delay":"0ms","speedCurve":"ease"}`
- `position`: `{"mode":"relative|absolute|fixed","origin":{"absolute":"top right"},"offset":{"vertical":"0px","horizontal":"0px"}}`
- `zIndex`: `"10"`
- `overflow`: `{"x":"hidden","y":"hidden"}`
- `filters`: `{"opacity":"80%","blur":"0px","saturate":"100%"}`
- `attributes`: custom HTML attributes on an element:
  `{"attributes":[{"id":"a1","name":"aria-label","value":"Main CTA","adminLabel":"","targetElement":"main"}]}`

## 5. Module-level `advanced`

- `module.advanced.htmlAttributes`: `{"id":"hero","class":"my-class other"}` — CSS ID / classes
- `module.advanced.link`: `{"url":"https://...","target":"on"}` — makes the whole module clickable
  (also columns: a clickable card column, seen in the 5.19 export with `"target":"off"`)
- `module.advanced.text.text`: `{"color":"light|dark","orientation":"left|center|right"}` — text color scheme and alignment
- `module.meta.adminLabel`: label shown in the Layers panel (helps users find things)
- `module.advanced.type` on sections: `"fullwidth"` for a fullwidth section

## 6. Global colors and variables

Reference a global color anywhere a color is accepted:

```
$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{}}})$
```
Optional `"settings":{"opacity":50}`. Built-in ids that exist on every site:
`gcid-primary-color`, `gcid-secondary-color`, `gcid-heading-color`, `gcid-body-color`,
`gcid-link-color` (they map to the Theme Customizer colors).

Reference a global variable (size, text, link...) with `"type":"content"`:

```
$variable({"type":"content","value":{"name":"gvid-abc123","settings":{}}})$
```

Ship new global colors/variables in the export envelope so they get created on import:

```json
"global_colors":[["gcid-brand-navy",{"color":"#0b1f3a","status":"active","label":"Brand Navy"}]],
"global_variables":[{"id":"gvid-h1-size","label":"H1 size","value":"clamp(36px,5vw,64px)","order":"1","status":"active","variableType":"numbers","type":"numbers"}]
```
Variable types: `numbers`, `strings`, `images`, `links`, `fonts`, `colors`.

In a layout tree they go in the top-level `global_colors` / `global_variables` keys (same
shapes). `divi_get_globals` lists the site's own ids; reference those instead of creating
new ones, and only ship ids that are new.

**What these tools do with them**: `divi_apply_layout` / `divi_create_page` (with
`sync_globals`, the default) add new ids and keep every existing id's value unless you pass
`overwrite_globals=true`; the built-in colors are never changed; the globals are backed up
first. `divi_merge_globals` does the same on its own, with `dry_run`.

**What Divi's Portability importer does with them** (manual import, Divi `GlobalData`):
entries are merged by id. New ids are added; an existing id gets the imported value
**site-wide**; ids not in the file are left alone. The built-in customizer colors are never
changed by a layout import. So: re-shipping a site's own colors with the same values is
harmless, but never reuse an existing id with a different value. Pick a fresh id for new colors.

Dynamic content uses the same syntax with a non-`gvid` name, e.g.
`$variable({"type":"content","value":{"name":"post_link_url_page","settings":{"post_id":"678"}}})$`.
Anything with a `post_id` only resolves on the site it came from; prefer plain URLs in
layouts meant for other sites.
Ids: `gcid-` / `gvid-` followed by a short unique slug.

Inside the block comment the quotes of `$variable(...)` end up escaped as
`\u0022`; the tools do that. In a layout tree write `{"$color_var": "gcid-..."}` /
`{"$content_var": "gvid-..."}` and never the `$variable(...)$` string by hand.

## 7. Custom CSS

```json
"css":{"desktop":{"value":{"mainElement":"transition: all .3s;","before":"...","after":"...","freeForm":"selector .child { ... }"},
                  "hover":{"mainElement":"transform: translateY(-3px);"}}}
```
In `freeForm`, `selector` is replaced by the module's unique class. Modules also
expose element-specific keys (e.g. `blurbTitle`, `numberCounterTitle`).

Page-wide CSS: the layout tree's top-level `custom_css` string (or `divi_set_page_css`). In a
Portability export it is `"page_settings_meta":{"_et_pb_custom_css":["..."]}`.

## 8. Rows and columns

Divi 5 rows are flex containers. The Visual Builder reads:

```json
// row
{"module":{"advanced":{"columnStructure":{"desktop":{"value":"1_3,1_3,1_3"}},
                       "flexColumnStructure":{"desktop":{"value":"equal-columns_3"}}},
           "decoration":{"layout":{"desktop":{"value":{"flexWrap":"nowrap"}},"tablet":{"value":{"flexWrap":"wrap"}},"phone":{"value":{"flexWrap":"wrap"}}}}}}
// each column
{"module":{"advanced":{"type":{"desktop":{"value":"1_3"}}},
           "decoration":{"sizing":{"desktop":{"value":{"flexType":"8_24"}},"tablet":{"value":{"flexType":"12_24"}},"phone":{"value":{"flexType":"24_24"}}}}}}
```

`flexType` is the width, usually in 24ths (fifths like `1_5`, `3_5` also work): 1/4 = `6_24`, 1/3 = `8_24`, 1/2 = `12_24`,
2/3 = `16_24`, 3/4 = `18_24`, full = `24_24`. `type` uses the classic fraction
(`1_4`, `1_3`, `1_2`, `2_3`, `3_4`, `4_4`). The `row` / `column` nodes write
both (`{"column": {"width": "1_3"}}`). Gaps between columns: row `layout.columnGap` / `rowGap`.

Row width: `sizing.width` (default ~80%) and `sizing.maxWidth` (default 1080px).
For edge-to-edge rows set `width:"100%"`, `maxWidth:"100%"`.

Nested rows: a `divi/row` may sit inside a `divi/column`.

Children of flex containers (contact fields in a form, modules in a group) take a width with
`module.decoration.sizing.<bp>.value.flexType` too: `"12_24"` = half, `"24_24"` = full. This
replaces Divi 4 options like `fullwidth_field`.

Anchor links: give the target section `{"section": {"css_id": "contacto"}}` and point a link to `"#contacto"`.

## 9. Icons

```json
{"unicode":"&#xf0e0;","type":"fa","weight":"900"}   // Font Awesome solid
{"unicode":"&#xf0e0;","type":"fa","weight":"400"}   // Font Awesome regular
{"unicode":"&#x24;","type":"divi","weight":"400"}   // Divi icon font
```
Font Awesome unicodes follow FA 5/6 free (envelope `f0e0`, phone `f095`, check `f00c`,
arrow-right `f061`, star `f005`, map-marker `f3c5`, chevron-down `f078`; newer codes like
`e05d` work too). To confirm an icon exists or find one by name, call `divi_find_icon`
(e.g. `query: "tooth smile"`): each result has a `value` ready for the tree,
`{"$fa": "&#xf5c9;", "weight": "900"}` or `{"$divi_icon": "&#xe00b;"}`.
