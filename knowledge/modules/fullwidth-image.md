# divi/fullwidth-image — Fullwidth Image

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_image`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `image` — innerContent; advanced: lightbox, overlay, overlayIcon

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
| `image.innerContent.linkUrl` | Image Link URL | string (not responsive) |
| `image.innerContent.linkTarget` | Image Link Target | one of: "off", "on" (not responsive) |
| `image.advanced.lightbox` | Open in Lightbox | "on" or "off" (not responsive) |
| `image.advanced.use` | Image Overlay | "on" or "off" (not responsive) |
| `image.advanced.backgroundColor` | Hover Overlay Color | color string |
| `image.advanced.hoverIcon` | Hover Icon Picker | icon {"unicode","type","weight"} |
| `image.advanced.iconColor` | Overlay Icon Color | color string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `alt` → `image.innerContent.*.alt`
- `hover_icon` → `image.advanced.overlayIcon.*.hoverIcon`
- `hover_overlay_color` → `image.advanced.overlay.*.backgroundColor`
- `overlay_icon_color` → `image.advanced.overlayIcon.*.iconColor`
- `show_bottom_space` → `module.advanced.spacing.*.showBottomSpace`
- `show_in_lightbox` → `image.advanced.lightbox.*`
- `src` → `image.innerContent.*.src`
- `title_text` → `image.innerContent.*.titleText`
- `url` → `image.innerContent.*.linkUrl`
- `url_new_window` → `image.innerContent.*.linkTarget`
- `use_overlay` → `image.advanced.overlay.*.use`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Fullwidth Image"}}}},"image":{"innerContent":{"desktop":{"value":{"linkTarget":"off"}}},"advanced":{"lightbox":{"desktop":{"value":"off"}},"overlay":{"desktop":{"value":{"use":"off"}}}}}}
```
