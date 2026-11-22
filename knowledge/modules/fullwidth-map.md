# divi/fullwidth-map — Fullwidth Map

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_map`
- Allowed children: `divi/map-pin`

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `map` — innerContent; advanced: googleAPIKey, mobileDragging, mouseWheel; decoration: filters
- `module` — innerContent; advanced: html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `map.innerContent` | Map Center Address | map field |
| `map.advanced.mapAdvancedWarning` |  | warning field (not responsive) |
| `map.advanced.googleAPIKey` | Google API Key | google-api-key-field field |
| `map.advanced.mouseWheel` | Mouse Wheel Zoom | "on" or "off" |
| `map.advanced.mobileDragging` | Draggable On Mobile | "on" or "off" |
| `module.innerContent` |  | see legacy mapping |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `address` → `map.innerContent.*.address`
- `address_lat` → `map.innerContent.*.lat`
- `address_lng` → `map.innerContent.*.lng`
- `google_api_key` → `map.advanced.googleAPIKey.*`
- `grayscale_filter_amount` → `map.advanced.grayscaleFilter.*.amount`
- `mobile_dragging` → `map.advanced.mobileDragging.*`
- `mouse_wheel` → `map.advanced.mouseWheel.*`
- `zoom_level` → `map.innerContent.*.zoom`
- `google_maps_script_notice` → `googleMapsScriptNotice.*`
- `map_center_map` → `mapCenterMap.*`

## Defaults applied when an attribute is omitted

```json
{"map":{"innerContent":{"desktop":{"value":{"address":"","zoom":18,"lat":0,"lng":0}}},"advanced":{"mouseWheel":{"desktop":{"value":"on"}},"mobileDragging":{"desktop":{"value":"on"}}}},"module":{"meta":{"adminLabel":{"desktop":{"value":"Fullwidth Map"}}},"decoration":{"sizing":{"desktop":{"value":{"width":"auto","maxWidth":"none","minHeight":"auto","maxHeight":"none"}}}}}}
```
