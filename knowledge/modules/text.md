# divi/text — Text

- Category: `module`
- Divi 4 shortcode: `et_pb_text`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — innerContent; decoration: bodyFont, headingFont

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `content.innerContent` | Body | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `ol_item_indent` → `content.decoration.bodyFont.ol.list.*.itemIndent`
- `ol_position` → `content.decoration.bodyFont.ol.list.*.position`
- `ol_type` → `content.decoration.bodyFont.ol.list.*.type`
- `quote_border_color` → `content.decoration.bodyFont.quote.border.*.styles.left.color`
- `quote_border_weight` → `content.decoration.bodyFont.quote.border.*.styles.left.width`
- `ul_item_indent` → `content.decoration.bodyFont.ul.list.*.itemIndent`
- `ul_position` → `content.decoration.bodyFont.ul.list.*.position`
- `ul_type` → `content.decoration.bodyFont.ul.list.*.type`
- `header_font` → `content.decoration.headingFont.h1.font.*`
- `header_font_size` → `content.decoration.headingFont.h1.font.*.size`
- `header_letter_spacing` → `content.decoration.headingFont.h1.font.*.letterSpacing`
- `header_line_height` → `content.decoration.headingFont.h1.font.*.lineHeight`
- `header_text_align` → `content.decoration.headingFont.h1.font.*.textAlign`
- `header_text_color` → `content.decoration.headingFont.h1.font.*.color`
- `header_text_shadow_blur_strength` → `content.decoration.headingFont.h1.textShadow.*.blur`
- `header_text_shadow_color` → `content.decoration.headingFont.h1.textShadow.*.color`
- `header_text_shadow_horizontal_length` → `content.decoration.headingFont.h1.textShadow.*.horizontal`
- `header_text_shadow_style` → `content.decoration.headingFont.h1.textShadow.*.style`
- `header_text_shadow_vertical_length` → `content.decoration.headingFont.h1.textShadow.*.vertical`
- `inline_fonts` → `content.decoration.inlineFont.*.families`

## Font groups

- `header` → `content.decoration.headingFont.h1`
- `header_2` → `content.decoration.headingFont.h2`
- `header_3` → `content.decoration.headingFont.h3`
- `header_4` → `content.decoration.headingFont.h4`
- `header_5` → `content.decoration.headingFont.h5`
- `header_6` → `content.decoration.headingFont.h6`
- `link` → `content.decoration.bodyFont.link`
- `ol` → `content.decoration.bodyFont.ol`
- `quote` → `content.decoration.bodyFont.quote`
- `text` → `content.decoration.bodyFont.body`
- `ul` → `content.decoration.bodyFont.ul`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}}}
```
