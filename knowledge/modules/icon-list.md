# divi/icon-list — Icon List

- Category: `module`
- Divi 4 shortcode: `et_pb_icon_list`
- Allowed children: `divi/icon-list-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `icon` — advanced: color, size; decoration: background, border, boxShadow, spacing
- `listItem` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `icon.advanced.color` | Icon Color | color string |
| `icon.advanced.size` | Icon Size | number or size string (e.g. "10", "24px") |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Icon List"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"ul"}}}}},"icon":{"advanced":{"color":{"desktop":{"value":"var(--gcid-primary-color)"}},"size":{"desktop":{"value":"16px"}}}},"listItem":{"decoration":{"font":{}}}}
```
