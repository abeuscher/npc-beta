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

**Last update:** 2026-05-14 (PMW1 closed at 284; CMS-fixes session 285 closed; home rebuild session 286 closed; About layout rebuild session 287 closed; Pricing chart widget build session 288 queued next).

**State:** PMW1 closed; the five CMS-side blockers PMW1 surfaced are landed in code as of session 285; the home is rebuilt as of session 286; About is rebuilt as of session 287. The layout-spec-driven execution pattern validated at 286 was re-validated at 287 — zero importer warnings on first pass, bands rendering in spec order, cross-section rhythm matching the spec's prediction. The Pricing page spec authored mid-287 surfaced a comparison-chart pattern no widget supports cleanly; per the brief's "no invented widgets — surface for discussion" protocol, session 288 lifts to a Pricing Chart Widget Build, with the Pricing page layout shifted to 289 and downstream sessions each shifting one slot. Pricing-chart-widget spec lives at `sessions/public website/pricing-chart-widget-spec.md` (data shape and visual intent; four design decisions resolved at planning time). The gap report now stands at 16 rows — G1 / G2 / G3-interim / G13 / G14 ✅ resolved; G4–G12 open from PMW1; G15 / G16 / G17 / G18 open from 286 (none manifested on About); G19 (no `appearance_config` border knob) and G20 (`.widget--text_block { height: 100% }` collides with stacked siblings in single-column layouts) open from 287. The pricing-page spec also names a forward gap **G-pricing-1** (border styling in `appearance_config`); 288 builds the new widget around it (border slot built, left empty until appearance_config is extended).

**Phase status:**

- **Phase 1 — Audit + Home cleanup.** ✅ Closed at session 284.
- **Session 285 — CMS Fixes Before Home Rebuild.** ✅ Closed. Lifted-gap session (outside the track's phase count per gap-resolution discipline). Shipped G1 layout `appearance_config` round-trip + G2 TextBlock CTAs + G3-interim `secondary-dark` button variant + G13/G14 typography defaults + Image widget aspect_ratio + max_width. Fast Pest 2408 / 0 (baseline 2390 + 18 new).
- **Session 286 — Home Layout Rebuild.** ✅ Closed. Home rebuilt against `sessions/public website/homepage-layout-spec.md` as nine sibling layout blocks delivering the eight spec bands. User-revision pass landed five small visual changes. Four new gap rows surfaced (G15 / G16 / G17 / G18). Fast Pest 2407 / 1.
- **Session 287 — About Layout Rebuild.** ✅ Closed. About rebuilt against `sessions/public website/about-layout-spec.md` as Band 1 (hero, gradient, Text + Image cells) + Band 2 (business case, four sibling 1-col layouts after structural workaround for stacked text widgets) + Band 3 (how this gets built, dark, Image + Text-with-three-CTAs). User-revision pass landed four changes (Band 2 widened to full container width via the four-sibling-layout pattern, pull-quote narrowed to ~80% via symmetric horizontal padding, real Boris Cherny quote dropped in with attribution linked to the source video, bold removed from quote). Two new gap rows surfaced — G19 (no `appearance_config` border knob) and G20 (global `.widget--text_block { height: 100% }` rule collides with stacked-sibling text widgets). Pricing-chart-widget pre-pass during this session: design-agent spec reviewed, four PM-level decisions resolved, 288 prompts drafted. Fast Pest 2407 / 1 (matches 286 baseline; the 1 failure pre-dates the session and reproduces independently).
- **Session 288 — Pricing Chart Widget Build.** Queued. Lifted-gap session (outside the track's phase count). Builds the `pricing_chart` widget end-to-end against `sessions/public website/pricing-chart-widget-spec.md`. Four planning-time decisions baked in: in-widget config repeater (not a collection), per-column "Emphasize this column" toggle, cross-card row alignment via CSS subgrid, ship one v1 "Marketing site tiers" preset matching the Pricing page's Band 2 verbatim. Surfaces a small system extension (a generic `repeater` field type — the only existing repeater is the specialized `buttons` field). Includes Pest schema-shape and render coverage; updates `WidgetPickerSession119Test` widget-count assertion; runs `php artisan build:public` for the widget asset bundle.
- **Session 289 — Pricing Layout Rebuild.** Queued. Layout-spec-driven execution against `sessions/public website/pricing-page-spec.md` using the now-existing pricing chart widget (one-click via the marketing-site preset for Band 2). Five bands: hero gradient → comparison chart white → à la carte tinted → "what I won't build" dark → final CTA gradient.
- **Session 290 — Contact** and **Session 291 — Demo.** Queued. Both layout-spec-driven; specs to be authored before each session per the established pre-pass pattern.
- **Session 292 — Page-capture harness + close-out.** Queued. Track's final session.

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
