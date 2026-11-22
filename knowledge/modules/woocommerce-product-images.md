# divi/woocommerce-product-images — Woo Product Images

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_images`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `image` — decoration: image
- `content` — advanced: product
- `elements` — advanced: showProductGallery, showProductImage, showSaleBadge
- `saleBadge` — decoration: background, border, boxShadow, font, spacing
- `galleryGrid` — decoration: layout

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `elements.advanced.showProductImage` | Show Featured Image | "on" or "off" (not responsive) |
| `elements.advanced.showProductGallery` | Show Gallery Images | "on" or "off" (not responsive) |
| `elements.advanced.showSaleBadge` | Show Sale Badge | "on" or "off" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `force_fullwidth` → `image.advanced.forceFullwidth.*`
- `form_field_custom_margin` → `saleBadge.decoration.spacing.*.margin`
- `form_field_custom_padding` → `saleBadge.decoration.spacing.*.padding`
- `product` → `content.advanced.product.*`
- `sale_badge_color` → `saleBadge.decoration.background.*.color`
- `show_product_gallery` → `elements.advanced.showProductGallery.*`
- `show_product_image` → `elements.advanced.showProductImage.*`
- `show_sale_badge` → `elements.advanced.showSaleBadge.*`

## Font groups

- `sale_badge` → `saleBadge.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Images"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}},"elements":{"advanced":{"showProductGallery":{"desktop":{"value":"on"}},"showProductImage":{"desktop":{"value":"on"}},"showSaleBadge":{"desktop":{"value":"on"}}}},"galleryGrid":{"decoration":{"layout":{"desktop":{"value":{"columnGap":"30px","display":"grid","gridColumnCount":"4","rowGap":"30px"}}}}}}
```
