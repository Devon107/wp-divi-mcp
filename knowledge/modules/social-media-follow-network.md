# divi/social-media-follow-network — Social Network

- Category: `child-module`
- Divi 4 shortcode: `et_pb_social_media_follow_network`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, transform, transition, zIndex
- `icon` — advanced: color, size
- `socialNetwork` — innerContent
- `iconLink` — (no settings)
- `button` — decoration: button

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `icon.advanced.color` | Icon Color | color string |
| `icon.advanced.size.useSize` | Use Custom Icon Size | "on" or "off" |
| `icon.advanced.size.size` | Icon Font Size | number or size string (e.g. "10", "24px") |
| `socialNetwork.innerContent.title` | Social Network | select field (not responsive) |
| `socialNetwork.innerContent.skypeUrl` | Account Name | string (not responsive) |
| `socialNetwork.innerContent.skypeAction` | Skype Button Action | one of: "call", "chat" (not responsive) |
| `socialNetwork.innerContent.link` | Account Link URL | string (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `follow_button` → `socialNetwork.advanced.followButton.*`
- `skype_action` → `socialNetwork.innerContent.*.skypeAction`
- `skype_url` → `socialNetwork.innerContent.*.skypeUrl`
- `url` → `socialNetwork.innerContent.*.link`
- `content` → `socialNetwork.innerContent.*.label`
- `social_network` → `socialNetwork.innerContent.*.title`
- `icon_color` → `icon.advanced.color.*`
- `use_icon_font_size` → `icon.advanced.size.*.useSize`
- `icon_font_size` → `icon.advanced.size.*.size`
- `custom_button` → `button.decoration.button.*.enable`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"html":{"desktop":{"value":{"elementType":"li"}}}}},"icon":{"advanced":{"size":{"desktop":{"value":{"useSize":"off","size":"16px"}}}}},"socialNetwork":{"innerContent":{"desktop":{"value":{"title":"facebook","label":"Facebook","skypeUrl":"","skypeAction":"call"}}}}}
```
