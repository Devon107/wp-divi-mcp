# divi/woocommerce-breadcrumb — Woo Breadcrumbs

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_breadcrumb`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: homeText, homeUrl, product, separator; decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `content.advanced.homeText` | Home Text | string |
| `content.advanced.homeUrl` | Home Link | string |
| `content.advanced.separator` | Separator | string (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `breadcrumb_home_text` → `content.advanced.homeText.*`
- `breadcrumb_home_url` → `content.advanced.homeUrl.*`
- `breadcrumb_separator` → `content.advanced.separator.*`
- `product` → `content.advanced.product.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `link` → `content.decoration.bodyFont.link`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Breadcrumbs"}}}},"content":{"advanced":{"product":{"desktop":{"value":"dynamic"}},"homeUrl":{"desktop":{"value":"dynamic"}},"homeText":{"desktop":{"value":"Home"}},"separator":{"desktop":{"value":" / "}}}}}
```
