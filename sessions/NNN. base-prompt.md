# Base Prompt — Session NNN (Post-Incident Test Integrity)

> **Number deliberately unassigned.** This is an emergent, post-incident session with no `release-plan.md` entry. The user assigns `NNN` and schedules it (stated intent: within the next 1–3 sessions, not later). When scheduled, copy these two files to the assigned number and replace every `NNN` token. Do **not** self-number or auto-pipeline into it.

**Start now.** Execute the reading list below, open the session prompt at `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md`, then begin the work. No confirmation needed.

---

We are about to begin a new session: **NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard & Fixture-Test Remediation**.

This session has **no execution-order position in `sessions/release-plan.md`** — it is an **emergent forcing-function session** triggered by a real production incident (the stale-stylesheet bug: the public site served a compiled CSS file whose styling no longer matched saved settings, and the test suite both failed to catch it and was misread as proof the styling was intended). It does **not** sit inside the Public Website Complete milestone execution order and does **not** block E6 (Theme Colors Refactor) or any other planned entry — it runs in its own slot whenever the user schedules it. It is adjacent in spirit to the planned `release-plan.md` § D4 (Test suite review — cost & shape) and the mutation-testing precedent (session 241, `docs/testing/mutation-audits.md`), but is **not** D4: D4 is a release-gated breadth pass; this is a narrow, incident-scoped integrity fix. The findings audit is **already complete and canonical** — this session *applies* it; it does not re-audit.

This session is **NOT boundary-touching** — no Fleet Manager surface is in scope. **CRM contract stays at v2.3.0.** Note for the record: a production-visible drift signal exposed as an additive `/api/health` field was explicitly *considered and ruled out* during scoping precisely to keep this session non-boundary; the drift guard built here is an in-suite (and optionally CLI) check only. If the work appears to need a Fleet contract or `/api/health` touch, **stop and surface** — scope has drifted.

**Session shape:** audit-style / **test-integrity remediation** (delete-leaning, with one new regression guard). One-sentence summary: stop the test suite from manufacturing the incident condition, add a cheap automatic check that fails the moment the served stylesheet no longer matches saved settings, and rewrite (or delete) the cluster of tests that re-implement production logic and prove nothing — scoped to the two incident lessons, with lower-priority naming/scoping findings explicitly deferred to the housekeeping inbox.

Before doing anything else:

1. Re-read the session templates (`sessions/template-base-prompt.md`, `sessions/template-session-prompt.md`, `sessions/template-session-log.md`) only if uncertain about a structural detail — stable since 276. `sessions/template-test-audit.md` is the closest tonal precedent (delete-leaning, evidence-tied rationale) — skim it for the "every deletion needs a one-line rationale tied to evidence, not vibes" discipline; this session inherits that discipline but the evidence is the embedded findings table, not a mutation report.
2. Read `sessions/session-outlines.md` — the Active-tracks block and the Housekeeping Inbox pointer. There is no forward stub for this session (it is emergent); do not hunt for one.
3. There is **no `release-plan.md` entry** for this session. Read `release-plan.md` § D4 *only* for context on the project's test-audit artifact convention (`sessions/NNN-test-audit-findings.md`) — this session does not execute D4 and must not absorb D4's breadth.
4. This session does **not** belong to an active track. No track doc.
5. Read `docs/app-reference.md` for environment/container names and the **runtime-vs-build-server boundary** — it is load-bearing here: the served public CSS bundle is produced by the external build server, written to the **gitignored, untracked** `public/build/widgets/` directory, and served from `manifest.json` with no reconciliation against the settings it came from. Read `docs/schema/README.md`; this session adds **no** DB schema changes.
6. Read the leverage surfaces before touching them:
   - `app/Services/AssetBuildService.php` — `build()` (the source-hash → bundle-filename → `manifest.json` derivation, ~lines 68–73 & 167–173), `collectSources()` (~line 335; concatenates SCSS partials + `generateButtonOverrideCss()` + `TypographyCompiler::compileScoped()`), and the constructor (~line 49; hardcodes `public_path('build/widgets')` as the output dir — this is the injection point both the pollution fix and the drift-guard test need).
   - `app/Services/WidgetAssetResolver.php` + `resources/views/layouts/public.blade.php` (~lines 157–162, 245) — the runtime serving path that reads the untracked manifest with zero drift detection.
   - The in-scope test files, each read fully before editing: `tests/Feature/BuildServerSettingsSession125Test.php`, `tests/Feature/WidgetJsLibsSession138Test.php`, `tests/Feature/DashboardSlotGridTest.php`, `tests/Feature/DesignSystemButtonsTest.php`, `tests/Feature/ContactExportTest.php`, `tests/Feature/CustomFieldTest.php`, `tests/Feature/TaxReceiptTest.php`, `tests/Feature/PublishedAtBackfillTest.php`, `tests/Feature/WidgetDefaultsLintTest.php`, `tests/e2e/page-builder/full-width-matrix.spec.ts`.
   - The production paths the tautological tests should be calling instead: the contact-export action/route, `App\Filament\Pages\…\DonorsPage::buildBreakdown()` (tax receipts), the widget-defaults lint routine.
