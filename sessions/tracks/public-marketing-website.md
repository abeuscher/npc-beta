# Track: Public Marketing Website

The canonical planning + history doc for the Public Marketing Website track — building the five-page nonprofitcrm.com marketing site inside the product's own page builder, dogfooding the CMS surface that nonprofit customers will use.

## What this is, in plain terms

We are designing a marketing site **for the product**. We are using a small set of well-known B2B SaaS marketing pages as **structural design guides** — lifting their basic layouts and section rhythm, assuming standard asset delivery, using FPO (placeholder) images from the sample-image library, and applying that design pattern to **the supplied copy**.

The brief explicitly limits the borrow to structure, not surface: section-as-slab layouts, alternating background bands, generous whitespace, restrained type scale, no animations. Surface decoration (custom illustration, marketing-bright saturation, abstract metaphor imagery) is the opposite of where this site lands. Reference images in `sessions/public website/references/` are structural specs only. Real photography (staged-stock-photo with the founder as subject) lands post-track.

This is a **content + visual-build track**, not an architectural arc. Distinct in shape from Widget Primitive and Fleet Manager Agent (both code-architecture tracks): the deliverable is page JSON exports, screenshots, and a gap report. Code only changes when the gap report surfaces a forcing function (missing widget, missing button style, missing config option) and the user approves the lift — those become independent follow-on sessions outside this track.

This doc carries three things:

- **Status snapshot** — where the track stands, what's next.
- **Phase Retrospectives** — compressed history of closed phases (sessions list, outcomes, gap-report deltas).
- **Forward plan** — the page list, the system conventions inherited from the brief, the phase sequence, and the gap-resolution discipline.

When a phase closes, its retrospective lands here and its release-plan position collapses to a one-liner. Per-session detail stays in the matching `sessions/(archived/)NNN. … — Log.md` files.

---

## Status snapshot

**Last update:** 2026-05-15 (PMW1 closed at 284; CMS-fixes session 285 closed; home rebuild session 286 closed; About layout rebuild session 287 closed; Pricing chart widget build session 288 closed; Pricing layout rebuild session 289 closed; Contact layout rebuild session 290 closed; form-submission notifications session 291 closed — lifted gap; G23 ✅ resolved as an operator-editable System Email + `{{contact_email}}` reuse + graceful mail-failure handling + FM pre-Beta pseudo-versioning; CRM contract stays v2.3.0; Demo at 292, page-capture harness/close at 293).

