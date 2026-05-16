# Track: Public Marketing Website — ✅ CLOSED (session 293)

The canonical planning + history doc for the Public Marketing Website track — the five-page nonprofitcrm.com marketing site built inside the product's own page builder, dogfooding the CMS surface that nonprofit customers will use. **The track is closed.** This doc is now history + durable reference; the running narrative has been compressed into Phase Retrospectives. Per-session detail lives in the (archived) `sessions/NNN. … — Log.md` files; the durable close-out is `sessions/public website/build-summary.md`.

---

## What this was

A marketing site built **for the product, inside the product** — lifting a small set of well-known B2B SaaS marketing pages as **structural** design guides (section-as-slab, alternating background bands, generous whitespace, restrained type scale, no animations), applying that to supplied copy with FPO placeholder images. A content + visual-build track, not an architectural arc: the deliverables were page JSON exports, screenshots, and a gap report. Code only changed when the gap report surfaced a forcing function and the user approved a lift — those ran as independent follow-on sessions outside the track's phase count.

The brief (`sessions/public website/brief.md`, authored by an outside agent collaborating with the user) was canonical for content; this doc carried planning shape only.

---

## Status — TRACK CLOSED

**Closed:** 2026-05-15 (session 293).

All five pages (`home` `/`, `about` `/about`, `pricing` `/pricing`, `contact` `/contact`, `demo` `/demo`) shipped as importable format-1.1.0 JSON, built into the running CMS, screenshot-captured, and committed to `sessions/public website/`. The gap report stands at 23 rows (6 resolved during the track via lifted sessions; the rest are now per-gap user lift decisions, no longer track-owned). `build-summary.md` is the durable whole-track close-out and carries the milestone-close-gate list. CRM ↔ Fleet Manager contract stayed **v2.3.0** for the entire track (no track session was contract-touching).

**Milestone-close gates remaining** (not track work — per-item user decisions, tracked in `build-summary.md`): Privacy/Terms pages; real photography across all five pages; Demo dark-side real copy + `/demo/enter` hookup; the Fleet-Manager shared-contract arc; the real `mailto:` address (G4).

---

## Phase Retrospectives

Compressed history. Each entry: sessions, key outcomes, gap deltas. Lifted-gap sessions ran outside the phase count per the gap-resolution discipline.

**Phase 1 — Audit + Home cleanup (session 284).** System-understanding audit (`audit-summary.md`: type scales, button styles, widget inventory, appearance-config schema, sample-image library) + the existing home cleaned to the brief's conventions. 10-row gap report scaffolded; `homepage-layout-spec.md` authored. Mid-session bugs lifted: missing `<header>`/`<footer>` chrome wrapper in `public.blade.php`; Quill bullet-render bug in HtmlSanitizer + public SCSS. Fast Pest 2390/0 preserved.

**Lifted-gap sessions (285 / 288 / 291; + the 290 in-session expansion).** 285 — CMS fixes: G1 layout `appearance_config` round-trip, G2 TextBlock CTAs + `cta_alignment`, G3-interim `secondary-dark` button variant, G13/G14 typography defaults, Image `aspect_ratio`/`max_width` (2408/0). 288 — the `pricing_chart` widget end-to-end + a generic `repeater` config field type as the system extension (2416/0). 291 — G23 resolved as an operator-editable `form_submission` System Email + `{{contact_email}}` reuse + graceful mail-failure handling; FM pre-Beta pseudo-versioning (`VERSION` + deploy-pipeline discipline) folded in here — no contract bump (2441/0). 290 in-session, user-approved: the `text.link_color` widget appearance control (CSS-variable indirection), resolving G22.

