# divi/imagely-gallery — Imagely Gallery

- Category: `module`
- Divi 4 shortcode: `-`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `imagelyGallery` — innerContent
- `title` — decoration: font
- `image` — decoration: image
- `counter` — decoration: font
- `description` — decoration: font
- `tagCloud` — decoration: font
- `pagination` — decoration: font
- `slideshowLink` — decoration: font
- `exifMetaData` — decoration: font
- `navigationArrows` — advanced: color, size; decoration: button

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `imagelyGallery.innerContent.galleryId` | Gallery | select-imagely-gallery field (not responsive) |
| `navigationArrows.advanced.size` | Arrow Size | number or size string (e.g. "10", "24px") |
| `navigationArrows.advanced.color` | Arrow Color | color string |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Imagely Gallery"}}}}}
```
