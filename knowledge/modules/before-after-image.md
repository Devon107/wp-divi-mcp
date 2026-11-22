# divi/before-after-image — Before/After Image

- Category: `module`
- Divi 4 shortcode: `et_pb_before_after_image`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `beforeImage` — innerContent; decoration: image
- `afterImage` — innerContent; decoration: image
- `slider` — advanced: arrowColor, color, orientation, position
- `beforeLabel` — innerContent
- `afterLabel` — innerContent
- `labels` — decoration: background, border, font, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `beforeImage.innerContent.src` | Before Image | URL string |
| `beforeImage.innerContent.id` | (synced from Before Image) | string |
| `beforeImage.innerContent.alt` | (synced from Before Image) | string |
| `beforeImage.innerContent.titleText` | (synced from Before Image) | string |
| `afterImage.innerContent.src` | After Image | URL string |
| `afterImage.innerContent.id` | (synced from After Image) | string |
| `afterImage.innerContent.alt` | (synced from After Image) | string |
| `afterImage.innerContent.titleText` | (synced from After Image) | string |
| `slider.advanced.orientation` | Orientation | one of: "horizontal", "vertical" (not responsive) |
| `slider.advanced.position` | Position | number or size string (e.g. "10", "24px") (not responsive) |
| `slider.advanced.color` | Slider Color | color string |
| `slider.advanced.arrowColor` | Arrow Color | color string |
| `beforeLabel.innerContent` | Before Label | string |
| `afterLabel.innerContent` | After Label | string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Before/After Image"}}},"decoration":{"spacing":{"desktop":{"value":{"margin":{"bottom":"0px"}}}}}},"slider":{"advanced":{"orientation":{"desktop":{"value":"horizontal"}}}}}
```
