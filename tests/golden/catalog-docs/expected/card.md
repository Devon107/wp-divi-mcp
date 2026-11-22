# divi/card — Card

- Category: `module`
- Divi 4 shortcode: `et_pb_card`
- Allowed children: `divi/card-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: gap, link; decoration: background, spacing
- `title` — innerContent; decoration: font
- `image` — innerContent
- `style` — advanced: d, s
- `content` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.link` |  | string |
| `module.advanced.gap` |  | number or size string (e.g. "10", "24px") (not responsive) |
| `title.innerContent.text` | Title | Text | string |
| `title.innerContent` |  | button-link field |
| `image.innerContent.src` | Image | URL string |
| `image.innerContent.alt` | (synced from Image) | string |
| `image.innerContent.titleText` | (synced from Image) | string |
| `style.advanced.kind` | Kind | one of: "a", "b", "c" |
| `content.innerContent` |  | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `text` → `title.innerContent.*.text`
- `deep.x` → `a.b`

## Font groups

- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"title":{"label":"Caf\u00e9 \u2192 \ud83d\ude00","n":1.5,"z":1.0,"e":[],"o":{}}}
```
