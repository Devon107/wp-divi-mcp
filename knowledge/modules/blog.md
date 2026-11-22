# divi/blog — Blog

- Category: `module`
- Divi 4 shortcode: `et_pb_blog`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, text; decoration: animation, attributes, background, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `post` — advanced: categories, dateFormat, excerptContent, excerptLength, excerptManual, number, offset, showExcerpt, type, useCurrentLoop; decoration: border
- `image` — advanced: enable; decoration: image
- `readMore` — advanced: enable; decoration: font
- `pagination` — advanced: enable; decoration: font
- `meta` — advanced: showAuthor, showCategories, showComments, showDate; decoration: font
- `title` — decoration: font
- `content` — decoration: bodyFont
- `overlay` — advanced: enable; decoration: background
- `overlayIcon` — decoration: icon
- `fullwidth` — decoration: border
- `blogGrid` — decoration: layout
- `masonry` — decoration: background

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `post.advanced.useCurrentLoop` | Posts For Current Page | "on" or "off" (not responsive) |
| `post.advanced.type` | Post Type | select field (not responsive) |
| `post.advanced.number` | Post Count | string (not responsive) |
| `post.advanced.categories` | Included Categories | checkboxes field (not responsive) |
| `post.advanced.dateFormat` | Date Format | string (not responsive) |
| `post.advanced.excerptContent` | Content Length | one of: "off", "on" |
| `post.advanced.excerptManual` | Use Post Excerpts | "on" or "off" (not responsive) |
| `post.advanced.excerptLength` | Excerpt Length | string (not responsive) |
| `post.advanced.offset` | Post Offset Number | string (not responsive) |
| `post.advanced.showExcerpt` | Show Excerpt | "on" or "off" |
| `image.advanced.enable` | Show Featured Image | "on" or "off" |
| `readMore.advanced.enable` | Show Read More Button | "on" or "off" |
| `pagination.advanced.enable` | Show Pagination | "on" or "off" |
| `meta.advanced.showAuthor` | Show Author | "on" or "off" |
| `meta.advanced.showDate` | Show Date | "on" or "off" |
| `meta.advanced.showCategories` | Show Categories | "on" or "off" |
| `meta.advanced.showComments` | Show Comment Count | "on" or "off" |
| `overlay.advanced.enable` | Featured Image Overlay | "on" or "off" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `fullwidth` → `fullwidth.advanced.enable.*`
- `use_current_loop` → `post.advanced.useCurrentLoop.*`
- `post_type` → `post.advanced.type.*`
- `posts_number` → `post.advanced.number.*`
- `include_categories` → `post.advanced.categories.*`
- `meta_date` → `post.advanced.dateFormat.*`
- `show_thumbnail` → `image.advanced.enable.*`
- `show_content` → `post.advanced.excerptContent.*`
- `use_manual_excerpt` → `post.advanced.excerptManual.*`
- `excerpt_length` → `post.advanced.excerptLength.*`
- `show_more` → `readMore.advanced.enable.*`
- `show_author` → `meta.advanced.showAuthor.*`
- `show_date` → `meta.advanced.showDate.*`
- `show_categories` → `meta.advanced.showCategories.*`
- `show_comments` → `meta.advanced.showComments.*`
- `show_excerpt` → `post.advanced.showExcerpt.*`
- `show_pagination` → `pagination.advanced.enable.*`
- `offset_number` → `post.advanced.offset.*`
- `use_overlay` → `overlay.advanced.enable.*`
- `overlay_icon_color` → `overlayIcon.decoration.icon.*.color`
- `hover_overlay_color` → `overlay.decoration.background.*.color`
- `hover_icon` → `overlayIcon.decoration.icon.*`
- `masonry_tile_background_color` → `masonry.decoration.background.*.color`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `layout_display` → `blogGrid.decoration.layout.*.display`
- `layout_grid_type` → `blogGrid.advanced.flexType.*`

## Font groups

- `header` → `title.decoration.font`
- `meta` → `meta.decoration.font`
- `read_more` → `readMore.decoration.font`
- `pagination` → `pagination.decoration.font`
- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ul` → `content.decoration.bodyFont.ul`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"meta":{"adminLabel":{"desktop":{"value":"Blog"}}}},"post":{"advanced":{"type":{"desktop":{"value":"post"}},"number":{"desktop":{"value":"10"}},"dateFormat":{"desktop":{"value":"M j, Y"}},"offset":{"desktop":{"value":"0"}},"excerptContent":{"desktop":{"value":"off"}},"excerptManual":{"desktop":{"value":"on"}},"showExcerpt":{"desktop":{"value":"on"}},"excerptLength":{"desktop":{"value":"270"}}}},"image":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"readMore":{"advanced":{"enable":{"desktop":{"value":"off"}}}},"pagination":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"meta":{"advanced":{"showAuthor":{"desktop":{"value":"on"}},"showDate":{"desktop":{"value":"on"}},"showCategories":{"desktop":{"value":"on"}},"showComments":{"desktop":{"value":"off"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"overlayIcon":{"decoration":{"icon":{"desktop":{"value":{"unicode":"&#xe050;","type":"divi","weight":"400"}}}}},"fullwidth":{"advanced":{"enable":{"desktop":{"value":"on"}}}},"blogGrid":{"decoration":{"layout":{"desktop":{"value":{"display":"grid","gridColumnCount":"3"}}}}}}
```
