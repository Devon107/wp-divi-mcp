# divi/team-member — Person

- Category: `module`
- Divi 4 shortcode: `et_pb_team_member`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `name` — innerContent; decoration: font
- `position` — innerContent; decoration: font
- `image` — innerContent; decoration: image
- `content` — innerContent; decoration: bodyFont
- `social` — innerContent; decoration: icon

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `name.innerContent` | Name | string |
| `position.innerContent` | Position | string |
| `image.innerContent.animation` | Image Animation | one of: "off", "fade_in", "left", "right", "top", "bottom" |
| `image.innerContent.url` | Image | URL string |
| `image.innerContent.id` | (synced from Image) | string |
| `image.innerContent.titleText` | (synced from Image) | string |
| `content.innerContent` | Body | HTML string |
| `social.innerContent.facebookUrl` | Facebook Profile Url | string (not responsive) |
| `social.innerContent.twitterUrl` | X Profile Url | string (not responsive) |
| `social.innerContent.linkedinUrl` | LinkedIn Profile Url | string (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `facebook_url` → `social.innerContent.*.facebookUrl`
- `icon_color` → `social.decoration.icon.*.color`
- `linkedin_url` → `social.innerContent.*.linkedinUrl`
- `name` → `name.innerContent.*`
- `twitter_url` → `social.innerContent.*.twitterUrl`
- `position` → `position.innerContent.*`
- `image_url` → `image.innerContent.*.url`
- `header_level` → `name.decoration.font.font.*.headingLevel`
- `icon_font_size` → `social.decoration.icon.*.size`
- `use_icon_font_size` → `social.decoration.icon.*.useSize`
- `animation` → `image.innerContent.*.animation`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `header` → `name.decoration.font`
- `position` → `position.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Person"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"name":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h4"}}}}}},"image":{"innerContent":{"desktop":{"value":{"animation":"off"}}}},"social":{"decoration":{"icon":{"desktop":{"value":{"size":"16px"}}}}}}
```
