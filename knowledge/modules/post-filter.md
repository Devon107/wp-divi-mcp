# divi/post-filter — Post Filter

- Category: `module`
- Divi 4 shortcode: `et_pb_post_filter`
- Allowed children: `divi/post-filter-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, filters, html, loop, targetLoop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `field` — (no settings)
- `option` — decoration: background, border, boxShadow, sizing, spacing
- `checkbox` — (no settings)
- `radio` — (no settings)
- `multipleOrderButton` — decoration: button
- `button` — decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.targetLoop` | Target Loop | select field |
| `module.advanced.filters.applyMode` | Apply Mode | one of: "auto", "submit" |
| `module.advanced.filters.relation` | Relation | one of: "and", "or" |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Post Filter"}}},"advanced":{"filters":{"desktop":{"value":{"applyMode":"submit","relation":"and"}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
