# divi/woocommerce-cart-notice — Woo Notice

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_cart_notice`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: pageType, product; decoration: bodyFont
- `button` — decoration: button
- `field` — advanced: width
- `form` — decoration: background, border, boxShadow, spacing
- `title` — decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.pageType` | Page Type | one of: "product", "cart", "checkout" |
| `content.advanced.product` | Product |  field (not responsive) |
| `field.advanced.width` | Fields Width | one of: "default", "fullwidth", "2_column" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `fields_width` → `field.advanced.width.*`
- `form_field_custom_margin` → `form.decoration.spacing.*.margin`
- `form_field_custom_padding` → `form.decoration.spacing.*.padding`
- `page_type` → `content.advanced.pageType.*`
- `placeholder_color` → `field.advanced.placeholder.font.font.*.color`
- `product` → `content.advanced.product.*`
- `required_field_indicator_color` → `fieldLabels.advanced.requiredFieldIndicatorColor.*`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`

## Font groups

- `content` → `content.decoration.bodyFont.body`
- `error` → `error.decoration.font`
- `field_label` → `fieldLabels.decoration.font`
- `form_field` → `field.decoration.font`
- `link` → `content.decoration.bodyFont.link`
- `title` → `title.decoration.bodyFont.body`
- `title_link` → `title.decoration.bodyFont.link`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Notice"}}},"decoration":{"spacing":{"desktop":{"value":{"margin":{"bottom":"2em","left":"0em","right":"0em","syncHorizontal":"off","syncVertical":"off","top":"0em"},"padding":{"bottom":"15px","left":"15px","right":"15px","syncHorizontal":"off","syncVertical":"off","top":"15px"}}}}}},"content":{"advanced":{"pageType":{"desktop":{"value":"dynamic"}},"product":{"desktop":{"value":"dynamic"}}}},"field":{"advanced":{"width":{"desktop":{"value":"default"}}}},"button":{"decoration":{"background":{"desktop":{"value":{"color":"#ffffff","enableColor":"on"}}},"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
