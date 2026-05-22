# Track: Test Audit

The standing test-quality maintenance arc — periodic audit of accumulated drift in the test suite itself, not the production code it tests. Recurring rather than time-bounded: each ~50 sessions of growth triggers a new cycle. Mirrors `tracks/code-review-and-cleanup.md` in shape (cyclic audit, no closure event, cycle retrospectives accumulate) but its target is *the tests* — Pest and Playwright both — not the application.

This doc carries three things:

- **Status snapshot** — where the track stands, when the next cycle triggers.
- **Cycle Retrospectives** — compressed history of closed cycles.
- **Forward plan** — the canonical cycle shape, the four audit dimensions, cadence trigger, standing improvements, and permanent inter-cycle artifacts.

When a cycle closes, its retrospective lands here. Per-session detail stays in the matching `sessions/(archived/)NNN. … — Log.md` files.

---

## Status snapshot

**Last update:** 2026-05-22 (Cycle 1 closed at A005; retrospective + status landed here).

**Complete:** Cycle 1 (**A005** — D1 Playwright spec discipline + D2 spec-claim integrity drain; `docs/testing/playwright-discipline.md` shipped).

**Active:** between cycles.

**Next trigger:** approximately **session 365** (≈50 sessions of growth from A005's close position, with the numeric track around 314–318) **or** a forcing function — whichever fires first. The D2 drain-pressure rule is a standing forcing function: 5+ unresolved `[test-integrity]` entries in the housekeeping inbox lifts a D2 cycle regardless of cadence. Subsequent cycles default to the D3 (mutation slice) and D4 (Pest file relevance) dimensions not run in Cycle 1.

**Prior partial audits — pre-track precursors:**

- **Session 094 (2026-03-30) — Test Audit & Bug Fixes** *(archived).* First Pest audit. Walked 33 test files / 195 tests with a per-file keep/update/remove table; codified the fast/slow split (>5s = `->group('slow')`); locked in the "new tests expected for behaviour-changing sessions" rule via the base prompt; produced six coverage-gap test files for previously untested areas (Organizations, Memberships, Funds/Campaigns, Tags, Navigation, Email Templates). Predates Playwright e2e. Treated as **track precursor**, not retconned into a cycle.
- **Session 241 (2026-04-28) — Test Audit (Mutation Testing) — First Slice** *(archived).* Installed Infection + PCOV, wrote the Pest 2 adapter shim at `bin/pest-for-infection.php`, ran the first mutation slice against `app/WidgetPrimitive/{ContractResolver,DataContract,Source}.php`, found zero surviving mutants and 18 N≥2-redundant test methods, applied `// guards:` override-keep markers across 9 test files. Shipped `docs/testing/mutation-audits.md` as the reusable workflow. **Carries forward into D3** — mutation slice queue.
- **Session 296 — `[test-integrity]` Tier-3 findings** *(in housekeeping inbox).* One-off finding pass surfaced six Pest tests where the test title overclaims what's asserted (factory-echo fixtures, magic-count change-detectors, "persists X" that doesn't round-trip). Tagged `[test-integrity]` in `sessions/housekeeping-inbox.md`; never drained. **Carries forward into D2** — spec-claim integrity drain.

---

## Cycle Retrospectives

### Cycle 1 — D1 + D2 audit (A005)

Window covered: the full `tests/e2e/` suite plus the s296 `[test-integrity]` backlog. Single agentic session; no carve-out (both dimensions fit). Test-code + planning-doc changes only — no application code. Work iteration merged via PR #20; close docs on `session-A005/2`.

#### Quantitative outcomes

| Gate | Pre-cycle | Cycle 1 close | Net delta |
|------|-----------|---------------|-----------|
| Playwright spec files | 28 | 16 | −12 (10 redundant importer specs + `inline-formatting-toolbar` parked + `ticket-tier-picker` CI-unstable) |
| Pest tests | baseline | baseline −2 | −2 vacuous (DonationCheckout factory-echo, ProductCarousel magic-count) |
| Pest tests renamed | — | 2 | QuickBooksSync sync-job titles → match mocked bodies |
| Pest tests rewritten | — | 2 | TaxReceipt → route through real `DonorsPage::buildBreakdown()` |
| e2e tests renamed | — | 2 | `layout-inspector`, `dashboard-settings` titles → match assertions |
| Workflow docs shipped | 1 (`mutation-audits.md`) | 2 | + `docs/testing/playwright-discipline.md` |
| `[test-integrity]` backlog | 8 entries (s296 Tier-3 ×5 + incidental ×1 + ticket-tier-picker + A003 informational) | drained | s296 Tier-3 + incidental + ticket-tier-picker dispositioned; A003 deletion records stay (informational) |

#### Load-bearing decisions

