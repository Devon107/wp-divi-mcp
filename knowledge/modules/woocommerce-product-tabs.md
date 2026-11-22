# divi/woocommerce-product-tabs — Woo Product Tabs

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_tabs`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — advanced: includeTabs, product; decoration: bodyFont
- `activeTab` — decoration: background, font
- `inactiveTab` — decoration: background
- `tab` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.product` | Product |  field (not responsive) |
| `content.advanced.includeTabs` | Included Tabs | checkboxes field |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `active_tab_background_color` → `activeTab.decoration.background.*.color`
- `active_tab_text_color` → `activeTab.decoration.font.font.*.color`
- `inactive_tab_background_color` → `inactiveTab.decoration.background.*.color`
- `include_tabs` → `content.advanced.includeTabs.*`
- `product` → `content.advanced.product.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `tab` → `tab.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Product Tabs"}}}},"content":{"advanced":{"includeTabs":{"desktop":{"value":"dynamic"}},"product":{"desktop":{"value":"dynamic"}}}}}
```
