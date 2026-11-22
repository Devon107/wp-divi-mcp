# divi/fullwidth-menu — Fullwidth Menu

- Category: `fullwidth-module`
- Divi 4 shortcode: `et_pb_fullwidth_menu`
- Allowed children: none

## Elements (top-level attribute keys)

Each element can hold `innerContent`, `advanced`, `decoration` sub-objects.

- `module` — advanced: html, link, loop, text; decoration: animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, order, overflow, position, scroll, sizing, spacing, sticky, transform, transition, zIndex
- `logo` — innerContent; decoration: border, boxShadow, filters, sizing
- `menu` — advanced: activeLinkColor, fullwidth, menuId, style; decoration: font
- `menuDropdown` — advanced: activeLinkColor, animation, direction, lineColor; decoration: background, font
- `menuMobile` — decoration: background, font
- `cartQuantity` — advanced: show; decoration: font
- `cartIcon` — advanced: show; decoration: font
- `searchIcon` — advanced: show; decoration: font
- `hamburgerMenuIcon` — decoration: font

## Divi 5 settings (authoritative)

Path → stored as `<path before the last segment>.<breakpoint>.<state>.<last segment>` when the
last segment is a subName (e.g. `button.innerContent` + `text` → `button.innerContent.desktop.value.text`);
otherwise the value sits directly at `<path>.desktop.value`.

| Path | Label | Value |
|---|---|---|
| `logo.innerContent.src` | Logo | URL string |
| `logo.innerContent.id` | (synced from Logo) | string |
| `logo.innerContent.alt` | (synced from Logo) | string |
| `logo.innerContent.titleText` | (synced from Logo) | string |
| `logo.innerContent.linkUrl` | Logo Link URL | string (not responsive) |
| `logo.innerContent.linkTarget` | Logo Link Target | one of: "off", "on" (not responsive) |
| `menu.advanced.menuId` | Menu | select-menu field (not responsive) |
| `menu.advanced.style` | Style | one of: "left_aligned", "centered", "inline_centered_logo" (not responsive) |
| `menu.advanced.activeLinkColor` | Active Link Color | color string |
| `menu.advanced.fullwidth` | Make Menu Links Fullwidth | one of: "off", "on" |
| `menuDropdown.advanced.direction` | Dropdown Menu Direction | one of: "downwards", "upwards" (not responsive) |
| `menuDropdown.advanced.animation` | Dropdown Menu Animation | one of: "expand", "fade", "flip", "slide" (not responsive) |
| `menuDropdown.advanced.lineColor` | Dropdown Menu Line Color | color string |
| `menuDropdown.advanced.activeLinkColor` | Dropdown Menu Active Link Color | color string (not responsive) |
| `cartQuantity.advanced.show` | Show Cart Quantity | one of: "off", "on" |
| `cartIcon.advanced.show` | Show Shopping Cart Icon | one of: "off", "on" |
| `searchIcon.advanced.show` | Show Search Icon | one of: "off", "on" |

## Legacy Divi 4 → Divi 5 mapping

From Divi's D4 conversion file: tells what each classic option means, but where it
disagrees with the table above, the Divi 5 settings table wins (e.g. some image paths).
`*` is the breakpoint + state, e.g. `desktop.value`.

- `menu_id` → `menu.advanced.menuId.*`
- `logo` → `logo.innerContent.*.src`
- `show_cart_icon` → `cartIcon.advanced.show.*`
- `show_cart_quantity` → `cartQuantity.advanced.show.*`
- `show_search_icon` → `searchIcon.advanced.show.*`
- `logo_url` → `logo.innerContent.*.linkUrl`
- `logo_url_new_window` → `logo.innerContent.*.linkTarget`
- `menu_style` → `menu.advanced.style.*`
- `submenu_direction` → `menuDropdown.advanced.direction.*`
- `fullwidth_menu` → `menu.advanced.fullwidth.*`
- `active_link_color` → `menu.advanced.activeLinkColor.*`
- `dropdown_menu_bg_color` → `menuDropdown.decoration.background.*.color`
- `dropdown_menu_line_color` → `menuDropdown.advanced.lineColor.*`
- `dropdown_menu_text_color` → `menuDropdown.decoration.font.font.*.color`
- `dropdown_menu_active_link_color` → `menuDropdown.advanced.activeLinkColor.*`
- `mobile_menu_bg_color` → `menuMobile.decoration.background.*.color`
- `mobile_menu_text_color` → `menuMobile.decoration.font.font.*.color`
- `cart_icon_color` → `cartIcon.decoration.font.font.*.color`
- `search_icon_color` → `searchIcon.decoration.font.font.*.color`
- `menu_icon_color` → `hamburgerMenuIcon.decoration.font.font.*.color`
- `cart_icon_font_size` → `cartIcon.decoration.font.font.*.size`
- `search_icon_font_size` → `searchIcon.decoration.font.font.*.size`
- `menu_icon_font_size` → `hamburgerMenuIcon.decoration.font.font.*.size`
- `logo_width` → `logo.decoration.sizing.*.width`
- `logo_max_width` → `logo.decoration.sizing.*.maxWidth`
- `logo_height` → `logo.decoration.sizing.*.height`
- `logo_max_height` → `logo.decoration.sizing.*.maxHeight`
- `dropdown_menu_animation` → `menuDropdown.advanced.animation.*`
- `logo_alt` → `logo.innerContent.*.alt`

## Font groups

- `default` → `module.decoration.font`
- `menu` → `menu.decoration.font`
- `cart_quantity` → `cartQuantity.decoration.font`

## Defaults applied when an attribute is omitted

```json
{"module":{"meta":{"adminLabel":{"desktop":{"value":"Fullwidth Menu"}}},"advanced":{"text":{"text":{"desktop":{"value":{"color":"light"}},"tablet":{"value":{"orientation":"right"}},"phone":{"value":{"orientation":"right"}}}}},"decoration":{"background":{"desktop":{"value":{"color":"#ffffff"}}}}},"logo":{"innerContent":{"desktop":{"value":{"linkTarget":"off"}}}},"menu":{"advanced":{"style":{"desktop":{"value":"left_aligned"}}}},"menuDropdown":{"advanced":{"direction":{"desktop":{"value":"downwards"}},"animation":{"desktop":{"value":"fade"}}}},"cartQuantity":{"advanced":{"show":{"desktop":{"value":"off"}}}},"cartIcon":{"advanced":{"show":{"desktop":{"value":"off"}}},"decoration":{"font":{"font":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","size":"17px"}}}}}},"searchIcon":{"advanced":{"show":{"desktop":{"value":"off"}}},"decoration":{"font":{"font":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","size":"17px"}}}}}},"hamburgerMenuIcon":{"decoration":{"font":{"font":{"desktop":{"value":{"color":"$variable({\"type\":\"color\",\"value\":{\"name\":\"gcid-primary-color\",\"settings\":{}}})$","size":"32px"}}}}}}}
```
