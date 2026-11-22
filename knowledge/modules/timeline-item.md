# divi/timeline-item — Timeline Item

- Category: `child-module`
- Divi 4 shortcode: `et_pb_timeline_item`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, transform, transition, zIndex
- `spacer` — advanced: displayElementsOnSpacer; decoration: background, border, boxShadow, layout, sizing, spacing
- `connector` — decoration: background, border, boxShadow, sizing, spacing
- `marker` — innerContent; advanced: position; decoration: background, border, boxShadow, icon, sizing, spacing
- `card` — decoration: background, border, boxShadow, layout, sizing, spacing
- `date` — innerContent; decoration: font
- `title` — innerContent; decoration: font
- `content` — innerContent; advanced: text; decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `spacer.advanced.displayElementsOnSpacer` | Display Elements On Spacer | "on" or "off" (not responsive) |
| `marker.innerContent` | Icon | icon {"unicode","type","weight"} |
| `marker.advanced.position` | Marker Position | one of: "default", "start", "center", "end" (not responsive) |
| `date.innerContent` | Date | string |
| `title.innerContent` | Title | string |
| `content.innerContent` | Body | HTML string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Timeline Item"}}}}}
```
