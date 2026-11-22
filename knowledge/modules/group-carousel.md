# divi/group-carousel — Group Carousel

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: `divi/group`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: auto, autoSpeed, centerMode, html, link, loop, pauseOnHover, slidesToScroll, slidesToShow, transitionSpeed; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `arrows` — advanced: color, leftIcon, position, rightIcon, show, size; decoration: background, border, boxShadow, spacing
- `dotNav` — advanced: alignment, color, position, show, size
- `children` — decoration: background, border, boxShadow, filters, layout, spacing, transform
- `activeGroups` — decoration: background, border, boxShadow, filters, layout, spacing, transform

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.auto` | Automatic Rotation | "on" or "off" |
| `module.advanced.speed` | Automatic Rotation Speed | number or size string (e.g. "10", "24px") |
| `module.advanced.transitionSpeed` | Transition Speed | number or size string (e.g. "10", "24px") |
| `module.advanced.pauseOnHover` | Pause on Hover | "on" or "off" |
| `module.advanced.centerMode` | Center Mode | "on" or "off" |
| `module.advanced.slidesToShow` | Slides to Show | number or size string (e.g. "10", "24px") |
| `module.advanced.slidesToScroll` | Slides to Scroll | number or size string (e.g. "10", "24px") |
| `arrows.advanced.showArrows` | Show Arrows | "on" or "off" |
| `arrows.advanced.leftIcon` | Left Arrow Icon | icon {"unicode","type","weight"} |
| `arrows.advanced.rightIcon` | Right Arrow Icon | icon {"unicode","type","weight"} |
| `arrows.advanced.color` | Arrow Color | color string |
| `arrows.advanced.size` | Arrow Size | number or size string (e.g. "10", "24px") |
| `arrows.advanced.position` | Arrow Position | one of: "outside", "inside", "center" |
| `dotNav.advanced.showDots` | Show Dot Navigation | "on" or "off" |
| `dotNav.advanced.position` | Dot Position | one of: "below", "above", "overlay" |
| `dotNav.advanced.alignment` | Dot Alignment | one of: "left", "center", "right" |
| `dotNav.advanced.size` | Dot Size | number or size string (e.g. "10", "24px") |
| `dotNav.advanced.color` | Dot Color | color string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Carousel"}}},"advanced":{"auto":{"desktop":{"value":"off"}},"pauseOnHover":{"desktop":{"value":"on"}},"centerMode":{"desktop":{"value":"off"}},"slidesToShow":{"desktop":{"value":"1"}},"slidesToScroll":{"desktop":{"value":"1"}}}},"arrows":{"advanced":{"showArrows":{"desktop":{"value":"on"}},"leftIcon":{"desktop":{"value":{"unicode":"&#x34;","type":"divi","weight":"400","target":"off"}}},"rightIcon":{"desktop":{"value":{"unicode":"&#x35;","type":"divi","weight":"400","target":"off"}}}}},"dotNav":{"advanced":{"showDots":{"desktop":{"value":"on"}}}}}
```
