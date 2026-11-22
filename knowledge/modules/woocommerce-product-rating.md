# divi/woocommerce-product-rating — Woo Product Rating

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_rating`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: product
- `body` — decoration: font
- `elements` — advanced: showRating, showReviewsLink
- `layout` — advanced: layout
- `rating` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `elements.advanced.showRating` | Show Star Rating | "on" or "off" |
| `elements.advanced.showReviewsLink` | Show Customer Reviews Count | "on" or "off" |
| `layout.advanced.layout` | Rating Layout | one of: "stacked", "inline" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `layout` → `layout.advanced.layout.*`
- `product` → `content.advanced.product.*`
- `show_rating` → `elements.advanced.showRating.*`
- `show_reviews_link` → `elements.advanced.showReviewsLink.*`

## Font groups

- `body` → `body.decoration.font`
- `rating` → `rating.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Rating"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}},"elements":{"advanced":{"showRating":{"desktop":{"value":"on"}},"showReviewsLink":{"desktop":{"value":"on"}}}},"layout":{"advanced":{"layout":{"desktop":{"value":"inline"}}}}}
```
