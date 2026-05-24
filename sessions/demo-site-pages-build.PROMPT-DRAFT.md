# NNN. Demo Site Pages — Build

> **DRAFT — no session number.** Slot this into the numeric sequence on return (rename to `NNN. Demo Site Pages — Build.md`). Drafted by the A006 cloud design session. The base-prompt rules (process, close gate, style) apply and are not repeated here.

Author the demo install's public website in the CMS, export the pages to JSON, and wire them into the demo baseline so the daily `demo:reset` re-establishes them. This is the **build** counterpart to A006's design — A006 produced the spec; this session produces the artifact.

---

## Stub reference

No `release-plan.md` slot. This executes the design in **`sessions/public website/demo-site-pages-spec.md`** (the canonical, build-ready document — read it first and in full). Context: the demo node (session 321) has lockdown + a daily `demo:reset` CRM baseline but no curated public site on top of it; A006 designed that site (Mill Creek Conservancy, 6 authored pages + the reused `/demo` gate). This session builds it.

**Supplants** the "sample event / sample donation showcase page" intent of the Default Content State stub — **demo-node only** (ratified at A006; fresh-install defaults untouched).

---

## Session-specific deltas

- **Spec is canonical.** `demo-site-pages-spec.md` carries the org identity, the seven-page list with per-page widget handles, the per-page copy, the data alignment, the nav, and the reset-wiring design. Build to it; don't re-derive.
- **Compose existing widgets only.** No new widgets. The spec's §6 gaps are data/wiring/deploy items, not missing widgets.
- **Build inside the product's own CMS**, the same dogfooding path the marketing site used (layout-spec-driven band build; zero importer warnings on first import is the quality bar). Reuse the section-band / appearance-config conventions in `home.json` / `about.json`.
- **Pages must render populated** against the `RandomDataGenerator` baseline (≈40 contacts / 6 events / 30 donations / 15 memberships / 5 posts / 6 products). The listing widgets (`events_listing`, `event_calendar`, `recent_donations`, `blog_listing`) query records at request time — author them against that data shape.

---

## Open questions to resolve at session start

Most are pre-resolved in the spec (§0). Two implementation choices remain:

- **Board collection home (spec §5.2):** bundle the curated board collection on the page-import path (lean) vs. a small dedicated seeder. Pick on first contact with the exporter's collection support.
- **Shipped-bundle location (spec §5.1):** confirm `database/seeders/demo_pages/` ships in the `--no-dev` image and is readable at reset time; make it the single source, mirror to `sessions/public website/` for review.

---

## Phases

### Phase 1 — Author the six pages in the CMS

Build Home, Events, Donate, About, Contact, News to spec §3 (bands, widget handles, copy). Use the production chrome defaults at build time (E16 / session 322 may have changed them — do **not** author around the current chrome; spec §4.1). Seed the curated board collection (spec §5.2, §3.4) so `board_members` on About renders. Verify each page renders populated against a local CRM baseline.

### Phase 2 — Export + bring in the `/demo` gate

Export each authored page to JSON; place canonical copies under `database/seeders/demo_pages/` and mirror to `sessions/public website/demo-*.json`. Copy the existing `sessions/public website/demo.json` into the demo page set (spec §4.2) — no clipping logic, it's just one more bundle. Achieve zero importer warnings on a clean re-import.

### Phase 3 — Wire the reset (spec §5.3)

Add a page-import step to `DemoBaselineSeeder::run()`, before the existing `wipe()` + `generate()`, inside the resolved-super_admin acting context: iterate `database/seeders/demo_pages/*.json`, `ContentImporter::import($bundle, new ImportLog(), ['replace_duplicate_pages' => true, 'import_media' => true])`. Put it inside `run()` (not only the full path) so `--soft` also restores defaced pages. Confirm idempotency (re-running returns the same site, not duplicated pages) and that curated Home/About override the framework starter pages by slug.

### Phase 4 — Resolve the Faker `--no-dev` blocker (spec §5.4)

`RandomDataGenerator` uses factories → `fakerphp/faker` (in `require-dev`); the prod `--no-dev` image lacks it and `demo:reset`'s baseline step fails. **Lean: move `fakerphp/faker` to `require`** (one-line `composer.json`), or build the demo node from a dev-deps image. This blocks the live demo regardless of pages — resolve it here.

### Phase 5 — Dress-rehearsal (session-321 isolated-stack recipe)

Run the isolated e2e stack flipped to `APP_ENV=demo` (transient, uncommitted compose override — dev DB untouched). Confirm end-to-end: `demo:reset` rebuilds + seeds + **imports the curated pages**; the seven pages render populated (events/donations/posts visible, board renders); `/` is the Mill Creek home, not the framework starter; a `--soft` reset restores a manually-defaced page; importer warnings are zero. Tear the stack down (`down -v`) and remove the override afterward.

---

## Out of scope

- New widgets (composition only; spec §6 gaps are data/wiring, not widgets).
- Changing fresh-install default content (demo-node only — spec §0).
- The live `nonprofitcrm.com` marketing site.
- FM-side demo work (node identity, version pin, egress firewall).
- Real photography; the `/demo` dark-side real copy swap (launch-time tasks tracked in `build-summary.md`).

---

## Testing

- **Slow Pest groups to run:** none specific; run the full fast suite (`./dev test`) as the close gate.
- **New tests expected:** **yes.**
  - `demo:reset` re-imports the curated pages (the seven slugs exist + published after a full reset; a `--soft` reset restores a deleted/edited page).
  - The curated pages override the framework starter pages by slug (Home is Mill Creek, not the starter).
  - The listing widgets render against the generated baseline (non-empty `events_listing` / `recent_donations` / `blog_listing` given the baseline counts).
  - Idempotency: two consecutive resets yield identical page counts (no duplication).
  - Faker availability: the baseline step runs without `Faker\Factory not found` (guards the Phase 4 resolution).

---

## Closing steps

Follow the close gate in the base prompt. Session-specific details:

- **Log file:** `sessions/NNN. Demo Site Pages — Build — Log.md`.
- **Branch:** `session-NNN/N` (final iteration).
- **Deliverables:** the seven page bundles (`database/seeders/demo_pages/*.json` + `sessions/public website/demo-*.json` mirror); the `DemoBaselineSeeder` import wiring; the Faker resolution; the new Pest tests; dress-rehearsal confirmation in the log.
- **VERSION:** bump per the pre-Beta pseudo-versioning discipline.
- **Cross-Repo:** non-boundary (v2.3.0 unchanged); no FM coordination beyond the existing handoff note.
- **Planning bookkeeping:** completed-sessions row; update `session-outlines.md`'s Default Content State note (the demo-site pages now exist as the showcase vehicle — point at the built artifact).
