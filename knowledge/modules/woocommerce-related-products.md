# divi/woocommerce-related-products — Woo Related Products

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_related_products`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `image` — decoration: image
- `content` — advanced: columnsNumber, includeCategories, offsetNumber, orderby, postsNumber, product
- `elements` — advanced: showImage, showName, showPrice, showRating, showSaleBadge
- `overlay` — decoration: background
- `overlayIcon` — decoration: icon
- `price` — decoration: font
- `productTitle` — decoration: font
- `rating` — decoration: font
- `saleBadge` — decoration: background, border, boxShadow, font, spacing
- `salePrice` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `content.advanced.postsNumber` | Product Count | string (not responsive) |
| `content.advanced.columnsNumber` | Column Layout | one of: "1", "2", "3", "4", "5", "6" (not responsive) |
| `content.advanced.orderby` | Order | one of: "default", "menu_order", "popularity", "date", "date-desc", "price", "price-desc" (not responsive) |
| `content.advanced.includeCategories` | Included Categories | checkboxes field (not responsive) |
| `content.advanced.offsetNumber` | Product Offset Number | string (not responsive) |
| `elements.advanced.showName` | Show Name | "on" or "off" |
| `elements.advanced.showImage` | Show Image | "on" or "off" |
| `elements.advanced.showPrice` | Show Price | "on" or "off" |
| `elements.advanced.showRating` | Show Rating | "on" or "off" |
| `elements.advanced.showSaleBadge` | Show Sale Badge | "on" or "off" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `columns_number` → `content.advanced.columnsNumber.*`
- `form_field_custom_margin` → `saleBadge.decoration.spacing.*.margin`
- `form_field_custom_padding` → `saleBadge.decoration.spacing.*.padding`
- `hover_icon` → `overlayIcon.decoration.icon.*`
- `hover_overlay_color` → `overlay.decoration.background.*.color`
- `icon_hover_color` → `overlayIcon.decoration.icon.*.color`
- `include_categories` → `content.advanced.includeCategories.*`
- `offset_number` → `content.advanced.offsetNumber.*`
- `orderby` → `content.advanced.orderby.*`
- `posts_number` → `content.advanced.postsNumber.*`
- `product` → `content.advanced.product.*`
- `sale_badge_color` → `saleBadge.decoration.background.*.color`
- `show_image` → `elements.advanced.showImage.*`
- `show_name` → `elements.advanced.showName.*`
- `show_price` → `elements.advanced.showPrice.*`
- `show_rating` → `elements.advanced.showRating.*`
- `show_sale_badge` → `elements.advanced.showSaleBadge.*`

## Font groups

- `price` → `price.decoration.font`
- `product_title` → `productTitle.decoration.font`
- `rating` → `rating.decoration.font`
- `sale_badge` → `saleBadge.decoration.font`
- `sale_price` → `salePrice.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Related Products"}}}},"content":{"advanced":{"columnsNumber":{"desktop":{"value":"dynamic"}},"offsetNumber":{"desktop":{"value":"0"}},"orderby":{"desktop":{"value":"default"}},"postsNumber":{"desktop":{"value":"dynamic"}},"product":{"desktop":{"value":"dynamic"}}}},"image":{"decoration":{"border":{"desktop":{"value":{"styles":{"all":{"style":"solid","width":"0px"}}}}}}},"elements":{"advanced":{"showImage":{"desktop":{"value":"on"}},"showName":{"desktop":{"value":"on"}},"showPrice":{"desktop":{"value":"on"}},"showRating":{"desktop":{"value":"on"}},"showSaleBadge":{"desktop":{"value":"on"}}}}}
```
