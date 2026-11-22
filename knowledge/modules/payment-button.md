# divi/payment-button — Payment Button

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: alignment, elements, html, loop, text; decoration: animation, attributes, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, spacing, sticky, transform, transition, zIndex
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.alignment` | Payment Button Alignment | one of: "left", "center", "right" |
| `button.innerContent.provider` | Provider | payment-provider field (not responsive) |
| `button.innerContent.resourceId` | Resource | payment-resource field (not responsive) |
| `button.innerContent.environment` | Environment | one of: "sandbox", "live" (not responsive) |
| `button.innerContent.amountMode` | Amount Mode | one of: "fixed", "user-defined" (not responsive) |
| `button.innerContent.amount` | Amount | string (not responsive) |
| `button.innerContent.currency` | Currency | select field (not responsive) |
| `button.innerContent.returnUrl` | Return URL | string (not responsive) |
| `button.innerContent.cancelUrl` | Cancel URL | string (not responsive) |
| `button.innerContent.description` | Description | string (not responsive) |
| `button.innerContent.openInNewTab` | Open In New Tab | "on" or "off" (not responsive) |
| `button.innerContent.text` | Payment Button Text | string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Payment Button"}}},"advanced":{"html":{"desktop":{"value":{"elementType":"a"}}},"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on","onHover":"off","placement":"left","settings":{"unicode":"&#xf1ed;","type":"fa","weight":"400"}}}}}},"innerContent":{"desktop":{"value":{"provider":"paypal","environment":"sandbox","amountMode":"fixed","amount":"10.00","currency":"USD","openInNewTab":"off"}}}}}
```
