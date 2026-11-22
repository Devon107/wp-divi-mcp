# divi/pricing-tables — Pricing Tables

- Category: `module`
- Divi 4 shortcode: `et_pb_pricing_tables`
- Allowed children: `divi/pricing-table`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: background, font
- `subtitle` — decoration: font
- `price` — decoration: background, border, font
- `currencyFrequency` — decoration: font
- `content` — advanced: bulletColor, showBullet; decoration: bodyFont
- `excluded` — decoration: font
- `featuredTable` — advanced: showDropShadow; decoration: background
- `featuredTitle` — decoration: background, font
- `featuredContent` — advanced: bulletColor; decoration: font
- `featuredSubtitle` — decoration: font
- `featuredPrice` — decoration: background, font
- `featuredCurrencyFrequency` — decoration: font
- `featuredExcluded` — decoration: font
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `children` — (no settings)

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.advanced.bulletColor` | Bullet Color | color string |
| `content.advanced.showBullet` | Show Bullets | "on" or "off" |
| `featuredTable.advanced.showDropShadow` | Show Featured Drop Shadow | "on" or "off" |
| `featuredContent.advanced.bulletColor` | Featured Bullet Color | color string |
| `button.innerContent` |  | see legacy mapping |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `bullet_color` → `content.advanced.bulletColor.*`
- `featured_table_background_color` → `featuredTable.decoration.background.*.color`
- `featured_table_bullet_color` → `featuredContent.advanced.bulletColor.*`
- `featured_table_currency_frequency_text_color` → `featuredCurrencyFrequency.decoration.font.font.*.color`
- `featured_table_excluded_text_color` → `featuredExcluded.decoration.font.font.*.color`
- `featured_table_header_background_color` → `featuredTitle.decoration.background.*.color`
- `featured_table_header_text_color` → `featuredTitle.decoration.font.font.*.color`
- `featured_table_price_background_color` → `featuredPrice.decoration.background.*.color`
- `featured_table_price_color` → `featuredPrice.decoration.font.font.*.color`
- `featured_table_subheader_text_color` → `featuredSubtitle.decoration.font.font.*.color`
- `featured_table_text_color` → `featuredContent.decoration.font.font.*.color`
- `header_background_color` → `title.decoration.background.*.color`
- `price_background_color` → `price.decoration.background.*.color`
- `show_bullet` → `content.advanced.showBullet.*`
- `show_featured_drop_shadow` → `featuredTable.advanced.showDropShadow.*`
- `button_rel` → `children.button.innerContent.*.rel`
- `header_level` → `title.decoration.font.font.*.headingLevel`

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
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Pricing Tables"}}}},"content":{"advanced":{"showBullet":{"desktop":{"value":"on"}}}},"featuredTable":{"advanced":{"showDropShadow":{"desktop":{"value":"on"}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}}}
```