**Phase 2 — Home + About + Pricing layout rebuilds (sessions 286 / 287 / 288–289).** The layout-spec-driven execution pattern was introduced at 286 (home: nine sibling layout blocks, eight spec bands; G15–G18 surfaced) and re-validated at 287 (about; G19/G20 surfaced) and 289 (pricing; the `pricing_chart` widget's first real-page placement, rendered acceptably without revision). Each rebuilt page imported with zero importer warnings on first pass, bands in spec order, cross-section rhythm matching the spec's prediction.

**Phase 3 — Contact + Demo (sessions 290 / 292).** Contact (290) started greenfield against a 3-band sketch, pivoted mid-session on user direction to a single gradient hero with an embedded `web_form` + 3:4 portrait; surfaced G21/G22/G23 (G22 resolved in-session, G23 lifted to 291). Demo (292) deliberately overrode the marketing conventions per `demo-page-spec.md`: a single-band 50/50 dark/light split + the form-less `/demo/enter` auto-login (tightly-scoped `demo` role, per-IP throttle, demo-server guard, threat model in `docs/security-demo.md`); surfaced G-demo-1/2/3 (accepted spec workarounds). Phase 3 complete at 292.

**Phase 4 — Page-capture harness + track close (session 293).** `scripts/generate-page-screenshots.js` — standalone host-run ESM Playwright script, parallel to `generate-thumbnails.js`, capturing the five published URLs at a 1280-wide viewport `fullPage` with a network-idle + `document.fonts.ready` settle and a DOM-strip of the dev Debugbar so the committed record is clean. Five `screenshots/page-*.png` captured, verified (correct pages, non-trivial, spec-order bands), and user-reviewed. `build-summary.md` written (whole-track close-out + milestone-close-gate list). `VERSION` bumped `0.292.1 → 0.293.1`. Fast Pest 2446/1 — the single red is the pre-existing 291 contract-doc `AppVersionStampTest` "seven-character git SHA" self-collision (not a 292/293 regression), left flagged as a user-owned doc-hygiene carry-forward in `build-summary.md` (not folded into 293). No new Pest (the harness is run-on-demand tooling). Track fully closed at this session.

---

## Durable reference

Retained because they record *how* the site was built and govern any future lifted gap-resolution session.

**System conventions (from the brief, decided once).** Section bands = full-bleed background-color slabs; background on the `type:"layout"` block, not inner widgets (inner-widget backgrounds only for intentional within-row contrast). Padding: 150/150 major bands, 25/50 widgets in column layouts. Background tones: `#ffffff`, `#d4d4f2` (tinted), `#373c44` (dark) — no new tones without a gap flag. Type + buttons fixed (missing variant → gap). Color source of truth: `appearance_config.text.color` (inline Quill color = inline emphasis only). No animations beyond the bar-chart load + carousel autoplay. No invented widgets (degrade + log a gap). Images: sample library as placeholders, marked for the real-photography swap. CTAs route to `/demo` except "See the Code" → GitHub and "Email me" → `mailto:`.

**Gap-resolution discipline.** Gaps were surfaced, not unilaterally resolved. Encounter → degrade + add a four-field row to `gap-report.md` → surface at close → **user decides per-gap**: accept-as-degraded or lift an independent follow-on session (sequenced into the milestone, not absorbed into the track's phase count). Now that the track is closed, every open row is a standalone per-gap user lift decision.

**Working folder** — `sessions/public website/` (committed; the artifacts are the deliverable): `brief.md` / `copy.md` / `references.md` + reference PNGs (inputs, do not edit); `existing-*-export.json` (ground-truth inputs); the five `{home,about,pricing,contact,demo}.json`; the per-page layout specs + `pricing-chart-widget-spec.md` + `demo-page-spec.md`; `audit-summary.md`; `gap-report.md`; `screenshots/page-*.png`; `build-summary.md` (the close-out).

**Track did not own:** the two in-product demo landing pages (`/my-nonprofit` donation-campaign LP, `/my-nonprofit-workshop` event LP) — functional product demonstrations inside demo accounts, excluded from the main site nav per the brief. They were never track deliverables and have no session; if built, they are post-Beta work the user lifts separately (recorded so the deferral is explicit, not implicit in the brief).

**Stance (held throughout):** the deliverable was content, not code (code lands only via a lifted gap); dogfood discipline (CMS friction → a gap); voice protection (a typesetter for the supplied copy, not a copywriter); visual restraint (structural borrow only; reference images were structural specs, never surface inspiration).

---

*All phases closed. No forward plan. Future work touching the marketing site (gap resolutions, the milestone-close gates, real photography, the demo LPs) runs as independent sessions outside this — now closed — track.*
