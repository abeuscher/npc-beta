# Contact Page — Layout Specification (in-session sketch, revised mid-session)

Authored in-session at 290 because no outside-agent pre-pass landed. Initial three-band sketch (hero / email / what-I-respond-to-fastest) was built, screenshot reviewed by the user, and superseded mid-session per user direction: drop the bottom two bands, embed the existing `web_form` widget in the hero alongside the portrait, swap the hero image to portrait orientation. Same authority model as `about-layout-spec.md` and `pricing-page-spec.md`.

## Page-level structural plan

One band only:

1. **Hero** — gradient, 2-col text + portrait. Left cell stacks the H1 header, the embedded contact form (white card on the gradient), and the email-address fallback line. Right cell holds the portrait at 3:4 (portrait orientation, taller-than-wide).

The mid-session shape change collapsed Contact from three bands to one. The form takes the conversion-action surface that the email-address-only band used to carry; the email line drops to a fallback role beneath the form; the "what I respond to fastest" band is dropped entirely (the form is structured enough — name, email, phone, message, demo-interest checkbox — that the response-priority guidance is no longer load-bearing).

## Band 1 — Hero

**Layout:** 2-col grid `grid_template_columns: "3fr 2fr"` (same as About / Pricing). Gap `2rem`, `align_items: start` (was `center` on About / Pricing — changed to `start` here because the left column is taller than the image and centered alignment would float the image off the visual top of the band).
**Background:** gradient `linear-gradient(135deg, #0a2540, #60a5fa)`.
**Padding:** 100px top, 150px bottom (was 200 bottom on the prior 3-band sketch — reduced since the page is now a single band and the visual rhythm doesn't depend on a deep bottom buffer to push the next band).
**Fullscreen / overlap-nav:** off. G16 / G12 carry forward.

**Left cell — three widgets stacked, top to bottom:**

1. **Text widget — H1 header.** White text via `appearance_config.text.color`. Single H1 *Contact*. Padding 25/0/25/0 to give the heading breathing room without pushing the form too far down.

2. **WebForm widget** — embeds the seeded `contact-page` form (handle resolved at `app/Models/Form` lookup time). The form carries five fields: Name (required, contact-mapped to first_name), Email (required, contact-mapped to email), Phone (optional, contact-mapped to phone), Message (required, no contact mapping), and a single checkbox *I am interested in setting up a demo* (optional, no contact mapping). Submit button label *Send*. `form_type: contact` so submissions auto-create / update a Contact record (email-keyed).
   - **Background:** `#ffffff` via the widget's `appearance_config.background.color`. This is the key visual choice — the form sits on a white card inside the gradient, so the form's hardcoded dark-text-on-light styling is readable. Without this, form labels and the checkbox label inherit the global `.form-label { color: $color-text-label }` rule and become invisible on the gradient.
   - **Padding:** 32px on all four sides — gives the form internal breathing room from the white card edge.
   - **Margin-bottom:** 25px to separate the form from the email-fallback line below.

3. **Text widget — email-address fallback line.** White text. Reads *Or email me directly: al@example.com* with the address as a real `<a href="mailto:al@example.com">` link. The link renders white-on-gradient via the new `appearance_config.text.link_color: #ffffff` widget control shipped this session (the G22 resolution — see `gap-report.md`). The composer emits it as a `--np-link-color` custom property on the widget wrapper; `.np-site a` consumes it via `var()`. No inline style, no sanitizer interaction.

**Right cell:** Image widget. **Aspect 3:4 (portrait orientation, taller-than-wide)** — the user explicitly switched from 4:3 to 3:4 mid-session to "give a little more height to work with" as the form occupies more vertical space than a hero-text-block alone. Label `contact-hero-portrait`. Placeholder: portrait media id=3 (Burkard Schliessmann) — fourth distinct portrait, not duplicating Home / About / Pricing.

## Cross-band rhythm

One band only — the cross-band rhythm pattern from About / Pricing doesn't apply. The page's job is "make the contact path scannable and submittable from one screen"; depth is achieved by the form's field-count (five fields), not by additional bands.

## Open gaps exercised this build

- **G18 (image cell-placement controls):** continues. Hero image at 3:4 with `max_width` blank ships full-cell-fill.
- **G20 (stacked text_blocks in one cell):** narrowly avoided. The hero left column stacks three widgets — two text_blocks separated by the WebForm widget. The WebForm widget does *not* carry the `.widget--text_block { height: 100% }` rule, so the two text_blocks each size to natural content rather than colliding for a definite-track height. Visually correct; would re-manifest if the form widget were swapped for a second text_block.
- **New: G21 — form labels hardcode light-bg color.** `.form-label` and `.form-check-label` in `resources/scss/_forms.scss` set `color: $color-text-label` (a dark color), which wins over inherited text color. Forms placed on a dark background (gradient hero band) ship with invisible labels unless the form is wrapped in a light-bg container. Workaround: per-widget `appearance_config.background.color: #ffffff` on the WebForm widget so the form sits in a white card. Logged in `gap-report.md`.
- **New: G22 — operator cannot recolor links inside a widget. ✅ resolved in-session.** Reframed from "sanitizer strips inline `style`" to the real need (links readable on non-default backgrounds) and resolved with a `appearance_config.text.link_color` widget control: composer emits `--np-link-color`, `.np-site a` consumes it via `var()`, sanitizer untouched. Quill `color`/`background` toolbar buttons removed in the same change so color is exclusively a widget-appearance decision. Full detail in `gap-report.md`.
- **New: G23 — form submissions don't email site owner.** The submission handler writes to `form_submissions` + syncs Contact + checks portal collision, but does not dispatch a notification to the site owner. The contact form is shipped with admin-table-only visibility for now; full leverage scoping for the form-notifications surface lives at `sessions/scoping-form-notifications.md`. Logged in `gap-report.md`.

## Authority

This spec overrides layout interpretations from `brief.md` and `copy.md` for the Contact page only. Mid-session shape change (form-in-hero, three-bands → one-band) is user-directed and overrides the prior version of this spec. Voice and tone come from `copy.md`; the form is the conversion surface this page now centers on.
