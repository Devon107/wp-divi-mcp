# divi/woocommerce-product-description — Woo Product Description

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_description`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: descriptionType, product; decoration: bodyFont, headingFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `content.advanced.descriptionType` | Description Type | one of: "description", "short_description" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `description_type` → `content.advanced.descriptionType.*`
- `product` → `content.advanced.product.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `header` → `content.decoration.headingFont.h1`
- `header_2` → `content.decoration.headingFont.h2`
- `header_3` → `content.decoration.headingFont.h3`
- `header_4` → `content.decoration.headingFont.h4`
- `header_5` → `content.decoration.headingFont.h5`
- `header_6` → `content.decoration.headingFont.h6`
- `link` → `content.decoration.bodyFont.link`
- `ol` → `content.decoration.bodyFont.ol`
- `quote` → `content.decoration.bodyFont.quote`
- `ul` → `content.decoration.bodyFont.ul`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Description"}}}},"content":{"advanced":{"descriptionType":{"desktop":{"value":"short_description"}},"product":{"desktop":{"value":"dynamic"}}}}}
```
