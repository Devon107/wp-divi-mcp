# divi/counter — Bar Counter

- Category: `child-module`
- Divi 4 shortcode: `et_pb_counter`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, conditions, filters, interactions, layout, order, overflow, position, scroll, spacing, transform, transition, zIndex
- `barCounter` — decoration: background, border, boxShadow, sizing
- `title` — innerContent; decoration: font
- `barProgress` — innerContent; decoration: background, font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `barProgress.innerContent` | Percent | string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `percent` → `barProgress.innerContent.*`
- `content` → `title.innerContent.*`
- `bar_background_color` → `barProgress.decoration.background.*.color`

## Font groups

- `percent` → `barProgress.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Bar Counter"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"li"}}}}},"barProgress":{"innerContent":{"desktop":{"value":"50"}}}}
```
