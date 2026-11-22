# divi/portfolio — Portfolio

- Category: `module`
- Divi 4 shortcode: `et_pb_portfolio`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `portfolio` — innerContent; advanced: showCategories, showPagination, showTitle
- `portfolioGrid` — decoration: layout
- `title` — decoration: font
- `overlay` — advanced: hoverIcon, iconColor; decoration: background
- `image` — decoration: image
- `meta` — decoration: font
- `pagination` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `portfolio.innerContent.postsNumber` | Post Count | string (not responsive) |
| `portfolio.innerContent.includedCategories` | Included Categories | checkboxes field (not responsive) |
| `portfolio.advanced.showTitle` | Show Title | "on" or "off" |
| `portfolio.advanced.showCategories` | Show Categories | "on" or "off" |
| `portfolio.advanced.showPagination` | Show Pagination | "on" or "off" |
| `overlay.advanced.iconColor` | Zoom Icon Color | color string |
| `overlay.advanced.hoverIcon` | Hover Icon Picker | icon {"unicode","type","weight"} |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `hover_icon` → `overlay.advanced.hoverIcon.*`
- `include_categories` → `portfolio.innerContent.*.includedCategories`
- `layout_display` → `portfolioGrid.decoration.layout.*.display`
- `posts_number` → `portfolio.innerContent.*.postsNumber`
- `show_categories` → `portfolio.advanced.showCategories.*`
- `show_pagination` → `portfolio.advanced.showPagination.*`
- `show_title` → `portfolio.advanced.showTitle.*`
- `fullwidth` → `portfolio.advanced.layout.*`
- `hover_overlay_color` → `overlay.decoration.background.*.color`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `zoom_icon_color` → `overlay.advanced.iconColor.*`

## Font groups

- `caption` → `meta.decoration.font`
- `pagination` → `pagination.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Portfolio"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"portfolio":{"innerContent":{"desktop":{"value":{"postsNumber":"10"}}},"advanced":{"showCategories":{"desktop":{"value":"on"}},"showTitle":{"desktop":{"value":"on"}},"showPagination":{"desktop":{"value":"on"}},"layout":{"desktop":{"value":"fullwidth"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"portfolioGrid":{"advanced":{"flexType":{"desktop":{"value":"24_24"}}},"decoration":{"layout":{"desktop":{"value":{"display":"grid","gridColumnCount":"4"}}}}},"overlay":{"advanced":{"hoverIcon":{"desktop":{"value":{"unicode":""}}}}}}
```
