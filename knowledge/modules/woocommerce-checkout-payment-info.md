# divi/woocommerce-checkout-payment-info — Woo Checkout Payment

- Category: `module`
- Divi 4 shortcode: `et_pb_wc_checkout_payment_info`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, loop, text, warning; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — decoration: bodyFont
- `button` — decoration: button
- `formNotice` — decoration: background, border, boxShadow, font, spacing
- `radioButton` — decoration: background, border, boxShadow, font, spacing
- `selectedRadioButton` — decoration: background, border, boxShadow, font, spacing
- `tooltip` — decoration: background, border, boxShadow, font, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.warning` |  | warning field (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `use_focus_border_color` → `tooltip.advanced.focusUseBorder.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `form_notice` → `formNotice.decoration.font`
- `link` → `content.decoration.bodyFont.link`
- `radio_button` → `radioButton.decoration.font`
- `selected_radio_button` → `selectedRadioButton.decoration.font`
- `tooltip` → `tooltip.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Woo Checkout Payment"}}},"decoration":{"background":{"desktop":{"value":{"color":"#ebe9eb"}}},"spacing":{"desktop":{"value":{"padding":{"bottom":"1em","left":"1em","right":"1em","syncHorizontal":"off","syncVertical":"off","top":"1em"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
