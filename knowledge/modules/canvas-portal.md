# divi/canvas-portal — Canvas Portal

- Category: `module`
- Divi 4 shortcode: `et_pb_canvas_portal`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `canvas` — advanced: canvasId
- `css` — advanced: cssClass, cssId, customCss

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `canvas.advanced.canvasId` | Canvas | select field (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `canvas_id` → `canvas.advanced.canvasId.*`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Canvas Portal"}}}},"canvas":{"advanced":{"canvasId":{"desktop":{"value":""}}}}}
```
