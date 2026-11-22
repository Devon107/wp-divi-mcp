# divi/number-counter — Number Counter

- Category: `module`
- Divi 4 shortcode: `et_pb_number_counter`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `number` — innerContent; advanced: enablePercentSign; decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `number.innerContent` | Number | string |
| `number.advanced.enablePercentSign` | Percent Sign | one of: "off", "on" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `counter_color` → `number.decoration.font.font.*.color`
- `number` → `number.innerContent.*`
- `percent_sign` → `number.advanced.enablePercentSign.*`
- `title` → `title.innerContent.*`
- `title_level` → `title.decoration.font.font.*.headingLevel`

## Font groups

- `number` → `number.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Number Counter"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light","orientation":"center"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"number":{"innerContent":{"desktop":{"value":"0"}},"advanced":{"enablePercentSign":{"desktop":{"value":"on"}}},"decoration":{"font":{"font":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}}}}}
```
