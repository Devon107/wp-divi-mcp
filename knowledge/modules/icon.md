# divi/icon — Icon

- Category: `module`
- Divi 4 shortcode: `et_pb_icon`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `icon` — innerContent; advanced: align, color, size
- `iconLink` — (no settings)

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `icon.innerContent` | Icon | icon {"unicode","type","weight"} |
| `icon.advanced.color` | Icon Color | color string |
| `icon.advanced.size` | Icon Size | number or size string (e.g. "10", "24px") |
| `icon.advanced.align` | Icon Alignment | one of: "left", "center", "right" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `align` → `icon.advanced.align.*`
- `font_icon` → `icon.innerContent.*`
- `icon_color` → `icon.advanced.color.*`
- `icon_width` → `icon.advanced.size.*`
- `title_text` → `icon.innerContent.*.title`
- `url` → `icon.innerContent.*.url`
- `url_new_window` → `icon.innerContent.*.target`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Icon"}}}},"icon":{"innerContent":{"desktop":{"value":{"unicode":"&#x21;","type":"divi","weight":"400","target":"off"}}},"advanced":{"color":{"desktop":{"value":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}},"size":{"desktop":{"value":"96px"}},"align":{"desktop":{"value":"center"}}}}}
```
