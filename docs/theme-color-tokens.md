# Theme Colour Tokens — Widget-Dev Contract

The canonical site-wide colour vocabulary. These are CSS custom properties
named `--np-color-*`, defined on `.np-site` and delivered into the public
bundle by `App\Services\ColorTokenCompiler` (via `AssetBuildService`).

**Widget rule:** read colour from these tokens — never hardcode a hex literal
and never reference a `$color-*` SCSS variable directly. A widget that
hardcodes hex receives neither the Theme default nor any future override; that
is the silent-failure class the Re-Taxonomy arc exists to close. (Auditing and
migrating the existing widgets to consume these tokens is session 298 — this
doc is the contract they migrate *to*.)

Defined on `.np-site` (the public namespace; primary purpose is page-builder
preview fidelity, the admin-leak guard is the by-product). They do **not**
exist in the Filament admin — never rely on them outside `.np-site`.

## Tier 1 — user-tunable (Theme → Colors)

Editable by the site admin on the Theme page. Every token always carries a
concrete value (concrete-values rule).

| Token | Default | Meaning |
|---|---|---|
| `--np-color-brand` | `#0172ad` | Primary brand colour |
| `--np-color-bg` | `#ffffff` | Page / body background |
| `--np-color-surface` | `#f3f4f6` | Raised surface (cards, wells) |
| `--np-color-text` | `#1f2937` | Body text |
| `--np-color-heading` | `#111827` | Heading text |
| `--np-color-text-muted` | `#6b7280` | Secondary / muted text |
| `--np-color-link` | `#0172ad` | In-content link colour |
| `--np-color-border` | `#e5e7eb` | Hairlines / dividers / input borders |
| `--np-color-header-bg` | `#ffffff` | Site header background |
| `--np-color-footer-bg` | `#ffffff` | Site footer background |
| `--np-color-nav-link` | `#373c44` | Nav link (header/footer) |
| `--np-color-nav-hover` | `#0172ad` | Nav link hover |
| `--np-color-nav-active` | `#0172ad` | Nav link, current page |

## Tier 2 — published, NOT user-tunable

A wider contract surface than the user knob set (keeps the Theme page calm,
keeps widgets consistent). Not shown on the Theme page.

| Token | Value | Meaning |
|---|---|---|
| `--np-color-success` | `#166534` | Success state |
| `--np-color-error` | `#991b1b` | Error state |
| `--np-color-warning` | `#854d0e` | Warning state |
| `--np-color-brand-contrast` | derived | Readable text/icon colour on a `brand` fill (WCAG-contrast pick of white vs near-black against the resolved `brand`) |
| `--np-color-focus-ring` | `var(--np-color-brand)` | Keyboard focus ring |

## Legacy alias

`--color-primary` is still emitted, aliased to `var(--np-color-brand)`, so
existing consumers (template `custom_scss`, the `$color-primary` SCSS fallback
chain, `--btn-*` brand references) keep resolving. New code should read
`--np-color-brand` directly.

## SCSS fallbacks

The site-level `$color-*` SCSS variables are demoted to build-time fallbacks
only — e.g. `$color-text: var(--np-color-text, #1f2937)`. They are no longer
the contract; the token is. The fallback hex equals the Tier-1/Tier-2 default,
so rendering is byte-identical when no override is set.

## Out of scope here

Per-template colour schemes (Default/Inverse) and the chrome page-shell model
are session 299. There is intentionally no per-template colour between
sessions 297 and 299; the Theme palette is the single site-wide source.
