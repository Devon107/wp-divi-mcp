# divi/signup — Email Optin

- Category: `module`
- Divi 4 shortcode: `et_pb_signup`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, showLabels, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `content` — innerContent; decoration: bodyFont
- `resultMessage` — decoration: font
- `formField` — (no settings)
- `button` — innerContent; decoration: background, border, boxShadow, button, font, sizing, spacing
- `field` — advanced: emailFullwidth, firstNameField, firstNameFullwidth, ipAddress, lastNameField, lastNameFullwidth, nameField, nameFieldOnly, nameFullwidth
- `checkbox` — (no settings)
- `radio` — (no settings)
- `footerContent` — innerContent
- `customFields` — advanced: enable, fields, notice
- `success` — advanced: action, message, redirectQuery, redirectUrl

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.showLabels` | Show Labels | "on" or "off" (not responsive) |
| `title.innerContent` |  | see legacy mapping |
| `content.innerContent` | Body | HTML string |
| `button.innerContent.text` | Button | string |
| `field.advanced.nameFieldOnly` | Name | "on" or "off" (not responsive) |
| `field.advanced.nameField` | Use Single Name Field | "on" or "off" (not responsive) |
| `field.advanced.firstNameField` | Show First Name Field | "on" or "off" (not responsive) |
| `field.advanced.lastNameField` | Show Last Name Field | "on" or "off" (not responsive) |
| `field.advanced.nameFullwidth` | Name Fullwidth | "on" or "off" |
| `field.advanced.firstNameFullwidth` | First Name Fullwidth | "on" or "off" |
| `field.advanced.lastNameFullwidth` | Last Name Fullwidth | "on" or "off" |
| `field.advanced.emailFullwidth` | Email Fullwidth | "on" or "off" |
| `field.advanced.ipAddress` | Include IP Address | "on" or "off" (not responsive) |
| `footerContent.innerContent` | Footer | HTML string |
| `customFields.advanced.enable` | Use Custom Fields | "on" or "off" (not responsive) |
| `customFields.advanced` |  | draggable-list field (not responsive) |
| `customFields.advanced.notice` |  | warning field (not responsive) |
| `success.advanced.action` | Action | one of: "message", "redirect" (not responsive) |
| `success.advanced.message` | Message | string (not responsive) |
| `success.advanced.redirectUrl` | Redirect URL | string (not responsive) |
| `success.advanced.redirectQuery` | Redirect URL Query | checkboxes field (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `recaptcha_account_name` → `module.advanced.spamProtection.*.account`
- `recaptcha_min_score` → `module.advanced.spamProtection.*.minScore`
- `use_spam_service` → `module.advanced.spamProtection.*.enabled`
- `captcha` → `module.advanced.spamProtection.*.useBasicCaptcha`
- `spam_provider` → `module.advanced.spamProtection.*.provider`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `title` → `title.innerContent.*`
- `button_text` → `button.innerContent.*.text`
- `description` → `content.innerContent.*`
- `footer_content` → `footerContent.innerContent.*`
- `success_action` → `success.advanced.action.*`
- `success_message` → `success.advanced.message.*`
- `success_redirect_query` → `success.advanced.redirectQuery.*`
- `success_redirect_url` → `success.advanced.redirectUrl.*`
- `use_custom_fields` → `customFields.advanced.enable.*`
- `use_custom_fields_notice` → `customFields.advanced.notice.*`
- `layout` → `module.advanced.layout.*`
- `show_labels` → `module.advanced.showLabels.*`
- `first_name_field` → `field.advanced.firstNameField.*`
- `first_name_fullwidth` → `field.advanced.firstNameFullwidth.*`
- `name_field` → `field.advanced.nameField.*`
- `name_field_only` → `field.advanced.nameFieldOnly.*`
- `name_fullwidth` → `field.advanced.nameFullwidth.*`
- `ip_address` → `field.advanced.ipAddress.*`
- `last_name_field` → `field.advanced.lastNameField.*`
- `last_name_fullwidth` → `field.advanced.lastNameFullwidth.*`
- `email_fullwidth` → `field.advanced.emailFullwidth.*`
- `use_focus_border_color` → `field.advanced.focusUseBorder.*`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `provider` → `module.advanced.emailService.*.provider`
- `activecampaign_account_name` → `module.advanced.emailService.*.account`
- `activecampaign_list` → `module.advanced.emailService.*.account`
- `aweber_account_name` → `module.advanced.emailService.*.account`
- `aweber_list` → `module.advanced.emailService.*.account`
- `campaign_monitor_account_name` → `module.advanced.emailService.*.account`
- `campaign_monitor_list` → `module.advanced.emailService.*.account`
- `constant_contact_account_name` → `module.advanced.emailService.*.account`
- `constant_contact_list` → `module.advanced.emailService.*.account`
- `convertkit_account_name` → `module.advanced.emailService.*.account`
- `convertkit_list` → `module.advanced.emailService.*.account`
- `emma_account_name` → `module.advanced.emailService.*.account`
- `emma_list` → `module.advanced.emailService.*.account`
- `feedblitz_account_name` → `module.advanced.emailService.*.account`
- `feedblitz_list` → `module.advanced.emailService.*.account`
- `fluentcrm_account_name` → `module.advanced.emailService.*.account`
- `fluentcrm_list` → `module.advanced.emailService.*.account`
- `getresponse_account_name` → `module.advanced.emailService.*.account`
- `getresponse_list` → `module.advanced.emailService.*.account`
- `hubspot_account_name` → `module.advanced.emailService.*.account`
- `hubspot_list` → `module.advanced.emailService.*.account`
- `icontact_account_name` → `module.advanced.emailService.*.account`
- `icontact_list` → `module.advanced.emailService.*.account`
- `infusionsoft_account_name` → `module.advanced.emailService.*.account`
- `infusionsoft_list` → `module.advanced.emailService.*.account`
- `madmimi_account_name` → `module.advanced.emailService.*.account`
- `madmimi_list` → `module.advanced.emailService.*.account`
- `mailchimp_account_name` → `module.advanced.emailService.*.account`
- `mailchimp_list` → `module.advanced.emailService.*.account`
- `mailerlite_account_name` → `module.advanced.emailService.*.account`
- `mailerlite_list` → `module.advanced.emailService.*.account`
- `mailpoet_account_name` → `module.advanced.emailService.*.account`
- `mailpoet_list` → `module.advanced.emailService.*.account`
- `mailster_account_name` → `module.advanced.emailService.*.account`
- `mailster_list` → `module.advanced.emailService.*.account`
- `ontraport_account_name` → `module.advanced.emailService.*.account`
- `ontraport_list` → `module.advanced.emailService.*.account`
- `salesforce_account_name` → `module.advanced.emailService.*.account`
- `salesforce_list` → `module.advanced.emailService.*.account`
- `sendinblue_account_name` → `module.advanced.emailService.*.account`
- `sendinblue_list` → `module.advanced.emailService.*.account`

## Font groups

- `header` → `title.decoration.font`
- `body` → `content.decoration.bodyFont.body`
- `result_message` → `resultMessage.decoration.font`
- `form_field` → `field.decoration.font`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ul` → `content.decoration.bodyFont.ul`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Email Optin"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"dark"}}}},"spamProtection":{"desktop":{"value":{"enabled":"off"}}},"showLabels":{"desktop":{"value":"off"}}},"decoration":{"background":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","enableColor":"on"}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}}},"content":{"decoration":{"bodyFont":{"body":{"font":{"desktop":{"value":{"size":"14px"}}}},"link":{"font":{"desktop":{"value":{"size":"14px"}}}},"ul":{"font":{"desktop":{"value":{"size":"14px"}}}},"ol":{"font":{"desktop":{"value":{"size":"14px"}}}},"quote":{"font":{"desktop":{"value":{"size":"14px"}}}}}}},"resultMessage":{"decoration":{"font":{"font":{"desktop":{"value":{"lineHeight":"1em","size":"26px"}}}}}},"button":{"innerContent":{"desktop":{"value":{"text":"Subscribe","linkUrl":"#"}}},"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}},"field":{"advanced":{"nameFieldOnly":{"desktop":{"value":"on"}},"nameField":{"desktop":{"value":"off"}},"firstNameField":{"desktop":{"value":"on"}},"lastNameField":{"desktop":{"value":"on"}},"firstNameFullwidth":{"desktop":{"value":"on"}},"lastNameFullwidth":{"desktop":{"value":"on"}},"nameFullwidth":{"desktop":{"value":"on"}},"emailFullwidth":{"desktop":{"value":"on"}},"ipAddress":{"desktop":{"value":"on"}},"focusUseBorder":{"desktop":{"value":"off"}}}},"customFields":{"advanced":{"enable":{"desktop":{"value":"off"}}}},"success":{"advanced":{"action":{"desktop":{"value":"message"}},"message":{"desktop":{"value":"Success!"}}}}}
```