- **FixtureRunner-is-the-safety-net rule, codified.** Before deleting any importer Playwright spec as Redundant, the same data path must be verified covered by `tests/Feature/Generated/ImportFixtureRunnerTest.php` (7 importers × 4 shapes) or a namespaced per-importer Feature test. This rule is now written into `docs/testing/playwright-discipline.md` as the standing importer-disposition gate.
- **Browser-only criterion shipped as a durable doc.** `docs/testing/playwright-discipline.md` is the artifact the next operator reads before adding a Playwright spec — Keep/Redundant/Mixed decision rule, local-first authoring discipline, the A003 anti-pattern catalog (URL-vs-route-registry mismatch, ambiguous `getByRole`, `.first()` on `[role="dialog"]` placeholders, `requiresConfirmation()` on custom Pages).
- **D2 default lean = rename to match the assertion**, not expand the test. Applied uniformly except where the test asserted nothing meaningful (factory-echo / magic-count → delete) or re-implemented production logic inline (TaxReceipt → rewrite through the real method).
- **Delete-not-requarantine for environment-blocked specs.** `ticket-tier-picker` was deleted rather than left `describe.skip`'d, per the A003 precedent and user policy; the coverage tradeoff (the multi-quantity spinner's *e2e* coverage) is recorded with a forward-queue note.

#### Blind spots that surfaced

- **The s296 lumping was imprecise.** `QuickBooksCustomerMatchingTest` was named in the backlog alongside `QuickBooksSyncTest` but its titles already matched its bodies — no action needed. Lesson: a `[test-integrity]` entry naming a *cluster* still needs per-test verification at drain time, not blanket action.
- **The DonationCheckout factory-echo masked a genuine coverage gap.** Deleting the vacuous test exposed that the real checkout-initiation controller path is unverified. The deletion is correct (the test never tested that path), but the gap is now explicit in the forward queue rather than falsely "covered."

#### Process incidents

