# divi/woocommerce-cart-products — Woo Cart Products

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_cart_products`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `elements` — advanced: showCouponCode, showProductImage, showRemoveItemIcon, showUpdateCartButton
- `layout` — advanced: rowLayout
- `tableHeader` — decoration: background, font
- `content` — decoration: bodyFont
- `table` — advanced: collapseTableGuttersBorders, horizontalGutterWidth, verticalGutterWidth; decoration: background, border, boxShadow, spacing
- `tableRow` — decoration: background, border, boxShadow
- `tableCell` — advanced: alternatingBackground; decoration: background, border, boxShadow, spacing
- `removeIcon` — decoration: background, font
- `image` — decoration: image
- `field` — (no settings)
- `button` — decoration: button
- `disabledButton` — decoration: button

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `elements.advanced.showProductImage` | Show Product Image | "on" or "off" |
| `elements.advanced.showCouponCode` | Show Coupon Code | "on" or "off" |
| `elements.advanced.showUpdateCartButton` | Show Update Cart Button | "on" or "off" |
| `elements.advanced.showRemoveItemIcon` | Show Remove Item Icon | "on" or "off" |
| `layout.advanced.rowLayout` | Product Row Layout | one of: "default", "horizontal", "vertical" |
| `table.advanced.collapseTableGuttersBorders` | Collapse Table Gutters and Borders | "on" or "off" |
| `table.advanced.horizontalGutterWidth` | Horizontal Gutters | number or size string (e.g. "10", "24px") |
| `table.advanced.verticalGutterWidth` | Vertical Gutters | number or size string (e.g. "10", "24px") |
| `tableCell.advanced.alternatingBackground` | Table Cell Alternating Background Color | color string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `collapse_table_gutters_borders` → `table.advanced.collapseTableGuttersBorders.*`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `horizontal_gutter_width` → `table.advanced.horizontalGutterWidth.*`
- `image_max_width` → `image.decoration.sizing.*.maxWidth`
- `placeholder_color` → `field.advanced.placeholder.font.font.*.color`
- `row_layout` → `layout.advanced.rowLayout.*`
- `show_coupon_code` → `elements.advanced.showCouponCode.*`
- `show_product_image` → `elements.advanced.showProductImage.*`
- `show_remove_item_icon` → `elements.advanced.showRemoveItemIcon.*`
- `show_update_cart_button` → `elements.advanced.showUpdateCartButton.*`
- `table_cell_alternating_background_color` → `tableCell.advanced.alternatingBackground.*`
- `table_header_background_color` → `tableHeader.decoration.background.*.color`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`
- `vertical_gutter_width` → `table.advanced.verticalGutterWidth.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `form_field` → `field.decoration.font`
- `icon` → `removeIcon.decoration.font`
- `link` → `content.decoration.bodyFont.link`
- `table_header` → `tableHeader.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"meta":{"adminLabel":{"desktop":{"value":"Woo Cart Products"}}}},"elements":{"advanced":{"showCouponCode":{"desktop":{"value":"on"}},"showProductImage":{"desktop":{"value":"on"}},"showRemoveItemIcon":{"desktop":{"value":"on"}},"showUpdateCartButton":{"desktop":{"value":"on"}}}},"layout":{"advanced":{"rowLayout":{"desktop":{"value":"default"}}}},"table":{"advanced":{"collapseTableGuttersBorders":{"desktop":{"value":"off"}},"horizontalGutterWidth":{"desktop":{"value":"0px"}},"verticalGutterWidth":{"desktop":{"value":"0px"}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"disabledButton":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
