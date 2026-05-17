# NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard & Fixture-Test Remediation

Stop the test suite from manufacturing the stale-stylesheet condition, add a cheap automatic check that fails the moment the served public stylesheet no longer matches saved settings, and rewrite or delete the cluster of tests that re-implement production logic and prove nothing — applying an already-completed, canonical post-incident audit, scoped to the two incident lessons.

---

## Stub reference (emergent forcing function)

No `release-plan.md` entry and no `session-outlines.md` stub — this is an **emergent, post-incident session**. The forcing function is a real production incident:

> The public site's primary button rendered orange/pill though no saved setting asked for it. Root cause: a stale compiled CSS file under the **gitignored, untracked** `public/build/widgets/` directory kept being served after the setting that produced it was removed; the build output is not tied to the settings it came from and nothing reconciles the two, so the drift went undetected until an unrelated rebuild. The investigation was nearly derailed because a fixture-based Pest test using those exact orange/pill values was cited as "proof the button was configured that way and working as intended" — a wrong inference: that test invents its own settings and checks the code echoes them; it says nothing about real-world state.

The two lessons drive the entire scope:

1. **The build pipeline can serve CSS that no longer matches saved configuration, with nothing detecting the drift.**
2. **Fixture-based tests confirm code behaviour but must never be read as confirmation of real-world state.**

The findings audit is **complete and canonical** (it produced the table below). This session *applies* the audit. It does not re-audit, does not widen scope, and does not re-litigate the agreed calls.

---

## Session-specific deltas

- **Scope is fixed by user decision.** The audit surfaced findings across three tiers. The user scoped this session to **Tier 1 (the incident cluster) + Tier 2a (the "green but proves nothing" group)**. Tier 3 (lower-priority naming/scoping fixes) is **explicitly deferred to the housekeeping inbox** — see Out of scope. Do not pull deferred items forward.
- **The drift guard is the centerpiece, and its shape is decided: a cheap, deterministic, in-suite check** (the user approved "cheap auto check" and explicitly ruled out the heavier production/Fleet-contract option). The mechanism is latent in the code already: `AssetBuildService::build()` derives `$hash = substr(md5(json_encode($this->collectSources())), 0, 8)` and bakes it into both the bundle filename and `manifest.json`. Drift detection is therefore: *recompute the current source hash; if it differs from the hash the served `manifest.json` encodes (or the manifest is absent while widget/settings sources exist), the served CSS no longer matches saved configuration.* Confirm the exact manifest/hash shape against the actual `AssetBuildService` at start and adapt silently to any drift in field names.
- **No production surface changes.** No new admin page, no `/api/health` field, no Fleet contract. CRM contract stays v2.3.0. An optional thin `php artisan` wrapper around the guard for operator convenience is permitted **only if cheap and non-boundary**; the in-suite test is the must-have.
- **The incident's own tests are NOT deleted.** `DesignSystemButtonsTest`'s button-CSS tests are accurately named generator round-trips — correct tests. The only change there is de-evocative fixture values (the brand-suggestive orange/pill values invited the misread). Keep the tests; change the fixtures; the new drift guard is what actually closes the gap they were wrongly assumed to cover.

### Forward-awareness — an upcoming taxonomy & tooling reshuffle (fold into the design, do not design against today's shape only)

A re-taxonomy and tooling reshuffle of the design/theme surface is scheduled to follow this session. The audit could not have known this; three concrete implications must shape the guard so it does not bake in an assumption that silently rots:

1. **The optional `built_at`-vs-settings heuristic must compare against the whole *design settings group*, not a hardcoded key list.** The reshuffle adds color-palette + scheme settings to that same design group. If the heuristic names `button_styles`/`typography` literally, it gets a blind spot for exactly the new tokens. Resolve the design group dynamically (whatever `SiteSetting` `group` the build-relevant design settings carry — confirm against the model/seed at start). The primary content-hash check is already robust to this — it hashes `collectSources()` output regardless of which settings fed it — so this caveat applies *only* to the optional second mechanism.
2. **A second, deliberately drift-proof delivery layer is incoming — the guard must be bundle-only *by intent*, not by assumption.** Post-reshuffle, per-template scheme overrides are delivered **request-time inline**, not via the build-server bundle, so they *cannot* drift and the guard correctly must **not** try to cover them. The hazard is writing the guard on an implicit "all themeable CSS lives in the bundle" premise, which becomes false. Scope the guard explicitly to the build-server bundle (the `collectSources()` → `manifest.json` path) and state in the test/method that request-time inline delivery is out of scope by design — so the boundary is intentional and survives the reshuffle, rather than rotting into a false-negative.
3. **Do not over-invest in finding 5.** `generateButtonOverrideCss()` and the whole button/color delivery path are being re-architected by the reshuffle. The fixture de-evocation is still worth the ~5 minutes (the tests remain valid round-trips and the brand-evocative values genuinely invited the misread), but **do not elaborate, harden, or extend that area** — no extra button-CSS assertions, no refactor there. The real closure is the Phase 1 guard, which already carries the weight.

