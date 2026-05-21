# 329. Code Shape & Fit (Apply) — Cycle 1

> Placeholder session number. Re-number when scheduling; runs immediately after 328 audit closes. Filename uses `NN2.` until the slot is assigned (paired with the audit's `NNN.` placeholder).

Apply the refactors agreed from session 328's audit. This is the **fix half** of the Code Shape & Fit track's first cycle. 328 produced the W-R1 through W-R5 backlogs, the intent-unrecoverable findings section, the Open Refactor Flags, and the (now-populated) Deliberately Kept register. 329 walks them with the user, ratifies pre-resolutions, and applies iteration-sliced.

The 273 session is the closest procedural precedent (cleanup track's apply session). This prompt mirrors its shape — Open Flags walked first, then per-workstream backlog walkthroughs, then iteration-sliced application — adapted to the refactor track's discipline: instance-fix default on class findings, prose diff narration at close-gate (no LOC target), Intent-citation discipline preserved through the iteration commits.

---

## Design decisions (resolved before starting)

- **Every row is a candidate, not a mandate.** The user will pre-resolve the Open Refactor Flags at 328-close. Walk through them at session start to ratify each pre-resolution and confirm iteration ordering. Items not chosen stay on the backlog as deferrals (with a note in the next cycle's pre-loaded findings) or are marked won't-fix in the Deliberately Kept register with citation.
- **Refactors that change public API shape are out of scope.** No DB column meaning changes, no JSON response shape changes, no migration writes. Anything that needs a schema change goes to the next cleanup cycle's squash.
- **Instance-fix default on class findings.** When a class-finding row offers pattern-fix vs instance-fix, the default is instance-fix unless the user explicitly opts into pattern-fix at walkthrough. Pattern fixes on N instances are N times the failure-mode cost; the anti-performative-change discipline is uncomfortable with bundling.
- **Six-iteration ceiling.** Past 6 iterations on a single apply branch is the carve-out smell — at that point, the next iteration's content is the carve-out. Hard heuristic.
- **Tests for behaviour-changing extractions.** Any iteration that introduces a new seam (a service split, a moved boundary) gets test coverage. Pure structural extractions with byte-equivalent semantics rely on the existing suite; the agent notes that explicitly in the commit message.
- **Close-gate diff narration.** At session close, the agent names what was consolidated in prose, citing specific abstractions / files / call sites removed and any new ones added. LOC delta and file count delta are reported as context — not as targets. Goodhart on LOC is a real failure mode here.
- **Iteration strategy:** each refactor move (or tightly-grouped set within the same workstream and risk tier) is its own iteration branch `session-329/1`, `session-329/2`, …. Each ships deploy-testable independently. **Do not bundle across workstreams or across risk tiers.** Bundling several W-R3 single-caller cleanups into one low-risk iteration is fine; bundling a W-R3 cleanup with a W-R5 seam move is not.
- **Intent-citation preserved through commits.** The commit message for each iteration carries the *Intent* citation from the backlog row. This propagates the discipline from the audit log into git history.

---

## Open Refactor Flags from 328 (pre-resolved per user direction at 328-close; ratify at walkthrough)

Populated at 328's close. Each flag carries: name, what was found, risk + scope, the dispatch options 328 proposed, the user's pre-resolution from 328-close, and the iteration slot.

Walk these first at session start. Resolutions are pre-agreed; the walkthrough confirms iteration ordering and scope refinements. Items the user shifts off the docket get noted in the Deliberately Kept register (with citation pointing at this session) or in the next cycle's pre-loaded findings.

### Flag W-Rx/Y — [name]

**328 finding:** [what was found]

**Risk + scope:** [why it's bigger than a typical backlog row]

**Proposed paths from 328:** [the dispatch options]

**Pre-resolution:** [the user's pick + rationale]

**Lands in iteration:** `session-329/N`

*(One block per flag. Empty until 328 closes.)*

---

## Deliberately Kept register walkthrough (from 328)

328 proposed register additions and retirements without unilateral edits. Walk both lists with the user before the workstream backlogs.

### Proposed additions (from 328 audit log)

Each row: pattern, what triggered the consideration, citation, and proposed workstream coverage.

For each:
- **Ratify** — pattern lands in the register at session close.
- **Reject** — pattern returns to the next cycle's audit as a candidate finding.
- **Modify** — adjust the pattern description / workstream coverage and ratify.

### Proposed retirements (from 328 audit log)

Each row: existing register entry, the staleness rationale 328 surfaced, and what the agent observed that suggests the carve-out no longer holds.

For each:
- **Retire** — entry removed from the register at session close; the underlying pattern becomes a fair audit target for the next cycle.
- **Affirm** — entry stays; the staleness was apparent but not actual.
- **Refine** — update the entry's justification or workstream coverage so it correctly reflects current intent; keep the carve-out.

Register file edits land at session close, in the same commit as the retrospective updates. The walkthrough decision goes in the apply log.

---

## W-R1 — Cardinality (apply)

328 contributes the backlog rows. Walk through, pick a subset, apply.

| # | Intent (with citation) | Current shape | Deviation | Proposed move |
|---|------------------------|---------------|-----------|---------------|

*(Populated from 328's W-R1 log section.)*

Walkthrough rule: for each row, the user picks **apply this iteration / defer to next cycle / won't-fix (add to Deliberately Kept register)**. Default is apply unless the user signals otherwise.

---

## W-R2 — Locality (apply)

| # | Intent (with citation) | Current shape | Deviation | Proposed move |
|---|------------------------|---------------|-----------|---------------|

*(Populated from 328's W-R2 log section. Class-finding rows clearly marked; per 328's two-cohort W-R2 framing, expect the `Import*ProgressPage` class-finding (419-786 LOC, uniform cohort) plus a separate instance finding on `ImportContactsPage` from the Entry pages cohort — the standing W11 outlier per cleanup Flag B. These are two distinct backlog rows, not one.)*

**Class-finding walkthrough discipline:** for each class-finding row, the user picks pattern-fix or instance-fix at walkthrough. Default is instance-fix; the user must explicitly opt into pattern-fix. Pattern-fix iterations carry an automatic risk-tier-bump in the testing requirements section.

---

## W-R3 — Directness (apply)

| # | Intent (with citation) | Current shape (with expected second caller note where applicable) | Deviation | Proposed move |
|---|------------------------|---|---|---|

*(Populated from 328's W-R3 log section.)*

**Single-caller findings have an extra walkthrough step.** Before applying, confirm the agent's expected-second-caller analysis is still accurate (the codebase may have changed between 328 and 329). If a second caller is now coming or pending in another session's work, the finding becomes a "wait" — the abstraction is justified by the imminent caller, not by current usage.

---

## W-R4 — Necessary vs. accidental work (apply)

| # | Intent (with citation) | Current shape | Deviation (cites the upstream disagreement, not the symptom) | Proposed move |
|---|------------------------|---------------|---|---|

*(Populated from 328's W-R4 log section.)*

**Walkthrough discipline:** verify each row's *Deviation* points at the upstream cause, not the local symptom. Apply moves that fix the cause; reject moves that just clean up the symptom — those are next-cleanup-cycle W7 / W8 work, not refactor work.

---

## W-R5 — Seam quality (apply)

| # | Intent (with citation) | Current shape | Deviation | Proposed move | What breaks if we move it |
|---|------------------------|---------------|-----------|---------------|---------------------------|

*(Populated from 328's W-R5 log section. Five columns, not four.)*

**Candidate sub-section (separate from above):** 328's W-R5 candidates that lacked breakage analysis don't enter the apply backlog. Walk them at session start anyway — the user may have context that closes the breakage analysis on the spot, lifting the candidate into the backlog. Candidates the walkthrough doesn't lift stay candidates for the next cycle.

**Seam moves are the highest-risk class in this session.** Default iteration slotting: late in the session order, after lower-risk extractions have shipped and the suite has confirmed green from those.

---

## Intent-unrecoverable findings (review at walkthrough; not for apply)

328's intent-unrecoverable section. Walk through with the user. Possible outcomes per entry:

- **User recalls the intent on the spot** — finding shifts to its proper workstream backlog with citation pointing at this walkthrough.
- **User does not recall** — finding stays intent-unrecoverable, carries forward to the next cycle's W-R5 documentation-gap walk.
- **User declares the code intentionally undocumented** — entry goes into the Deliberately Kept register.

**Do not refactor intent-unrecoverable code this session.** The refactor would itself become an undocumented decision.

---

## Suggested iteration shape

A recommendation — the user reorders, bundles, or splits after the walkthrough. Six-iteration ceiling.

- **`session-329/1` — Low-risk W-R3 single-caller cleanups.** The most mechanical refactors in the backlog: collapse confirmed single-caller abstractions whose expected-second-caller analysis confirmed *no second caller coming*. Inline the body at the call site, delete the abstraction. Each cleanup is small; bundle several into one iteration. Suite confirms green at iteration close.
- **`session-329/2` — W-R4 cause-fixes.** Apply the W-R4 rows where the upstream disagreement is well-scoped and the cause-fix is localized (e.g. two layers agreeing on a single representation). Tests as needed for behavior-changing extractions.
- **`session-329/3` — W-R1 cardinality fixes.** Batch query consolidations, derived-value pass-throughs, render-pass merges. Each gets a test confirming the new cardinality at iteration close (regression-guard pattern, see `tests/Feature/PageBuilderApiTest.php` for the query-count-bound test shape from cleanup cycle 2).
- **`session-329/4` — W-R2 instance-fix from the class-finding(s).** Per 328's two-cohort W-R2 framing: pick the worst-Locality instance from the `Import*ProgressPage` class-finding cohort (likely `ImportInvoiceDetailsProgressPage` at the top of the LOC range, with invoice-group semantics as the candidate weakly-connected slice). The `ImportContactsPage` instance finding from the Entry pages cohort is a separate backlog row — the walkthrough decides whether this iteration absorbs both or whether they split into `/4` + `/4b` (or one defers to next cycle). Tests via existing suite for byte-equivalent moves; behaviour-changing extractions get new coverage.
- **`session-329/5` — W-R5 seam moves.** The highest-risk iteration. Apply at most one or two seam moves; each must have its *What breaks if we move it* analysis confirmed at walkthrough. **Full fast suite + slow group + Playwright + manual smoke after.** Import-flow seam moves specifically touch the slowest test population, so the slow group is non-optional here — this mirrors cleanup track 273's discipline of running the full slow group after high-risk iterations, not just after the riskiest one in aggregate.
- **`session-329/6` — Buffer / Open Flag absorption.** Reserve for anything that surfaced during earlier iterations or needs a second pass. If unused, the session closes early at /5.

Bundle or split as the user prefers. Each iteration ships with its own test run and manual-test pause where applicable.

---

## Testing

- **Slow test groups to run this session:** non-optional after `session-329/5` (W-R5 seam moves) regardless of which seams are touched. Additionally, if any other iteration touches importer flows, run the importer slow group in that iteration. Otherwise none by default.
- **New tests expected:** yes, scoped per-iteration:
  - W-R1 iterations: query-count regression-guard tests (one per cardinality fix).
  - W-R2 / W-R5 behaviour-changing extractions: feature tests covering the new seam.
  - Byte-equivalent structural extractions: rely on existing suite; note in commit message.
- **Fast suite target after the session:** existing 274-baseline + sum of new tests per iteration. Log the final delta.
- **Playwright suite:** required after any iteration touching frontend code; required after `/5` regardless.
- **`npm run build`:** required for any iteration touching front-end source.
- **`php artisan build:public`:** required only if a widget asset surface was touched (Laravel artisan command, not an npm script).

---

## Security checklist

Before closing:

- [ ] Permission gates preserved on every endpoint touched
- [ ] Portal `contact_id` scoping preserved on any portal-adjacent refactor
- [ ] Schema-key filtering still enforced on `PageBuilderApiController::update()` after any Vue / API surface touched
- [ ] FM contract version parity test still passes (the cleanup-track artifact at `tests/Feature/Infrastructure/FmContractVersionParityTest.php`)
- [ ] No new untrusted-input → filesystem paths introduced
- [ ] No behavior changes smuggled in alongside structural extractions (the commit messages must distinguish behavior-changing from byte-equivalent moves explicitly)
- [ ] Fast Pest suite passes
- [ ] `npm run build` passes on every iteration that touched front-end source
- [ ] `npm run test:e2e` passes after the iterations requiring it
- [ ] **Close-gate diff narration filed in the log** (see *Closing steps* below)
- [ ] **No findings landed without recoverable Intent citations.** Every applied refactor's commit message carries the citation from the backlog row.

---

## Out of scope

- New features of any kind
- Migration squash (next cleanup cycle's squash session)
- Schema changes
- Refactors not present on the W-R1 through W-R5 backlog or Open Refactor Flags from 328 — add to the next refactor cycle's pre-loaded findings rather than expanding mid-session
- Bug fixes beyond incidental one-line cases surfaced during a refactor
- Cleanup-track work (convention drift, dead code, doc coverage)
- Pattern fixes on class findings unless the user explicitly opts in at walkthrough
- Refactoring intent-unrecoverable code
- Performance optimization as a primary goal — refactors that happen to improve perf as a side effect are fine

---

## Closing steps

Follow the close gate in `sessions/329. base-prompt.md`. Session-specific details:

- **Log file:** `sessions/329. Code Shape & Fit (Apply) — Cycle 1 — Log.md`. Include:
  - Per-iteration summary (what landed, what tests added, deferred items)
  - Final disposition of each Open Refactor Flag (Applied in /N | Carved out | Won't-fix → Deliberately Kept register)
  - W-R1 through W-R5 outcome tables (applied vs deferred vs won't-fix)
  - Intent-unrecoverable walkthrough outcomes
  - **Close-gate diff narration:** prose summary of what was consolidated, citing specific abstractions / files / call sites removed and any new ones added. LOC delta and file count delta as context. The agent's narration is the artifact; raw counts are footnotes.
- **Branch:** `session-329/N` (final iteration).
- **Update the track doc:** `sessions/tracks/code-shape-and-fit.md` § *Cycle Retrospectives* gets the full Cycle 1 retrospective (window covered, sessions list, quantitative outcomes table, load-bearing decisions, blind spots, process incidents, won't-fixes reaffirmed). Mirrors the cleanup track's Cycle 2 retrospective shape.
- **Update the track doc's Status snapshot:** mark Cycle 1 complete; set Next trigger session ≈ 379 (≈ +50 sessions from this close per the cadence rule).
- **Update the Deliberately Kept register at `sessions/tracks/code-shape-and-fit-deliberately-kept.md`:** entries added during the walkthrough (won't-fix items with citation pointing at this session) land in the register file. Stale entries flagged by 328 and ratified-for-retirement at walkthrough get removed with a brief log note.
- **Stub closeout:** `sessions/session-outlines.md` § "Code Shape & Fit — 2-session cycle" — mark the 329 child stub ✅ closed; collapse the cluster's release-plan position to a one-liner pointing at the track doc.
- **Cycle 1 carve-outs:** any carve-out lifted from the apply session per the 6-iteration ceiling gets its dedicated successor session drafted at close (mirroring how 273-close carved out 275 for rich-text sanitization). Insert into `sessions/release-plan.md` at the agreed position.
