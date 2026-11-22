# divi/woocommerce-product-add-to-cart — Woo Product Add To Cart

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_add_to_cart`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: product
- `button` — decoration: button
- `dropdownMenus` — (no settings)
- `elements` — advanced: showQuantity, showStock
- `field` — (no settings)
- `fieldLabels` — advanced: fieldLabelPosition

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `elements.advanced.showQuantity` | Show Quantity Field | "on" or "off" |
| `elements.advanced.showStock` | Show Stock | "on" or "off" |
| `fieldLabels.advanced.fieldLabelPosition` | Fields Label Position | one of: "inline", "stacked" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `field_label_position` → `fieldLabels.advanced.fieldLabelPosition.*`
- `form_field_custom_margin` → `dropdownMenus.decoration.spacing.*.margin`
- `form_field_custom_padding` → `dropdownMenus.decoration.spacing.*.padding`
- `product` → `content.advanced.product.*`
- `show_quantity` → `elements.advanced.showQuantity.*`
- `show_stock` → `elements.advanced.showStock.*`
- `use_focus_border_color` → `dropdownMenus.advanced.focusUseBorder.*`

## Font groups

- `dropdown_menus` → `dropdownMenus.decoration.font`
- `field_label` → `fieldLabels.decoration.font`
- `fields` → `field.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Add To Cart"}}},"advanced":{"text":{"text":{"desktop":{"value":{}}}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}},"elements":{"advanced":{"showQuantity":{"desktop":{"value":"on"}},"showStock":{"desktop":{"value":"on"}}}},"fieldLabels":{"advanced":{"fieldLabelPosition":{"desktop":{"value":"inline"}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on","onHover":"on","placement":"right","settings":{"unicode":"&#x35;","type":"divi","weight":"400"}}}}}}}}
```
