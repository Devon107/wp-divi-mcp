# divi/gallery — Gallery

- Category: `module`
- Divi 4 shortcode: `et_pb_gallery`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: auto, autoSpeed, elements, fullwidth, html, link, postsNumber, showTitleAndCaption, text; decoration: animation, attributes, background, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `caption` — decoration: font
- `pagination` — advanced: showPagination; decoration: font
- `item` — decoration: border
- `image` — advanced: galleryIds, galleryOrderby, orientation; decoration: image
- `overlay` — innerContent; advanced: hoverOverlayColor, zoomIconColor
- `galleryGrid` — decoration: layout

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.fullwidth` | Layout | one of: "off", "on" (not responsive) |
| `module.advanced.postsNumber` | Image Count | string (not responsive) |
| `module.advanced.showTitleAndCaption` | Show Title and Caption | "on" or "off" |
| `module.advanced.auto` | Automatic Animation | "on" or "off" (not responsive) |
| `module.advanced.autoSpeed` | Automatic Animation Speed (in ms) | string (not responsive) |
| `pagination.advanced.showPagination` | Show Pagination | "on" or "off" |
| `image.advanced.orientation` | Thumbnail Orientation | one of: "landscape", "portrait" (not responsive) |
| `image.advanced.galleryIds` | Images | upload-gallery field (not responsive) |
| `image.advanced.galleryOrderby` | Image Order | one of: "default", "rand" (not responsive) |
| `overlay.innerContent.icon` | Overlay Icon | icon {"unicode","type","weight"} |
| `overlay.advanced.zoomIconColor` | Overlay Icon Color | color string |
| `overlay.advanced.hoverOverlayColor` | Overlay Background Color | color string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `auto` → `module.advanced.auto.*`
- `auto_speed` → `module.advanced.autoSpeed.*`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `fullwidth` → `module.advanced.fullwidth.*`
- `gallery_captions` → `image.advanced.galleryCaptions.*`
- `gallery_ids` → `image.advanced.galleryIds.*`
- `gallery_orderby` → `image.advanced.galleryOrderby.*`
- `hover_icon` → `overlay.innerContent.*.icon`
- `hover_overlay_color` → `overlay.advanced.hoverOverlayColor.*`
- `layout_display` → `galleryGrid.decoration.layout.*.display`
- `orientation` → `image.advanced.orientation.*`
- `posts_number` → `module.advanced.postsNumber.*`
- `show_pagination` → `pagination.advanced.showPagination.*`
- `show_title_and_caption` → `module.advanced.showTitleAndCaption.*`
- `zoom_icon_color` → `overlay.advanced.zoomIconColor.*`

## Font groups

- `title` → `title.decoration.font`
- `caption` → `caption.decoration.font`
- `pagination` → `pagination.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Gallery"}}},"advanced":{"fullwidth":{"desktop":{"value":"off"}},"showTitleAndCaption":{"desktop":{"value":"on"}},"postsNumber":{"desktop":{"value":"4"}},"text":{"text":{"desktop":{"value":{"color":"light"}}}},"autoSpeed":{"desktop":{"value":"7000"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"pagination":{"advanced":{"showPagination":{"desktop":{"value":"on"}}}},"galleryGrid":{"decoration":{"layout":{"desktop":{"value":{"display":"grid"}}}}},"image":{"advanced":{"galleryOrderby":{"desktop":{"value":"default"}},"orientation":{"desktop":{"value":"landscape"}}}}}
```