---

## Canonical findings table (this is the scope)

### Tier 1 — Lesson 1 (build pipeline serves drifting CSS undetected)

| # | File · test(s) · lines | Problem | Action |
|---|---|---|---|
| 1 | `tests/Feature/BuildServerSettingsSession125Test.php` — "prefers site settings over env config…" (81), "falls back to env config…" (117) | Calls real `AssetBuildService::build()` with faked HTTP; **writes the real `public/build/widgets/manifest.json` + a `.test{}` bundle, no cleanup**. After a fast-suite run the served manifest points at a 6-byte stub. `$result` is never asserted — the build can fully fail and the test still passes. | **REWRITE** — isolate output away from `public/build/**`; assert which URL was used **and** that `$result->success` is true. |
| 2 | `tests/Feature/WidgetJsLibsSession138Test.php` — "buildLibraryBundles produces files…" (64), "manifest includes libs key…" (95) | Writes real `public/build/libs/*` + `manifest.json`, then `File::deleteDirectory(public_path('build/widgets'))` — **deletes the entire real served bundle dir**; cleanup is unguarded (skipped if an assertion throws). | **REWRITE** — isolate output to a temp/faked path; assert manifest shape there; no `public/build/**` writes or deletes. |
| 3 | `tests/Feature/DashboardSlotGridTest.php` — "dashboard page loads successfully for an authenticated super_admin" (77) | Reads the real on-disk `manifest.json`; asserts the swiper-lib markup **only if the untracked file happens to exist**. On a clean checkout the assertion silently no-ops — passes regardless of whether lib injection works. | **REWRITE** — stub a manifest in-test (no real-FS read) and assert the lib markup unconditionally; or split the manifest-dependent assertion into its own deterministic test. |
| 4 | `tests/e2e/page-builder/full-width-matrix.spec.ts` — "editor renders solid bg color set via DB on a column" (273), "…gradient bg…" (290) | Assert *computed* appearance resolved through the served bundle; nothing in the Playwright harness forces a fresh `build:public`, so a stale `public/build` produces a green run — the exact drift that derailed the original investigation. | **ADD-DRIFT-GUARD** — force a fresh build before the assertion, or assert the freshly-composed inline style attribute rather than the bundle-resolved computed value. |
| 5 | `tests/Feature/DesignSystemButtonsTest.php` — "saves button styles…" (18), "emits secondary-dark CSS custom properties in the public bundle" (62), "generates button override css in the build pipeline" (114) | Tests are **correct and accurately named** (generator round-trips). Not misleading by name — the incident was a human misread. But the brand-evocative orange/pill fixture values (`#ff0000`, `#ff5500`, `pill`) invited that misread, and nothing reconciles the served bundle against saved settings. | **KEEP** the tests; swap fixtures to obviously-synthetic values (e.g. `#123456`, a non-`pill` radius) — **and stop there**. Button/color delivery (`generateButtonOverrideCss()`) is being re-architected by the incoming reshuffle; the swap is ~5 min and the tests stay valid round-trips, but do not elaborate, harden, or extend this area. The reconciliation gap is closed by the **new drift guard** (Phase 1), not by editing these. |
| 6 | *(structural gap — no test)* | Nothing anywhere reconciles served `public/build/widgets/*.css` against current `SiteSetting` button/typography config or the manifest's own `built_at`. The incident is undetectable by construction. | **ADD** the drift guard + its two-direction regression test (the centerpiece — Phase 1). |

### Tier 2a — Lesson 2 (tests re-implement production logic and assert their own copy)

