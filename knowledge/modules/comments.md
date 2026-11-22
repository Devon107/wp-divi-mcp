# divi/comments — Comments

- Category: `module`
- Divi 4 shortcode: `et_pb_comments`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, showLabels, showReply, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `image` — advanced: showAvatar; decoration: image
- `field` — (no settings)
- `button` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `commentCount` — advanced: showCount; decoration: font
- `formTitle` — decoration: font
- `meta` — advanced: showMeta; decoration: font
- `commentText` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.showReply` | Show Reply Button | one of: "off", "on" |
| `module.advanced.showLabels` | Show Labels | one of: "off", "on" (not responsive) |
| `image.advanced.showAvatar` | Show Author Avatar | one of: "off", "on" |
| `commentCount.advanced.showCount` | Show Comment Count | one of: "off", "on" |
| `meta.advanced.showMeta` | Show Meta | one of: "off", "on" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `use_focus_border_color` → `field.advanced.useFocusBorderColor.*`
- `header_level` → `commentCount.decoration.font.font.*.headingLevel`
- `title_level` → `formTitle.decoration.font.font.*.headingLevel`
- `show_avatar` → `image.advanced.showAvatar.*`
- `show_count` → `commentCount.advanced.showCount.*`
- `show_meta` → `meta.advanced.showMeta.*`
- `show_reply` → `module.advanced.showReply.*`
- `show_labels` → `module.advanced.showLabels.*`
- `form_background_color` → `module.decoration.background.*.color`
- `form_field_background_color` → `field.decoration.background.*.color`

## Font groups

- `header` → `commentCount.decoration.font`
- `title` → `formTitle.decoration.font`
- `meta` → `meta.decoration.font`
- `body` → `commentText.decoration.font`
- `form_field` → `field.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Comments"}}},"advanced":{"showReply":{"desktop":{"value":"on"}},"showLabels":{"desktop":{"value":"off"}},"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"image":{"advanced":{"showAvatar":{"desktop":{"value":"on"}}}},"field":{"decoration":{"border":{"desktop":{"value":{"radius":{"sync":"on","topLeft":"3px","topRight":"3px","bottomLeft":"3px","bottomRight":"3px"},"styles":{"all":{"color":"","style":"solid"}}}}}},"advanced":{"focusUseBorder":{"desktop":{"value":"off"}},"focus":{"border":{"desktop":{"value":{"radius":{"sync":"on","topLeft":"3px","topRight":"3px","bottomLeft":"3px","bottomRight":"3px"},"styles":{"all":{"color":"","style":"solid"}}}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"commentCount":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h1"}}}}},"advanced":{"showCount":{"desktop":{"value":"on"}}}},"formTitle":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"meta":{"advanced":{"showMeta":{"desktop":{"value":"on"}}}}}
```
