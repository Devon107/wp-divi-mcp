# divi/svg — SVG

- Category: `module`
- Divi 4 shortcode: `et_pb_svg`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `svg` — innerContent; advanced: fill, stroke

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `svg.innerContent.sourceType` | Source Type | one of: "code", "src" (not responsive) |
| `svg.innerContent.code` | SVG Code | code field |
| `svg.innerContent.src` | SVG File | URL string |
| `svg.innerContent.srcNotice` |  | warning field (not responsive) |
| `svg.innerContent.title` | Title | string (not responsive) |
| `svg.innerContent.linkUrl` | Link URL | string (not responsive) |
| `svg.innerContent.linkTarget` | Link Target | one of: "off", "on" (not responsive) |
| `svg.innerContent.rel` | Link Rel | string (not responsive) |
| `svg.advanced.color` | Fill Color | color string |
| `svg.advanced.opacity` | Fill Opacity | number or size string (e.g. "10", "24px") |
| `svg.advanced.width` | Stroke Width | number or size string (e.g. "10", "24px") |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"SVG"}}}},"svg":{"innerContent":{"desktop":{"value":{"sourceType":"code","linkTarget":"off"}}}}}
```
