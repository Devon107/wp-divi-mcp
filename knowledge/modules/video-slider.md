# divi/video-slider — Video Slider

- Category: `module`
- Divi 4 shortcode: `et_pb_video_slider`
- Allowed children: `divi/video-slider-item`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html; decoration: animation, attributes, background, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `video` — decoration: border, boxShadow
- `overlay` — advanced: showImageOverlay
- `playIcon` — decoration: background, icon
- `sliderControls` — advanced: color, useArrows, useThumbnails

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `overlay.advanced.showImageOverlay` | Show Image Overlays on Main Video | "on" or "off" |
| `sliderControls.advanced.color` | Slider Controls Color | one of: "light", "dark" |
| `sliderControls.advanced.useArrows` | Show Arrows | "on" or "off" |
| `sliderControls.advanced.useThumbnails` | Slider Controls | one of: "on", "off" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `src` → `video.innerContent.*.src`
- `src_webm` → `video.innerContent.*.webm`
- `image_src` → `thumbnail.innerContent.*.src`
- `thumbnail_overlay_color` → `overlay.decoration.background.*.color`
- `use_icon_font_size` → `playIcon.decoration.icon.*.useSize`
- `icon_font_size` → `playIcon.decoration.icon.*.size`
- `play_icon_color` → `playIcon.decoration.icon.*.color`
- `font_icon` → `playIcon.decoration.icon.*`
- `show_image_overlay` → `overlay.advanced.*.showImageOverlay`
- `controls_color` → `sliderControls.advanced.*.color`
- `show_thumbnails` → `sliderControls.advanced.*.useThumbnails`
- `show_arrows` → `sliderControls.advanced.*.useArrows`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Video Slider"}}}},"overlay":{"advanced":{"desktop":{"value":{"showImageOverlay":"off"}}},"decoration":{"background":{"desktop":{"value":{"color":"rgba(0,0,0,.6)"}}}}},"playIcon":{"decoration":{"icon":{"desktop":{"value":{"size":"96px","useSize":"off"}}}}},"sliderControls":{"advanced":{"desktop":{"value":{"color":"light","useArrows":"on","useThumbnails":"on"}}}}}
```
