# divi/tabs — Tabs

- Category: `module`
- Divi 4 shortcode: `et_pb_tabs`
- Allowed children: `divi/tab`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `content` — decoration: background, bodyFont
- `tab` — decoration: background, font
- `activeTab` — decoration: background, font

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `body_ol_position` → `content.decoration.bodyFont.ol.list.*.position`
- `body_ol_type` → `content.decoration.bodyFont.ol.list.*.type`
- `body_ol_item_indent` → `content.decoration.bodyFont.ol.list.*.itemIndent`
- `body_ul_position` → `content.decoration.bodyFont.ul.list.*.position`
- `body_ul_type` → `content.decoration.bodyFont.ul.list.*.type`
- `body_ul_item_indent` → `content.decoration.bodyFont.ul.list.*.itemIndent`
- `active_tab_background_color` → `activeTab.decoration.background.*.color`
- `active_tab_text_color` → `activeTab.decoration.font.font.*.color`
- `inactive_tab_background_color` → `inactiveTab.decoration.background.*.color`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `tab` → `tab.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Tabs"}}}}}
```
