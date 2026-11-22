# divi/map-pin — Map Pin

- Category: `child-module`
- Divi 4 shortcode: `et_pb_map_pin`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `pin` — innerContent; advanced: html, loop
- `title` — innerContent
- `content` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `pin.innerContent` | Map Pin Address | map field (not responsive) |
| `title.innerContent` | Title | string |
| `content.innerContent` | Body | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `pin_address` → `pin.innerContent.*.address`
- `pin_address_lat` → `pin.innerContent.*.lat`
- `pin_address_lng` → `pin.innerContent.*.lng`
- `title` → `title.innerContent.*`
- `zoom_level` → `pin.innerContent.*.zoom`

## Defaults applied when an attribute is omitted

```json
{"pin":{"innerContent":{"desktop":{"value":{"address":"","zoom":18,"lat":0,"lng":0}}}}}
```
