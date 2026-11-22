# divi/post-filter-item — Post Filter Item

- Category: `child-module`
- Divi 4 shortcode: `et_pb_post_filter_item`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `field` — innerContent; advanced: checkboxOptions, orderbyEnabledOptions, radioOptions, selectOptions
- `option` — decoration: background, border, boxShadow, sizing, spacing
- `checkbox` — (no settings)
- `radio` — (no settings)
- `multipleOrderButton` — decoration: button
- `button` — decoration: background, border, boxShadow, button, font, sizing, spacing
- `label` — innerContent

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `field.innerContent.type` | Field Type | one of: "text", "single_selection", "multi_selection", "woocommerce", "sort", "button" |
| `field.innerContent.fieldValueType` | Field Value Type | one of: "text", "number", "date", "date-time", "time" |
| `field.innerContent.option` | Field Option | select field |
| `field.innerContent.customFieldKey` | Meta Key | string (not responsive) |
| `field.innerContent` |  | post-filter-item-clear-custom-field-value-options-cache field (not responsive) |
| `field.innerContent.labelDateFormat` | Label Date Format | string (not responsive) |
| `field.innerContent.comparison` | Field Comparison | one of: "=", "!=", ">", ">=", "<", "<=", "LIKE", "NOT LIKE", "IN", "NOT IN", "EXISTS", "NOT EXISTS" |
| `field.innerContent.placeholder` | Field Placeholder | string |
| `field.innerContent.optionsMethod` | Options Method | one of: "automatic", "manual" (not responsive) |
| `field.advanced.orderbyEnabledOptions` | Order By Options | checkboxes field (not responsive) |
| `field.advanced.checkboxOptions` | Options | sortable-list field (not responsive) |
| `field.advanced.radioOptions` | Options | sortable-list field (not responsive) |
| `field.advanced.selectOptions` | Options | sortable-list field (not responsive) |
| `label.innerContent` | Field Label | string |

## Defaults applied when an attribute is omitted

```json
{"module":{"decoration":{"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"field":{"innerContent":{"desktop":{"value":{"type":"text","fieldValueType":"text","option":"post_content","optionsMethod":"automatic","labelDateFormat":"M j, Y"}}}},"label":{"innerContent":{"desktop":{"value":"Search"}}},"button":{"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