**State:** PMW1 closed; the five CMS-side blockers PMW1 surfaced are landed in code as of session 285; the home is rebuilt as of session 286; About is rebuilt as of session 287; the new `pricing_chart` widget is shipped as of session 288; the Pricing page is rebuilt as of session 289 (the widget's first real-page placement — rendered acceptably without revision). The layout-spec-driven execution pattern validated at 286 was re-validated at 287 and again at 289 — zero importer warnings on first pass each time, bands rendering in spec order, cross-section rhythm matching the spec's prediction. Pricing-chart-widget spec lives at `sessions/public website/pricing-chart-widget-spec.md`; the widget at `app/Widgets/PricingChart/` ships v1 with background-tint emphasis on the recommended column (which reads as visibly subtle in the live Pricing page but acceptable for v1), a built-but-unfilled border slot for the v1.1 appearance-config border-knob upgrade, and a public `marketingSiteTiers()` helper that session 289 consumed verbatim. Demo-page spec is in place at `sessions/public website/demo-page-spec.md`; Demo architecture resolved at 289 close (single-button auto-login, no email, per-IP rate-limit, 24h wipe via Fleet Manager, demo page on demo server, Fleet Manager shared-contract updates lifted to a cross-session arc — captured in project memory `project_demo_page_architecture.md`). The gap report stands at 17 rows — G1 / G2 / G3-interim / G13 / G14 ✅ resolved; G4–G12 open from PMW1; G15 / G16 / G17 / G18 open from 286 (G18 continued through About and Pricing as full-cell-fill default; G15 / G16 / G17 not exercised on Pricing); G19 (no `appearance_config` border knob) and G20 (`.widget--text_block { height: 100% }` collides with stacked siblings in single-column layouts) open from 287 (G20 specifically avoided on Pricing Band 3 via a 4-slot/2-column-CSS-wrapped layout shape; G19 not exercised on Pricing); G-pricing-1 (border styling in `appearance_config`) open from 288 with the widget-level workaround applied, sharing the same underlying surface as G19; the typography small-body / caption row absence noted at 289 with the italic-only footnote workaround acceptable; a minor Band 5 secondary-text-link rendering compromise vs spec also noted at 289 (single Text widget renders two CTAs as a row, not as primary above + secondary as text-link below — acceptable for v1). Contact is rebuilt as of session 290 — the layout-spec pattern held for a greenfield page, but the session pivoted mid-stream on user direction to a single hero with an embedded `web_form` contact form (the seeded `contact-page` form) + 3:4 portrait, the 3-band `copy.md` shape dropped. The pivot surfaced G21 / G22 / G23; G22 was resolved in-session as a user-approved code expansion — the `text.link_color` widget appearance feature (`AppearanceStyleComposer` emits `--np-link-color`, `.np-site a` consumes it via var-indirection, inspector field added, Quill `color`/`background` toolbar buttons removed, `button.btn` SCSS carve-out so form submit buttons render). G21 (form-label-on-dark) open with the same var pattern as its proven path; G23 (form-submission notifications) lifted to session 291 and **✅ resolved at 291** (shipped as an operator-editable System Email, not the originally-scoped fixed template). The gap report stands at 20 rows (G21 open; G22 ✅ at 290; G23 ✅ at 291). Form-notification security threat model in `docs/security-forms.md` (Finding register rows 4–9) moved from pre-registered to **as-shipped** at 291's close.

**Phase status:**

- **Phase 1 — Audit + Home cleanup.** ✅ Closed at session 284.
- **Session 285 — CMS Fixes Before Home Rebuild.** ✅ Closed. Lifted-gap session (outside the track's phase count per gap-resolution discipline). Shipped G1 layout `appearance_config` round-trip + G2 TextBlock CTAs + G3-interim `secondary-dark` button variant + G13/G14 typography defaults + Image widget aspect_ratio + max_width. Fast Pest 2408 / 0 (baseline 2390 + 18 new).
- **Session 286 — Home Layout Rebuild.** ✅ Closed. Home rebuilt against `sessions/public website/homepage-layout-spec.md` as nine sibling layout blocks delivering the eight spec bands. User-revision pass landed five small visual changes. Four new gap rows surfaced (G15 / G16 / G17 / G18). Fast Pest 2407 / 1.
- **Session 287 — About Layout Rebuild.** ✅ Closed. About rebuilt against `sessions/public website/about-layout-spec.md` as Band 1 (hero, gradient, Text + Image cells) + Band 2 (business case, four sibling 1-col layouts after structural workaround for stacked text widgets) + Band 3 (how this gets built, dark, Image + Text-with-three-CTAs). User-revision pass landed four changes (Band 2 widened to full container width via the four-sibling-layout pattern, pull-quote narrowed to ~80% via symmetric horizontal padding, real Boris Cherny quote dropped in with attribution linked to the source video, bold removed from quote). Two new gap rows surfaced — G19 (no `appearance_config` border knob) and G20 (global `.widget--text_block { height: 100% }` rule collides with stacked-sibling text widgets). Pricing-chart-widget pre-pass during this session: design-agent spec reviewed, four PM-level decisions resolved, 288 prompts drafted. Fast Pest 2407 / 1 (matches 286 baseline; the 1 failure pre-dates the session and reproduces independently).
- **Session 288 — Pricing Chart Widget Build.** ✅ Closed. Lifted-gap session (outside the track's phase count). Shipped the `pricing_chart` widget end-to-end at `app/Widgets/PricingChart/` (definition + Blade template + SCSS with CSS subgrid for cross-card row alignment + thumbnail). Four planning-time decisions held: in-widget config repeater (not a collection), per-column emphasize toggle, cross-card row alignment via CSS subgrid, marketing-site three-tier configuration as the v1 demo / one-click setup. Two design corrections during the build: the "preset" framing collapsed to a public `marketingSiteTiers()` helper + `demoConfig()` (preset rules forbid content-group keys; columns is content-group); CTA composition reuses the existing `buttons` field type at the column level rather than per-column primitive triplets. System extension: new generic `repeater` field type (Vue `RepeaterField.vue` + `RepeaterRowField.vue`, registered in InspectorField's component map; recurses via `defineAsyncComponent`; nested fields dispatch to existing TextField / RichTextField / ToggleField / ButtonListField; lives entirely in widget `config_schema` JSON, no `widget_types` column changes; lint test extended with a `repeater` case allowing array defaults). Eight new Pest tests (schema shape, defaults round-trip, seeder row, demo config, render, empty-columns guard, subgrid padding for uneven row counts). Widget count assertions bumped 38 → 39 in `WidgetPickerSession119Test` and `ProductCarouselTest`. Fast Pest 2416 / 0 (the pre-existing `DashboardBuilderApiControllerTest` failure that pre-dated 287 / 286 is no longer reproducing).
- **Session 289 — Pricing Layout Rebuild.** ✅ Closed. Pricing rebuilt against `sessions/public website/pricing-page-spec.md`. Five bands shipped: Band 1 hero (gradient, Text + Image cells per About / Pricing precedent), Band 2 comparison chart (white, single `pricing_chart` widget instance owning heading + columns + footnote — three columns populated verbatim from `marketingSiteTiers()` with Monthly emphasized + "Recommended" eyebrow + asterisk-anchored footnote wrapped in `<em>`), Band 3 à la carte (tinted, 1-col heading + 4-slot inner layout with `grid_template_columns: "1fr 1fr"` for a 2×2 visual grid via 1-widget-per-slot — G20 specifically avoided), Band 4 what-I-won't-build (dark, Image + three-paragraph principles text), Band 5 final CTA (gradient, single Text widget centered with smaller-italic question paragraph + two CTAs). Re-imported with zero importer warnings on first pass; screenshot reviewed and approved without revision. The new `pricing_chart` widget's first real-page placement worked cleanly — subgrid row-alignment, mobile collapse, v1 background-tint emphasis on Monthly all rendered as designed (emphasis reads as visibly subtle but acceptable per the spec's path-2 v1 trade). Demo-page architecture conversation during close locked in the single-button auto-login + 24h wipe model; Fleet Manager shared-contract updates lifted to a cross-session arc. Fast Pest 2416 / 0 (exact match with 288 baseline).
- **Session 290 — Contact Layout Rebuild.** ✅ Closed. Started as a greenfield 3-band `copy.md` build (in-session layout sketch, no pre-pass); pivoted mid-session on user direction to a single gradient hero with an embedded `web_form` contact form (new seeded `contact-page` form: name/email/phone/message + demo-interest checkbox) + 3:4 portrait, bottom two bands dropped. Surfaced G21 (form-label-on-dark — white-card workaround) / G22 (sanitizer strips inline style — resolved) / G23 (no site-owner notification — lifted to 291). In-scope user-approved code expansion: shipped `appearance_config.text.link_color` (composer `--np-link-color` + `.np-site a` var-indirection + inspector field + Quill `color`/`background` toolbar removal + `button.btn` carve-out), resolving G22. Form-notification security threat-model pre-registered in `docs/security-forms.md`. Fast Pest 2421 / 0 (2416 + 5 new). Demo + harness renumbered (+1) by the 291 lift.
- **Session 291 — Form Submission Notifications.** ✅ Closed. Lifted-gap session (outside the track's phase count, like 285 / 288) resolving **G23**. Shipped — but expanded twice on user direction beyond the originally-scoped fixed-template shape: form submissions now email via an **operator-editable `form_submission` System Email** (`FormSubmissionMailable` → `EmailTemplate::forHandle`; `e()`-escaped `{{submission}}` token; subject token-set excludes submission data), a `FormSubmissionObserver` (`#[ObservedBy]`) dispatching per `Form.settings.notifications` entry, the recipient token being the **existing `{{contact_email}}`** CMS setting (the proposed new `site_owner_email` key was added then removed for reuse-over-new-surface), a routing-only FormResource section, and the seeded `contact-page` wired via `updateOrCreate`. Graceful mail-failure handling added (`FormNotificationDeliveryException` → form inline error, page intact, server-side `report()`, no leak; admin test-email hardened too). FM **pre-Beta pseudo-versioning** folded in (repo-root `VERSION` `0.291.1`; `deploy.yml` reads/validates/bakes + immutable GHCR tag + never-overwrite guard; FM contract-doc `version`-field description corrected — **no contract bump**, CRM contract stays **v2.3.0**). `form_submission` footer line removed (interim; root-cause `footer_reason` token-interpolation gap → post-Beta roadmap stub). `docs/security-forms.md` rows 4–9 moved to as-shipped. Fast Pest **2441 / 0**. Note: the planning audit while drafting 292 found the "Demo builds on the notification primitive" framing **stale** — Demo is form-less/no-email per the s289 architecture and does not consume this surface; the consumer is the Contact page. See `sessions/291. … — Log.md`.
- **Session 292 — Demo Page Build.** Queued. Shape change vs the content sessions: page itself is short (one band, two cells, one button per `demo-page-spec.md` — already accepted at 289 close), but the conversion-action wiring is a code-architecture deliverable: a **form-less** single-button auto-login endpoint on the demo server (per-IP rate-limit, shared tightly-scoped "Demo User" account/role, demo-server-only guard). It does **not** build on 291's notification surface — the Demo page collects no form/email per `[[demo-page-architecture]]`; the earlier linkage was superseded. Fleet Manager shared-contract updates (demo-server identity, abuse alerting, wipe coordination) remain flagged as cross-session work, not absorbed into 292. `VERSION` bumps to `0.292.x` (291 discipline).
- **Session 293 — Page-capture harness + close-out.** Queued. Track's final session.

**Track owns:** the five marketing pages' JSON exports (`home`, `about`, `pricing`, `contact`, `demo`), the gap report at `sessions/public website/gap-report.md`, the eventual Playwright page-capture script + its output at `sessions/public website/screenshots/`, and the `build-summary.md` close-out doc.

**Track does not own:** the two in-product demo landing pages (`/my-nonprofit`, `/my-nonprofit-workshop`) — those live inside demo nonprofit accounts as functional demonstrations, not marketing pages, and are excluded from the main site nav per the brief. Any gap-resolution session lifted from this track's gap report (new widget, new button style, new config option) runs as its own normal-cadence session, not under this track.

---

## Premise

The Public Website Complete milestone (`release-plan.md` Rule 12, lifted at session 282) requires a credible-looking public website to anchor the investment-conversation demo. The site is built inside the product's own CMS for two reasons:

1. **Dogfooding.** The marketing site is the most visible demonstration that the CMS can build a real public website. If we use a separate stack (static site generator, third-party CMS) the implicit message is that our product isn't enough.
2. **Iteration speed.** Marketing copy and structural choices change. Editing a page inside the product is faster than editing a static site, and the editing surface is the same one customers use.

The brief was authored by an outside agent collaborating with the user and lives at `sessions/public website/brief.md`. It establishes the page list, voice, system conventions (section bands, padding, background tones, typography fixed, no animations, no invented widgets), authority hierarchy, and gap-surfacing protocol. **It is canonical for this track** — the track doc carries planning shape only, not content decisions.

---

## Scope

**In scope:**

- Five marketing pages produced as importable page JSON exports against format version 1.1.0:
  1. **Home** — exists; structural cleanup to align with conventions (smallest scope, lowest risk; first deliverable).
  2. **About** — exists; export, extend, link to in-product demo LPs.
  3. **Pricing** — partial in system; complete against the structure in `copy.md`.
  4. **Contact** — new build; deliberately simple.
  5. **Demo** — new build; conversion point with a Form widget for demo access.
- A `gap-report.md` accumulating every gap encountered, even when the workaround is satisfactory. Format per brief: *what was attempted / what blocked it / workaround used / recommended action*.
- A Playwright page-capture script that renders each of the five pages in the dev environment and writes `screenshots/page-{slug}.png`. Brief points at `scripts/generate-thumbnails.js` as the reference for invoking Playwright in this codebase; this is a parallel new script, not an extension (different mount point — full public pages, not single widget previews).
- A `build-summary.md` close-out doc listing what was completed, what was deferred, and what needs the user's input.

**Out of scope (stays elsewhere or stays deferred):**

- **The in-product demo landing pages.** `/my-nonprofit` (donation campaign LP) and `/my-nonprofit-workshop` (event LP) live inside demo nonprofit accounts and are functional demonstrations of the product, not marketing pages. About links to them; the track does not author them.
- **Gap-resolution implementation.** Each gap that warrants a code change (new widget, new button style, new config option) gets surfaced by the report and lifted into its own session per the user's approval. Not part of this track's work — the track surfaces, the user approves, separate sessions resolve.
- **New widget creation.** Per brief: "If a section would benefit from a widget that does not exist, build the section in a degraded form using existing widgets and log a gap with the recommendation."
- **Extending the typography scale or button styles.** Same rule — flag as a gap.
- **Real photography.** Sample image library is used as placeholders; real photos (staged-stock-photo with the founder as subject) come post-track.
- **The mailto address** in copy is a placeholder (`mailto:al@example.com`); track surfaces a single gap asking the user to provide the real address. Not a track-side decision.

---

## Sequence

### Phase 1 — Audit + Home cleanup *(estimated 1 session)*

**Audit deliverable:** a system-understanding summary the brief explicitly asks for before any JSON is written. Scope:

- Available type scales (locate in `resources/scss/` and `DesignSystemPage` typography state).
- Available button styles (`button_styles` SiteSetting JSON, primary / secondary / text-only inventory).
- Available widgets (`WidgetRegistry`, `widget_types` table — handles + cardinality + which can live inside layout slots).
- Appearance config schema (`appearance_config` shape per `AppearanceStyleComposer`).
- Sample image library inventory.

The audit output is the input to Phase 2 / 3 and to the user's design choices on E5 (Mobile Type Scaling), E6 (Theme Colors Refactor), E7 (Column-Layout Mobile Collapse). Surface as a markdown summary in the working folder; the user reviews before Phase 2 starts.

**Home cleanup deliverable:** the existing home page export, edited to align with the section-band / padding / appearance-config-as-text-color conventions in the brief. Re-imported into the system, screenshot taken, gap report scaffolded with first findings.

Why home first: the brief calls out "may need structural cleanup to align with conventions below" for home. It is the smallest scope and the most established pattern (already exists, copy is finalized). It validates the export → edit → import workflow before we apply it to greenfield pages.

### Phase 2 — Pricing build-out + About extend *(estimated 1 session)*

Both are extensions of pages that already exist in the system. Pricing is partial in the system AND copy-incomplete in places — `copy.md` carries the structural spec but as the page is built against the SaaS-pattern references, gaps in copy may surface (band heading mismatches, missing supporting sentences, transitions between bands that don't read as drafted). About expands on the "This is not a SaaS company" theme and links to the two in-product demo LPs (`/my-nonprofit`, `/my-nonprofit-workshop`).

**Copy supplementation discipline.** When the build hits a copy gap, the agent does **not** invent copy. Two paths: (a) flag the gap in the gap report with a recommendation for what kind of copy is needed and let the user supply it before the page closes, or (b) if the gap is small and editorial (a connecting sentence, a heading variant), surface the proposed wording to the user inline and proceed only on approval. Voice protection rule applies: `copy.md` is the canonical voice; any supplementation matches that voice.

Pre-work: export current Pricing and About from the admin into the working folder as `existing-pricing-page-export.json` and `existing-about-page-export.json` (the brief calls these out as required inputs). The user runs the export action; the agent treats those files as ground truth.

Both pages absorb gap report rows as discovered.

### Phase 3 — Contact + Demo *(estimated 1 session)*

Both greenfield — neither page exists in the system yet. Contact is deliberately simple (hero band + email band + optional "what I respond to fastest" band). **Demo is the CTA destination for every marketing CTA across all five pages** — the conversion linchpin — and it does not yet exist as a published page in the CMS. Built fresh from `copy.md` as the starting point.

Demo includes a Form widget configured for demo-access intake (name / email / interest / message — all optional per copy). The form-widget configuration is the most concrete piece of "real product configuration" in the track — it tests whether the existing Form widget can express the all-fields-optional shape with a helper-text underneath the email field, or whether that surfaces a gap.

**Copy supplementation likely.** Demo is greenfield, and `copy.md`'s Demo section may not survive contact with the SaaS-pattern reference layouts intact — the structural shape of a "form + reassurance + privacy" page, when laid out against the references, may want supporting copy that the doc doesn't carry (transition lines between bands, micro-copy near the form fields beyond the email helper, a closing line near the privacy band). Same supplementation discipline as Phase 2: the agent does not invent — flag in the gap report or surface inline for user approval.

### Phase 4 — Page-capture harness + screenshots + close-out *(estimated 1 session)*

Build the Playwright page-capture script — distinct from `scripts/generate-thumbnails.js` (which captures widget previews in dev mode at `/dev/widgets/{handle}`). The new script captures the five public pages at their actual published URLs.

Sketch:

```
scripts/generate-page-screenshots.js
  → for each page slug in [home, about, pricing, contact, demo]:
    → goto http://localhost/{slug}
    → wait for fonts + bundle to settle (manifest-load idle)
    → fullPage: true screenshot
    → write to sessions/public website/screenshots/page-{slug}.png
```

Run on demand, not in CI. Output committed to the working folder so the user can review without running the script.

Close-out: write `build-summary.md` listing what shipped per phase, what gaps were surfaced, and what needs the user's input. Phase 4's session log doubles as the track-closure entry.

---

## System conventions inherited from the brief

These are decided. Track does not re-decide them per page.

**Section bands.** Major content sections are full-bleed background-color slabs. Background lives on the `type: "layout"` block, not on the widgets inside it. Inner-widget backgrounds only when contrast within a row is the intentional effect.

**Section padding.** 150px top + 150px bottom for major bands. 25px top + 50px bottom for widgets inside column layouts.

**Background tones.** `#ffffff` (default), `#d4d4f2` (tinted band, established by home's "What it does"), `#373c44` (dark band, established by home's "This is not a SaaS company"). No new tones without a gap-report flag.

**Type and buttons.** Fixed. No extension. Missing variant → gap.

**Color in content.** Standardize on `appearance_config.text.color` as the source of truth. Inline `style="color:rgb(...)"` inside Quill content is for inline emphasis only (single phrase highlight), not for setting a section's text color. The existing home export mixes both models; Phase 1 normalizes.

**No animations.** No parallax. No scroll-triggered reveal animations. Bar chart load animation and existing carousel autoplay are the only motion permitted.

**No invented widgets.** Degrade gracefully + log the gap.

**Images.** Sample image library as placeholders. Mark each placeholder in the page JSON with a note in the widget label or a custom field so a later pass can swap them.

**CTAs.** Every marketing CTA routes to `/demo`. Exceptions: "See the Code" → public GitHub repo; "Email me" → `mailto:` placeholder.

**Authority hierarchy** *(per brief)*. When sources conflict: **(1)** existing exported pages in the system are ground truth for what the system actually produces — preserve their structure when extending; **(2)** written copy in `copy.md` is the words — do not paraphrase; **(3)** reference images are structural specs only — section ordering, column counts, padding rhythm, not color / font / imagery style; **(4)** the agent's own design instinct is the last resort — substantive choices made at this level are flagged as gaps for review.

---

## Gap-resolution discipline

The brief is explicit: *"Gaps are surfaced for discussion, not unilaterally resolved. The agent should not extend the widget system, the typography scale, the button styles, or the appearance config schema without an explicit go-ahead."*

How that flows operationally:

1. Track session encounters a missing widget / button style / config option.
2. Agent uses degraded form, adds row to `gap-report.md` with the four required fields.
3. At session close, the agent surfaces the new gap-report rows as part of the manual-test handoff.
4. **User decides per-gap:** accept the degraded form (mark "Won't fix — accepted as degraded" in the report), or lift a follow-on session to resolve.
5. Lifted gaps become standalone sessions in `release-plan.md`, sequenced into the Public Website Complete milestone alongside the existing E5–E17 entries. They are **not** absorbed into this track's phase count.

This is the same protocol as the housekeeping inbox lifts for batch sessions — surface, discuss, lift to release-plan if approved, run on normal cadence. The track itself does not balloon.

**Forecast:** the brief explicitly anticipates "a tabbed feature explainer is the most likely candidate" for a missing widget. Other plausible gaps (informed estimate, not a commitment): a ghost-on-dark button variant for the dark-band CTAs; a per-page hero variant that doesn't force `overlap_nav: true`; a headline-only widget distinct from the hero. Real gaps land when phases run.

---

## Working folder

`sessions/public website/` (folder name with a space, per user direction at lift). Holds:

- `brief.md` — canonical input; do not edit.
- `copy.md` — page-by-page marketing copy; do not paraphrase.
- `references.md` + four reference-image PNGs — structural specs only, not surface inspiration.
- `existing-home-page-export.json` — canonical example of valid format-1.1.0 JSON.
- `existing-about-page-export.json` + `existing-pricing-page-export.json` — exports the user produces from admin before Phase 2 starts; treated as ground truth.
- `gap-report.md` — accumulates across phases.
- `home.json` / `about.json` / `pricing.json` / `contact.json` / `demo.json` — Phase 1–3 deliverables. Filenames TBD; brief doesn't specify.
- `screenshots/page-{slug}.png` — Phase 4 deliverables.
- `build-summary.md` — Phase 4 close-out.

Folder is committed (artifacts are the track's deliverable, not transient working files).

---

## Sequencing inside Public Website Complete milestone

Lifted at session 283. Inserted into the milestone's execution-order list at position 34 onwards as four phase entries (Phases 1–4). E5 / E6 / E7 / E8 sequence after Phase 1 so the audit output can inform their design choices.

Two reasonable sequencings considered:

- **(a) Track interleaves with E5–E8.** Phase 1 runs first (~position 34), then E5 / E6 / E7 / E8 land informed by its audit output (~positions 35–38), then Phases 2–4 land (~positions 39–41). Cleaner but more interleaving overhead.
- **(b) Track runs after E8.** Sequential, simpler, may end up redoing some appearance choices once E5 / E6 / E7 ship.

**Chosen: (a).** Phase 1's audit output is exactly the kind of input that informs E5–E8 design. The interleaving cost is small.

Final positions resolve at lift time (next session-outlines + release-plan update).

---

## Stance

- **The deliverable is content, not code.** No production code lands from this track. If code lands, it's because a gap got lifted to a follow-on session.
- **Dogfood discipline.** If the workflow of building these pages inside the CMS surfaces friction, that friction becomes a gap. The marketing site doubles as a UX rehearsal of the CMS itself.
- **Voice protection.** Plain-spoken, signed-by-a-person, anti-SaaS. The brief is explicit: do not paraphrase or "improve" copy. The agent is a typesetter for this track, not a copywriter.
- **Visual restraint.** Closer to Attio than to Clay or Gong. When in doubt, less decoration. Reference images are structural specs only.
