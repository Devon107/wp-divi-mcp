# divi/button — Button

- Category: `module`
- Divi 4 shortcode: `et_pb_button`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: alignment, elements, html, loop, text; decoration: animation, attributes, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, spacing, sticky, transform, transition, zIndex
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.alignment` | Button Alignment | one of: "left", "center", "right" |
| `button.innerContent.text` | Button Text | string |
| `button.innerContent.linkUrl` | Link URL | string (not responsive) |
| `button.innerContent.linkTarget` | Link Target | one of: "off", "on" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `button_text` → `button.innerContent.*.text`
- `button_url` → `button.innerContent.*.linkUrl`
- `url_new_window` → `button.innerContent.*.linkTarget`
- `custom_button` → `button.decoration.button.*.enable`
- `button_alignment` → `module.advanced.alignment.*`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Button"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"a"}}},"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
