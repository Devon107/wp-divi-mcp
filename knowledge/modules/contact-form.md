# divi/contact-form — Contact Form

- Category: `module`
- Divi 4 shortcode: `et_pb_contact_form`
- Allowed children: `divi/contact-field`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, showLabels, successMessage, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `captcha` — decoration: font
- `field` — (no settings)
- `checkbox` — (no settings)
- `radio` — (no settings)
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `email` — innerContent; advanced: receiver
- `redirect` — innerContent; advanced: useRedirect

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.showLabels` | Show Labels | one of: "off", "on" |
| `module.advanced.successMessage` | Success Message | string (not responsive) |
| `title.innerContent` | Title | string |
| `button.innerContent.text` | Submit Button | string |
| `email.innerContent` | Message Pattern | string (not responsive) |
| `email.advanced.receiver` | Email Address | string (not responsive) |
| `redirect.innerContent` | Redirect URL | string (not responsive) |
| `redirect.advanced.useRedirect` | Enable Redirect URL | "on" or "off" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `_unique_id` → `module.advanced.uniqueId.*`
- `success_message` → `module.advanced.successMessage.*`
- `title` → `title.innerContent.*`
- `title_level` → `title.decoration.font.font.*.headingLevel`
- `submit_button_text` → `button.innerContent.*.text`
- `submit_button_full_width` → `button.decoration.sizing.*.width`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`
- `custom_message` → `email.innerContent.*`
- `email` → `email.advanced.receiver.*`
- `use_redirect` → `redirect.advanced.useRedirect.*`
- `redirect_url` → `redirect.innerContent.*`
- `recaptcha_account_name` → `module.advanced.spamProtection.*.account`
- `recaptcha_list` → `module.advanced.spamProtection.*.account`
- `recaptcha_site_key` → `module.advanced.spamProtection.*.siteKey`
- `recaptcha_secret_key` → `module.advanced.spamProtection.*.secretKey`
- `recaptcha_min_score` → `module.advanced.spamProtection.*.minScore`
- `use_spam_service` → `module.advanced.spamProtection.*.enabled`
- `captcha` → `module.advanced.spamProtection.*.useBasicCaptcha`
- `spam_provider` → `module.advanced.spamProtection.*.provider`

## Font groups

- `title` → `title.decoration.font`
- `captcha` → `captcha.decoration.font`
- `form_field` → `field.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Contact Form"}}},"advanced":{"showLabels":{"desktop":{"value":"off"}},"spamProtection":{"desktop":{"value":{"enabled":"off","useBasicCaptcha":"on"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h1"}}}}}},"button":{"innerContent":{"desktop":{"value":{"text":"Submit"}}},"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}},"sizing":{"desktop":{"value":{"alignment":"right"}}}}},"redirect":{"advanced":{"useRedirect":{"desktop":{"value":"off"}}}}}
```
