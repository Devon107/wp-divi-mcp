# divi/column-inner — Inner Column

- Category: `structure`
- Divi 4 shortcode: `et_pb_column_inner`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, spacing, sticky, transform, transition, zIndex

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `saved_specialty_column_type` → `module.advanced.savedSpecialtyColumnType.*`
- `specialty_columns` → `module.advanced.specialtyColumns.*`
- `type` → `module.advanced.type.*`
- `layout_display` → `module.decoration.layout.*.display`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Column"}}}}}
```
