# divi/slide — Slide

- Category: `child-module`
- Divi 4 shortcode: `et_pb_slide`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: attributes, background, conditions, disabledOn, filters, interactions, layout, order, overflow, sizing, spacing, transform, transition, zIndex
- `image` — innerContent; advanced: alignment
- `title` — innerContent; decoration: font
- `contentOverlay` — advanced: useTextOverlay; decoration: background, border
- `button` — innerContent; decoration: button
- `content` — innerContent; decoration: bodyFont
- `slideOverlay` — advanced: useBackgroundOverlay; decoration: background
- `arrows` — advanced: color
- `dotNav` — advanced: color
- `video` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `image.innerContent.src` | Image | URL string |
| `image.innerContent.id` | (synced from Image) | string |
| `image.innerContent.alt` | (synced from Image) | string |
| `image.innerContent.titleText` | (synced from Image) | string |
| `image.advanced.alignment` | Image Alignment | one of: "center", "bottom" |
| `title.innerContent` | Title | string |
| `contentOverlay.advanced.use` | Use Text Overlay | "on" or "off" (not responsive) |
| `button.innerContent.text` | Button | string |
| `button.innerContent.linkUrl` | Link URL | string (not responsive) |
| `button.innerContent.linkTarget` | Link Target | one of: "off", "on" (not responsive) |
| `content.innerContent` | Body | HTML string |
| `slideOverlay.advanced.use` | Use Background Overlay | "on" or "off" (not responsive) |
| `arrows.advanced.color` | Arrow Color | color string |
| `video.innerContent` | Video | URL string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `admin_title` → `module.meta.adminLabel.*`
- `allow_player_pause` → `module.decoration.background.*.video.allowPlayerPause`
- `arrows_custom_color` → `arrows.advanced.color.*`
- `button_text` → `button.innerContent.*.text`
- `url_new_window` → `button.innerContent.*.linkTarget`
- `button_link` → `button.innerContent.*.linkUrl`
- `content` → `content.innerContent.*`
- `dot_nav_custom_color` → `dotNav.decoration.background.*.color`
- `heading` → `title.innerContent.*`
- `image_alt` → `image.innerContent.*.alt`
- `video_url` → `video.innerContent.*`
- `image` → `image.innerContent.*.src`
- `alt` → `image.innerContent.*.alt`
- `alignment` → `image.advanced.alignment.*`
- `use_bg_overlay` → `slideOverlay.advanced.use.*`
- `bg_overlay_color` → `slideOverlay.decoration.background.*.color`
- `use_text_overlay` → `contentOverlay.advanced.use.*`
- `text_overlay_color` → `contentOverlay.decoration.background.*.color`
- `text_border_radius` → `contentOverlay.decoration.border.*.radius`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"dark"}}}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}},"meta":{"adminLabel":{"desktop":{"value":""}}}},"image":{"advanced":{"alignment":{"desktop":{"value":"center"}}}},"contentOverlay":{"decoration":{"border":{"desktop":{"value":{"radius":{"sync":"on","bottomLeft":"3px","bottomRight":"3px","topLeft":"3px","topRight":"3px"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
