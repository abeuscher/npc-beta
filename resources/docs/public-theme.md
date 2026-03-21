---
title: Public Theme
handle: public-theme
description: Manage the public site's visual appearance — brand colour, fonts, logo, site chrome, and advanced SCSS.
routes:
  - filament.admin.pages.site-theme-page
---

# Public Theme

**CMS › Site Theme** is the single place to control the visual appearance of your public-facing website. It has two tabs serving two different audiences.

---

## Appearance tab

The Appearance tab is available to any user with CMS access. Changes made here take effect on the **next public page request** — no build step is required.

### Brand colour

The **Primary colour** field sets the main accent colour for the site. It is injected as `--pico-primary` into the inline `<style>` block on every public page, overriding the Pico CSS default. Enter a hex value (`#3b82f6`) or use the colour picker.

### Fonts

Two **Select** fields control the heading font and the body font:

- **Heading font** — applied as `--pico-font-family-heading`.
- **Body font** — applied as `--pico-font-family-sans-serif`.

The list includes system font stacks (no external request needed) and a curated set of Google Fonts. When a Google Font is selected, the public layout automatically renders a `<link>` tag to load it from Google Fonts — no manual work required.

> **Note:** Brand colour and font choices always take precedence over compiled SCSS values at runtime, because they are injected as inline `<style>` rules after the stylesheet loads.

### Logo

Upload a PNG, JPEG, or SVG logo. When set, it appears in the top-left of the site header, before any header content. The file is stored in `storage/app/public/site/`.

### Nav & Header Colours

Four colour pickers let you style the header and navigation independently of the rest of the page:

| Field | CSS rule applied |
|---|---|
| Header background | `header { background: … }` |
| Nav link colour | `header nav a { color: … }` |
| Nav hover colour | `header nav a:hover { color: … }` |
| Nav active colour | `header nav a[aria-current="page"] { color: … }` |

These rules are scoped to the header only and do not affect page content.

### Site Chrome

- **Header nav handle** — the navigation menu handle to load in the site header (default: `primary`).
- **Footer nav handle** — the navigation menu handle to load in the site footer (default: `footer`).
- **Header content** — rich text rendered on the left side of the header, beside the logo. Use this for a tagline, phone number, or other brand element.

The two **Custom header/footer override** placeholders indicate whether `custom/header.blade.php` or `custom/footer.blade.php` are present on this deployment. When present, those files replace the default header and footer components entirely.

---

## SCSS Editor tab

The SCSS Editor tab is only visible to users who have been explicitly granted the **`edit_theme_scss`** permission. This is a developer-level grant that is not assigned to any role by default.

### What the file controls

The editor reads and writes `resources/scss/_theme.scss`. This file is imported into the main `public.scss` stylesheet and is compiled with the full Pico CSS build. Use it to:

- Override Pico SCSS variables (e.g. `$spacing-block`, `$breakpoints`)
- Add custom SCSS rules that apply to any public-facing HTML

### Save & Build

Clicking **Save & Build** will:

1. **Validate** the submitted SCSS using the server-side compiler. If there is a syntax error, a danger notification appears with the error message and the file is **not written to disk**.
2. **Write** the validated SCSS to `resources/scss/_theme.scss`.
3. **Run** `npm run build` to recompile all public assets.
4. **Show** the full build output in a monospace block below the editor. If the build fails, the first 500 characters of stderr also appear in a danger notification.

The page waits while the build runs — this is expected for a developer tool and typically takes 2–4 seconds.

> **Note:** Brand colour and font overrides set in the Appearance tab are applied at runtime via inline CSS custom properties and always take precedence over compiled values. You do not need to rebuild after changing Appearance settings.

---

## Light/dark mode toggle

The public site includes a gear icon in the top-right of the header. Clicking it reveals a toggle between light mode, dark mode, and the OS default. The preference is stored in `localStorage` and applied on every page load.
