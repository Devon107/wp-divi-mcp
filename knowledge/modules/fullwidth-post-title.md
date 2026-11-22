# divi/fullwidth-post-title — Fullwidth Post Title

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_post_title`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — advanced: showTitle; decoration: font
- `meta` — advanced: dateFormat, showAuthor, showCategories, showCommentsCount, showDate, showMeta; decoration: font
- `textWrapper` — advanced: useBackground; decoration: background
- `featuredImage` — advanced: enabled, forceFullwidth, placement; decoration: sizing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.advanced.showTitle` | Show Title | "on" or "off" |
| `meta.advanced.showMeta` | Show Meta | "on" or "off" |
| `meta.advanced.showAuthor` | Show Author | "on" or "off" |
| `meta.advanced.showDate` | Show Date | "on" or "off" |
| `meta.advanced.dateFormat` | Date Format | string (not responsive) |
| `meta.advanced.showCategories` | Show Post Categories | "on" or "off" |
| `meta.advanced.showCommentsCount` | Show Comments Count | "on" or "off" |
| `textWrapper.advanced.useBackground` | Use Text Background Color | "on" or "off" (not responsive) |
| `featuredImage.advanced.enabled` | Show Featured Image | "on" or "off" |
| `featuredImage.advanced.forceFullwidth` | Force Fullwidth | "on" or "off" (not responsive) |
| `featuredImage.advanced.placement` | Featured Image Placement | one of: "below", "above", "background" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `title` → `title.advanced.showTitle.*`
- `meta` → `meta.advanced.showMeta.*`
- `author` → `meta.advanced.showAuthor.*`
- `date` → `meta.advanced.showDate.*`
- `date_format` → `meta.advanced.dateFormat.*`
- `categories` → `meta.advanced.showCategories.*`
- `comments` → `meta.advanced.showCommentsCount.*`
- `featured_image` → `featuredImage.advanced.enabled.*`
- `featured_placement` → `featuredImage.advanced.placement.*`
- `force_fullwidth` → `featuredImage.advanced.forceFullwidth.*`
- `image_width` → `featuredImage.decoration.sizing.*.width`
- `image_max_width` → `featuredImage.decoration.sizing.*.maxWidth`
- `image_height` → `featuredImage.decoration.sizing.*.height`
- `image_max_height` → `featuredImage.decoration.sizing.*.maxHeight`
- `image_alignment` → `featuredImage.decoration.sizing.*.alignment`
- `text_background` → `textWrapper.advanced.useBackground.*`
- `text_bg_color` → `textWrapper.decoration.background.*.color`
- `text_color` → `module.advanced.text.text.*.color`
- `title_level` → `title.decoration.font.font.*.headingLevel`

## Font groups

- `title` → `title.decoration.font`
- `meta` → `meta.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Post Title"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h1"}}}}},"advanced":{"showTitle":{"desktop":{"value":"on"}}}},"meta":{"advanced":{"showMeta":{"desktop":{"value":"on"}},"showAuthor":{"desktop":{"value":"on"}},"showDate":{"desktop":{"value":"on"}},"dateFormat":{"desktop":{"value":"M j, Y"}},"showCommentsCount":{"desktop":{"value":"on"}},"showCategories":{"desktop":{"value":"on"}}}},"textWrapper":{"decoration":{"background":{"desktop":{"value":{"color":"rgba(255,255,255,0.9)"}}}},"advanced":{"useBackground":{"desktop":{"value":"off"}}}},"featuredImage":{"decoration":{"sizing":{"desktop":{"value":{"alignment":"center","width":"100%","maxWidth":"none","height":"auto","maxHeight":"none"}}}},"advanced":{"enabled":{"desktop":{"value":"on"}},"placement":{"desktop":{"value":"below"}},"forceFullwidth":{"desktop":{"value":"on"}}}}}
```
