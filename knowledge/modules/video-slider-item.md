# divi/video-slider-item — Video Slider Item

- Category: `child-module`
- Divi 4 shortcode: `et_pb_video_slider_item`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html; decoration: animation, attributes, conditions, disabledOn, filters, interactions, order, overflow, position, sticky, transform, transition, zIndex
- `overlay` — innerContent; decoration: background
- `playIcon` — decoration: icon
- `sliderControls` — advanced: color
- `video` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `overlay.innerContent` |  | see legacy mapping |
| `sliderControls.advanced.color` | Slider Arrows Color | one of: "dark", "light" |
| `video.innerContent.src` | Video MP4 File Or Youtube URL | URL string |
| `video.innerContent.webm` | Video WEBM File | URL string |

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
- `background_layout` → `sliderControls.advanced.*.color`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Video Slider Item"}}}},"overlay":{"advanced":{"desktop":{"value":{"showImageOverlay":"off"}}}},"playIcon":{"decoration":{"icon":{"desktop":{"value":{"size":"96px","useSize":"off"}}}}},"sliderControls":{"advanced":{"desktop":{"value":{"color":"dark"}}}}}
```
