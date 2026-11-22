# divi/sidebar — Sidebar

- Category: `module`
- Divi 4 shortcode: `et_pb_sidebar`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — decoration: font
- `sidebar` — innerContent; advanced: layout; decoration: font
- `sidebarWidgets` — advanced: flexType

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `sidebar.innerContent.area` | Widget Area | select-widget-area field (not responsive) |
| `sidebar.advanced.alignment` | Alignment | one of: "left", "right" |
| `sidebar.advanced.showBorder` | Show Border Separator | "on" or "off" |
| `sidebarWidgets.advanced.flexType` | Column Width | select-column-class field |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `area` → `sidebar.innerContent.*.area`
- `background_color` → `module.decoration.background.*.color`
- `header_level` → `title.decoration.font.*.headingLevel`
- `orientation` → `sidebar.advanced.layout.*.alignment`
- `show_border` → `sidebar.advanced.layout.*.showBorder`

## Font groups

- `body` → `sidebar.decoration.font`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Sidebar"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"sidebar":{"innerContent":{"desktop":{"value":{"area":"sidebar-1"}}},"advanced":{"layout":{"desktop":{"value":{"alignment":"left","showBorder":"on"}}}}},"sidebarWidgets":{"advanced":{"flexType":{"desktop":{"value":"24_24"}}}}}
```
