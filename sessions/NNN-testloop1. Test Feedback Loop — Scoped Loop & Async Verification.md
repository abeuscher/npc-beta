# NNN. Test Feedback Loop — Scoped Inner Loop & Async Full-Suite Verification · DRAFT FOR REVIEW

> **Session 1 of a two-session test-cost split.** Iteration-cost forcing function: the full suite costs ~20 min, run ~hourly during scoped design work — dead time. **Session 2 (`NNN-testloop2`)** does parallelization + isolation. This session makes the inner loop ~30 s, moves the full run off the human's clock onto CI, and rewrites the close ritual to fix-forward — **no coverage removed**.

One sentence: turn "run 2,450 tests every hour and wait at session close" into "run a ~30-second scoped signal in the inner loop, push, and let CI verify the full suite asynchronously and report back."

---

## Stub reference (emergent; the iteration-speed slice of § D4)

No `release-plan.md` entry. This is the **iteration-speed slice** of the planned § D4 (Test suite review — cost & shape) / the "Test Suite Audit — Cost, Coverage, and Shape" stub, lifted early per Rule 11's sanctioned exception. **Out of scope and left to D4:** mutation-proven dead-test pruning, assertion-density / setup-to-assertion analysis, coverage-shape. **In Session 2, not here:** `--parallel` + test-isolation. This session is purely *when/where/who-waits*, not *what runs*.

The motivating analysis (canonical for scope): the 20 minutes is three problems — (1) inner loop runs ~95% irrelevant tests for scoped design work; (2) the close gate makes the human wait for the full run; (3) the suite may be over its <5-min fast budget. This session fixes (1) and (2) and the cheap part of (3); Session 2 fixes the expensive part of (3).

---

## Open questions to resolve at session start

1. **Durable form of scoped selection.** An interim `./dev test:design` (a 16-file `DESIGN_TESTS` path list in `dev`) is already wired and usable. Decide the durable mechanism:
   - **(a) Keep a maintained path list + an integrity guard test** (a Pest test asserting every file matching the design-surface naming/namespace heuristic is in the list, so the list can't silently rot). Lowest friction, explicit, but heuristic-bound.
   - **(b) A real Pest `->group('design')` tag** across the cluster. Idiomatic and greppable; invasive (touches ~16 files / many `it()`s) and Pest groups here are per-test, not central.
   - **(c) Coverage-mapped / `--dirty` selection.** Most "automatic" but least predictable for source-only changes.
   Recommendation: **(a)** — the guard test removes the only real downside (staleness) at trivial cost, and keeps the mechanism legible. Also generalise the pattern so other hot subsystems (importer, widget-primitive) can get their own scoped target later without rework. Surface the choice; don't silently pick (b)'s churn.
2. **CI runner shape.** GitHub Actions workflow on push to non-`main` branches running the full fast suite, then slow, then Playwright (sequential — `feedback_test_runs_not_parallel`), reporting status back. Confirm it composes with the remote-execution / Claude-Code-on-web flow the user actually uses (status visible where they work) and does not race `deploy.yml` / the `VERSION` immutable-tag step.

---

## Phases

### Phase 1 — Formalise the scoped inner loop

Per Open Q1: settle the durable mechanism, replace/keep the `dev test:design` interim accordingly, and (recommended) add the integrity guard so the design-surface set cannot silently drift as tests are added/renamed. The drift cluster (`AssetBuild` / `BuildServer` / `WidgetJsLibs` / appearance) **stays in the scoped set** so the inner loop still catches the stale-stylesheet bug class. Document the command in the `dev` usage block and `CLAUDE.md` (the inner-loop command for scoped work). Optionally seed the same pattern for one other hot subsystem as a template, only if cheap.

### Phase 2 — Async full-suite verification (CI) + close-gate restructure

Add a deploy-safe GitHub Actions workflow: on push to non-`main` branches, run the full fast suite → slow → Playwright (sequential), report status. It must not publish images, touch `VERSION`, or interfere with `deploy.yml`. Then **rewrite the close-gate discipline** in `CLAUDE.md` and `sessions/template-base-prompt.md` so the convention becomes: implementation complete → push → CI verifies the full suite asynchronously → red is fixed-forward; the human does **not** block-wait on a local full run at close. Keep the *requirement* that the branch is green before merge — only the *who-waits/when* changes. The wording change is the load-bearing artifact; get it precise and consistent across both files.

### Phase 3 — Slow-group hygiene re-audit (mechanical, no coverage change)

Time the suite; any test >5 s not tagged `->group('slow')` is silently inflating the "fast" loop — move it into the slow group. Nothing is deleted or skipped. Record the before/after fast-suite wall time and the reclassified set.

---

## Out of scope

- `--parallel` / paratest and test-isolation cleanup — **Session 2 (`NNN-testloop2`)**.
- Mutation-proven dead-test pruning, assertion-density / coverage-shape work — **release-gated § D4**; do not pull forward.
- Deleting, skipping, or `->markTestSkipped()`-ing any test for speed — explicitly forbidden this session (zero coverage loss).
- Any Fleet / `/api/health` / schema change. CRM contract stays v2.3.0.
- Reworking the Pest/PHPUnit bootstrap or introducing a new runner/framework.

---

## Testing

- **Slow groups:** none new (this session *reclassifies* slow membership, Phase 3).
- **New Pest:** at most the scoped-set **integrity guard** (Open Q1a) — a test that the design-surface list matches the naming/namespace heuristic so it can't rot. No behavioural coverage added or removed.
- **Verification (self, objective):** `./dev test:design` (or its successor) returns green in seconds over the cluster; the CI workflow runs the full suite green on a pushed non-`main` branch and reports status; `deploy.yml` behaviour is provably unaffected (workflow paths/triggers disjoint). Pest and Playwright run sequentially in CI.
- **Manual (user judgment):** the rewritten close-gate wording in `CLAUDE.md` + `template-base-prompt.md` — surface verbatim for the user's approval at handoff (this is a discipline change, their call).

---

## Closing steps

Follow the close gate in the base prompt. Session-specific: log `sessions/NNN. Test Feedback Loop — Scoped Loop & Async Verification — Log.md`; artifact = the durable scoped-selection mechanism (+ integrity guard) + the deploy-safe CI workflow + the verbatim close-gate rewording in both `CLAUDE.md` and `template-base-prompt.md` + the slow-group re-audit before/after + `VERSION` `0.NNN.x`; branch `session-NNN/N`; next session = `NNN-testloop2`, refreshed against the CI this session shipped, drafted-forward only when the user names it.
