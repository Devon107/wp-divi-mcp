# divi/search — Search

- Category: `module`
- Divi 4 shortcode: `et_pb_search`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `searchPlaceholder` — innerContent
- `search` — advanced: excludePages, excludePosts, showButton
- `module` — advanced: elements, excludedCategories, html, link, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `field` — advanced: focus, placeholder; decoration: background, font
- `button` — decoration: background, font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `searchPlaceholder.innerContent` | Input Placeholder | string |
| `search.advanced.showButton` | Show Button | "on" or "off" |
| `search.advanced.excludePages` | Exclude Pages | "on" or "off" (not responsive) |
| `search.advanced.excludePosts` | Exclude Posts | "on" or "off" (not responsive) |
| `module.advanced.excludedCategories` | Exclude Categories | checkboxes field (not responsive) |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `button_color` → `button.decoration.background.*.color`
- `exclude_pages` → `search.advanced.excludePages.*`
- `exclude_posts` → `search.advanced.excludePosts.*`
- `include_categories` → `search.advanced.includeCategories.*`
- `show_button` → `search.advanced.showButton.*`
- `placeholder` → `searchPlaceholder.innerContent.*`
- `form_field_custom_margin` → `field.decoration.spacing.*.margin`
- `form_field_custom_padding` → `field.decoration.spacing.*.padding`
- `placeholder_color` → `field.advanced.placeholder.font.font.*.color`

## Font groups

- `button` → `button.decoration.font`
- `form_field` → `field.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"search":{"advanced":{"showButton":{"desktop":{"value":"on"}},"excludePages":{"desktop":{"value":"off"}},"excludePosts":{"desktop":{"value":"off"}}}},"module":{"meta":{"adminLabel":{"desktop":{"value":"Search"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}},"decoration":{"spacing":{"desktop":{"value":{"padding":{"bottom":"0.715em","left":"0.715em","right":"0.715em","syncHorizontal":"off","syncVertical":"off","top":"0.715em"}}}}}},"button":{"decoration":{"font":{"font":{"desktop":{"value":{"lineHeight":"1em"}}}}}}}
```
