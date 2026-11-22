# divi/gravity-forms — Gravity Form

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `gravityForm` — innerContent; advanced: showConfirmationMessagePreview, showValidationMessagesPreview, useAjax
- `title` — decoration: font
- `sectionHeading` — decoration: font
- `subLabel` — decoration: font
- `description` — decoration: font
- `field` — (no settings)
- `radioButton` — (no settings)
- `checkbox` — (no settings)
- `imageChoice` — (no settings)
- `button` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `nextButton` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `previousButton` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `saveButton` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `fileUploadButton` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `validationSummary` — decoration: bodyFont
- `validationFieldMessage` — decoration: font
- `formConfirmation` — decoration: bodyFont
- `progressBar` — decoration: font
- `requiredMarker` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `gravityForm.innerContent.formId` | Form | select-gravity-forms field (not responsive) |
| `gravityForm.advanced.useAjax` | Use Ajax | "on" or "off" (not responsive) |
| `gravityForm.advanced.showValidationMessagesPreview` | Show validation messages (builder preview) | "on" or "off" (not responsive) |
| `gravityForm.advanced.showConfirmationMessagePreview` | Show confirmation message (builder preview) | "on" or "off" (not responsive) |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Gravity Forms"}}}},"gravityForm":{"advanced":{"useAjax":{"desktop":{"value":"off"}},"showValidationMessagesPreview":{"desktop":{"value":"off"}},"showConfirmationMessagePreview":{"desktop":{"value":"off"}}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"off"}}}}}},"nextButton":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"off"}}}}}},"previousButton":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"off"}}}}}}}
```
