# divi/table-of-contents — Table of Contents

- Category: `module`
- Divi 4 shortcode: `et_pb_table_of_contents`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `list` — innerContent; advanced: interaction; decoration: font
- `list1` — decoration: font
- `list2` — decoration: font
- `list3` — decoration: font
- `list4` — decoration: font
- `list5` — decoration: font
- `list6` — decoration: font
- `marker` — decoration: font
- `emptyState` — innerContent; decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `title.innerContent` | Title | string |
| `list.innerContent.includedHeadings` | Included Headings | checkboxes field (not responsive) |
| `list.innerContent.includeOwnTitle` | Include Module Title | one of: "off", "on" (not responsive) |
| `list.advanced.interaction.smoothScroll` | Smooth Scroll | one of: "off", "on" (not responsive) |
| `list.advanced.interaction.scrollOffsetPx` | Scroll Offset | number or size string (e.g. "10", "24px") (not responsive) |
| `emptyState.innerContent` | Empty State Message | string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Table of Contents"}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h2"}}}}},"innerContent":{"desktop":{"value":"Table of Contents"}}},"list":{"advanced":{"layout":{"desktop":{"value":{"markerStyle":"ordered"}}},"interaction":{"desktop":{"value":{"smoothScroll":"on","scrollOffsetPx":"0"}}}}},"emptyState":{"innerContent":{"desktop":{"value":"No headings found in this post."}}}}
```
