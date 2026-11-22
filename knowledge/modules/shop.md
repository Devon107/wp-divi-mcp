# divi/shop — Woo Products

- Category: `module`
- Divi 4 shortcode: `et_pb_shop`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `image` — decoration: image
- `overlay` — decoration: background
- `overlayIcon` — decoration: icon
- `content` — advanced: columnsNumber, includeCategories, offsetNumber, orderby, postsNumber, type, useCurrentLoop
- `elements` — advanced: showImage, showName, showPagination, showPrice, showRating, showSaleBadge
- `price` — decoration: font
- `rating` — decoration: font
- `saleBadge` — decoration: background, border, boxShadow, font, spacing
- `salePrice` — decoration: font
- `pagination` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.type` | Product Type | one of: "recent", "latest", "featured", "sale", "best_selling", "top_rated", "product_category" (not responsive) |
| `content.advanced.useCurrentLoop` | Use Current Loop | "on" or "off" (not responsive) |
| `content.advanced.postsNumber` | Product Count | string (not responsive) |
| `content.advanced.columnsNumber` | Column Layout | one of: "0", "1", "2", "3", "4", "5", "6" (not responsive) |
| `content.advanced.orderby` | Order | one of: "default", "menu_order", "popularity", "rating", "date", "date-desc", "price", "price-desc" (not responsive) |
| `content.advanced.offsetNumber` | Product Offset Number | string (not responsive) |
| `content.advanced.includeCategories` | Included Categories | checkboxes field (not responsive) |
| `elements.advanced.showPagination` | Show Pagination | "on" or "off" |
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
- `sale_badge_color` → `saleBadge.decoration.background.*.color`
- `show_image` → `elements.advanced.showImage.*`
- `show_name` → `elements.advanced.showName.*`
- `show_pagination` → `elements.advanced.showPagination.*`
- `show_price` → `elements.advanced.showPrice.*`
- `show_rating` → `elements.advanced.showRating.*`
- `show_sale_badge` → `elements.advanced.showSaleBadge.*`
- `type` → `content.advanced.type.*`
- `use_current_loop` → `content.advanced.useCurrentLoop.*`

## Font groups

- `price` → `price.decoration.font`
- `rating` → `rating.decoration.font`
- `sale_badge` → `saleBadge.decoration.font`
- `sale_price` → `salePrice.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Products"}}}},"content":{"advanced":{"type":{"desktop":{"value":"recent"}},"useCurrentLoop":{"desktop":{"value":"off"}},"columnsNumber":{"desktop":{"value":"0"}},"offsetNumber":{"desktop":{"value":"0"}},"orderby":{"desktop":{"value":"default"}},"postsNumber":{"desktop":{"value":"12"}}}},"elements":{"advanced":{"showPagination":{"desktop":{"value":"off"}},"showName":{"desktop":{"value":"on"}},"showImage":{"desktop":{"value":"on"}},"showPrice":{"desktop":{"value":"on"}},"showRating":{"desktop":{"value":"on"}},"showSaleBadge":{"desktop":{"value":"on"}}}}}
```
