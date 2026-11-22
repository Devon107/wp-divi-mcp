# divi/lottie — Lottie

- Category: `module`
- Divi 4 shortcode: `et_pb_lottie`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `lottie` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `lottie.innerContent.src` | Lottie Animation File | URL string |
| `lottie.innerContent.uploadNotice` |  | warning field (not responsive) |
| `lottie.innerContent.trigger` | Trigger Animation | one of: "onLoad", "onView", "onHover", "onClick", "onScroll" |
| `lottie.innerContent.loop` | Loop Animation | "on" or "off" |
| `lottie.innerContent.speed` | Animation Speed | number or size string (e.g. "10", "24px") |
| `lottie.innerContent.direction` | Direction | one of: "forward", "backward" |
| `lottie.innerContent.mode` | Play Mode | one of: "normal", "bounce" |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Lottie Animation"}}},"advanced":{}},"lottie":{"innerContent":{"desktop":{"value":{"src":"","trigger":"onLoad","loop":"off","speed":1,"direction":"forward","mode":"normal"}}}}}
```
