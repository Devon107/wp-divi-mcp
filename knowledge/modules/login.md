# divi/login — Login

- Category: `module`
- Divi 4 shortcode: `et_pb_login`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: currentPageRedirect, elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `content` — innerContent; decoration: bodyFont
- `field` — (no settings)
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.currentPageRedirect` | Redirect To The Current Page | one of: "off", "on" (not responsive) |
| `title.innerContent` | Title | string |
| `content.innerContent` | Body | HTML string |
| `button.innerContent.text` | Button | string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `current_page_redirect` → `module.advanced.currentPageRedirect.*`
- `title` → `title.innerContent.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `form_field` → `field.decoration.font`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Login"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"dark"}}}},"currentPageRedirect":{"desktop":{"value":"off"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","enableColor":"on"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"button":{"innerContent":{"desktop":{"value":{"text":"Login","linkUrl":"#"}}},"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
