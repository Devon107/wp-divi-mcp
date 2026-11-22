# divi/breadcrumbs — Breadcrumbs

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `trail` — (no settings)
- `breadcrumb` — decoration: background, border, boxShadow, font, sizing, spacing
- `breadcrumbLink` — decoration: background, border, boxShadow, font, sizing, spacing
- `home` — innerContent; decoration: background, border, boxShadow, font, sizing, spacing
- `separator` — innerContent; decoration: background, border, boxShadow, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `trail.advanced.htmlTag` | Trail HTML Tag | one of: "nav", "div", "span", "p" (not responsive) |
| `home.innerContent.text` | Home Text | string |
| `home.innerContent.url` | Home URL | string |
| `separator.innerContent.text` | Separator | string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Breadcrumbs"}}},"decoration":{"layout":{"desktop":{"value":{"flexDirection":"row"}}},"spacing":{"desktop":{"value":{"margin":{"bottom":"0px"}}}}}},"trail":{"advanced":{"htmlTag":{"desktop":{"value":"nav"}}}},"home":{"innerContent":{"desktop":{"value":{"text":"Home","url":""}}}},"separator":{"innerContent":{"desktop":{"value":{"text":"/"}}}}}
```
