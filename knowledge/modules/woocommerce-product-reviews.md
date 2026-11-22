# divi/woocommerce-product-reviews — Woo Product Reviews

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_reviews`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `image` — decoration: image
- `content` — advanced: product
- `button` — decoration: button
- `comment` — decoration: font
- `elements` — advanced: showAvatar, showCount, showMeta, showRating
- `field` — (no settings)
- `formTitle` — decoration: font
- `meta` — decoration: font
- `reviewCount` — decoration: font
- `starRating` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field |
| `elements.advanced.showAvatar` | Show Author Avatar | "on" or "off" |
| `elements.advanced.showCount` | Show Comment Count | "on" or "off" |
| `elements.advanced.showMeta` | Show Meta | "on" or "off" |
| `elements.advanced.showRating` | Show Rating | "on" or "off" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `header_level` → `reviewCount.decoration.font.font.*.headingLevel`
- `product` → `content.advanced.product.*`
- `show_avatar` → `elements.advanced.showAvatar.*`
- `show_count` → `elements.advanced.showCount.*`
- `show_meta` → `elements.advanced.showMeta.*`
- `show_rating` → `elements.advanced.showRating.*`
- `show_reply` → `elements.advanced.showReply.*`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`

## Font groups

- `body` → `comment.decoration.font`
- `form_field` → `field.decoration.font`
- `header` → `reviewCount.decoration.font`
- `meta` → `meta.decoration.font`
- `rating` → `starRating.decoration.font`
- `title` → `formTitle.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"meta":{"adminLabel":{"desktop":{"value":"Woo Product Reviews"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}}}},"elements":{"advanced":{"showAvatar":{"desktop":{"value":"on"}},"showCount":{"desktop":{"value":"on"}},"showMeta":{"desktop":{"value":"on"}},"showRating":{"desktop":{"value":"on"}},"showReply":{"desktop":{"value":"on"}}}},"reviewCount":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
