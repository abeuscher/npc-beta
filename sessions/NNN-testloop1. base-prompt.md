# Base Prompt — Session NNN (Test Feedback Loop — Scoped Loop + Async Verification) · DRAFT FOR REVIEW

> **Draft.** Number deliberately unassigned (placeholder `NNN-testloop1`). Emergent, **iteration-cost forcing function** (the full suite is costing ~20 min/run, run ~hourly — dead time on scoped design work). This is **Session 1 of a two-session split**: this session = scoped inner loop + async full-suite verification + slow-group hygiene; **Session 2 (`NNN-testloop2`)** = parallelization + test-isolation cleanup. The two are independent in code but Session 1 should land first (it provides the CI that Session 2's parallelized suite runs in). When scheduled, likely warrants a `release-plan.md` note linking it to § D4; flag at scheduling.

**Start now.** Execute the reading list, open `sessions/NNN-testloop1. Test Feedback Loop — Scoped Loop & Async Verification.md`, then begin. No confirmation needed.

---

We are about to begin a new session: **NNN. Test Feedback Loop — Scoped Inner Loop & Async Full-Suite Verification**.

This session has **no execution-order position in `sessions/release-plan.md`** — emergent. It is the **iteration-speed slice** carved out of the planned § D4 (Test suite review — cost & shape) per Rule 11's sanctioned early-lift exception ("if iteration friction starts costing real time before D4's slot lands, lift … sooner"). It deliberately does **not** take D4's coverage-shape / mutation-pruning work — that stays release-gated. Coordinate with, do not absorb, D4 and the "Test Suite Audit — Cost, Coverage, and Shape" stub.

This session is **NOT boundary-touching** — no Fleet Manager surface, no `/api/health`. **CRM contract stays at v2.3.0.** No DB schema change. It **does** change a project-wide ritual (the session close gate's "run the suite and wait" step) — that is the point, and it is a user-approved discipline change, not a coverage reduction.

**Session shape:** infrastructure / developer-experience. One-sentence summary: make the inner dev loop a ~30-second scoped signal instead of the full local run (measured ≈11 min / ≈2,460 tests for the fast suite; the felt ~20 min/hour reflects running full + repeated + close-gate runs), **harden the already-existing `.github/workflows/tests.yml`** so the full suite verifies asynchronously off the human's critical path with a fast-first signal and Playwright added, *additively* shift the close-gate discipline toward CI-as-source-of-truth (without yet retiring block-and-wait — that is staged), and re-audit the fast/slow `->group('slow')` classification — **with zero loss of total coverage** (same tests, same gate; only *when* and *who-waits* changes).

**Sequencing note (ROI):** this session is independent of the Theme/Template Re-Taxonomy arc, but its payoff is highest **run immediately before the arc's RISK session (298 — Widget Color-Token Consumption Audit)**: that is a ~38-widget SCSS migration with many build/verify cycles over exactly the drift cluster the scoped loop targets. Recommend slotting testloop1 right before 298; testloop2 can follow or interleave. (No renumbering — this is a sequencing recommendation only.)

Before doing anything else:

1. Re-read the session templates only if uncertain about a structural detail — stable since 276.
2. Read `sessions/session-outlines.md` — Active-tracks + the "Test Suite Audit — Cost, Coverage, and Shape" stub (this session is the iteration-speed slice of it; do not do the rest).
3. Read `sessions/release-plan.md` § D4 — for the explicit scope boundary (D4 keeps mutation/coverage-shape + `--parallel`; the `--parallel`/isolation slice is **Session 2**, not here).
4. Not part of an active track.
5. Read `docs/app-reference.md` for container/env names and the test invocation pattern (`docker compose exec app php artisan test`; the `dev` wrapper). Read `docs/schema/README.md`; no schema this session.
6. Read the leverage surfaces:
   - `dev` — the shorthand wrapper. It already carries an **interim** `test` + `test:design` (a curated 16-file design-surface path list in the `DESIGN_TESTS` array — typography + color/appearance + the build-pipeline/drift cluster, drift files included on purpose). This session decides the *durable* form of scoped selection (see the session prompt's open question) and may generalise beyond `design`.
   - `phpunit.xml` — testsuites, the `slow` group convention, env. `CLAUDE.md` — the "Fast vs slow classification" rule (>5s ⇒ `->group('slow')`; fast suite target <5 min) and the close-gate "run the fast test suite … fix failures before proceeding" step (the ritual this session restructures).
   - `sessions/template-base-prompt.md` close gate + `CLAUDE.md` — the canonical homes of the "verify tests yourself before close" discipline. Both must be edited consistently so the async model is the documented convention, not an undocumented deviation.
   - **`.github/workflows/tests.yml` — THIS ALREADY EXISTS and is already the async CI.** It triggers on `push: ["**"]` + PRs to `main`, stands up Postgres 16 + Redis + PHP 8.4 + Node, and runs **`php artisan test` (full suite — fast *and* slow, no `--exclude-group`)** with **no Playwright step**. Phase 2 **hardens this file**; it does **not** add a new workflow (doing so would create a duplicate). Read it before touching anything.
   - `.github/workflows/deploy.yml` + `.github/workflows/deploy-demo.yml` — the deploy workflows; the `tests.yml` changes must not touch / race the immutable-`VERSION` deploy logic.
   - Existing `->group('slow')` usage across `tests/` — the classification to re-audit (CI currently runs slow inline, so a fast→slow split shortens first-signal time).
   - `tests/Feature/AssetBundleDriftGuardTest.php` (added by session 296) + the now-injectable `AssetBuildService` constructor — part of the design/drift cluster; ensure it is in the scoped set (the interim `dev` list has been updated; the durable group tag must include it).
7. Open `sessions/NNN-testloop1. Test Feedback Loop — Scoped Loop & Async Verification.md` and read it carefully.
8. Note any drift in a brief work-log entry; proceed unless something needs a decision per the drift/decision-threshold rules.

---

## Starting state inherited from the preceding session

*(Required. Unnumbered; "preceding session" = whatever runs immediately before. Confirm baselines from that log at start; adapt to drift silently.)*

- **CRM contract at v2.3.0** — unchanged; not boundary-touching.
- **Schema baseline:** no schema change this session.
- **Fast suite green at the preceding session's close baseline** — confirm exact count. This session adds essentially no test *coverage*; it may add one or two guard tests (e.g. the scoped-group integrity guard) and re-tags slow outliers. Total coverage unchanged by design.
- **`dev test:design` interim already wired** (the `DESIGN_TESTS` array in `dev`) — usable now; this session decides whether to formalise it as a Pest group, a maintained guarded list, or coverage-mapped selection.
- **Playwright suite** — baseline preserved; the async CI runs it (sequentially after Pest — `feedback_test_runs_not_parallel` still holds within a single CI run).
- **Build/version discipline (load-bearing):** the new CI workflow must not touch / race the `VERSION` immutable-tag logic in `deploy.yml`; bump `VERSION` per-iteration as usual for this session's own commits.
- **Housekeeping inbox** — focused infra session; do not absorb inbox items.

---

## Process rules for every session

Base-prompt process rules apply. Session-specific notes:

- **No coverage is removed. Ever, this session.** Scoping is an *inner-loop accelerator*; the full suite still runs on every push via CI. If any change would reduce what gets run before merge, stop — that is D4's call, not this session's.
- **The close-gate change is STAGED, not a same-session hard replacement.** Day one of a not-yet-proven fast/Playwright CI is the wrong moment to retire the canonical block-and-wait safety net. This session makes the change **additive**: document that "CI-green on the pushed branch is the authoritative signal" and that the human *may* stop block-waiting once CI is observed green — but **leave the existing block-and-wait wording in place** as the fallback until (a) the hardened CI has a track record over a few sessions and (b) Open Q2 (does CI status surface where the user actually works?) is answered. Retiring the old wording is an explicit later step, not this session's.
- **Harden `tests.yml`; do not add a workflow.** It already exists and already runs on every push. The work: split so the **fast suite runs first** (quick signal) then slow, **add a Playwright stage** (sequential, never concurrent with Pest — `feedback_test_runs_not_parallel`), keep triggers. It must not publish images, touch `VERSION`, or interfere with `deploy.yml`/`deploy-demo.yml`.
- **Main-green must be explicitly enforced — highest-stakes item.** `CLAUDE.md` lets the user merge on their own cadence; fix-forward + human-doesn't-wait + that = nothing currently guarantees "branch green before merge to main." The session must surface (it cannot self-apply) a **required repo-admin action: GitHub branch protection on `main` requiring the `Tests` status check to pass before merge.** This is a user/admin step, not a committable file — flag it as a blocking handoff, not an optional note. Without it the async model silently weakens the main-green guarantee.
- **Surface the scoped-selection durable-form decision before building it** (see the session prompt's open question) — it has a maintainability tradeoff worth a user call.
- **Slow-group re-audit is mechanical, not a coverage change** — time the suite, move >5s untagged tests into `->group('slow')`, nothing deleted.
- **Verify objectively, yourself.** The deliverables are objectively checkable: `./dev test:design` returns green in seconds; the CI workflow runs the full suite green on a pushed branch and reports. Close those loops yourself; the user judges only the close-gate wording change.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below happens only when the user explicitly says to close.** Do not ask, suggest, or pipeline.

### Phase 1 — Attenuate and prepare next session

Successor is **Session 2 (`NNN-testloop2`, parallelization + isolation)** — its draft exists; refresh its inherited-state against what this session shipped (esp. the CI workflow it will run in). Draft nothing else unless the user names it.

### Phase 2 — Close

When the user says to close: log `sessions/NNN. Test Feedback Loop — Scoped Loop & Async Verification — Log.md` per the template (scoped-selection durable form chosen + rationale; the CI workflow; the `CLAUDE.md`/template close-gate rewording verbatim; the slow-group re-audit results; `VERSION` bump; drift). Update `completed-sessions.md`, archive the previous session's files, commit on the final `session-NNN/N` branch, notify, do not push. Never push to main / force-push / merge to main. Do not begin Session 2 until the user starts it.

---

## Style rules

Base-prompt style rules apply. Iteration commit messages describe the deliverable; PM-level status tone per `memory/feedback_pm_level_tone.md`; no docstrings/comments/type-annotations on code you did not write. **Extend, don't reinvent** — build on the existing `dev` wrapper, the existing `slow` group convention, and the existing CI; do not introduce a new test runner or framework. This is a feedback-loop restructure, not a test rewrite.
