# divi/accordion — Accordion

- Category: `module`
- Divi 4 shortcode: `et_pb_accordion`
- Allowed children: `divi/accordion-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `closedToggleIcon` — decoration: icon
- `content` — decoration: bodyFont
- `openToggle` — decoration: background, font
- `closedToggle` — decoration: background, font

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `open_toggle_background_color` → `openToggle.decoration.background.*.color`
- `open_toggle_text_color` → `openToggle.decoration.font.font.*.color`
- `toggle_icon` → `closedToggleIcon.decoration.icon.*`
- `icon_color` → `closedToggleIcon.decoration.icon.*.color`
- `use_icon_font_size` → `closedToggleIcon.decoration.icon.*.useSize`
- `icon_font_size` → `closedToggleIcon.decoration.icon.*.size`
- `closed_toggle_background_color` → `closedToggle.decoration.background.*.color`
- `closed_toggle_text_color` → `closedToggle.decoration.font.font.*.color`
- `toggle_level` → `title.decoration.font.font.*.headingLevel`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `closed_toggle` → `closedToggle.decoration.font`
- `toggle` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Accordion"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h5"}}}}}},"closedToggleIcon":{"decoration":{"icon":{"desktop":{"value":{"useSize":"off"}}}}}}
```