| # | File · test(s) · lines | Problem | Action |
|---|---|---|---|
| 7 | `tests/Feature/ContactExportTest.php` — content-type (14), header row (48), row-per-contact (68), filename-date (96) | Rebuilds the CSV inline; **never calls the exporter**. Line 96 interpolates the date then asserts the string contains it — cannot fail. Names claim "the export…". | **REWRITE** 14/48/68 to invoke the real export action/route and assert its real headers/body/row-count. **DELETE** the line-96 tautology (or fold a real `Content-Disposition` assertion into the rewritten content-type test). |
| 8 | `tests/Feature/CustomFieldTest.php` — "contact export includes custom field columns after standard columns" (264) | A code comment concedes the export "is manual-tested"; the body asserts DB rows. The name claims export column ordering, which is never exercised. | **REWRITE** to exercise the real export and assert custom-field columns follow standard ones; or **RENAME-RESCOPE** to a name that matches what it actually checks. |
| 9 | `tests/Feature/TaxReceiptTest.php` — "…correct total…" (14), "…multi-fund breakdown" (71) | Re-implements `DonorsPage::buildBreakdown()` inline ("Simulate the buildBreakdown logic…") and asserts its own copy. Cannot catch a regression in the real receipt path. | **REWRITE** to call `DonorsPage::buildBreakdown()` (or the receipt service) directly so the production path is exercised. |
| 10 | `tests/Feature/PublishedAtBackfillTest.php` — whole file, 6 tests (11, 30, 43, 58, 77, 91) | Runs raw `DB::statement("UPDATE events SET published_at = created_at …")` **inline** and asserts the SQL did what the SQL says. **Confirmed at scoping: no live backfill migration or command exists** in `database/migrations/` or `app/Console`. The tests guard nothing. | **DELETE the file** — unless the start-of-session investigation reveals a backfill is genuinely *needed and missing* (an open question, below): then write the production backfill and a test that invokes it. |
| 11 | `tests/Feature/WidgetDefaultsLintTest.php` — "detects a toggle-with-string-default as a lint failure" (108) | Asserts `is_bool('yes')` is false; **never invokes the lint**. A broken linter passes. The name claims the lint detects the defect. | **REWRITE** to run the real lint routine over the bad definition and assert it reports the failure; **DELETE** only if a sibling test already does this. |

---

## Open questions to resolve at session start

1. **`PublishedAtBackfillTest` — delete vs. build-the-missing-feature.** Confirmed at scoping: no live `published_at` backfill exists. Default action is **delete the file** (it guards nothing). Decide at start whether a backfill is actually *needed* (e.g., legacy rows with null `published_at` in a real deployment). If not needed → delete. If genuinely needed and missing → that is a real production gap: **surface it** (it expands scope; do not silently build it). Cheap to reverse if delete is wrong; the surfacing trigger is "a real missing feature," per the decision-threshold rule.
2. **Test-isolation mechanism for the build-writing tests (findings 1–3).** Pick the cleanest of: (a) make `AssetBuildService`'s output dir constructor-injectable and point tests (and the drift-guard test) at a temp dir; (b) test-time base-path/`Storage` override; (c) faked disk. Recommendation: **(a)** — smallest change, also lets the drift-guard test build into a temp dir without touching real `public/build`. Local design call; decide and note unless (a) turns out to require a non-local refactor (then surface).
3. **Drift-guard surface — in-suite only, or also a thin CLI.** The in-suite regression test is the must-have. A `php artisan` wrapper for operators is optional and only if cheap; it must stay non-boundary (no `/api/health`, no Fleet contract). Decide based on cost once the guard method exists.

---

## Phases

### Phase 1 — The drift guard (centerpiece)

Add a cheap, deterministic reconciliation check **scoped, by intent, to the build-server bundle** (`collectSources()` → `manifest.json`): recompute the current source hash from `AssetBuildService::collectSources()` and compare it to the hash the served `manifest.json` encodes (confirm exact shape against the code first). Define "stale" precisely: hash mismatch, **or** manifest absent while source content exists, **or** (if cheap and unambiguous) manifest `built_at` older than the latest update within the **design settings group resolved dynamically** — *not* a hardcoded `button_styles`/`typography` key list (the incoming reshuffle adds palette/scheme tokens to that group; hardcoding keys would blind-spot exactly the new tokens). State explicitly in the method/test that request-time inline per-template scheme overrides (incoming, deliberately drift-proof) are **out of guard scope by design** — the bundle-only boundary is intentional, not an "all themeable CSS is in the bundle" assumption. Cover it with an in-suite test proving **both directions**: (a) immediately after a (faked-HTTP, temp-output) build the check reports *fresh*; (b) after mutating a design-group `SiteSetting` with no rebuild the check reports *stale*. Optional thin `artisan` wrapper only if cheap and non-boundary.

