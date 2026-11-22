# divi/social-media-follow — Social Media Follow

- Category: `module`
- Divi 4 shortcode: `et_pb_social_media_follow`
- Allowed children: `divi/social-media-follow-network`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — innerContent; advanced: elements, html, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `icon` — advanced: color, size
- `socialNetwork` — advanced: followButton
- `button` — innerContent; decoration: button

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.text.text.orientation` | Module Alignment | one of: "left", "center", "right" |
| `module.innerContent` |  | see legacy mapping |
| `icon.advanced.color` | Icon Color | color string |
| `icon.advanced.size.useSize` | Use Custom Icon Size | "on" or "off" |
| `icon.advanced.size.size` | Icon Font Size | number or size string (e.g. "10", "24px") |
| `socialNetwork.advanced.followButton` | Follow Button | one of: "off", "on" |
| `socialNetwork.advanced.followButton.linkUrl` | Link URL | string (not responsive) |
| `socialNetwork.advanced.followButton.linkTarget` | Link Target | one of: "off", "on" (not responsive) |
| `button.innerContent` |  | see legacy mapping |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `follow_button` → `socialNetwork.advanced.followButton.*`
- `url_new_window` → `button.innerContent.*.linkTarget`
- `icon_color` → `icon.advanced.color.*`
- `use_icon_font_size` → `icon.advanced.size.*.useSize`
- `icon_font_size` → `icon.advanced.size.*.size`
- `custom_button` → `button.decoration.button.*.enable`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Social Media Follow"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"ul"}}},"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"decoration":{"background":{"desktop":{"value":{"color":""}}}}},"icon":{"advanced":{"size":{"desktop":{"value":{"useSize":"off","size":"16px"}}}}},"socialNetwork":{"advanced":{"followButton":{"desktop":{"value":"off"}}}}}
```
