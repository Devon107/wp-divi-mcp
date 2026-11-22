# divi/cta — Call To Action

- Category: `module`
- Divi 4 shortcode: `et_pb_cta`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `content` — innerContent; decoration: bodyFont
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `content.innerContent` |  | HTML string |
| `button.innerContent.text` | Button | string |
| `button.innerContent.linkUrl` | Link URL | string (not responsive) |
| `button.innerContent.linkTarget` | Link Target | one of: "off", "on" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `button_text` → `button.innerContent.*.text`
- `button_url` → `button.innerContent.*.linkUrl`
- `content` → `content.innerContent.*`
- `url_new_window` → `button.innerContent.*.linkTarget`
- `title` → `title.innerContent.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `body_ul_type` → `content.decoration.bodyFont.ul.list.*.type`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Call To Action"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}},"advanced":{"text":{"text":{"desktop":{"value":{"orientation":"center","color":"dark"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
