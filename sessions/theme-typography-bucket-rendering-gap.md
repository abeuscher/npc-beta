# Theme Typography — Bucket Rendering Gap

**Surfaced by:** A001/3 site import/export round-trip test (2026-05-21). User exported a site with Lato set as "header font" and "nav font" in the Theme Editor, wiped the DB, re-imported, and observed headings/nav rendering as Georgia instead of Lato — even after a manual `Rebuild site styles` action. Initial assumption was an import/export bug, but tracing through the codebase showed the bundle is round-tripping the stored `SiteSetting` faithfully. The symptom is a pre-existing **bucket-vs-element rendering gap** in the theme typography pipeline that the round-trip merely surfaces.

This is a **standalone planning artifact**, not an A001 task. Filed for a future small numeric session.

---

## Symptom

In the Theme Editor → Typography panel, three "bucket" controls exist:

- **Heading font** (`typography.buckets.heading_family`)
- **Body font** (`typography.buckets.body_family`)
- **Nav font** (`typography.buckets.nav_family`)

User sets these and expects:

- Heading bucket → drives `h1`–`h6` rendering on the public site
- Body bucket → drives body / paragraph rendering
- Nav bucket → drives nav rendering

What actually happens:

- **Body bucket works correctly.** `--font-family-body` is emitted as a `:root` CSS variable in `resources/views/layouts/public.blade.php:131` and consumed by `resources/scss/_base.scss:26` (`.np-site { font-family: var(--font-family-body, system-ui, sans-serif) }`). Setting the body bucket → `.np-site` base font changes.
- **Heading bucket only loads the Google Font.** `--font-family-heading` is *emitted* as a CSS variable (public.blade.php:128) but **no SCSS rule consumes it**. Headings get their font from `TypographyCompiler` output, which reads `elements[h1].font.family` — a separate per-element value. The bucket value is only consulted by `TypographyCompiler::googleFontsUsed()` to decide which Google Fonts to load into `<head>`.
- **Nav bucket has no effect at all.** Not emitted as a CSS variable. Not read by any SCSS rule. Not consulted by `TypographyCompiler` for nav elements (no nav selector in `ELEMENT_SELECTORS`). Setting `nav_family` only causes the Google Font to be loaded — entirely cosmetic in admin UI, zero impact on rendering.

There IS an "Inherit from heading bucket" affordance on each heading element in the Theme Editor (`resources/js/theme-editor/TypographyPanel.vue:36`, `applyInheritedFamily()`) — clicking it copies the bucket family into that element's `font.family`. But this is a per-element manual action, not automatic propagation. A user can set the heading bucket and never click inherit, leaving all element families at the default (`'Inter', system-ui, sans-serif`) — which is exactly what the affected user's bundle showed.

---

## Why the import/export round-trip exposes this

Pre-wipe, the user had at some point clicked "inherit from heading bucket" on h1/h2/h3 (or set per-element families manually), which actually stored Lato in their element families. The compiled CSS bundle on the build server cached that state.

Between then and the export, the user re-edited the bucket (or some other path) without re-propagating to elements — leaving the stored typography with Lato in buckets but Inter in elements. The build-server CSS bundle was still the older cached output where elements WERE Lato, so the live site continued to render Lato.

On export, the raw stored typography went into `payload.design.typography` faithfully — buckets with Lato, elements with Inter.

On import + DB wipe + rebuild, the build server re-compiled with the current elements (Inter), producing CSS that *no longer* sets headings to Lato. With elements at Inter and nav silently falling through to body (Georgia), the user sees the bucket settings appear to have evaporated.

**The round-trip is faithful.** The wipe simply forced a fresh CSS rebuild that reflected the actual stored data, which had drifted from what was rendered.

---

## Diagnosis: where the gap is

Three discrete problems, separable:

1. **`--font-family-heading` is declared but never consumed.** Easy fix in SCSS: add a heading rule like
   ```scss
   .np-site h1, .np-site h2, .np-site h3, .np-site h4, .np-site h5, .np-site h6 {
     font-family: var(--font-family-heading, var(--font-family-body, system-ui, sans-serif));
   }
   ```
   With caveat — `TypographyCompiler::compile()` already emits per-element family overrides via `.np-site h1 { font-family: <elements.h1.font.family> }`, which would **win on specificity (0,1,1 to 0,1,0)** over a heading-group rule. So this alone changes nothing for users who have non-default element families. Fix only helps users who leave elements at the default and rely on the bucket — which is what the UX implies but the data shape doesn't deliver.

