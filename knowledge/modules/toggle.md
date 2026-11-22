# divi/toggle — Toggle

- Category: `module`
- Divi 4 shortcode: `et_pb_toggle`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, open, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `openToggleIcon` — decoration: icon
- `closedToggleIcon` — decoration: icon
- `title` — innerContent; decoration: font
- `closedTitle` — innerContent; decoration: font
- `content` — innerContent; decoration: bodyFont
- `openToggle` — decoration: background, font
- `closedToggle` — decoration: background

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.open` | State | one of: "off", "on" (not responsive) |
| `title.innerContent` |  | see legacy mapping |
| `closedTitle.innerContent` |  | see legacy mapping |
| `content.innerContent` | Content | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `open` → `module.advanced.open.*`
- `title` → `title.innerContent.*`
- `closed_toggle_background_color` → `closedToggle.decoration.background.*.color`
- `toggle_icon` → `closedToggleIcon.decoration.icon.*`
- `icon_color` → `closedToggleIcon.decoration.icon.*.color`
- `use_icon_font_size` → `closedToggleIcon.decoration.icon.*.useSize`
- `icon_font_size` → `closedToggleIcon.decoration.icon.*.size`
- `open_toggle_background_color` → `openToggle.decoration.background.*.color`
- `open_toggle_icon` → `openToggleIcon.decoration.icon.*`
- `open_icon_color` → `openToggleIcon.decoration.icon.*.color`
- `open_use_icon_font_size` → `openToggleIcon.decoration.icon.*.useSize`
- `open_icon_font_size` → `openToggleIcon.decoration.icon.*.size`
- `closed_toggle_text_color` → `closedTitle.decoration.font.font.*.color`
- `title_text_color` → `closedTitle.decoration.font.font.*.color`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `open_toggle_text_color` → `openToggle.decoration.font.font.*.color`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `closed_title` → `closedTitle.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Toggle"}}},"advanced":{"open":{"desktop":{"value":"off"}}}},"openToggleIcon":{"decoration":{"icon":{"desktop":{"value":{"useSize":"off"}}}}},"closedToggleIcon":{"decoration":{"icon":{"desktop":{"value":{"useSize":"off"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h5"}}}}}}}
```
