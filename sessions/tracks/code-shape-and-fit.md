# Track: Code Shape & Fit

The companion arc to Code Review & Cleanup. Where the cleanup track asks *"does this conform to convention and contain no rot?"*, this track asks *"is this the simplest expression of the intent it's trying to express?"*. Recurring rather than bounded, like the cleanup track; cycles trigger on growth-since-last-refactor, not on absolute time.

This doc carries two things:

- **Status snapshot** — where the track stands, when the next cycle triggers.
- **Forward plan** — cycle shape, cadence trigger, the seven workstream values, anti-performative-change discipline, standing artifacts.

Closed-cycle history (sessions, outcomes, decisions, blind spots, carry-forwards) lives in the companion [`code-shape-and-fit-retrospectives.md`](code-shape-and-fit-retrospectives.md), indicated at the end of this doc. When a cycle closes, its retrospective lands there and the cluster's release-plan position collapses to a one-liner. Per-session detail stays in `sessions/(archived/)NNN. … — Log.md` files.

---

## Status snapshot

**Last update:** 2026-05-21 (session 313 close).

**Complete:** Cycle 1 (sessions 312 audit + 313 apply).

**Active:** between cycles.

**Next trigger:** ≈ session 379 (≈ +50 sessions from this close per the cadence rule), or sooner if a forcing function emerges (a feature track blocked behind a deferred refactor pick, or a refactor-shaped finding from a cleanup cycle that exceeds cleanup's apply scope). The track's first review session A004 is queued out-of-band to evaluate Cycle 1 against stated goals before Cycle 2 plans.

---

## Forward plan — Cycle shape

### Cycle shape — 2 sessions

**2 sessions: audit / apply.** No squash session — refactor moves don't generate migration churn the way cleanup cycles do. If a refactor pick happens to drop a column or rename a table, that's absorbed into the next cleanup cycle's squash, not handled here.

- **Audit session.** Walks the seven workstreams (W-R1 through W-R7 below). Output is seven tables of findings (one per workstream), each with the columns described in the workstream definitions. **No code changes during the audit.** This explicitly inverts the standing fix-in-place-during-audit default that cleanup follows — and the inversion is deliberate. Cleanup's in-audit "safe fixes" are byte-equivalent corrections (dead imports, stale references, single-line framework-alignment wins). Refactor's smallest move is structural by definition; there is no equivalent "trivially safe" category that justifies smuggling changes into a findings-gathering session. Every refactor finding goes on the backlog and is ratified at the apply session before execution. An agent reading both tracks should not generalize cleanup's default here.
- **Apply session.** Six-iteration ceiling, same as cleanup. Each iteration is one refactor move on its own `session-NNN/N` branch, deployed and tested independently. Past 6 iterations is the carve-out smell — lift to a dedicated successor session per the cleanup track's 207 / 275 precedent.

The standard mid-session split check from the existing workflow applies. If a scoped 2-session shape balloons during the audit, the workflow handles the split; this doc doesn't override that.

**Subsystem coverage.** The audit walks by subsystem, and which subsystems get walked is otherwise left to the agent — which means some subsystems can go unwalked cycle after cycle and silently accrue refactor debt that never surfaces. To prevent systematic blind spots, every audit session records two lists in its log: the subsystems it walked this cycle, and the subsystems it did not. The next cycle's audit must walk at least the subsystems that have gone longest unwalked, before any discretionary walks. The intent is rotation, not exhaustiveness — no single cycle has to cover everything, but nothing should be permanently invisible to the track. The subsystems easiest to skip are the ones that rarely change: queue jobs and listeners, console commands, scheduled tasks, the storage abstractions, the mail/notification layer. Those are the ones the coverage list exists to protect.

**Class findings.** When a workstream surfaces N instances of the same structural shape (e.g. Locality surfacing the seven Filament `Import*Page` classes at 700+ LOC sharing the same trait-based pattern), the audit captures it as a single *class finding* row, not N individual rows. The class finding's *Proposed move* column carries both dispatch options:

- **Pattern fix** — single iteration that lifts the shared shape across all instances. More efficient; higher per-iteration risk; only viable when the pattern is genuinely identical and a single shared abstraction lifts cleanly.
- **Instance fix** — pick the worst instance, land that iteration, leave the rest for a future cycle. More conservative; preserves iteration budget; the default when instances have meaningfully diverged or the lift isn't clearly clean.

The apply session picks based on iteration budget remaining and risk tolerance. **Default leaning is instance-fix unless the user explicitly opts into pattern-fix** — the anti-performative-change discipline is uncomfortable with "let's refactor seven things at once," and the cost of a failed pattern fix is N times higher than the cost of a failed instance fix.

**One-shot vs. iterative pattern fixes — weigh the opt-in accordingly.** Some pattern fixes are *one-shot*: lifting the shared abstraction eliminates the deviating shape entirely, and no future cycle re-surfaces it. Others are merely *faster bulk work*: fixing all instances at once accomplishes what fixing them one per cycle would also accomplish, just sooner. The opt-in is worth most in the one-shot case — there, the instance-fix default would leave the same shape to be re-found and re-fixed every cycle until all instances are done, so a clean one-shot lift is worth more than its instance count suggests. For the merely-faster case the conservative instance-fix default should hold, because the only thing pattern-fix buys is speed and the only thing it costs is N-times-higher failure blast radius. When proposing a pattern fix, the apply session states which of the two it is.

If the audit produces an over-scope finding (a class of refactor too big for a single apply iteration — e.g. "restructure the entire importer subsystem around polymorphism instead of `match()`"), the audit captures it as an Open Refactor Flag and the apply session decides whether to absorb a slice or punt to a dedicated session. Same shape as cleanup's Open Flag handling.

### Cadence trigger

**Approximately every 50 sessions of growth**, same cadence as cleanup but offset (see *Status snapshot* for the interleave decision). Trigger evaluation:

1. **Hard cadence:** at session ≈ (last refactor cycle apply + 50), schedule the next refactor cycle's audit session.
2. **Forcing function:** lift sooner if a feature track is blocked behind a refactor pick that's been deferred two cycles in a row, or if a refactor-shaped finding surfaces during a cleanup cycle's audit that exceeds cleanup's apply scope.
3. **Don't compress under cadence pressure.** Same rule as cleanup.

### The seven workstreams

Each workstream measures a specific kind of deviation from the simplest expression of intent. The first five are spatial lenses — where work happens, where state lives, how direct the path is, which work is necessary, where boundaries sit. W-R6 adds a temporal lens (ordering and atomicity) and W-R7 a naming lens (does the name match the behavior). The workstreams are orthogonal; a single finding can show up under more than one (e.g. a value computed three times in three locations is both a Cardinality and a Locality finding). Dedupe at the apply-session intake, not during the audit.

**Output discipline shared across all seven workstreams:** every finding row carries four columns — *Intent*, *Current shape*, *Deviation*, *Proposed move* — except W-R5 and W-R6, which carry a fifth (*what breaks if we move/reorder it*). The *Intent* column requires a citation, not an inference: a test name, docstring, commit message, session-log reference, or memory entry that establishes what the code is trying to do. "I think it's trying to..." is not an Intent. If no source exists, the finding doesn't land in its workstream; instead, the agent files it as an **intent-unrecoverable** finding, a distinct shape that surfaces the absence of recoverable intent as the primary issue. Intent-unrecoverable findings inform the next cycle's W-R5 (Seam quality) and W-R6 (Temporal) walks and may indicate boundaries or ordering invariants that need documenting before any refactor lands. This blocks "I refactored this to be cleaner" by removing the agent's ability to invent plausible intent.

**Intent-unrecoverable lifecycle.** A finding filed as intent-unrecoverable carries forward to the next cycle's documentation-gap walk rather than the apply backlog. If the same finding survives two consecutive cycles' documentation-gap walks without any recoverable intent surfacing — across two full cycles of trying, no one can reconstruct why the code is shaped this way — it closes out as "treating as load-bearing by inability to prove otherwise": the structure stays untouched, the carry-forward ends, and the closing note records that the intent could not be recovered. This stops intent-unrecoverable findings from accreting into a permanent open list that no cycle ever resolves.

#### W-R1: Cardinality

Where work is happening N times when 1 would do, or 1 time where N callers actually need different things. Distinct from cleanup's W7 (Duplicated logic) — W7 catches *exact* code duplication; Cardinality catches *structural* multiplication of work even when the code isn't textually identical.

**Detection patterns:**

- Values computed more than once within a single request lifecycle (grep for re-derivations of the same expression in different scopes that both have access to the upstream source).
- Query loops where a single batched query would do — `N+1` shape, but also `N+M` and `N+N` shapes (sibling loops fetching parallel data).
- Render passes that walk the same tree twice for related outputs.
- Abstractions serving two callers whose needs have diverged enough that the shared abstraction now exists only to satisfy the abstraction, not the callers.
- **Callee-side cardinality.** A single method whose signature has accreted optional parameters or flags to serve successive callers' edge cases, or whose return shape has grown wider than any single caller consumes (each caller destructures a different subset). The bullet above is the caller-side view; this is the callee-side view — the method is trying to be N methods at once. Proposed move is usually to split it by caller-need.

**Example output row:**

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Compute donor cohort timestamps once per report build | `CohortBuilder` derives `$cohort->meta['period_start']` at `:84` (build phase) and again at `:141` (render phase) from raw membership rows | Derivation runs twice; upstream caller already holds the value | Pass derived value through render phase; delete second derivation. Net -8 LOC. |

#### W-R2: Locality

State lives close to its users. Code that changes together lives together. This is the inverse of W-R1 and cleanup's W7 — instead of asking "is this duplicated?" it asks "is this split when it shouldn't be?"

**Detection patterns:**

- State threading through 3+ call layers as bare arguments where the intermediate frames don't read it, only forward it.
- Conceptual units smeared across 4+ files where 1-2 would do (the test: when you change one of the files, do you change the others on the same commit most of the time?).
- Helpers that live in one namespace but are only ever called from another.
- Tests sitting in a directory whose subject lives elsewhere.

**Note on long files.** The cleanup track's W11 already lists language outliers. The Locality lens asks a different question of the same list: *are the responsibilities inside this long file locally related, or is it a bag of weakly-connected concerns that happen to share a lifecycle?* `ImportContactsPage` at 1017 LOC may stay a won't-fix in the cleanup dimension (consequence-of-pattern), but Locality asks whether the page's responsibilities all genuinely belong on the same surface. If they do, the size is fine. If they don't, the finding stands.

#### W-R3: Directness

The code path between intent and execution should not route through indirection that adds nothing.

**Detection patterns:**

- Abstractions with exactly one caller. **Trap:** the abstraction may be correct and the second caller may be coming. The output row must include an *expected second caller* if any; if the agent can't name one, the abstraction is unjustified.
- Wrappers whose body is `return $this->inner($sameArgs)` (no semantic weight added).
- Intermediate variables assigned once and read once on the next line.
- Chains of delegators where each layer adds zero semantic weight.
- Option flags that have only ever been called with one value across the entire call site list.

**Example output row:**

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Resolve the active tenant's storage disk | `TenantContext::resolveStorageDisk()` calls `$this->getStorageDiskName()` which returns `$this->storage_disk` | Single-caller getter; the property is already public-readable | Inline `$this->storage_disk` at the call site; delete `getStorageDiskName()`. No expected second caller. |

#### W-R4: Necessary vs. accidental work

Work the domain requires vs. work the current shape imposes. The diagnostic question (borrowed from cleanup's W8 framing): *"would this work exist if the system around it were shaped right?"* If yes, the work is necessary. If no, the work is a symptom and the surrounding shape is the cause — the finding points at the cause, not the symptom.

**Detection patterns:**

- Values re-derived in function bodies that are already on `$this->` or in scope.
- Re-fetches / re-serializations / re-sorts of data already in the right form upstream.
- Defensive copies of immutable data.
- Conversions between formats that exist because two adjacent layers disagree on representation (the finding is on the disagreement, not on either layer's conversion).
- **Failure-mode disagreement across a call path.** A path where the layers disagree on how failure is represented — one throws, the next returns null, the outermost catches and substitutes a default — so the code spends work translating between failure models. The finding is on the disagreement, not on any single layer's handling; the proposed move names the one failure model the path should express.
- **Data-shape / operation-shape marshalling.** Where a method spends significant work translating between a storage representation and a caller representation. To land as a finding rather than noise — and to stop an eager audit from labelling ordinary mapping code a "mismatch" — this requires a hard threshold: either **(a)** a single method where **≥30% of its non-signature body lines are pure marshalling** (a line that copies a field from one representation to another with no transformation beyond renaming or type-casting counts as marshalling; lines that compute, validate, or branch do not), **or (b)** the **same marshalling block of ≥5 lines appears verbatim or near-verbatim across ≥3 call sites**. Below both thresholds it does not land. The row must state which threshold triggered and show the counted figure.

#### W-R5: Seam quality

Where the boundaries between modules are drawn. **Distinct from the integration-seam sweep in the cleanup track's Cycle 3+ standing improvements** — that catches *correctness* at seams (silent winners, disagreeing defaults). W-R5 catches whether seams are in the right *place at all*.

**Detection patterns:**

- Class pairs where >50% of one's public API exists to feed the other (the boundary is between them, but the dependency runs through them — the seam is misplaced).
- Pipeline-shaped work expressed as method-on-class instead of stage-on-stream.
- Push interfaces where pull would compose better, or vice versa.
- Observer boundaries firing on the wrong granularity.

**Hardest workstream to operationalize** because "where the seam belongs" is genuinely a design call. Output rows must include a fifth column beyond the standard four: *what breaks if we move it*. Anything the agent can't fill that column for is not a finding — it's a candidate, and stays off the apply backlog until the breakage analysis is complete.

#### W-R6: Temporal shape and ordering invariants

The first five workstreams ask spatial questions — where work happens, where state lives, where boundaries sit. This one asks a temporal question: does the code make its ordering and atomicity requirements explicit, or do they live as undocumented assumptions a reader has to reconstruct? An operation whose correctness depends on running before or after another, or on running inside a single transaction, expresses its intent badly when that dependency is implicit.

**Detection patterns:**

- Observer or event-listener chains where the firing order is load-bearing but undocumented — reordering the registrations would change behavior, and nothing says so.
- Operations presented as synchronous that are actually eventually-consistent (a read immediately after a write that may not reflect it), or the reverse.
- Logical units of work that span multiple writes without a transaction boundary, where a partial failure leaves inconsistent state.
- Sequential loops or pipeline stages whose order is load-bearing for the output but whose ordering rationale is not recoverable from the code. These frequently overlap with intent-unrecoverable findings — if the order matters and no source says why, file as intent-unrecoverable rather than proposing a reorder.

Like W-R5, findings here carry the fifth column — *what breaks if we change the ordering or atomicity* — because the cost of a wrong temporal refactor is a class of bug (race, partial write, stale read) that tests frequently don't catch. A finding without that column filled is a candidate, not a finding.

#### W-R7: Naming-intent fidelity

Distinct from the cleanup track's naming-consistency checks, which catch convention drift (camelCase vs snake_case, inconsistent prefixes). This workstream catches a deeper problem: the name promises one thing and the body does another, so the name actively misdirects a reader about the code's intent. A misleading name is the inverse of expressing intent simply — it carries a different intent than the code does.

**Detection patterns:**

- A method whose name names one responsibility but whose body carries several (`validateInput` that also mutates state; `getX` that also writes; `processOrder` that fulfils, invoices, and notifies).
- A name that describes an obsolete behavior the code no longer has (the method was repurposed; the name wasn't).
- A boolean parameter or return whose name states the opposite of what the value means at the call sites.
- A class or service whose name claims a scope materially narrower or wider than what it actually owns.

The *Proposed move* is usually one of: rename to match behavior, or split so each name covers one responsibility. A pure rename is low-risk; a split is a structural change carrying the same risk profile as a Locality or Cardinality fix. The row must say which, because the two have very different blast radii.

### Anti-performative-change discipline

This track is unusually exposed to performative diff-making. An agent told to "improve the codebase" will reshuffle code, add abstractions, rename variables, and call it done. Three structural guards:

1. **Every finding cites recoverable intent.** The Intent column (see *Output discipline* above) requires a citation, not an inference. If the agent cannot find a test name, docstring, commit message, session-log entry, or memory entry that establishes intent, the finding becomes intent-unrecoverable rather than a refactor finding — surfacing the documentation gap as the primary issue. This removes the agent's ability to invent plausible intent to justify a refactor.

2. **Close-gate diff narration.** At apply-session close, the agent names what was consolidated, in prose, citing the specific abstractions / files / call sites removed and any new ones added. LOC delta and file count delta are reported as context — not as targets. The point is not "diff went down"; the point is the agent can articulate the shape change in concrete terms. Goodhart's law on LOC is a real failure mode here — refactors that recover invariants, add explicit phases, or surface implicit assumptions often grow LOC legitimately, and a directional LOC target would create an incentive to game that. The narration sits in the apply-session log and informs the retrospective.

3. **Citation enforcement is two-part: prompt requires, reviewer verifies.** The audit-agent prompt requires every finding to cite a recoverable source for the code's intent, quoted verbatim with the line numbers of the quoted text. The reviewer spot-checks those citations against actual file content before findings land on the refactor backlog. The verbatim-with-line-numbers rule raises the cost of fabrication; the spot-check catches what slips through. Both halves are required — an agent under instruction to find issues will produce findings to file, and prompt scaffolding alone does not prevent invented citations. Findings whose citations fail spot-check either reclassify to intent-unrecoverable (if the citation was the only support) or have the citation corrected (if a real source exists nearby and was misquoted).

**An empty workstream table is a valid result.** Finding nothing in a lens is not a failed walk — it is the expected outcome when the codebase genuinely has no deviation of that kind. The audit-agent prompt must frame zero findings as success, not as a quota to fill: an agent that feels pressure to produce a finding per lens will manufacture marginal ones, which is the exact failure mode the cited-intent discipline exists to prevent. Expect the Directness (W-R3) and Seam-quality (W-R5) lenses in particular to come back empty most cycles — after sustained cleanup-track discipline, single-caller wrappers and zero-weight indirection are largely gone, and well-placed seams stay well-placed. A future cycle should not loosen those two lenses' detection patterns to make them yield; empty is the signal that they are working, not evidence they need tuning.

**Bookkeeping codes stay out of user-facing prose.** The workstream identifiers, flag codes, and carve-out shorthand are project-internal jargon. They belong in the audit log, the apply-session log, and commit messages — where precision matters and the reader has the context to decode them — not in status summaries or discussion with the user, where they read as impenetrable abbreviations. Translate to plain language when surfacing anything to the user; keep the codes in the artifacts.

### Standing improvements (Cycle 2+ carries)

- **Sequence-after-cleanup-cycle preference.** The default ordering is a trailing pair after the most recent cleanup cycle, so the Locality walk consumes fresh cleanup outputs rather than stale ones. If the next cleanup cycle has not run by the time the refactor trigger fires, decide whether to wait or run ahead using a concrete test rather than judgement:
  - Count, among the findings that would actually reach the apply backlog — inline findings plus flags slated for action, **excluding** intent-unrecoverable findings (which are not acted on and so cannot waste apply work) — how many are *stale-touched*.
  - A finding is **stale-touched** when its *Current shape* cites code that the pending cleanup cycle's known scope is likely to move, rename, or delete. If the pending cleanup scope is not yet defined, treat the importer subsystem and any file on the cleanup track's current long-file outlier list as the stale set.
  - If **more than 30%** of the apply-backlog findings are stale-touched, sequence after the cleanup cycle. At or below 30%, running ahead is defensible.
  - State the count, the denominator, and the stale set used in the audit log so the call is auditable rather than asserted.

### Permanent inter-cycle artifacts

- **Audit-finding template** at `sessions/refactor-track-finding-template.md` — the four-column (or five-column for W-R5 and W-R6) row format with worked examples per workstream. Lives alongside the track for agents to reference during audit sessions. **Cycle 1 deliverable** — the first cycle creates it as part of its work.

- **Deliberately Kept register** at `sessions/refactor-track-deliberately-kept.md` — an enumerated list of patterns that look like W-R1 through W-R7 findings but are intentional architectural decisions. The audit session consults this register and skips findings that match a registered pattern. Apply walkthroughs do not relitigate registered carve-outs.

  **Lifecycle.** The register lands with this doc, pre-seeded; new carve-outs accrete from each apply walkthrough between now and Cycle 1 (and after) as decisions are made. Cycle 1 inherits a populated list rather than bootstrapping one — and the forcing-function value (an explicit place to write down "we decided to keep this, here's why") starts compounding immediately.

  **Retirement.** Carve-outs can be retired at apply-session intake when the original justification no longer holds. The retiring agent cites which session retired the carve-out and what changed. Without this, the register becomes a write-only ratchet — every intentional decision sticks even when the original forcing function is gone. To keep retirement from being purely passive, every apply walkthrough actively walks the register and asks of each entry whether its original justification still holds, rather than waiting for someone to happen to notice a stale carve-out. Most entries survive most walks; the walk is cheap, and the failure mode it guards against — a register that only ever grows — is not.

  Pre-seeded entries (landed with this doc):

  - Per-namespace `FieldMapper` services — uniform UX over per-type plumbing, decided at s259. Looks like a Directness or Cardinality target; isn't.
  - Page vs View distinction — looks like a Locality/Seam target; isn't.
  - Inline-eligibility per-widget roster — looks like a Cardinality/Directness target; isn't.
  - *(Append others as intentional decisions land between this doc and Cycle 1.)*

### What stays in the track doc vs. what stays in the release plan

- **This doc** owns the cycle shape, the cadence rule, the seven workstream definitions, the anti-performative-change discipline, and the standing improvements. Closed-cycle retrospectives live in the companion `code-shape-and-fit-retrospectives.md`.
- **`sessions/release-plan.md`** carries each cycle's two numbered execution-order entries when the cycle is queued (mirroring how cleanup cycles sit in execution order). Position in execution order is the release-plan's concern; *when* and *how* the cycle runs is this doc's concern.
- **`sessions/session-outlines.md`** carries a one-liner under active tracks pointing here. Per-cycle detail stops bloating the outlines doc.

---

## Open questions for first cycle

Two things this draft doesn't yet decide. Resolving them locks the first cycle's shape.

1. **W-R1 vs. W7 boundary.** Both catch "this work could happen once." The proposed boundary is *textual* (W7) vs *structural* (W-R1), but in practice some findings will plausibly belong to either. Two options: (a) refactor track owns all multiplication findings, cleanup's W7 retires; (b) keep both, accept some overlap, dedupe at apply intake. Leaning (b) — the cleanup track's W7 catches exact-duplication patterns that are mechanical to fix in-line; the refactor W-R1 catches structural multiplication that needs strategic thinking. Different operating modes, both worth keeping.
2. **First cycle's audit input.** Does the audit session walk the codebase cold, or does it consume cleanup Cycle 3's W11 outliers as pre-loaded findings (the way 272's prompt consumed 271's findings)? Trailing pair makes this easy — Cycle 3's outputs are fresh and feed directly in.

---

## Cycle Retrospectives

Closed-cycle history lives in the companion doc: [`code-shape-and-fit-retrospectives.md`](code-shape-and-fit-retrospectives.md). **Read it before planning a new cycle** — its carry-forwards and blind spots shape what the next cycle pre-loads. It is kept out of the main body deliberately, to keep this doc focused on forward operation rather than history; the pointer sits here, at the end, rather than frontloaded.