2. **`--font-family-nav` is never emitted as a CSS variable, never consumed in SCSS.** Two-half fix: emit the variable in `public.blade.php` alongside heading/body, then add a nav rule (`.np-site nav { font-family: var(--font-family-nav, ...) }`). No specificity conflict — nav isn't in `TypographyCompiler::ELEMENT_SELECTORS`.

3. **Bucket-to-element propagation is manual, not automatic.** Either:
   - Make the Theme Editor auto-propagate bucket changes into elements (write Lato into `elements.h1.font.family` whenever `buckets.heading_family` changes), OR
   - Treat buckets as the inheritance default and have elements OPT IN to overriding (which means `TypographyCompiler` would emit `font-family: <element.family ?? var(--font-family-heading)>` per heading), OR
   - Accept the manual model and clarify the UX (rename "Heading font" to "Default heading font (use Inherit on each heading to apply)" — terrible UX).

(1) and (2) are SCSS / layout-level. (3) is a product/UX call.

---

## Proposed fix

Three-part, can be one small session:

### Part A — wire the bucket CSS variables into SCSS

- Emit `--font-family-nav` in `public.blade.php` alongside heading/body.
- Add base rules in `_base.scss`:
  ```scss
  .np-site h1, .np-site h2, .np-site h3, .np-site h4, .np-site h5, .np-site h6 {
      font-family: var(--font-family-heading, inherit);
  }
  .np-site nav {
      font-family: var(--font-family-nav, inherit);
  }
  ```
  Using `inherit` as the fallback means: bucket unset → headings/nav inherit from `.np-site` body (today's behavior). Bucket set → bucket wins.
- **Specificity ordering:** the `TypographyCompiler` per-element CSS must continue to win over the new bucket-default rule (since some users have per-element overrides they don't want clobbered). Verify by Pest test that emits both, parses the compiled output, and asserts element-specific rules sort after the bucket-default rules in the served bundle.

### Part B — Theme Editor auto-propagation

- In `TypographyPanel.vue`'s store, when a bucket field changes, propagate the new value into every relevant element's `font.family` for elements currently matching the *previous* bucket value (i.e. those that were inheriting). Elements that diverged from the bucket stay diverged.
- Equivalent to a "smart propagate" — only touches elements that were tracking the bucket, leaving manual overrides intact.

### Part C — make `nav` a first-class element

- Add `nav` to `TypographyResolver::ELEMENTS` + element defaults.
- Add `nav` to `TypographyCompiler::ELEMENT_SELECTORS` so per-element nav font/size/spacing can be controlled.
- Update the Theme Editor to expose nav as an editable element.
- This is a bigger lift; probably its own session if pursued.

**Minimum viable cut** to address the user's reported symptom: Part A only. Buckets become functional defaults that drive rendering when elements are unset. Element overrides keep winning when present. Both ergonomic and backwards compatible. Part B is a follow-up that improves the UX flow but isn't required to fix the bug.

---

## Scope estimate

- **Part A:** ~30 min. Two SCSS rules, one Blade emit, one Pest test asserting specificity order in compiled output. SCSS is hard-no for agentic sessions but in scope for any numeric session.
- **Part B:** ~1 hr. Vue store change + UI affordance to indicate "this element is tracking the bucket / this element diverges." Playwright spec for the propagation behavior.
- **Part C:** ~2 hr. Resolver + compiler extension + Theme Editor UI surface. Probably its own session.

**Recommendation:** Part A as a one-iteration session in the next numeric slot. Part B + C deferred to a follow-up if the UX itch persists after A.

---

## Files affected

- `resources/views/layouts/public.blade.php` (emit `--font-family-nav`)
- `resources/scss/_base.scss` (heading + nav rules)
- `app/Services/TypographyCompiler.php` (verify specificity ordering — read-only check)
- `tests/Feature/TypographyCompilerTest.php` (specificity regression test)
- For Part B: `resources/js/theme-editor/TypographyPanel.vue` + `resources/js/theme-editor/stores/typography.ts`

None of this is import/export work. The bundle round-trip is faithful and correct; the gap is in the rendering pipeline.
