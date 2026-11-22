# divi/icon-list-item — Icon List Item

- Category: `child-module`
- Divi 4 shortcode: `et_pb_icon_list_item`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — innerContent; decoration: font
- `icon` — innerContent; advanced: color, size; decoration: background, border, boxShadow, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.innerContent` | Text | string |
| `icon.innerContent` | Icon | icon {"unicode","type","weight"} |
| `icon.advanced.color` | Icon Color | color string |
| `icon.advanced.size` | Icon Size | number or size string (e.g. "10", "24px") |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Icon List Item"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"li"}}}}},"content":{"innerContent":{"desktop":{"value":"List item text"}}},"icon":{"innerContent":{"desktop":{"value":{"unicode":"&#x21;","type":"divi","weight":"400","target":"off"}}}}}
```
