# divi/woocommerce-product-meta — Woo Product Meta

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_meta`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: product, separator; decoration: bodyFont
- `elements` — advanced: showCategories, showSku, showTags
- `layout` — advanced: metaLayout

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `content.advanced.separator` | Separator | string |
| `elements.advanced.showSku` | Show SKU | "on" or "off" |
| `elements.advanced.showCategories` | Show Categories | "on" or "off" |
| `elements.advanced.showTags` | Show Tags | "on" or "off" |
| `layout.advanced.metaLayout` | Meta Layout | one of: "stacked", "inline" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `meta_layout` → `layout.advanced.metaLayout.*`
- `product` → `content.advanced.product.*`
- `separator` → `content.advanced.separator.*`
- `show_categories` → `elements.advanced.showCategories.*`
- `show_sku` → `elements.advanced.showSku.*`
- `show_tags` → `elements.advanced.showTags.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `link` → `content.decoration.bodyFont.link`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Meta"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}},"separator":{"desktop":{"value":"/"}}}},"elements":{"advanced":{"showCategories":{"desktop":{"value":"on"}},"showSku":{"desktop":{"value":"on"}},"showTags":{"desktop":{"value":"on"}}}},"layout":{"advanced":{"metaLayout":{"desktop":{"value":"inline"}}}}}
```
