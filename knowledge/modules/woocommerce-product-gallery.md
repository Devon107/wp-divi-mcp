# divi/woocommerce-product-gallery — Woo Product Gallery

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_gallery`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `image` — advanced: galleryCaptions, galleryIds, galleryOrderby, postsNumber; decoration: border, boxShadow, filters, sizing
- `content` — advanced: postsNumber, product, showPagination, showTitleAndCaption
- `caption` — decoration: font
- `layout` — advanced: fullwidth, orientation
- `galleryGrid` — decoration: layout
- `pagination` — decoration: font
- `overlay` — decoration: background
- `overlayIcon` — decoration: icon

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field |
| `content.advanced.postsNumber` | Image Count | string (not responsive) |
| `content.advanced.showPagination` | Show Pagination | "on" or "off" |
| `content.advanced.showTitleAndCaption` | Show Title and Caption | "on" or "off" |
| `layout.advanced.fullwidth` | Layout | one of: "off", "on" (not responsive) |
| `layout.advanced.orientation` | Thumbnail Orientation | one of: "landscape", "portrait" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `fullwidth` → `layout.advanced.fullwidth.*`
- `gallery_captions` → `image.advanced.galleryCaptions.*`
- `gallery_ids` → `image.advanced.galleryIds.*`
- `gallery_orderby` → `image.advanced.galleryOrderby.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `hover_icon` → `overlayIcon.decoration.icon.*`
- `hover_overlay_color` → `overlay.decoration.background.*.color`
- `orientation` → `layout.advanced.orientation.*`
- `posts_number` → `content.advanced.postsNumber.*`
- `product` → `content.advanced.product.*`
- `show_pagination` → `content.advanced.showPagination.*`
- `show_title_and_caption` → `content.advanced.showTitleAndCaption.*`
- `zoom_icon_color` → `overlayIcon.decoration.icon.*.color`

## Font groups

- `caption` → `caption.decoration.font`
- `pagination` → `pagination.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Gallery"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}},"postsNumber":{"desktop":{"value":"4"}},"showPagination":{"desktop":{"value":"on"}},"showTitleAndCaption":{"desktop":{"value":"on"}}}},"layout":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"orientation":{"desktop":{"value":"landscape"}}}},"galleryGrid":{"decoration":{"layout":{"desktop":{"value":{"columnGap":"30px","display":"grid","gridColumnCount":"4","rowGap":"30px"}},"tablet":{"value":{"columnGap":"30px","gridColumnCount":"2","rowGap":"30px"}},"phone":{"value":{"columnGap":"30px","gridColumnCount":"1","rowGap":"30px"}}}}},"image":{"advanced":{"galleryOrderby":{"desktop":{"value":"off"}}}},"overlay":{"decoration":{"background":{"desktop":{"value":{"color":"rgba(255,255,255,0.9)"}}}}},"overlayIcon":{"decoration":{"icon":{"desktop":{"value":{"unicode":"&#x21;","type":"divi","weight":"400","color":"#333333"}}}}}}
```
