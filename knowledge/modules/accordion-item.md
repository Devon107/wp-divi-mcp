# divi/accordion-item — Accordion Item

- Category: `child-module`
- Divi 4 shortcode: `et_pb_accordion_item`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `closedToggleIcon` — decoration: icon
- `openToggle` — decoration: background, font
- `closedToggle` — decoration: background, font
- `content` — innerContent; decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `content.innerContent` | Body | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `title` → `title.innerContent.*`
- `open_toggle_background_color` → `openToggle.decoration.background.*.color`
- `open_toggle_text_color` → `openToggle.decoration.font.font.*.color`
- `icon_color` → `closedToggleIcon.decoration.icon.*.color`
- `toggle_icon` → `closedToggleIcon.decoration.icon.*`
- `use_icon_font_size` → `closedToggleIcon.decoration.icon.*.useSize`
- `icon_font_size` → `closedToggleIcon.decoration.icon.*.size`
- `closed_toggle_background_color` → `closedToggle.decoration.background.*.color`
- `closed_title_font_size` → `closedToggle.decoration.font.font.*.size`
- `closed_toggle_text_color` → `closedToggle.decoration.font.font.*.color`
- `title_font_size` → `title.decoration.font.font.*.size`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `title_text_align` → `title.decoration.font.font.*.textAlign`
- `title_text_color` → `title.decoration.font.font.*.color`
- `open` → `module.advanced.open.*`
- `toggle_level` → `title.decoration.font.font.*.headingLevel`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `closed_title` → `closedToggle.decoration.font`
- `closed_toggle` → `closedToggle.decoration.font`
- `title` → `title.decoration.font`
- `toggle` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"open":{"desktop":{"value":"off"}}}},"closedToggleIcon":{"decoration":{"icon":{"desktop":{"value":{"useSize":"off"}}}}}}
```