7. Open `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md` and read it carefully — it carries the canonical findings table (scope is fixed there), the drift-guard design, the open questions, the explicit deferred set, and a **Forward-awareness** subsection on an incoming taxonomy/tooling reshuffle that materially shapes how the guard must be written (design-settings group resolved dynamically not hardcoded; bundle-only by intent; do not over-invest in finding 5). Read that subsection *before* designing the guard.
8. Note any drift between the prompt and the actual code in a brief work-log entry; proceed unless something requires a decision per the drift and decision-threshold rules below.

---

## Starting state inherited from the preceding session

*(Required section. This session is unnumbered; "preceding session" = whatever session the user runs immediately before it, ≥295. The user has stated it lands within 1–3 sessions of session 295, so the baselines below are anchored to the 295-area state and must be re-confirmed against the actual preceding session's log at start — adapt silently to drift per the process rules.)*

- **CRM contract at v2.3.0** — unchanged, and this session does not touch it. Not boundary-touching. The Fleet `/api/health` drift-signal option was ruled out at scoping to keep it that way.
- **Schema baseline:** no DB schema changes expected from the immediately-preceding sessions (295 is a `SiteSetting`-JSON-shape change, not a `database/migrations/` file); **this session adds none**. If the preceding session's log shows a new migration, note it and proceed — it does not interact with this session's scope.
- **Fast test suite green at the preceding session's close baseline** (≈2450+ / 0 as of 295-area; confirm the exact number from the preceding session's log). Expected delta this session: **net change is small and intentional** — new drift-guard test(s) added; the rewritten tautological tests stay roughly count-neutral (some split, some deleted); `PublishedAtBackfillTest` likely removed entirely (6 tests) if the start-of-session investigation confirms no live backfill exists. Record the exact before/after count and explain every delta in the log.
- **Playwright suite** — baseline preserved from the preceding session; this session touches **one** spec (`tests/e2e/page-builder/full-width-matrix.spec.ts`) to make its two DB-driven appearance assertions stale-bundle-proof. `feedback_test_runs_not_parallel` applies — never run Pest and Playwright concurrently.
- **Build/version discipline (carry-forward — load-bearing):** the deploy pipeline hard-fails a forgotten/duplicate repo-root `VERSION` bump. Bump `VERSION` to the assigned session's `0.NNN.<iteration>` as part of the iteration, exactly as every recent session does.
- **Planning state:** no `release-plan.md` or `session-outlines.md` structural changes are introduced by this session beyond (a) recording it in `completed-sessions.md` at close and (b) appending the explicitly-deferred findings (see the session prompt's Out-of-scope) to `sessions/housekeeping-inbox.md`. This session creates no new release-plan entry and resolves no forward stub.
- **Housekeeping inbox** at `sessions/housekeeping-inbox.md` — this session is the **producer** of inbox items for the deferred Tier-3 findings (it does not absorb existing inbox items; it is a focused remediation, not a housekeeping batch). The s290 link-color / Contact-hero items and the s291 post-Beta system-email-footer stub remain untouched.

---

## Process rules for every session

Base-prompt process rules (`sessions/template-base-prompt.md`) apply in full. Session-specific notes:

- **The audit is done; do not redo it.** The findings table in the session prompt is canonical scope. Do not widen it by re-scanning the suite, and do not narrow it by re-litigating individual calls — every in-scope item already has an agreed action. If, while editing, you find a *new* instance of the same anti-pattern in a file you are already touching, note it in the log and add it to the housekeeping inbox at close; do not chase it mid-session.
- **Every deletion carries a one-line rationale in the log, tied to the finding** (the `template-test-audit.md` discipline). "Removed `PublishedAtBackfillTest` — no live backfill migration or command exists (confirmed at start); the tests run raw SQL inline and assert that SQL did what it says."
- **Tests must never write the real served directory.** No test in the suite may, after this session, write or delete anything under `public/build/**` or the real `manifest.json`. The chosen isolation mechanism (constructor-injectable output dir vs. test-time path override vs. faked disk) is a small, local design call — confirm the cleanest one against the actual `AssetBuildService` constructor and decide; surface only if the only clean option turns out to be a non-local refactor.
- **The drift guard is the centerpiece — cheap and deterministic, no production-surface change.** It recomputes the current source hash and compares it to the hash the served manifest encodes (the shape is latent in `AssetBuildService::build()` already — confirm exact form at start). It must be exercised by an in-suite test that proves both directions (fresh build → fresh; settings mutated without rebuild → stale detected). A thin `php artisan` wrapper for operators is *optional and only if cheap* — it must remain non-boundary (no Fleet contract, no `/api/health`).
- **Write the guard for the post-reshuffle world, not just today's shape** (see the session prompt's Forward-awareness subsection). The optional `built_at` heuristic must compare against the **design settings group resolved dynamically**, never a hardcoded `button_styles`/`typography` key list — the incoming reshuffle adds palette/scheme tokens to that group and a hardcoded list would blind-spot exactly the new tokens. Scope the guard to the build-server bundle **by explicit intent**: a second, request-time inline delivery layer for per-template scheme overrides is incoming and is deliberately drift-proof, so the guard must state it does not (and should not) cover that layer — do not build on an implicit "all themeable CSS is in the bundle" premise that will silently rot. The primary content-hash check is already robust to the group expansion; this caveat is about not encoding a stale assumption in the optional heuristic or the guard's scope statement.
- **Surface, don't silently pick, the two genuine forks** (see the session prompt's Open Questions): the `PublishedAtBackfillTest` delete-vs-build-the-missing-feature call, and the test-isolation mechanism. Both are cheap to reverse; decide and note unless the investigation reveals a real missing feature (then surface).
- **When implementation is complete**, run the fast suite: `docker compose exec app php artisan test --exclude-group=slow`. It must be green with every count delta explained. Then run the touched Playwright spec separately (never concurrently with Pest) to confirm the stale-bundle-proofing holds.
- **Verify objective outcomes yourself.** The drift guard's correctness is fully objective — prove it with the test, do not punt to the user. There is no visual/UX surface in this session; there is nothing for the user to manually judge. Report results and stop; do not suggest closing.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below this line happens only when the user explicitly says to close the session.** Do not ask whether to close. Do not suggest it. Do not pipeline into these steps after the suite is green. Wait for the user to initiate each phase.

