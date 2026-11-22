# divi/tab — Tab

- Category: `child-module`
- Divi 4 shortcode: `et_pb_tab`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, conditions, disabledOn, filters, interactions, layout, order, overflow, position, sizing, spacing, transform, transition, zIndex
- `content` — innerContent; decoration: bodyFont
- `title` — innerContent; decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.innerContent` | Body | HTML string |
| `title.innerContent` | Title | string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `title` → `title.innerContent.*`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `body_ol_position` → `content.decoration.bodyFont.ol.list.*.position`
- `body_ol_type` → `content.decoration.bodyFont.ol.list.*.type`
- `body_ol_item_indent` → `content.decoration.bodyFont.ol.list.*.itemIndent`
- `body_ul_position` → `content.decoration.bodyFont.ul.list.*.position`
- `body_ul_type` → `content.decoration.bodyFont.ul.list.*.type`
- `body_ul_item_indent` → `content.decoration.bodyFont.ul.list.*.itemIndent`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `tab` → `title.decoration.font`
