# Widget Conventions

Established in session 122. All future widget development should follow these patterns.

---

## Markup

- **Semantic class names only.** No Tailwind utilities in widget templates. Use named classes that describe what the element *is*, not how it looks: `.widget--hero`, `.hero-bg`, `.hero-content`, `.hero-ctas`.
- **BEM-ish naming.** Widget root: `.widget--{handle}`. Modifier classes for state/variants: `.hero--fullscreen`, `.hero--overlap-nav`. Child elements: `.hero-body`, `.hero-inner`.

## Styling

- **All widget CSS lives in SCSS** compiled by Vite (currently `resources/scss/_custom.scss`, will move to a build-server-compiled bundle in session 122 Build Server Integration).
- **No inline styles.** The only `style` attribute allowed on a widget is for setting CSS custom properties (variables) that carry per-instance data.
- **CSS custom properties for per-instance values.** Values that differ per widget instance on a page (e.g. user-uploaded background image URL, overlay opacity) go as variables on the widget root element: `style="--hero-bg: url('...') center/cover no-repeat; --hero-overlay: 0.5;"`. The SCSS consumes them: `.hero-bg { background: var(--hero-bg); }`.
- **Class toggles for config switches.** Boolean config toggles map to modifier classes on the widget root. The SCSS handles the visual difference. Example: `fullscreen: true` → class `hero--fullscreen` → `.hero--fullscreen { min-height: 100vh; }`.

## Config schema features

- **`hidden_when`**: hide a field when another toggle field is truthy. Example: `min_height` is hidden when `fullscreen` is on.
- **`shown_when`**: show a field only when another toggle field is truthy. Example: `nav_link_color` only appears when `overlap_nav` is on.
- **`group`**: fields with the same `group` value render side by side in a 2-column grid. The group wrapper inherits `shown_when`/`hidden_when` from the first field in the group.
- **`type: 'color'`**: colour picker (swatch + text input). Saves as a hex string.
- **`type: 'video'`**: video file upload (MP4/WebM). Uses the same Spatie media pipeline as `type: 'image'`. Accessed via `$configMedia['field_key']` in the template.

## Full width

- The `full_width` column on `widget_types` controls whether the widget renders inside or outside the `max-w-7xl` content container.
- Widgets can also expose a `full_width` toggle in their `config_schema` so users can override per instance. The PageController reads `config.full_width` and uses it if present, falling back to the widget type default.
- **All new widgets should include a `full_width` config toggle** if their design could work at both contained and full-bleed widths.

## Buttons

- The shared button partial is at `resources/views/widget-shared/buttons.blade.php`.
- Uses semantic classes: `.btn-group`, `.btn-group--center`, `.btn`, `.btn--primary`, `.btn--secondary`, `.btn--text`.
- CSS for buttons is in `resources/scss/_custom.scss`.

## Nav overlay (full bleed heroes)

- When a hero widget has `overlap_nav: true`, the PageController shares `__navOverlap` (and optional `__navOverlayLinkColor`, `__navOverlayHoverColor`) with the layout.
- The layout's `.site-nav-wrapper--overlay` class positions the nav absolutely over the content and makes it transparent.
- Hero provides `nav_link_color` and `nav_hover_color` config fields (colour pickers, grouped side by side, shown only when overlap is on) that set `--nav-link-color` and `--nav-hover-color` CSS variables on the nav wrapper.
