# divi/fullwidth-slider — Fullwidth Slider

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_slider`
- Allowed children: `divi/slide`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: auto, autoIgnoreHover, autoSpeed, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `arrows` — advanced: color, show
- `pagination` — advanced: show
- `title` — decoration: font
- `button` — decoration: button
- `content` — decoration: bodyFont, sizing
- `children` — advanced: button, content, contentOverlay, slideOverlay; decoration: background, border
- `image` — advanced: showOnMobile; decoration: border, boxShadow
- `dotNav` — decoration: background

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.auto` | Automatic Animation | "on" or "off" (not responsive) |
| `module.advanced.autoSpeed` | Automatic Animation Speed (in ms) | string (not responsive) |
| `module.advanced.autoIgnoreHover` | Continue Automatic Slide on Hover | "on" or "off" (not responsive) |
| `arrows.advanced.color` | Arrow Color | color string |
| `arrows.advanced.show` | Show Arrows | "on" or "off" |
| `pagination.advanced.show` | Show Controls | "on" or "off" |
| `children.slideOverlay.advanced.use` | Use Background Overlay | "on" or "off" (not responsive) |
| `children.contentOverlay.advanced.use` | Use Text Overlay | "on" or "off" (not responsive) |
| `children.content.advanced.showOnMobile` | Show Content On Mobile | "on" or "off" (not responsive) |
| `children.button.advanced.showOnMobile` | Show CTA On Mobile | "on" or "off" (not responsive) |
| `image.advanced.showOnMobile` | Show Image / Video On Mobile | "on" or "off" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `auto` → `module.advanced.auto.*`
- `auto_speed` → `module.advanced.autoSpeed.*`
- `auto_ignore_hover` → `module.advanced.autoIgnoreHover.*`
- `arrows_custom_color` → `arrows.advanced.color.*`
- `dot_nav_custom_color` → `dotNav.decoration.background.*.color`
- `show_arrows` → `arrows.advanced.show.*`
- `show_content_on_mobile` → `children.content.advanced.showOnMobile.*`
- `show_cta_on_mobile` → `children.button.advanced.showOnMobile.*`
- `show_image_video_mobile` → `image.advanced.showOnMobile.*`
- `show_pagination` → `pagination.advanced.show.*`
- `use_bg_overlay` → `children.slideOverlay.advanced.use.*`
- `bg_overlay_color` → `children.slideOverlay.decoration.background.*.color`
- `use_text_overlay` → `children.contentOverlay.advanced.use.*`
- `text_overlay_color` → `children.contentOverlay.decoration.background.*.color`
- `text_border_radius` → `children.contentOverlay.decoration.border.*.radius`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `content_width` → `content.decoration.sizing.*.width`
- `content_max_width` → `content.decoration.sizing.*.maxWidth`
- `button_rel` → `children.button.innerContent.*.rel`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Fullwidth Slider"}}},"advanced":{"autoSpeed":{"desktop":{"value":"7000"}}}},"arrows":{"advanced":{"show":{"desktop":{"value":"on"}}}},"pagination":{"advanced":{"show":{"desktop":{"value":"on"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"children":{"button":{"advanced":{"showOnMobile":{"desktop":{"value":"on"}}}},"content":{"advanced":{"showOnMobile":{"desktop":{"value":"on"}}}}}}
```
