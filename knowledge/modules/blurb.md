# divi/blurb — Blurb

- Category: `module`
- Divi 4 shortcode: `et_pb_blurb`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `imageIcon` — innerContent; advanced: color, placement; decoration: animation, background, spacing
- `module` — advanced: elements, html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `title` — innerContent; decoration: font
- `content` — innerContent; decoration: bodyFont
- `contentContainer` — decoration: sizing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `imageIcon.innerContent.useIcon` | Use Icon | "on" or "off" (not responsive) |
| `imageIcon.innerContent.icon` | Icon | icon {"unicode","type","weight"} |
| `imageIcon.innerContent.src` | Image | URL string |
| `imageIcon.innerContent.id` | (synced from Image) | string |
| `imageIcon.innerContent.alt` | (synced from Image) | string |
| `imageIcon.innerContent.titleText` | (synced from Image) | string |
| `imageIcon.advanced.color` | Icon Color | color string |
| `imageIcon.advanced.placement` | Image/Icon Placement | one of: "top", "left" |
| `title.innerContent` |  | see legacy mapping |
| `content.innerContent` |  | HTML string |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `content` → `content.innerContent.*`
- `content_max_width` → `contentContainer.decoration.sizing.*.maxWidth`
- `image` → `imageIcon.innerContent.*.src`
- `use_icon` → `imageIcon.innerContent.*.useIcon`
- `font_icon` → `imageIcon.innerContent.*.icon`
- `url_new_window` → `title.innerContent.*.target`
- `url` → `title.innerContent.*.url`
- `title` → `title.innerContent.*.text`
- `alt` → `imageIcon.innerContent.*.alt`
- `icon_color` → `imageIcon.advanced.color.*`
- `image_icon_background_color` → `imageIcon.decoration.background.*.color`
- `icon_placement` → `imageIcon.advanced.placement.*`
- `icon_alignment` → `imageIcon.advanced.alignment.*`
- `header_level` → `title.decoration.font.font.*.headingLevel`
- `animation` → `imageIcon.innerContent.*.animation`
- `inline_fonts` → `content.decoration.inlineFont.*.families`
- `image_icon_width` → `imageIcon.advanced.width.*`

## Font groups

- `body` → `content.decoration.bodyFont.body`
- `body_link` → `content.decoration.bodyFont.link`
- `body_ol` → `content.decoration.bodyFont.ol`
- `body_quote` → `content.decoration.bodyFont.quote`
- `body_ul` → `content.decoration.bodyFont.ul`
- `header` → `title.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"imageIcon":{"innerContent":{"desktop":{"value":{"useIcon":"off"}}},"advanced":{"color":{"desktop":{"value":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$"}},"placement":{"desktop":{"value":"top"}}}},"module":{"meta":{"adminLabel":{"desktop":{"value":"Blurb"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}}}}}},"title":{"decoration":{"font":{"font":{"desktop":{"value":{"headingLevel":"h4"}}}}}}}
```