### Phase 1 — Attenuate and prepare next session

After implementation is complete and the suite is green, draft the next session's documents **only when the user names the next session** — this is an emergent session with no successor in the plan, so there is nothing to auto-draft. Do not assume E6 or any other entry follows it.

### Phase 2 — Close

When the user says to close:

- **Session log:** write `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard — Log.md` per `sessions/template-session-log.md` (copy its structure exactly; do not base it on a previous log). Document: the suite-pollution fix and its isolation mechanism; the drift guard's final shape and its two-direction proof; each rewritten test (old behaviour → new behaviour, what it now actually exercises); each deleted test with its one-line evidence-tied rationale; the `DesignSystemButtons` fixture de-evocation; the `VERSION` bump; the exact fast-Pest before/after count with every delta explained; the Playwright spec change.
- **Update `sessions/completed-sessions.md`:** append this session's row (number + title).
- **Update `sessions/housekeeping-inbox.md`:** append the explicitly-deferred Tier-3 findings (listed in the session prompt's Out-of-scope) as inbox items so they are not lost.
- **`sessions/session-outlines.md`:** no roadmap change (this session resolved no forward stub and created no entry). Touch it only if the user directs.
- **Archive the previous session:** move all files matching `sessions/(preceding-number). *.md` into `sessions/archived/` (skip silently if already archived or absent).
- **Commit:** stage all changed files (the service, the new guard + its test, every rewritten/deleted test file, the touched Playwright spec, `VERSION`, the log, `completed-sessions.md`, the housekeeping-inbox update, archived previous-session files) on the final `session-NNN/N` branch and notify the user. Do not push — the user pushes on their own cadence. Never push to main, never force-push, never merge to main yourself (see `CLAUDE.md` Git Workflow for the full forbidden list).
- **Do not begin the next session** until the user explicitly starts it.

---

## Style rules

Base-prompt style rules apply. Session-specific:

- **Iteration commit messages describe the deliverable** — e.g. "Iteration /1 — Stale-stylesheet drift guard + AssetBuildService test isolation (no test writes public/build) + ContactExport/TaxReceipt/CustomField tautology rewrites + PublishedAtBackfillTest removal + DesignSystemButtons fixture de-evocation + VERSION 0.NNN.1".
- **PM-level tone for status reports** per `memory/feedback_pm_level_tone.md` — the user is an engineer but did not run the audit; report outcomes and risk, not mechanics, in status updates. (The prompt itself is engineering-precise by convention; the *reporting* is PM-level.)
- **No docstrings, comments, or type annotations on code you did not write.**
- **Extend, don't reinvent.** The drift guard rides the source-hash logic already in `AssetBuildService::build()`; the test-isolation rides the existing constructor; the tautology rewrites call production paths that already exist. This is a remediation of a closed audit, not a test-architecture project. Do not add a test framework, a CI stage, or a broader "test health" pass — that is D4, explicitly not this.
