# divi/signup-custom-field — Custom Field

- Category: `child-module`
- Divi 4 shortcode: `et_pb_signup_custom_field`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, order, overflow, position, scroll, sizing, spacing, transform, transition, zIndex
- `field` — decoration: background, font
- `checkbox` — (no settings)
- `radio` — (no settings)
- `fieldItem` — innerContent; advanced: allowedSymbols, checkboxOptions, fullwidth, hidden, id, maxLength, minLength, predefinedField, radioOptions, required, selectOptions, type
- `conditionalLogic` — innerContent; advanced: enable, relation

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `fieldItem.innerContent` | Title | string |
| `fieldItem.advanced.fullwidth` | Make Fullwidth | "on" or "off" (not responsive) |
| `fieldItem.advanced.predefinedField` | Field | select field (not responsive) |
| `fieldItem.advanced.id` | Field ID | string (not responsive) |
| `fieldItem.advanced.type` | Type | one of: "input", "email", "text", "checkbox", "radio", "select" (not responsive) |
| `fieldItem.advanced.minLength` | Minimum Length | number or size string (e.g. "10", "24px") (not responsive) |
| `fieldItem.advanced.maxLength` | Maximum Length | number or size string (e.g. "10", "24px") (not responsive) |
| `fieldItem.advanced.allowedSymbols` | Allowed Symbols | one of: "all", "letters", "numbers", "alphanumeric" (not responsive) |
| `fieldItem.advanced.checkboxOptions` | Options | sortable-list field (not responsive) |
| `fieldItem.advanced.radioOptions` | Options | sortable-list field (not responsive) |
| `fieldItem.advanced.selectOptions` | Options | sortable-list field (not responsive) |
| `fieldItem.advanced.required` | Required Field | "on" or "off" (not responsive) |
| `fieldItem.advanced.hidden` | Hidden Field | "on" or "off" (not responsive) |
| `conditionalLogic.innerContent` | Rules | conditions field (not responsive) |
| `conditionalLogic.advanced.enable` | Enable | "on" or "off" (not responsive) |
| `conditionalLogic.advanced.relation` | Relation | one of: "off", "on" (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `predefined_field` → `fieldItem.advanced.predefinedField.*`
- `hidden` → `fieldItem.advanced.hidden.*`
- `use_focus_border_color` → `fieldItem.advanced.useFocusBorder.*`
- `allowed_symbols` → `fieldItem.advanced.allowedSymbols.*`
- `booleancheckbox_options` → `fieldItem.advanced.booleanCheckboxOptions.*`
- `checkbox_checked` → `fieldItem.advanced.checkboxChecked.*`
- `checkbox_options` → `fieldItem.advanced.checkboxOptions.*`
- `conditional_logic` → `conditionalLogic.advanced.enable.*`
- `conditional_logic_relation` → `conditionalLogic.advanced.relation.*`
- `conditional_logic_rules` → `conditionalLogic.innerContent.*`
- `field_id` → `fieldItem.advanced.id.*`
- `field_title` → `fieldItem.innerContent.*`
- `field_type` → `fieldItem.advanced.type.*`
- `fullwidth_field` → `fieldItem.advanced.fullwidth.*`
- `max_length` → `fieldItem.advanced.maxLength.*`
- `min_length` → `fieldItem.advanced.minLength.*`
- `radio_options` → `fieldItem.advanced.radioOptions.*`
- `required_mark` → `fieldItem.advanced.required.*`
- `select_options` → `fieldItem.advanced.selectOptions.*`

## Font groups

- `form_field` → `field.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"advanced":{"html":{"desktop":{"value":{"elementType":"p"}}}}},"fieldItem":{"advanced":{"fullwidth":{"desktop":{"value":"on"}},"id":{"desktop":{"value":""}},"type":{"desktop":{"value":"input"}},"required":{"desktop":{"value":"on"}}},"innerContent":{"desktop":{"value":"New Field"}}}}
```