### Phase 2 — Stop the suite manufacturing the incident

Apply findings 1–3: isolate `AssetBuildService` output so no test writes or deletes `public/build/**` or the real `manifest.json`, and make the manifest-dependent dashboard assertion deterministic. Apply finding 4: make the two `full-width-matrix.spec.ts` DB-driven appearance assertions stale-bundle-proof. Apply finding 5: de-evocative `DesignSystemButtons` fixtures (keep the tests).

### Phase 3 — Remediate the "proves nothing" cluster

Apply findings 7–11: rewrite each tautological test to exercise the real production path it claims to cover; delete the line-96 tautology; delete `PublishedAtBackfillTest` (subject to Open Question 1). Every deletion gets a one-line, evidence-tied rationale in the work log.

### Phase 4 — Verify and reconcile counts

Run the fast Pest suite green; run the touched Playwright spec separately (never concurrently). Record the exact before/after fast-Pest count and explain **every** delta (added guard test, split/rewritten tests, deleted `PublishedAtBackfillTest` ×6, etc.). Bump `VERSION` to `0.NNN.<iteration>`.

---

## Out of scope

- **Tier 3 findings — deferred to the housekeeping inbox at close, not fixed here** (per the user's scope decision): `DonationCheckoutTest` "creates a pending donation record on checkout initiation" (factory-echo / FMR); `QuickBooksSyncTest` + `QuickBooksCustomerMatchingTest` "creates a … receipt" sync-job names (subject-mocked, rename-rescope — the redaction/skip siblings are fine); `ProductCarouselTest` "seeder total widget count includes product_carousel" (magic-count change-detector); `full-width-matrix.spec.ts` "every widget handle renders without console errors" (name overclaims the narrow regex it checks); `layout-inspector.spec.ts` "…persists background + padding" (name implies a render round-trip; keep-with-note). List these verbatim in `sessions/housekeeping-inbox.md` at close so they are not lost.
- **Any Fleet Manager contract / `/api/health` / response-schema change** — explicitly ruled out at scoping to keep this session non-boundary. CRM contract stays v2.3.0. If the work appears to need it, stop and surface — scope drifted.
- **A broad test-suite cost/coverage/shape pass** — that is `release-plan.md` § D4, a separate release-gated session. Do not absorb its breadth, do not run mutation testing, do not touch the fast/slow split.
- **Re-auditing the suite or widening the findings table** — the audit is closed. New same-pattern instances found incidentally go to the inbox, not into this session.
- **Auto-drafting a successor session** — emergent session; no successor in the plan. Draft the next session's documents only if the user names one.

---

## Testing

- **Slow test groups to run this session:** none.
- **New tests expected:** **yes** — the drift-guard regression test (two-direction: fresh-after-build, stale-after-settings-change) is the centerpiece and is required. The Tier-2a items are rewrites of existing tests to exercise real production paths (net count roughly neutral, minus the deleted line-96 tautology and the deleted `PublishedAtBackfillTest` file). Fast-Pest target: the preceding session's green baseline, plus the guard test, minus the explained deletions — all green, every delta logged. Pest and Playwright run sequentially, never in parallel.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific:

- **Log file:** `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard — Log.md` per `sessions/template-session-log.md`. Document the drift guard's final shape + two-direction proof, the test-isolation mechanism chosen, each rewritten test (old → new, what it now actually exercises), each deletion with its one-line evidence-tied rationale, the `DesignSystemButtons` fixture de-evocation, the exact fast-Pest before/after count with every delta explained, the Playwright spec change, and the `VERSION` bump.
- **Artifact:** the new drift guard + its regression test; an `AssetBuildService` test suite that never touches `public/build/**`; the rewritten Tier-2a tests exercising real production paths; `PublishedAtBackfillTest` resolved (deleted, or replaced by a real backfill + proper test if Open Question 1 surfaced a genuine gap); the deferred Tier-3 findings recorded in `sessions/housekeeping-inbox.md`; `VERSION` `0.NNN.x`.
- **Branch:** `session-NNN/N` (final iteration), once the user assigns the number.
- **Next session:** none predetermined — emergent session. Draft a successor only when the user names it.
