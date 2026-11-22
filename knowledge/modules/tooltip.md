# divi/tooltip — Tooltip

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `content` — innerContent; decoration: bodyFont
- `module` — advanced: elements, html, link, loop, tooltip; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, scroll, sizing, spacing, sticky, transform, transition, zIndex

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.innerContent` | Body | HTML string |
| `module.advanced.tooltip.trigger` | Show Tooltip On | one of: "hover", "click", "always" |
| `module.advanced.tooltip.positionMode` | Tooltip Movement | one of: "anchored", "followCursor" |
| `module.advanced.tooltip.placement` | Tooltip Position | placement-grid-picker field |
| `module.advanced.tooltip.skid` | Tooltip Skid | number or size string (e.g. "10", "24px") |
| `module.advanced.tooltip.distance` | Tooltip Distance | number or size string (e.g. "10", "24px") |
| `module.advanced.tooltip.openDelay` | Tooltip Open Delay | number or size string (e.g. "10", "24px") |
| `module.advanced.tooltip.closeDelay` | Tooltip Close Delay | number or size string (e.g. "10", "24px") |
| `module.advanced.tooltip.showArrow` | Show Tooltip Arrow | "on" or "off" |
| `module.advanced.tooltip.arrowColor` | Tooltip Arrow Color | color string |
| `module.advanced.tooltip.arrowPlacement` | Tooltip Arrow Placement | placement-grid-picker field |
| `module.advanced.tooltip.arrowOffset` | Tooltip Arrow Position | number or size string (e.g. "10", "24px") |
| `module.advanced.tooltip.arrowSize` | Tooltip Arrow Size | number or size string (e.g. "10", "24px") |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Tooltip"}}},"advanced":{"tooltip":{"desktop":{"value":{"trigger":"hover","positionMode":"anchored","placement":"outside top center","skid":"0px","distance":"8px","openDelay":0,"closeDelay":0,"showArrow":"off","arrowPlacement":"outside bottom center","arrowOffset":"0px","arrowSize":"6px"}}}}}}
```
