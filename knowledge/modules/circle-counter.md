# divi/circle-counter — Circle Counter

- Category: `module`
- Divi 4 shortcode: `et_pb_circle_counter`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `circle` — advanced: circle
- `title` — innerContent; decoration: font
- `number` — innerContent; advanced: percentSign; decoration: font
- `contentContainer` — (no settings)

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `circle.advanced.color` | Circle Color | color string |
| `circle.advanced.background.color` | Circle Background Color | color string |
| `circle.advanced.background.opacity` | Circle Background Opacity | number or size string (e.g. "10", "24px") |
| `title.innerContent` | Title | string |
| `number.innerContent` | Number | string |
| `number.advanced.percentSign` | Percent Sign | one of: "off", "on" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `number` → `number.innerContent.*`
- `percent_sign` → `number.advanced.percentSign.*`
- `title` → `title.innerContent.*`
- `bar_bg_color` → `circle.advanced.color.*`
- `circle_color` → `circle.advanced.background.*.color`
- `circle_color_alpha` → `circle.advanced.background.*.opacity`
- `title_level` → `title.decoration.font.font.*.headingLevel`

## Font groups

- `number` → `number.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Circle Counter"}}},"advanced":{"text":{"text":{"desktop":{"value":{"orientation":"center","color":"light"}}}}}},"circle":{"advanced":{"color":{"desktop":{"value":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}},"background":{"desktop":{"value":{"color":"","opacity":"0.1"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"number":{"advanced":{"percentSign":{"desktop":{"value":"on"}}},"innerContent":{"desktop":{"value":"0"}}}}
```
