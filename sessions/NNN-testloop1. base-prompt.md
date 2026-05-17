# Base Prompt — Session NNN (Test Feedback Loop — Scoped Loop + Async Verification) · DRAFT FOR REVIEW

> **Draft.** Number deliberately unassigned (placeholder `NNN-testloop1`). Emergent, **iteration-cost forcing function** (the full suite is costing ~20 min/run, run ~hourly — dead time on scoped design work). This is **Session 1 of a two-session split**: this session = scoped inner loop + async full-suite verification + slow-group hygiene; **Session 2 (`NNN-testloop2`)** = parallelization + test-isolation cleanup. The two are independent in code but Session 1 should land first (it provides the CI that Session 2's parallelized suite runs in). When scheduled, likely warrants a `release-plan.md` note linking it to § D4; flag at scheduling.

**Start now.** Execute the reading list, open `sessions/NNN-testloop1. Test Feedback Loop — Scoped Loop & Async Verification.md`, then begin. No confirmation needed.

---

We are about to begin a new session: **NNN. Test Feedback Loop — Scoped Inner Loop & Async Full-Suite Verification**.

This session has **no execution-order position in `sessions/release-plan.md`** — emergent. It is the **iteration-speed slice** carved out of the planned § D4 (Test suite review — cost & shape) per Rule 11's sanctioned early-lift exception ("if iteration friction starts costing real time before D4's slot lands, lift … sooner"). It deliberately does **not** take D4's coverage-shape / mutation-pruning work — that stays release-gated. Coordinate with, do not absorb, D4 and the "Test Suite Audit — Cost, Coverage, and Shape" stub.

This session is **NOT boundary-touching** — no Fleet Manager surface, no `/api/health`. **CRM contract stays at v2.3.0.** No DB schema change. It **does** change a project-wide ritual (the session close gate's "run the suite and wait" step) — that is the point, and it is a user-approved discipline change, not a coverage reduction.

**Session shape:** infrastructure / developer-experience. One-sentence summary: make the inner dev loop a ~30-second scoped signal instead of a ~20-minute full run, move the full fast(+slow+Playwright) suite off the human's critical path onto CI that reports asynchronously, restructure the close gate to fix-forward instead of block-and-wait, and re-audit the fast/slow `->group('slow')` classification — **with zero loss of total coverage** (same tests, same gate; only *when* and *who-waits* changes).

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
   - `.github/workflows/deploy.yml` — existing CI. A new test workflow must **not** interfere with deploy / the immutable-`VERSION` logic; it runs on push to non-`main` working branches and reports status only.
   - Existing `->group('slow')` usage across `tests/` — the classification to re-audit.
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
- **The async model must be documented as convention, not left implicit.** Edit `CLAUDE.md` and `sessions/template-base-prompt.md`'s close gate together so "tests are verified by CI on push, failures fixed-forward" is the written discipline. An undocumented deviation from the close gate is worse than the slow loop.
- **CI must be additive and deploy-safe.** New workflow triggers on push to non-`main` branches, runs the suite, reports status. It must not publish images, touch `VERSION`, or interfere with `deploy.yml`. Confirm before merging the workflow.
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
