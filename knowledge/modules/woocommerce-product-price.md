# divi/woocommerce-product-price — Woo Product Price

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_price`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: product
- `price` — decoration: font
- `saleNewPrice` — decoration: font
- `saleOldPrice` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `product` → `content.advanced.product.*`

## Font groups

- `before_sale_price` → `saleOldPrice.decoration.font`
- `body` → `price.decoration.font`
- `sale_price` → `saleNewPrice.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Price"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}}}
```
