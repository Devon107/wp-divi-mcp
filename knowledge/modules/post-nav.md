# divi/post-nav — Pagination

- Category: `module`
- Divi 4 shortcode: `et_pb_post_nav`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, inSameTerm, targetLoop, taxonomyName; decoration: animation, attributes, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, sticky, transform, transition, zIndex
- `links` — advanced: nextText, prevText, showNext, showPrev; decoration: background, border, boxShadow, font, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `module.advanced.inSameTerm` | Navigate Within Current Category | "on" or "off" (not responsive) |
| `module.advanced.taxonomyName` | Custom Taxonomy Name | string (not responsive) |
| `module.advanced.targetLoop` | Target Loop | one of: "main_query" (not responsive) |
| `links.advanced.prevText` | Previous Link | string |
| `links.advanced.nextText` | Next Link | string |
| `links.advanced.showPrev` | Show Previous Post Link | "on" or "off" |
| `links.advanced.showNext` | Show Next Post Link | "on" or "off" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `in_same_term` → `module.advanced.inSameTerm.*`
- `taxonomy_name` → `module.advanced.taxonomyName.*`
- `show_prev` → `links.advanced.showPrev.*`
- `show_next` → `links.advanced.showNext.*`
- `prev_text` → `links.advanced.prevText.*`
- `next_text` → `links.advanced.nextText.*`

## Font groups

- `title` → `links.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Pagination"}}},"advanced":{"inSameTerm":{"desktop":{"value":"off"}}}},"links":{"advanced":{"showPrev":{"desktop":{"value":"on"}},"showNext":{"desktop":{"value":"on"}}}}}
```
