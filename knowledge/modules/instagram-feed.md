# divi/instagram-feed — Instagram Feed

- Category: `module`
- Divi 4 shortcode: `et_pb_instagram_feed`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `feed` — innerContent; advanced: link; decoration: background, border, boxShadow, layout, sizing, spacing
- `item` — decoration: background, border, boxShadow, sizing, spacing
- `media` — decoration: image
- `followButton` — innerContent; advanced: show; decoration: background, border, boxShadow, button, font, sizing, spacing

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `feed.innerContent.accountId` | Account | instagram-service-account field (not responsive) |
| `feed.innerContent.postCount` | Posts | number or size string (e.g. "10", "24px") (not responsive) |
| `feed.advanced.config.lightbox` | Open in Lightbox | "on" or "off" (not responsive) |
| `followButton.innerContent.text` | Follow Button Text | string (not responsive) |
| `followButton.advanced.show` | Show Follow Button | "on" or "off" (not responsive) |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Instagram Feed"}}}},"feed":{"decoration":{"layout":{"desktop":{"value":{"display":"grid","gridColumnWidths":"equal","gridColumnCount":"3","rowGap":"16px","columnGap":"16px"}}}},"advanced":{"config":{"desktop":{"value":{"lightbox":"on"}}}},"innerContent":{"desktop":{"value":{"accountId":"","postCount":"6"}}}},"followButton":{"advanced":{"show":{"desktop":{"value":"on"}}},"decoration":{"button":{"desktop":{"value":{"icon":{"enable":"on"}}}}}}}
```
