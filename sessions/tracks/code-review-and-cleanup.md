# Track: Code Review & Cleanup

The standing maintenance arc — periodic audit of accumulated drift, application of the resulting cleanup picks, and migration squash. Recurring rather than time-bounded: each ~50 sessions of growth triggers a new cycle. Unlike Widget Primitive or Fleet Manager Agent (both arcs with a defined endpoint), this track has no closure event; cycle retrospectives accumulate indefinitely as the codebase ages.

This doc carries three things:

- **Status snapshot** — where the track stands, when the next cycle triggers.
- **Cycle Retrospectives** — compressed history of closed cycles (sessions list, outcomes, key decisions, carry-forwards).
- **Forward plan** — the canonical cycle shape, cadence trigger, standing improvements, and permanent inter-cycle gates.

When a cycle closes, its retrospective lands here and the cluster's release-plan position collapses to a one-liner. Per-session detail stays in the matching `sessions/(archived/)NNN. … — Log.md` files.

---

## Status snapshot

**Last update:** 2026-06-08 (**Cycle 3 fully closed** at the 348 migration squash — audit 344 + apply 345 + carve-out A 346 + carve-out B 347 + squash 348 all ✅; Cycle-3 retrospective added below).

**Complete:** Cycle 1 (sessions 205 / 206 audit + apply, 208 squash; carve-out 207 — Column Layout Inspector Appearance Unification). Cycle 2 (sessions 271 / 272 audit, 273 apply, 274 squash; carve-out 275 — Rich-Text Surface Sanitization Hardening). Cycle 3 (session 344 audit, 345 apply, 348 squash; carve-outs 346 — Bundle Import/Export Split + 347 — InlineFormatToolbar Extraction).

**Active:** none. Cycle 3 closed at the 348 squash; no cycle is in flight. See the Cycle-3 retrospective below for the full per-session detail.

**Next trigger:** approximately session **398** (after the Cycle-3 squash at 348; ~50 sessions of growth is the target cadence). Trigger condition is "session ~398 OR a forcing function — whichever comes first." Forcing functions historically: a refactor that wants a clean baseline, a flag carry-forward that's been deferred two cycles in a row, an audit-shaped finding surfacing during regular work that warrants a full sweep.

---

## Cycle Retrospectives

### Cycle 1 — Audit + Squash (sessions 205 / 206 / 208; carve-out 207)

