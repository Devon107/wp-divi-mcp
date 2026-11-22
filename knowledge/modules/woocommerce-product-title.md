# divi/woocommerce-product-title — Woo Product Title

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_title`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `content` — advanced: product

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

- `background_layout` → `module.advanced.text.text.*.color`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `product` → `content.advanced.product.*`

## Font groups

- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"meta":{"adminLabel":{"desktop":{"value":"Woo Product Title"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h1"}}}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}}}
```
