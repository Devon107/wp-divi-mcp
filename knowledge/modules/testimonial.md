# divi/testimonial — Testimonial

- Category: `module`
- Divi 4 shortcode: `et_pb_testimonial`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `company` — innerContent; decoration: font
- `quoteIcon` — decoration: background, icon
- `content` — innerContent; decoration: bodyFont
- `author` — innerContent; decoration: font
- `jobTitle` — innerContent; decoration: font
- `portrait` — innerContent; decoration: image
- `testimonialDescription` — (no settings)

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `company.innerContent.text` | Company | string |
| `company.innerContent.linkUrl` | Company Link URL | string (not responsive) |
| `company.innerContent.linkTarget` | Company Link Target | one of: "off", "on" (not responsive) |
| `content.innerContent` | Body | HTML string |
| `author.innerContent` | Author | string |
| `jobTitle.innerContent` | Job Title | string |
| `portrait.innerContent.src` | Image | URL string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `author` → `author.innerContent.*`
- `company_name` → `company.innerContent.*.text`
- `content` → `content.innerContent.*`
- `font_icon` → `quoteIcon.decoration.icon.*`
- `icon_font_size` → `quoteIcon.decoration.icon.*.size`
- `job_title` → `jobTitle.innerContent.*`
- `portrait_height` → `portrait.decoration.sizing.*.height`
- `portrait_url` → `portrait.innerContent.*.url`
- `portrait_width` → `portrait.decoration.sizing.*.width`
- `quote_icon` → `quoteIcon.decoration.icon.*.show`
- `quote_icon_background_color` → `quoteIcon.decoration.background.*.color`
- `quote_icon_color` → `quoteIcon.decoration.icon.*.color`
- `url` → `company.innerContent.*.linkUrl`
- `url_new_window` → `company.innerContent.*.linkTarget`
- `use_icon_font_size` → `quoteIcon.decoration.icon.*.useSize`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `author` → `author.decoration.font`
- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `company` → `company.decoration.font`
- `position` → `jobTitle.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Testimonial"}}},"decoration":{"background":{"desktop":{"value":{"color":"#f5f5f5"}}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"company":{"innerContent":{"desktop":{"value":{"linkTarget":"off"}}}},"quoteIcon":{"decoration":{"icon":{"desktop":{"value":{"show":"on","useSize":"off"}}}}}}
```
