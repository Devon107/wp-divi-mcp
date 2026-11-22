# divi/audio — Audio

- Category: `module`
- Divi 4 shortcode: `et_pb_audio`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `caption` — decoration: font
- `artistName` — innerContent
- `albumName` — innerContent
- `audio` — innerContent
- `title` — innerContent; decoration: font
- `image` — innerContent; decoration: image

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `artistName.innerContent` | Artist | string |
| `albumName.innerContent` | Album | string |
| `audio.innerContent` | Audio File | URL string (not responsive) |
| `title.innerContent` | Title | string |
| `image.innerContent.src` | Image | URL string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `album_name` → `albumName.innerContent.*`
- `artist_name` → `artistName.innerContent.*`
- `title` → `title.innerContent.*`
- `audio` → `audio.innerContent.*`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `image_url` → `image.innerContent.*.src`
- `title_text` → `image.innerContent.*.titleText`
- `alt` → `image.innerContent.*.alt`

## Font groups

- `caption` → `caption.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Audio"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"dark"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}}}
```
