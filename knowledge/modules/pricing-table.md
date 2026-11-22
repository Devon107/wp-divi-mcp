# divi/pricing-table — Pricing Table

- Category: `child-module`
- Divi 4 shortcode: `et_pb_pricing_table`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, featured, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, transform, transition, zIndex
- `currencyFrequency` — innerContent; decoration: font
- `subtitle` — innerContent; decoration: font
- `title` — innerContent; decoration: background, font
- `price` — innerContent; decoration: background, border, font
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `excluded` — decoration: font
- `content` — innerContent; advanced: bulletColor; decoration: bodyFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.featured` | Make This Table Featured | "on" or "off" (not responsive) |
| `currencyFrequency.innerContent.currency` | Currency | string |
| `currencyFrequency.innerContent.per` | Frequency | string |
| `subtitle.innerContent` | Subtitle | string |
| `title.innerContent` | Title | string |
| `price.innerContent` | Price | string |
| `button.innerContent.text` | Button | string |
| `button.innerContent.linkUrl` | Link URL | string (not responsive) |
| `button.innerContent.linkTarget` | Link Target | one of: "off", "on" (not responsive) |
| `content.innerContent` | Body | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `bullet_color` → `content.advanced.bulletColor.*`
- `button_text` → `button.innerContent.*.text`
- `button_url` → `button.innerContent.*.linkUrl`
- `url_new_window` → `button.innerContent.*.linkTarget`
- `content` → `content.innerContent.*`
- `currency` → `currencyFrequency.innerContent.*.currency`
- `featured` → `module.advanced.featured.*`
- `header_background_color` → `title.decoration.background.*.color`
- `per` → `currencyFrequency.innerContent.*.per`
- `price_background_color` → `price.decoration.background.*.color`
- `subtitle` → `subtitle.innerContent.*`
- `sum` → `price.innerContent.*`
- `title` → `title.innerContent.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `layout_grid_type` → `module.decoration.sizing.*.flexType`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `currency_frequency` → `currencyFrequency.decoration.font`
- `excluded` → `excluded.decoration.font`
- `header` → `title.decoration.font`
- `price` → `price.decoration.font`
- `subheader` → `subtitle.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"featured":{"desktop":{"value":"off"}}},"decoration":{"sizing":{"desktop":{"value":{"flexType":"12_24"}},"phone":{"value":{"flexType":"24_24"}}}}}}
```