- **Cloud-session has no Docker** — the canonical local `./dev test` was unavailable. CI was the authoritative verification signal (push → `Tests` aggregate green); the TaxReceipt rewrite in particular relied on CI to confirm. Worked cleanly; the `Tests` aggregate landed green on the merged commit.
- **Close docs landed on a separate branch.** The work iteration merged (PR #20) before close, so the close documents went on `session-A005/2` branched off the post-merge main and a second PR — rather than riding the work branch as the template's default assumes.

#### Won't-fixes reaffirmed

- **Page-builder cluster stays browser-required.** `inline-editing-foundation`, `inline-editing-phase2`, `layout-inspector` are JS-driven editor UI; kept. The `inline-editing-foundation:79` cold-load flake stays on passive watch rather than triggering selector-hardening this cycle (decide after evidence).
- **A003 deletion records stay in the inbox** — they are informational `[test-integrity]` entries documenting already-executed dispositions, not actionable backlog.

---

## Forward plan

### Cycle shape — single audit session by default

Each cycle is a **single audit session**. Mirrors the code-review track's "audit / apply / squash" pattern collapsed into one — test-suite audits don't carry the migration-squash dimension, and the apply work for a test audit is typically small enough (delete X, rename Y, write the Z spec that's missing) to land in the same session.

**Carve-out shape** when needed. If the audit surfaces multi-dimension scope that exceeds a single session — e.g., a mutation slice's report has dozens of dispositions, *and* the Playwright sweep wants substantial rewrites — lift the heavier dimension to a successor session per the 207 / 275 / 313→313/2 precedent established by the code-review track.

### Four audit dimensions

Each cycle picks one or more dimensions based on what's accumulated. Not every cycle runs all four; the cadence-driven cycle picks whichever dimensions have evidence pressure.

#### D1 — Playwright spec discipline

**The criterion:** Playwright specs cover only what a real browser can verify — visible appearance, JS-driven UI behavior, user interaction flows, CSS rendering, browser-mediated side effects (file downloads, FilePond uploads, drag-drop, Choices.js init). Anything testable via Pest Feature or Unit is misplaced.

**Sweep procedure:**

1. List every spec under `tests/e2e/`. For each, characterize what its assertions actually test.
2. Bucket per spec: **Keep** (browser-required), **Redundant** (data/business logic covered by Pest), **Mixed** (some browser, some data — rewrite to UI-only or split).
3. For Redundant verdicts, verify the Pest coverage exists before deleting — the importer suite's `tests/Feature/Generated/ImportFixtureRunnerTest.php` is the canonical example (FixtureRunner runs all 7 importers × 4 shapes off-Livewire, covering the data paths the Livewire wizard specs duplicate).
4. Apply deletions / rewrites. Each disposition lands a `[test-integrity]` housekeeping line that names what was removed and the coverage tradeoff.

**Anti-patterns this dimension catches:**

- Wrong URL in a spec that no one ever ran (A003 site-import-export: spec navigated `/admin/site-import-export`; actual route is `/admin/site-import-export-page` per Filament's auto-slug). Catchable by mechanically resolving each `page.goto` against the route registry.
- Ambiguous role-based selectors (`getByRole('button', { name: 'Export Site' })` matching both the trigger and the modal's `modalSubmitActionLabel`). Catchable by grep + manual inspection.
- `.first()` on `[role="dialog"]` selectors that picks the hidden pre-rendered placeholder. Catchable by grep — these are smells.
- Specs that have never been green in CI (track-it-down-or-delete heuristic). Cross-reference the `ci-logs/playwright` branch's run history.

**Workflow doc deliverable** (first cycle): `docs/testing/playwright-discipline.md`. Mirror `docs/testing/mutation-audits.md`'s shape — short, durable, links the procedure to its rationale. The doc the next operator reads before adding a Playwright spec.

#### D2 — Spec-claim integrity (Pest + Playwright)

**The criterion:** every test's title accurately describes what its body asserts. Catches the "name overclaims" class of bug — `"every widget handle renders without console errors"` that actually checks a narrow regex; `"persists background + padding"` that doesn't perform a render round-trip; `"creates a pending donation record on checkout initiation"` that's a factory-echo fixture-mirror.

**Sweep procedure:**

1. Drain the existing `[test-integrity]` backlog in `sessions/housekeeping-inbox.md` — s296 Tier-3 deferred, s296 incidental, A003 deletions, ticket-tier-picker disposition. Each item is either: action-now (rename / rescope / add the missing assertion / delete), or reaffirmed-and-marked.
2. Forward-scan: grep `it(` / `test(` titles for promise verbs ("renders", "persists", "creates", "every", "only", "all", "ensures") and verify the body matches the claim. Output a delta table of mismatches.
3. Apply: rename to match the actual assertion, or extend the assertion to match the title, or delete. Per-case judgment; default lean is **rename to match what's asserted** rather than expand the test.

#### D3 — Mutation testing slice

**Existing workflow at `docs/testing/mutation-audits.md`.** Each cycle picks one slice from the forward queue and runs Infection against it. Per s241 lean: net-new coverage for surviving mutants is *out of scope* for this dimension — the deliverable is fewer, sharper tests via mutation evidence.

**Forward queue (from s241):** `AppearanceStyleComposer`, Importer `FieldMapper`, page-builder save flow, Filament resource action visibility, Fleet Manager controller (now substantial post v2.3.0).

A cycle that runs D3 ships its findings table + applied dispositions + any `// guards:` override-keep markers per the s241 pattern.

#### D4 — Pest file relevance + fast/slow classification

**The s094 dimensions, refreshed.** Per-file: does it still match infrastructure? Is it still testing active behavior? Is it a false-confidence test? Is it isolated? Is it slow? Each file gets a keep / update / remove verdict.

**Modern overlay:** the fast/slow boundary at >5s lands; the `design` Pest group (`./dev test:design` per CLAUDE.md) is the inner-loop accelerator for scoped design work. D4 verifies the boundary holds — no fast tests creeping over 5s, no slow-group tests under it, no `design` group additions that drift from the reviewed list.

### Cadence trigger

**Approximately every 50 sessions of growth** (modeled on `tracks/code-review-and-cleanup.md`'s cadence). Trigger evaluation rules:

1. **Hard cadence:** at session ≈ (last cycle close + 50), schedule the next audit session.
2. **Forcing function:** lift sooner if a class-of-bug surfaces in regular work that audits would catch — A003's "specs were getting committed without ever running locally" was the forcing function for this track's creation; a similar surface (e.g., a green Pest suite passing a known regression because the test was vacuous) lifts a cycle out of cadence.
3. **Drain pressure on `[test-integrity]`.** When the housekeeping inbox accumulates 5+ unresolved `[test-integrity]` entries, that's a D2 forcing function regardless of cadence.

### Standing improvements (Cycle 1+ carries)

Lifted from A003 and the planning discussion:

- **Local-first Playwright discipline.** A spec doesn't get committed until the author has run `npm run test:e2e -- --grep "<spec name>"` against the local stack and seen it green. This catches the URL-bug class at write time and is the inner-loop change that retires "CI Playwright is a 15-minute outer feedback loop." The discipline lands as a process rule (CLAUDE.md + base-prompt update) once the first cycle's D1 sweep validates the codebase against it.
- **`docs/testing/playwright-discipline.md`** ships as a D1 deliverable on Cycle 1. Subsequent cycles refresh it as Playwright conventions evolve.
- **`[test-integrity]` drainage cadence.** Each cycle drains all `[test-integrity]` entries that fall within its picked dimensions. Items not drained this cycle stay in the inbox until next cycle's drainage.
- **Don't compress under cadence pressure.** A cycle that hits the cadence trigger but reveals more work than one session can absorb expands to a carve-out — never truncates.

### Permanent inter-cycle artifacts

- **`docs/testing/mutation-audits.md`** (s241) — D3 workflow doc. Reusable across cycles.
- **`bin/pest-for-infection.php`** (s241) — Pest 2 + Infection 0.32 adapter shim. Reusable across slices.
- **`docs/testing/playwright-discipline.md`** — D1 workflow doc. Lands at A005 close as a Cycle 1 deliverable.

### What stays in the release plan vs. what stays here

- **The track doc (this file)** owns the *cycle shape*, the *four audit dimensions*, the *cadence rule*, the *retrospectives*, and the *standing improvements*.
- **`sessions/release-plan.md`** carries each cycle's execution-order entry when scheduled (single-session shape; carve-out becomes a second entry if needed).
- **`sessions/session-outlines.md`** carries a one-liner under active tracks pointing here. Per-cycle detail stops bloating the outlines doc.
