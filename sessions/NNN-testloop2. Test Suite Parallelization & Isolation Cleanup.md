# NNN. Test Suite Parallelization & Isolation Cleanup · DRAFT FOR REVIEW

> **Session 2 of the two-session test-cost split.** Pure speed, zero coverage loss. **Session 1 (`NNN-testloop1`) should land first** (it provides the CI the parallel suite runs in) but this is code-independent and can run standalone. This is the § D4 parallelization slice, lifted early per the plan's sanctioned Rule-11 exception; the rest of D4 (mutation/coverage-shape) stays release-gated.

One sentence: make `php artisan test --parallel` green and the default fast invocation by fixing the test-isolation defects that block it — recovering ~3–5× wall time with the identical set of tests.

---

## Stub reference (emergent; the parallelization slice of § D4)

No `release-plan.md` entry. The release plan explicitly anticipates this lift: D4 "scopes Pest `--parallel` viability … decides whether to … fold the test-isolation cleanup … or lift it as a follow-on per Rule 11. Carry-forward exception: if iteration friction … starts costing real time before D4's slot lands, lift parallelization sooner as a standalone fix-shape session." It now does. **Out of scope, left to D4:** mutation-proven pruning, assertion density, coverage shape. This session changes *how fast the same tests run*, nothing about *what runs*.

---

## Open questions to resolve at session start

1. **Residual isolation surface size.** The release-plan-named defects (shared `storage/app/private/` paths; the `seedWidgetCollections` flake) are the *known* list. The first `--parallel` run reveals the rest. If that surface is larger than one session can root-cause cleanly, decide with the user: land parallelization with a small, explicitly-logged serial-only carve-out for the stragglers + a carry-forward, vs. extend. Do not silently serial-carve-out a large set to force green.
2. **Coordination with the stale-stylesheet drift-guard session.** Its fix stops the build-pipeline tests writing real `public/build/**` (a parallel-safety win). If it has shipped, build on it. If not, fix those tests' isolation in a way *consistent with* its raw-config/temp-output direction — cross-reference, do not invent a second approach.

---

## Phases

### Phase 1 — Install & wire paratest; surface the failure set

Add Laravel's first-party parallel testing (paratest) as a dev dependency, wire `--parallel` as the default fast-suite invocation (`dev`, CI from Session 1, `CLAUDE.md`'s inner-loop guidance). Run it. Capture the full set of tests that fail/flke only under parallelism — this is the isolation defect list (framework handles per-process DBs; the residue is shared filesystem / static / time / env state).

### Phase 2 — Root-cause the isolation defects

Fix at the source, defect by defect: tests own their own temp paths/fixtures instead of sharing; non-isolated seeders (`seedWidgetCollections`) made per-process-safe; static caches/time/env reset between tests where leaked. The build-pipeline real-artifact writers (`BuildServerSettingsSession125Test`, `WidgetJsLibsSession138Test`) coordinate with the drift-guard session's direction (Open Q2). Prefer ownership over serialization; a serial-only carve-out is allowed only with a logged one-line rationale and user awareness (Open Q1).

### Phase 3 — Prove order-independence & make it the default

Run `--parallel` ≥2× and shuffled; the **identical pass count** must hold every run (this is the zero-coverage-loss proof). Make `--parallel` the default fast invocation everywhere it's invoked (`dev`, CI, docs). Record before/after wall time.

---

## Out of scope

- Mutation-proven dead-test pruning, assertion-density / coverage-shape work — **release-gated § D4**.
- Deleting or skipping tests for speed; large serial-only carve-outs to force green without root-causing — forbidden (zero coverage loss is the invariant).
- The scoped inner loop / CI / close-gate rewrite — **Session 1 (`NNN-testloop1`)**.
- Any Fleet / `/api/health` / schema change. CRM contract stays v2.3.0.
- Re-architecting the build-pipeline tests beyond isolation — that is the drift-guard session's domain; coordinate only.

---

## Testing

- **Slow groups:** unchanged (Session 1 owns slow-group reclassification).
- **New Pest:** none expected (this session fixes existing tests' isolation; it does not add behavioural coverage). Isolation fixes may refactor setup/teardown but must not change what a test asserts.
- **Verification (self, objective):** `--parallel` green with the **identical pass count** as the serial baseline, proven across ≥2 shuffled runs (order-independence); before/after wall-time recorded; Playwright still runs sequentially (never concurrent with Pest).
- **Manual (user judgment):** only if Open Q1 forces a serial-only carve-out decision — surface the straggler list + proposed carve-out for the user's call.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific: log `sessions/NNN. Test Suite Parallelization & Isolation Cleanup — Log.md`; artifact = paratest wired as default + every isolation defect and its source fix + any logged serial-only carve-out + the identical-pass-count/order-independence proof + before/after wall time + coordination notes with the drift-guard session + `VERSION` `0.NNN.x`; branch `session-NNN/N`; terminal for this split — successor only if the user names one.
