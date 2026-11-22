# divi/dropdown — Dropdown

- Category: `module`
- Divi 4 shortcode: `et_pb_dropdown`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: dropdown, elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, scroll, sizing, spacing, sticky, transform, transition, zIndex

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.dropdown.position` | Dropdown Position | one of: "floating", "inline" |
| `module.advanced.dropdown.horizontalMode` | Horizontal Position Mode | one of: "trigger", "row", "viewport" |
| `module.advanced.dropdown.showOn` | Show Dropdown On | one of: "hover", "click" |
| `module.advanced.dropdown.direction` | Dropdown Direction | one of: "above", "below", "left", "right" |
| `module.advanced.dropdown.alignment` | Dropdown Alignment | one of: "start", "center", "end" |
| `module.advanced.dropdown.offset` | Dropdown Offset | number or size string (e.g. "10", "24px") |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Dropdown"}}},"advanced":{"dropdown":{"desktop":{"value":{"showOn":"hover","direction":"below","alignment":"start","offset":"20px"}}}}}}
```
