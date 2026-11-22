# divi/fullwidth-post-slider — Fullwidth Post Slider

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_post_slider`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: auto, autoIgnoreHover, autoSpeed, html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `content` — advanced: showOnMobile; decoration: bodyFont, sizing
- `meta` — advanced: enable; decoration: font
- `image` — advanced: enable, placement, showOnMobile; decoration: border, boxShadow, filters
- `button` — innerContent; advanced: enable, showOnMobile; decoration: background, border, boxShadow, button, font, sizing, spacing
- `post` — advanced: categories, contentSource, excerptLength, excerptManual, number, offset, orderby
- `arrows` — advanced: color, enable
- `pagination` — advanced: enable; decoration: background
- `slideOverlay` — advanced: use; decoration: background
- `contentOverlay` — advanced: use; decoration: background, border

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.auto` | Automatic Animation | "on" or "off" (not responsive) |
| `module.advanced.autoSpeed` | Automatic Animation Speed (in ms) | string (not responsive) |
| `module.advanced.autoIgnoreHover` | Continue Automatic Slide on Hover | "on" or "off" (not responsive) |
| `content.advanced.showOnMobile` | Show Content On Mobile | "on" or "off" (not responsive) |
| `meta.advanced.enable` | Show Post Meta | "on" or "off" |
| `image.advanced.enable` | Show Featured Image | "on" or "off" |
| `image.advanced.placement` | Featured Image Placement | one of: "background", "left", "right", "top", "bottom" (not responsive) |
| `image.advanced.showOnMobile` | Show Image On Mobile | "on" or "off" (not responsive) |
| `button.innerContent.text` | Button | string |
| `button.advanced.enable` | Show Read More Button | "on" or "off" |
| `button.advanced.showOnMobile` | Show CTA On Mobile | "on" or "off" (not responsive) |
| `post.advanced.number` | Post Count | string (not responsive) |
| `post.advanced.categories` | Included Categories | checkboxes field (not responsive) |
| `post.advanced.orderby` | Order By | one of: "date_desc", "date_asc", "title_asc", "title_desc", "rand" (not responsive) |
| `post.advanced.contentSource` | Content Display | one of: "off", "on" |
| `post.advanced.excerptManual` | Use Post Excerpts | one of: "off", "on" (not responsive) |
| `post.advanced.excerptLength` | Excerpt Length | string (not responsive) |
| `post.advanced.offset` | Offset Number | string (not responsive) |
| `arrows.advanced.color` | Arrow Color | color string |
| `arrows.advanced.enable` | Show Arrows | "on" or "off" |
| `pagination.advanced.enable` | Show Controls | "on" or "off" |
| `slideOverlay.advanced.use` | Use Background Overlay | "on" or "off" (not responsive) |
| `contentOverlay.advanced.use` | Use Text Overlay | "on" or "off" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `more_text` → `button.innerContent.*.text`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `arrows_custom_color` → `arrows.advanced.color.*`
- `content_source` → `post.advanced.contentSource.*`
- `dot_nav_custom_color` → `pagination.decoration.background.*.color`
- `image_placement` → `image.advanced.placement.*`
- `orderby` → `post.advanced.orderby.*`
- `show_arrows` → `arrows.advanced.enable.*`
- `show_content_on_mobile` → `content.advanced.showOnMobile.*`
- `show_cta_on_mobile` → `button.advanced.showOnMobile.*`
- `show_image` → `image.advanced.enable.*`
- `show_image_video_mobile` → `image.advanced.showOnMobile.*`
- `show_meta` → `meta.advanced.enable.*`
- `show_more_button` → `button.advanced.enable.*`
- `use_current_loop` → `post.advanced.currentLoop.*`
- `posts_number` → `post.advanced.number.*`
- `include_categories` → `post.advanced.categories.*`
- `use_manual_excerpt` → `post.advanced.excerptManual.*`
- `excerpt_length` → `post.advanced.excerptLength.*`
- `show_pagination` → `pagination.advanced.enable.*`
- `offset_number` → `post.advanced.offset.*`
- `use_text_overlay` → `contentOverlay.advanced.use.*`
- `text_overlay_color` → `contentOverlay.decoration.background.*.color`
- `text_border_radius` → `contentOverlay.decoration.border.*.radius`
- `use_bg_overlay` → `slideOverlay.advanced.use.*`
- `bg_overlay_color` → `slideOverlay.decoration.background.*.color`
- `content_width` → `content.decoration.sizing.*.width`
- `content_max_width` → `content.decoration.sizing.*.maxWidth`
- `auto` → `module.advanced.auto.*`
- `auto_speed` → `module.advanced.autoSpeed.*`
- `auto_ignore_hover` → `module.advanced.autoIgnoreHover.*`

## Font groups

- `header` → `title.decoration.font`
- `body` → `content.decoration.bodyFont.body`
- `meta` → `meta.decoration.font`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ul` → `content.decoration.bodyFont.ul`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Fullwidth Post Slider"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"dark","orientation":"center"}}}},"autoSpeed":{"desktop":{"value":"7000"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"content":{"advanced":{"showOnMobile":{"desktop":{"value":"on"}}}},"meta":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"image":{"advanced":{"enable":{"desktop":{"value":"on"}},"placement":{"desktop":{"value":"background"}}}},"button":{"innerContent":{"desktop":{"value":{"text":"Read More"}}},"advanced":{"showOnMobile":{"desktop":{"value":"on"}},"enable":{"desktop":{"value":"on"}}},"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"post":{"advanced":{"number":{"desktop":{"value":"10"}},"orderby":{"desktop":{"value":"date_desc"}},"contentSource":{"desktop":{"value":"off"}},"excerptManual":{"desktop":{"value":"on"}},"excerptLength":{"desktop":{"value":"270"}},"offset":{"desktop":{"value":"0"}}}},"arrows":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"pagination":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"slideOverlay":{"advanced":{"use":{"desktop":{"value":"on"}}}}}
```
