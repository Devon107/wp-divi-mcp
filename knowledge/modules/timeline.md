# divi/timeline — Timeline

- Category: `module`
- Divi 4 shortcode: `et_pb_timeline`
- Allowed children: `divi/timeline-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, timeline; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `track` — decoration: background, border, boxShadow, sizing, spacing
- `item` — decoration: background, border, boxShadow, sizing, spacing
- `itemEven` — decoration: background, border, boxShadow, sizing, spacing
- `spacer` — decoration: background, border, boxShadow, layout, sizing, spacing
- `spacerEven` — decoration: background, border, boxShadow, layout, sizing, spacing
- `children` — advanced: spacer
- `connector` — decoration: background, border, boxShadow, sizing, spacing
- `marker` — advanced: position; decoration: background, border, boxShadow, icon, sizing, spacing
- `card` — decoration: background, border, boxShadow, layout, sizing, spacing
- `cardEven` — decoration: background, border, boxShadow, layout, sizing, spacing
- `date` — decoration: font
- `dateEven` — decoration: font
- `title` — decoration: font
- `titleEven` — decoration: font
- `content` — decoration: bodyFont
- `contentEven` — decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.timeline.direction` | Direction | one of: "vertical", "horizontal" |
| `module.advanced.timeline.position` | Position | one of: "right", "left", "alternating" |
| `module.advanced.timeline.startFrom` | Start From | one of: "right", "left" |
| `children.spacer.advanced.displayElementsOnSpacer` | Display Elements On Spacer | "on" or "off" (not responsive) |
| `marker.advanced.position` | Marker Position | one of: "start", "center", "end" (not responsive) |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Timeline"}}},"advanced":{"timeline":{"desktop":{"value":{"direction":"vertical","position":"right"}}}}}}
```
