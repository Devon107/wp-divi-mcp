# divi/contact-form-7 — Contact Form 7 Styler

- Category: `module`
- Divi 4 shortcode: `et_pb_contact_form_7`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `form` — advanced: formId
- `field` — (no settings)
- `radioButton` — (no settings)
- `checkbox` — (no settings)
- `button` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `messageValidation` — decoration: background, bodyFont, border
- `messageSuccess` — decoration: background, bodyFont, border
- `messageInvalid` — decoration: background, bodyFont, border
- `messageAcceptance` — decoration: background, bodyFont, border
- `messageSpam` — decoration: background, bodyFont, border
- `messageFailure` — decoration: background, bodyFont, border

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `form.advanced.formId` | Form | select-contact-form-7 field (not responsive) |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Contact Form 7 Styler"}}},"decoration":{"spacing":{"desktop":{"value":{"margin":{"bottom":"0px"}}}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"form":{}}
```
