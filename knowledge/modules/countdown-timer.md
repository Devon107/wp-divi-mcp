# divi/countdown-timer — Countdown Timer

- Category: `module`
- Divi 4 shortcode: `et_pb_countdown_timer`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `content` — advanced: dateTime
- `number` — decoration: font
- `separator` — decoration: font
- `label` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `content.advanced.dateTime` | Date | timepicker field (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `title` → `title.innerContent.*`
- `date_time` → `content.advanced.dateTime.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`

## Font groups

- `header` → `title.decoration.font`
- `label` → `label.decoration.font`
- `numbers` → `number.decoration.font`
- `separator` → `separator.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Countdown Timer"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}},"advanced":{"text":{"text":{"desktop":{"value":{"orientation":"center","color":"dark"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h4"}}}}}},"content":{"advanced":{"dateTime":{"desktop":{"value":"2020-03-23 01:30"}}}}}
```
