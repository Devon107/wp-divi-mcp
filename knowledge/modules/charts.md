# divi/charts — Chart

- Category: `module`
- Divi 4 shortcode: `et_pb_charts`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: elements, html, link, loop; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `chart` — innerContent; advanced: config, legend, subtitle, title, tooltip

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `chart.innerContent.data` | Data | table-editor field (not responsive) |
| `chart.innerContent.title` | Title | string (not responsive) |
| `chart.innerContent.subtitle` | Subtitle | string (not responsive) |
| `chart.innerContent.legendTitle` | Legend Title | string (not responsive) |
| `chart.advanced.config.type` | Chart Type | one of: "line", "area", "bar", "radar", "pie", "doughnut", "polarArea", "scatter", "bubble" |
| `chart.advanced.config.showTitle` | Show Title | "on" or "off" (not responsive) |
| `chart.advanced.config.showSubtitle` | Show Subtitle | "on" or "off" (not responsive) |
| `chart.advanced.config.showLegend` | Show Legend | "on" or "off" (not responsive) |
| `chart.advanced.config.showLegendTitle` | Show Legend Title | "on" or "off" (not responsive) |
| `chart.advanced.config.showTooltip` | Show Tooltip | "on" or "off" (not responsive) |
| `chart.advanced.legend.title.color` | Legend Title Color | color string (not responsive) |

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Chart"}}}},"chart":{"advanced":{"config":{"desktop":{"value":{"type":"line","showTitle":"on","showSubtitle":"on","showLegend":"on","showLegendTitle":"off","showTooltip":"on"}}},"title":{"font":{"desktop":{"value":{"textAlign":"center"}}}},"subtitle":{"font":{"desktop":{"value":{"textAlign":"center"}}}},"legend":{"layout":{"desktop":{"value":{"position":"top","align":"center"}}},"markers":{"desktop":{"value":{"padding":"10px","usePointStyle":"off","pointStyle":"circle","boxWidth":"40px","boxHeight":"12px"}}}},"tooltip":{"box":{"desktop":{"value":{"backgroundColor":"rgba(0, 0, 0, 0.8)","borderColor":"rgba(0, 0, 0, 0)","borderWidth":"0px","cornerRadius":"6px","padding":"6px"}}},"colorBoxes":{"desktop":{"value":{"displayColors":"on","boxWidth":"12px","boxHeight":"12px"}}}}}}}
```
