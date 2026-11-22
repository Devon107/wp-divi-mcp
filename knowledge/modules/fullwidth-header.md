# divi/fullwidth-header — Hero

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_header`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, headerFullscreen, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `image` — innerContent; advanced: orientation
- `logo` — innerContent
- `title` — innerContent; decoration: font
- `subhead` — innerContent; decoration: font
- `content` — innerContent; advanced: maxWidth, orientation; decoration: bodyFont
- `buttonOne` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `buttonTwo` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `scrollDown` — decoration: icon
- `overlay` — decoration: background

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.text.text.orientation` | Text & Logo Alignment | one of: "left", "center", "right" (not responsive) |
| `module.advanced.headerFullscreen` | Make Fullscreen | "on" or "off" (not responsive) |
| `image.innerContent.src` | Header Image | URL string |
| `image.innerContent.id` | (synced from Header Image) | string |
| `image.innerContent.alt` | (synced from Header Image) | string |
| `image.innerContent.titleText` | (synced from Header Image) | string |
| `image.advanced.orientation` | Image Alignment | select field (not responsive) |
| `logo.innerContent.src` | Logo Image | URL string |
| `logo.innerContent.id` | (synced from Logo Image) | string |
| `logo.innerContent.alt` | (synced from Logo Image) | string |
| `logo.innerContent.titleText` | (synced from Logo Image) | string |
| `logo.innerContent.width` | (synced from Logo Image) | string |
| `logo.innerContent.height` | (synced from Logo Image) | string |
| `title.innerContent` | Title | string |
| `subhead.innerContent` | Subtitle | string |
| `content.advanced.orientation` | Text Vertical Alignment | select field (not responsive) |
| `content.advanced.maxWidth` | Content Width | number or size string (e.g. "10", "24px") |
| `content.innerContent` |  | HTML string |
| `buttonOne.innerContent.text` | Button #1 | string |
| `buttonOne.innerContent.linkUrl` | Button #1 Link URL | string (not responsive) |
| `buttonTwo.innerContent.text` | Button #2 | string |
| `buttonTwo.innerContent.linkUrl` | Button #2 Link URL | string (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `background_overlay_color` → `overlay.decoration.background.*.color`
- `button_one_text` → `buttonOne.innerContent.*.text`
- `button_one_url` → `buttonOne.innerContent.*.linkUrl`
- `button_two_text` → `buttonTwo.innerContent.*.text`
- `button_two_url` → `buttonTwo.innerContent.*.linkUrl`
- `content` → `content.innerContent.*`
- `content_font_color` → `content.decoration.bodyFont.body.font.*.color`
- `content_max_width` → `content.advanced.maxWidth.*`
- `content_orientation` → `content.advanced.orientation.*`
- `header_fullscreen` → `module.advanced.headerFullscreen.*`
- `header_image_url` → `image.innerContent.*.src`
- `header_scroll_down` → `scrollDown.decoration.icon.*.show`
- `image_alt_text` → `image.innerContent.*.alt`
- `image_orientation` → `image.advanced.orientation.*`
- `image_title` → `image.innerContent.*.title`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `logo_alt_text` → `logo.innerContent.*.alt`
- `logo_image_url` → `logo.innerContent.*.src`
- `logo_title` → `logo.innerContent.*.title`
- `scroll_down_icon` → `scrollDown.decoration.icon.*`
- `scroll_down_icon_color` → `scrollDown.decoration.icon.*.color`
- `scroll_down_icon_size` → `scrollDown.decoration.icon.*.size`
- `subhead` → `subhead.innerContent.*`
- `subhead_font_color` → `subhead.decoration.font.font.*.color`
- `text_orientation` → `module.advanced.text.text.*.orientation`
- `title` → `title.innerContent.*`
- `title_font_color` → `title.decoration.font.font.*.color`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `content_ol_position` → `content.decoration.bodyFont.ol.list.*.position`
- `content_ol_type` → `content.decoration.bodyFont.ol.list.*.type`
- `content_ol_item_indent` → `content.decoration.bodyFont.ol.list.*.itemIndent`
- `content_ul_position` → `content.decoration.bodyFont.ul.list.*.position`
- `content_ul_type` → `content.decoration.bodyFont.ul.list.*.type`
- `content_ul_item_indent` → `content.decoration.bodyFont.ul.list.*.itemIndent`

## Font groups

- `content` → `content.decoration.bodyFont.body`
- `content_link` → `content.decoration.bodyFont.link`
- `content_ol` → `content.decoration.bodyFont.ol`
- `content_quote` → `content.decoration.bodyFont.quote`
- `content_ul` → `content.decoration.bodyFont.ul`
- `subhead` → `subhead.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Hero"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"section"}}},"text":{"text":{"desktop":{"value":{"color":"dark","orientation":"left"}}}},"headerFullscreen":{"desktop":{"value":"off"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}}},"image":{"advanced":{"orientation":{"desktop":{"value":"center"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h1"}}}}}},"content":{"advanced":{"orientation":{"desktop":{"value":"center"}},"maxWidth":{"desktop":{"value":"100%"}}}},"buttonOne":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"buttonTwo":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"scrollDown":{"decoration":{"icon":{"desktop":{"value":{"show":"off","unicode":"&#x3b;","type":"divi","weight":"400","size":"50px"}}}}}}
```
