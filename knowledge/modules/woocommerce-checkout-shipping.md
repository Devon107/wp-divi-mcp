# divi/woocommerce-checkout-shipping — Woo Checkout Shipping

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_checkout_shipping`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, loop, text, warning; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `field` — (no settings)
- `checkbox` — (no settings)
- `radio` — (no settings)
- `layout` — advanced: fieldsWidth

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.warning` |  | warning field (not responsive) |
| `layout.advanced.fieldsWidth` | Fields Width | one of: "default", "fullwidth", "2_column" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `fields_width` → `layout.advanced.fieldsWidth.*`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `placeholder_color` → `field.advanced.placeholder.font.font.*.color`
- `required_field_indicator_color` → `fieldLabels.advanced.requiredFieldIndicatorColor.*`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`

## Font groups

- `field_label` → `fieldLabels.decoration.font`
- `form_field` → `field.decoration.font`
- `title` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Checkout Shipping"}}}},"layout":{"advanced":{"fieldsWidth":{"desktop":{"value":"default"}}}}}
```
