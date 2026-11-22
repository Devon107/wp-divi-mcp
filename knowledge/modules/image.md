# divi/image — Image

- Category: `module`
- Divi 4 shortcode: `et_pb_image`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: align, elements, html, loop, sizing, spacing; decoration: animation, attributes, background, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sticky, transform, transition, zIndex
- `image` — innerContent; advanced: lightbox, overlay, overlayIcon; decoration: border, boxShadow, fit

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.align` | Image Alignment | one of: "left", "center", "right" |
| `module.advanced.sizing.forceFullwidth` | Force Fullwidth | "on" or "off" |
| `module.advanced.showBottomSpace` | Show Space Below The Image | "on" or "off" |
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

- `align` → `module.advanced.align.*`
- `alt` → `image.innerContent.*.alt`
- `force_fullwidth` → `module.advanced.sizing.*.forceFullwidth`
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
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Image"}}},"advanced":{"spacing":{"desktop":{"value":{"showBottomSpace":"on"}}},"forceFullwidth":{"desktop":{"value":"off"}}}},"image":{"innerContent":{"desktop":{"value":{"linkTarget":"off"}}},"advanced":{"lightbox":{"desktop":{"value":"off"}},"overlay":{"desktop":{"value":{"use":"off"}}}}}}
```