Window covered: pre-205 codebase. Three-session cluster: audit + apply (205 / 206), squash (208). One in-cycle carve-out (207 — Column Layout Inspector Appearance Unification, lifted out of the audit's apply-half).

Outcomes (per session logs):

- **Flag A** raised against `LayoutInspectorPanel.vue` architectural divergence — re-framed in Cycle 2 W4b as "substantially closed" by 207's `appearance_config jsonb` migration + reuse of `BackgroundPanel` / `SectionLayoutPanel`. Final closure landed in Cycle 2 (273/5).
- **Flag B** raised against `ImportContactsPage` convergence with the namespaced importer pattern — won't-fix; reaffirmed at Cycles 1 and 2 closes.
- **Flag C** schema-key filter on `PageBuilderApiController::update()` — gate landed at Cycle 1 close, still in place.
- Squash absorbed pre-205 migration history into the schema dump baseline at session 208.

Carry-forwards from Cycle 1 (active at Cycle 2 start): Flag A re-evaluation (closed at Cycle 2 W7 #5); Flag B re-affirmation (held).

Reference: `sessions/archived/207. Column Layout Inspector — Appearance Unification — Log.md` for the canonical carve-out shape that Cycle 2's W4c → 275 repeated.

### Cycle 2 — Audit / Apply / Squash + Carve-out (sessions 271 / 272 / 273 / 274; carve-out 275)

Window covered: sessions 207 → 268, ~60 sessions of growth since Cycle 1's squash. Four-session cluster: audit-half-1 (271 horizontal sweeps + W11/W12 quantitative), audit-half-2 (272 subsystem deep walks), apply (273, six iterations on one branch), squash (274). One in-cycle carve-out lifted at 273 close (275 — Rich-Text Surface Sanitization Hardening, mirroring 207's shape).

#### Quantitative outcomes

| Gate | Pre-cycle baseline (268) | Cycle 2 close (274) | Net delta |
|------|--------------------------|---------------------|-----------|
| Fast Pest | 2163 / 0 | 2166 / 0 | +3 (271 PG-skew test, 273 +5 observer/parity, 274 −3 obsolete migration test deletion) |
| Playwright | 37 / 4 flaky on first run | 42 / 0 | +5 specs, 4 flakes resolved |
| Migrations on disk | 18 (208 → 273 window) | 0 | −18 absorbed into 3544→3915-line schema dump |
| Resources gated `canAccess()` | 3 of 21 | 21 of 21 | +18 (Flag W6/A) |
| Observers covering CUD | Contact + Membership + Donation | + Note, Organization, Affiliation, DonationCredit | +4 (W8 #2 / Flag W6/B) |
| FM contract version sources | 3 independent (drift bug at 268) | 1 + parity test | −2 (W8 #3 / Flag W4/A) |
| `editor.ts` page-builder store | 930 LOC monolith | 700 LOC + 4 composables | extraction split |
| `LayoutInspectorPanel.vue` | 780 LOC (Flag A from 205, open) | 208 LOC + dedicated tab component | Flag A finally resolved |
| Dead imports | 24 across 19 files | 0 | sweep |
| Orphan files | 1 (`posts/show.blade.php`) | 0 | deleted |

#### Load-bearing decisions and gates

- **`HasContractVersion` trait + `FmContractVersionParityTest`** (273/4) — single declaration site for `'2.3.0'`; CI-enforced parity against the spec doc's `Contract Version:` line. Gates against future contract-version drift permanently.
- **`#[ObservedBy]` attribute unification** (273/1) — all observed models use the modern Eloquent attribute pattern; old `Model::observe()` calls in `AppServiceProvider::boot()` retired. Foundation that cleanly composed with the `SanitisesRichTextCustomFields` trait shipped at 275.
- **`canAccess()` sweep across 18 Resources** (271 W6/A) — uniform permission-gate pattern; all admin Resources now share the same shape.
- **Carve-out shape locked in** — W4c → 275 mirrored 207's procedural shape; pattern is now established as the canonical response to audit-half findings exceeding apply-iteration capacity.

#### Blind spots that surfaced in 275

Two real bugs the Cycle 2 audit + apply did not catch (both surfaced during Session 275's work):

1. **Dead inert code that compiles cleanly.** `CollectionResource::getRelationManagers()` (Filament 2 method name) instead of `getRelations()` (Filament 3) — the misnamed method has never been called; the items relation manager has never rendered on `/admin/collections/{id}/edit`. The audit's grep + horizontal sweeps don't catch dead code that compiles and breaks no tests.
2. **Codebase-convention drift inside a new addition.** `SanitisesRichTextCustomFields` trait (introduced at session 275 boundary #6) queried `model_type` with FQCN; the codebase convention is lowercase short names (`'contact'`, `'event'`, `'page'`). Production importer paths silently no-op'd. Caught when an importer-driven test exercised the path; missed when the trait's own boundary test happened to use FQCN-form defs and masked the bug.

Both are class-of-bug "the test would have caught it if a test exercised that path" — neither audits nor grep can substitute for the test that doesn't exist yet.

#### Process incidents

- **273/5 parallel Pest+Playwright run took down nginx/PHP-FPM.** Surfaced the recovery-ladder discipline (now `feedback_test_runs_not_parallel.md`) and the "must run sequentially" rule. Real cost: a few minutes of debugging + a new memory rule.
- **Cumulative-load FilePond polling flake** carried from 273 to 274 as a passive watch; did not reappear at 274 or 275.

#### Won't-fixes reaffirmed

- W11 PHP outliers (`InteractsWithImportWizard` 1192 LOC, `ImportContactsPage` 1017 LOC, `InteractsWithImportProgress` 950 LOC) — consequence-of-pattern, not extractable today.
- W7 #2 (`ImporterPage` action closure repetition) — Filament idiomatic shape; factoring would obscure.
- W8 #4 (chunked-tick → queued-job migration) — substantial rewrite, future-session candidate if forcing function emerges.
- Flag B (Contacts importer namespaced-pattern convergence) — divergence is real and load-bearing per session 206; won't-fix.

References: `sessions/archived/271–274. … — Log.md` for full per-session detail.

### Cycle 3 — Audit / Apply / Squash + two Carve-outs (sessions 344 / 345 / 348; carve-outs 346 / 347)

Window covered: sessions 274 → 343, ~70 sessions of growth since Cycle 2's squash. The compressed 3-session shape (344 audit / 345 apply / 348 squash) expanded to 5 by two carve-outs lifted at the audit close (346 — Bundle Import/Export Split; 347 — InlineFormatToolbar Extraction), both placed before the squash so the squash absorbed any migrations they produced. The audit folded Cycle 2's audit-pair into one pass and ran the three Cycle-3 standing additions (static-reflection scan, boolean-settings sweep, integration-seam sweep).

#### Quantitative outcomes

| Gate | Pre-cycle baseline (343) | Cycle 3 close (348) | Net delta |
|------|--------------------------|---------------------|-----------|
| Fast Pest | 2799 / 0 | 2812 / 0 | +13 (345 +15 apply; 346 +2 `BootstrapCacheVolume` guards via the 345/2 merge; 347 +0 Vue-only; 348 −4 obsolete migration-test fallout) |
| Migrations on disk | 13 (274 → 347 window) | 0 | −13 absorbed into 3915→4015-line schema dump |
| `ContentImporter` | 1961 LOC monolith | 555 + `Import/` collaborators | 346 clean split |
| `ContentExporter` | 1168 LOC monolith | 404 + `Export/` collaborators | 346 clean split |
| `InlineFormatToolbar.vue` | 1768 LOC | 1071 + 4 composables + 3 sub-components | 347 split (−39%) |
| Open Flags (344-A…G) | — | A–E applied, F blessed, G deferred | 5 fixed / 1 blessed / 1 carried |
| Standing inter-cycle gates | FM parity test only | + `ConventionDriftTest` + `filament-signature-scan.php` | +2 (345 / 344) |

#### Load-bearing decisions and gates

- **`ConventionDriftTest` (345/5)** — the Cycle-3 standing deliverable; one gate wrapping the reusable `scripts/filament-signature-scan.php` (no `[KNOWN-BAD]` signature hits — pins the `getRelationManagers` inert-dead-code class Cycle 2 missed), the `model_type` lowercase-short-name convention, the load-bearing trait constants, and the boolean-SiteSetting convention. Now permanent alongside `FmContractVersionParityTest`.
- **`filament-signature-scan.php` (344)** — reflects every app Filament subclass against its framework ancestors; caught the live `getRelationManagers` v2/v3 method-name bug (dead code that compiled and broke no test) on first run. Reusable each cycle.
- **Carve-out shape reaffirmed** — 346/347 lifted at the audit close per the 207/275 precedent, both behaviour-preserving clean decompositions the owner chose over light file-moves, both absorbed by the squash.
- **Squash data-phase discipline (348)** — the reusable insight: on `migrate:fresh`, migrations run before seeders against an empty DB, so data-phase transforms are no-ops at fresh-install time and the seeders alone produce the end shape; the green suite is the standing proof (verified concretely — media 44/44 `content_hash` + CAS layout on disk; dropped-column writes self-check). Phase 5 stayed a separate buffer commit for the one obsolete migration-test fallout (`MemosTrixToQuillTest`, 4 cases), mirroring 274.

#### Process incidents

- **Mid-346 deploy-brick (FM-surfaced).** The 0.341.01→0.345.06 upgrade bricked a live node and the auto-deploy server with a stale `bootstrap/cache` package manifest naming the 342-removed Debugbar provider (a persistent named volume shadowed the new image's manifest, fataling at framework bootstrap). Fixed on `session-345/2` — entrypoint manifest-clear + a baked HEALTHCHECK booting framework+DB (so a non-booting image fails at `docker_up` instead of false-greening into migrate), the `bootstrap_cache` volume dropped from prod compose, `BootstrapCacheVolumeTest` guarding both — merged + deployed by the owner. Not the FM contract endpoint; `HealthController` untouched, v2.3.0.

#### Carry-forwards into Cycle 4

- **Flag 344-G** (Transaction/Campaign/Fund audit-trail observer) — judgment call, deferred at 345 and again at 348. First cycle-boundary carry; per the cadence rule a flag deferred two cycles running becomes a forcing function.
- Standing won't-fixes reaffirmed: three large importer PHP files, `ImporterPage` action closures, chunked-tick→queued-job, Flag B (Contacts importer convergence), `_custom.scss` partial extraction.

References: `sessions/(archived/)344 / 345 / 346 / 347 / 348. … — Log.md` for full per-session detail.

---

## Forward plan — next cycle (≈ session 398)

### Cycle shape — 3 sessions instead of 4

Cycle 2's four-session shape was a tiny bit long. The canonical shape is **3 sessions: audit / apply / squash**, with carve-outs lifted at the audit close when apply scope exceeds the ~6-iteration ceiling. Cycle 3 ran exactly this — 3 sessions + 2 carve-outs (346/347) — and confirmed it: the audit folds both halves, apply stays bounded, and the squash stays a clean standalone.

- **Audit session** (single, replacing 271 + 272). Folds horizontal sweeps (W1/W5/W6/W9/W10/W11/W12 quantitative output) and subsystem deep walks (W2/W3/W4/W4b/W4c) into one session. Output is the merged W7/W8 tables, the Open Flags block, and the carve-out decisions. The sweep findings feed the deep walks within the same context window. Drop the audit-pair shape; one audit session handles both halves.
- **Apply session** (replaces 273). Six-iteration ceiling — past 6 iterations is the carve-out smell. Apply consumes the W7/W8/W11/W12/Open Flags backlog deliberately; iterations land per-extraction-or-fix on a single `session-NNN/1` branch.
- **Squash session** (replaces 274). Migration squash + small Phase 3 picks that didn't fit the apply session. Procedurally light, mechanical, high-leverage. Stays standalone — folding into the apply session mixes transactional risk profiles.

The carve-out shape stays the same. If the audit surfaces multi-iteration scope that exceeds the apply session's capacity, lift to a dedicated successor session per the 207 / 275 precedent.

### Cadence trigger

**Approximately every 50 sessions of growth** (Cycle 2 was 60; aiming tighter). Trigger evaluation rules:

1. **Hard cadence:** at session ≈ (last cycle squash + 50), schedule the next cycle's audit session.
2. **Forcing function:** lift sooner if a refactor wants a clean baseline, a flag carry-forward gets deferred two cycles in a row, or an audit-shaped finding surfaces during regular work that warrants a full sweep.
3. **Don't compress under cadence pressure.** A cycle that hits the cadence trigger but reveals more work than 3 sessions can absorb should expand to 4 (or carve-out) rather than truncate. Cadence pacing is a planning aid, not a constraint on the work.

### Standing improvements (Cycle 3+ carries)

Lifted from Cycle 2's blind-spot list and process incidents:

- **Convention-drift Pest test as a permanent inter-cycle gate.** New test (or extension of the existing `FmContractVersionParityTest`) scans for known divergences: Filament resource method names against the v3 base class signatures (catches the `getRelationManagers` → `getRelations` class of bug); `CustomFieldDef::query()->where('model_type', ...)` call sites match the lowercase short-name convention; trait-constant access patterns. Each cycle adds rows for new convention drift the audit surfaces. Catches inert-dead-code and convention drift between cycles.
- **Static reflection in the audit pass.** Add a scripted scan of resource subclasses comparing public/protected method signatures against framework base-class signatures. Catches "wrong method name, dead code" cases the grep+horizontal-sweep miss. Reusable in future cycles.
- **Carve-out auto-flag past ~6 commits.** Don't relitigate at apply-session close; decide at audit-session close. Audit-half findings exceeding ~6 iterations of apply work auto-flag a carve-out candidate. Saves the end-of-apply "this needs its own session" conversation.
- **Apply-iteration ceiling = 6 commits.** Past 6 iterations on a single apply-session branch is a smell — at that point, the next-iteration content is the carve-out. Hard heuristic, not soft.
- **W11 outlier auto-skip-with-note.** Outliers >5× language average that pre-existed two cycles ago and aren't in active work skip with a brief reaffirmation note. Cuts re-litigation time. Lift only when a forcing function emerges (a feature touching the file, a flag finally maturing).
- **Pest + Playwright sequential run discipline.** Already locked in as `feedback_test_runs_not_parallel.md`. No new action; standing rule.
- **Squash session keeps Phase 5 as a buffer.** Cycle 2's squash needed a follow-on commit to delete an obsolete migration test file (3 tests). Keep the Phase 5 slot for fallout from the squash; don't bundle into the squash commit itself.
- **Boolean SiteSetting / config-shape consistency audit.** Cycle 3 audit pass should sweep every boolean-shaped tenant setting and per-model boolean field for read/write shape drift — `'1'`/`'0'` vs. `'true'`/`'false'` vs. native `bool` casts vs. `filter_var(..., FILTER_VALIDATE_BOOLEAN)`. Today's `horizon_enabled` is the local `'true'`/`'false'` precedent for `SiteSetting` boolean keys and was followed for `notes_edit_only_by_creator` at session 276; codify the convention and surface every diverging call site as an Apply pick. Lifted from session 276 design discussion — there is no benefit to two storage shapes, and cross-shape comparison bugs are silent.
- **Integration-seam sweep.** Cycle 3+ audit should add an explicit pass for *multiple write paths into the same data* and *implicit defaults that disagree across paths*. The session-308 typography incident is the local pattern: the Theme/Typography editor and `ContentImporter::importDesign()` both wrote to `SiteSetting::typography` with no user-visible conflict resolution; the import path silently won. The audit catch is not "this system is broken" — both sides are correctly built; the seam between them was implicit. Sweep targets: every `SiteSetting` row touched from more than one call site, every Eloquent observer firing on a model also written by an importer, every shared-default constant (Resolver defaults, blade snippets) consumed by more than one writer. Won't-fix is fine where the overlap is intentional and conflict-free; surface the unintentional or silent-winner cases. Memory ref: `feedback_audit_integration_seams.md` — most reported app-level bugs are this class, not greenfield gaps, and the fix is usually a flag + a pre-flight check at the seam rather than rebuilding either system.

### Permanent inter-cycle artifacts

- **`tests/Feature/Infrastructure/FmContractVersionParityTest.php`** (273/4) — gates FM contract version-stamp drift across `HealthController`, `BackupController`, and the spec doc's `Contract Version:` line. Existing.
- **Convention-drift Pest test** — shipped at 345/5 as `tests/Feature/Infrastructure/ConventionDriftTest.php`, alongside the FM parity test; wraps `scripts/filament-signature-scan.php` + the `model_type` / trait-constant / boolean-SiteSetting conventions. Each cycle adds rows for new convention drift the audit surfaces.
- **`sessions/code-review-large-files-partial-extraction.md`** — advisory artifact from Cycle 2 (session 270, predating the cluster). Reusable as a starting input for Cycle 3 audit's W11 row evaluations; refresh during the audit session by re-running its grep counts.

### What stays in the release plan vs. what stays here

- **The track doc (this file)** owns the *cycle shape*, the *cadence rule*, the *retrospectives*, and the *standing improvements*.
- **`sessions/release-plan.md`** carries each cycle's three numbered execution-order entries when the cycle is queued (mirroring how 271–274 sat at #26 in the previous shape). Position in execution order is the release-plan's concern; *when* and *how* the cycle runs is the track doc's concern.
- **`sessions/session-outlines.md`** carries a one-liner under active tracks pointing here. Per-cycle detail stops bloating the outlines doc.
