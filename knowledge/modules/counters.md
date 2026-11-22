# divi/counters — Bar Counters

- Category: `module`
- Divi 4 shortcode: `et_pb_counters`
- Allowed children: `divi/counter`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `barCounter` — decoration: background, border, boxShadow
- `title` — decoration: font
- `barProgress` — advanced: usePercentages; decoration: font
- `children` — decoration: background

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `barProgress.advanced.usePercentages` | Show Percentage | one of: "off", "on" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `use_percentages` → `barProgress.advanced.usePercentages.*`
- `bar_bg_color` → `children.barProgress.decoration.background.*.color`
- `layout_display` → `module.decoration.layout.*.display`

## Font groups

- `percent` → `barProgress.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Bar Counters"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"ul"}}},"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"barProgress":{"advanced":{"usePercentages":{"desktop":{"value":"on"}}}},"children":{"barProgress":{"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}}}}}
```
