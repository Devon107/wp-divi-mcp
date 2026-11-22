# divi/fullwidth-post-content — Fullwidth Post Content

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_post_content`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html; decoration: animation, attributes, background, bodyFont, border, boxShadow, conditions, disabledOn, filters, headingFont, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, zIndex
- `image` — decoration: border, boxShadow

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `ol_item_indent` → `module.decoration.bodyFont.ol.list.*.itemIndent`
- `ol_position` → `module.decoration.bodyFont.ol.list.*.position`
- `ol_type` → `module.decoration.bodyFont.ol.list.*.type`
- `quote_border_color` → `module.decoration.bodyFont.quote.border.*.styles.left.color`
- `quote_border_weight` → `module.decoration.bodyFont.quote.border.*.styles.left.width`
- `ul_item_indent` → `module.decoration.bodyFont.ul.list.*.itemIndent`
- `ul_position` → `module.decoration.bodyFont.ul.list.*.position`
- `ul_type` → `module.decoration.bodyFont.ul.list.*.type`
- `header_font` → `module.decoration.headingFont.h1.font.*`
- `header_font_size` → `module.decoration.headingFont.h1.font.*.size`
- `header_letter_spacing` → `module.decoration.headingFont.h1.font.*.letterSpacing`
- `header_line_height` → `module.decoration.headingFont.h1.font.*.lineHeight`
- `header_text_align` → `module.decoration.headingFont.h1.font.*.textAlign`
- `header_text_color` → `module.decoration.headingFont.h1.font.*.color`
- `header_text_shadow_blur_strength` → `module.decoration.headingFont.h1.textShadow.*.blur`
- `header_text_shadow_color` → `module.decoration.headingFont.h1.textShadow.*.color`
- `header_text_shadow_horizontal_length` → `module.decoration.headingFont.h1.textShadow.*.horizontal`
- `header_text_shadow_style` → `module.decoration.headingFont.h1.textShadow.*.style`
- `header_text_shadow_vertical_length` → `module.decoration.headingFont.h1.textShadow.*.vertical`
- `inline_fonts` → `module.decoration.inlineFont.*.families`

## Font groups

- `text` → `module.decoration.bodyFont.body`
- `link` → `module.decoration.bodyFont.link`
- `ul` → `module.decoration.bodyFont.ul`
- `ol` → `module.decoration.bodyFont.ol`
- `quote` → `module.decoration.bodyFont.quote`
- `header` → `module.decoration.headingFont.h1`
- `header_2` → `module.decoration.headingFont.h2`
- `header_3` → `module.decoration.headingFont.h3`
- `header_4` → `module.decoration.headingFont.h4`
- `header_5` → `module.decoration.headingFont.h5`
- `header_6` → `module.decoration.headingFont.h6`
