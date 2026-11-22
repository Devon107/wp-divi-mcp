# divi/divider — Divider

- Category: `module`
- Divi 4 shortcode: `et_pb_divider`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `divider` — advanced: line

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `divider.advanced.line.show` | Show Divider | "on" or "off" |
| `divider.advanced.line.color` | Line Color | color string |
| `divider.advanced.line.style` | Line Style | one of: "solid", "dashed", "dotted", "double", "groove", "ridge", "inset", "outset", "none" |
| `divider.advanced.line.position` | Line Position | one of: "top", "center", "bottom" |
| `divider.advanced.line.weight` | Divider Weight | number or size string (e.g. "10", "24px") |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `show_divider` → `divider.advanced.line.*.show`
- `color` → `divider.advanced.line.*.color`
- `divider_style` → `divider.advanced.line.*.style`
- `divider_position` → `divider.advanced.line.*.position`
- `divider_weight` → `divider.advanced.line.*.weight`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Divider"}}}},"divider":{"advanced":{"line":{"desktop":{"value":{"show":"on","color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","style":"solid","position":"top","weight":"1px"}}}}}}
```
