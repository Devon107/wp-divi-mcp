# divi/woocommerce-product-additional-info — Woo Product Information

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_additional_info`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `content` — advanced: product; decoration: bodyFont
- `attribute` — decoration: font
- `elements` — advanced: showTitle
- `table` — decoration: background, border, boxShadow, spacing
- `tableCell` — decoration: background, border, boxShadow, spacing
- `tableRow` — decoration: background, border, boxShadow, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `elements.advanced.showTitle` | Show Title | "on" or "off" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `form_field_custom_margin` → `tableCell.decoration.spacing.*.margin`
- `form_field_custom_padding` → `tableCell.decoration.spacing.*.padding`
- `product` → `content.advanced.product.*`
- `show_title` → `elements.advanced.showTitle.*`

## Font groups

- `attribute` → `attribute.decoration.font`
- `body` → `content.decoration.bodyFont.body`
- `header` → `title.decoration.font`
- `link` → `content.decoration.bodyFont.link`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Information"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}},"elements":{"advanced":{"showTitle":{"desktop":{"value":"on"}}}}}
```
