# divi/fullwidth-portfolio — Post Carousel

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_portfolio`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: autoRotate, autoRotateSpeed, elements, html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `portfolio` — innerContent; advanced: layout, showDate, showTitle; decoration: font
- `title` — innerContent; decoration: font
- `overlay` — decoration: background, icon
- `image` — decoration: image
- `meta` — decoration: font
- `portfolioGrid` — (no settings)
- `portfolioItemTitle` — (no settings)

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.autoRotate` | Automatic Carousel Rotation | "on" or "off" |
| `module.advanced.autoRotateSpeed` | Automatic Carousel Rotation Speed (in ms) | string |
| `portfolio.innerContent.includedCategories` | Included Categories | checkboxes field (not responsive) |
| `portfolio.innerContent.type` | Post Type | select field (not responsive) |
| `portfolio.innerContent.postsNumber` | Post Count | string (not responsive) |
| `portfolio.advanced.layout` | Layout | one of: "on", "off" (not responsive) |
| `portfolio.advanced.showTitle` | Show Title | "on" or "off" |
| `portfolio.advanced.showDate` | Show Date | "on" or "off" |
| `title.innerContent` | Carousel Title | string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `auto` → `module.advanced.autoRotate.*`
- `auto_speed` → `module.advanced.autoRotateSpeed.*`
- `hover_icon` → `overlay.decoration.icon.*`
- `hover_overlay_color` → `overlay.decoration.background.*.color`
- `zoom_icon_color` → `overlay.decoration.icon.*.color`
- `include_categories` → `portfolio.innerContent.*.includedCategories`
- `posts_number` → `portfolio.innerContent.*.postsNumber`
- `show_date` → `portfolio.advanced.showDate.*`
- `show_title` → `portfolio.advanced.showTitle.*`
- `fullwidth` → `portfolio.advanced.layout.*`
- `title` → `title.innerContent.*`
- `title_level` → `portfolio.decoration.font.font.*.headingLevel`
- `portfolio_header_level` → `title.decoration.font.font.*.headingLevel`
- `post_type` → `portfolio.innerContent.*.type`

## Font groups

- `caption` → `meta.decoration.font`
- `title` → `portfolio.decoration.font`
- `portfolio_header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Post Carousel"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light","orientation":"center"}}}},"autoRotate":{"desktop":{"value":"off"}},"autoRotateSpeed":{"desktop":{"value":"7000"}}}},"portfolio":{"innerContent":{"desktop":{"value":{"postsNumber":"","type":"post"}}},"advanced":{"showTitle":{"desktop":{"value":"on"}},"showDate":{"desktop":{"value":"on"}},"layout":{"desktop":{"value":"on"}}},"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h3"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"overlay":{"decoration":{"icon":{"desktop":{"value":{"unicode":""}}}}},"portfolioGrid":{"advanced":{"flexType":{"desktop":{"value":"6_24"},"tabletWide":{"value":"12_24"},"tablet":{"value":"12_24"},"phoneWide":{"value":"24_24"},"phone":{"value":"24_24"}}}}}
```
