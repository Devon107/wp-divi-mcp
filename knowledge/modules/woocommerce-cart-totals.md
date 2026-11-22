# divi/woocommerce-cart-totals — Woo Cart Totals

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_cart_totals`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `content` — decoration: bodyFont
- `button` — innerContent; decoration: button
- `columnLabel` — decoration: font
- `field` — (no settings)
- `table` — advanced: collapseTableGuttersBorders, horizontalGutterWidth, verticalGutterWidth; decoration: background, border, boxShadow, spacing
- `tableCell` — decoration: background, border, boxShadow, spacing
- `tableRow` — decoration: background, border, boxShadow

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `button.innerContent` |  | see legacy mapping |
| `table.advanced.collapseTableGuttersBorders` | Collapse Table Gutters and Borders | "on" or "off" |
| `table.advanced.horizontalGutterWidth` | Horizontal Gutters | number or size string (e.g. "10", "24px") |
| `table.advanced.verticalGutterWidth` | Vertical Gutters | number or size string (e.g. "10", "24px") |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `collapse_table_gutters_borders` → `table.advanced.collapseTableGuttersBorders.*`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `horizontal_gutter_width` → `table.advanced.horizontalGutterWidth.*`
- `placeholder_color` → `field.advanced.placeholder.font.font.*.color`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`
- `vertical_gutter_width` → `table.advanced.verticalGutterWidth.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `column_label` → `columnLabel.decoration.font`
- `form_field` → `field.decoration.font`
- `link` → `content.decoration.bodyFont.link`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Cart Totals"}}}},"table":{"advanced":{"collapseTableGuttersBorders":{"desktop":{"value":"off"}},"horizontalGutterWidth":{"desktop":{"value":"0px"}},"verticalGutterWidth":{"desktop":{"value":"0px"}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
