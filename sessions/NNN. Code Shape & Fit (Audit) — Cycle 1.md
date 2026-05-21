# 328. Code Shape & Fit (Audit) — Cycle 1

> Placeholder session number. Re-number when scheduling; trailing-pair plan puts this immediately after cleanup Cycle 3's squash session closes. Filename uses `NNN.` until the slot is assigned.

First audit of the Code Shape & Fit track. Walks five workstreams (W-R1 Cardinality / W-R2 Locality / W-R3 Directness / W-R4 Necessary vs. accidental work / W-R5 Seam quality) against the codebase as it stands at cleanup Cycle 3's squash. This is the **audit half** of a two-session cycle — session 329 walks the findings with the user, ratifies pre-resolutions, and applies iteration-sliced.

Because this is the track's first cycle, one permanent artifact gets created here alongside the audit work:

- `sessions/tracks/code-shape-and-fit-finding-template.md` — the four-column (or five-column for W-R5) row format with worked examples per workstream.

The Deliberately Kept register at `sessions/tracks/code-shape-and-fit-deliberately-kept.md` already exists (landed alongside the track doc and accreted between then and Cycle 1). This session validates the register, surfaces stale entries for retirement, and proposes additions for the 329 walkthrough — but does not silently edit it.

Pre-loaded findings carry forward from cleanup Cycle 3's close. No code changes during this session per the track's audit rule.

---

## Design decisions (resolved before starting)

- **No code changes during the audit.** This inverts cleanup's fix-in-place-during-audit default and the inversion is deliberate per the track doc. Refactor's smallest move is structural by definition; there is no "trivially safe" refactor in the way cleanup has trivially safe corrections. Every finding goes on the backlog and is ratified at session 329 before execution. Do not generalize cleanup's default here.
- **Intent column requires citation.** Every finding's *Intent* cites a test name, docstring, commit message, session-log reference, or memory entry that establishes what the code is trying to do. "I think it's trying to..." is not Intent. If no source exists, file the finding as **intent-unrecoverable** in a separate section — that's a distinct shape that surfaces a documentation gap, not a refactor finding.
- **Class findings collapse into single rows.** When a workstream surfaces N instances of the same structural shape (e.g. Locality surfacing the seven `Import*Page` classes at 700+ LOC sharing the same trait-based pattern), capture as one class-finding row with both dispatch options (pattern fix / instance fix) in the *Proposed move* column. Do not produce N individual rows for what is structurally one finding.
- **Consult the Deliberately Kept register.** Findings that match a registered carve-out get skipped, not landed. **Retirement check:** if a carve-out's original justification looks stale (the architectural reason no longer holds, the cited session's decision has been superseded, the code the carve-out protects has materially changed), surface the entry for retirement at the 329 walkthrough rather than silently skipping the finding. The retirement decision belongs to the walkthrough, not the audit; the audit's job is to flag the staleness with citation.
- **New carve-outs surfaced during the audit get proposed for register addition;** the addition itself happens at the 329 walkthrough, not unilaterally during the audit.
- **Open Refactor Flag captures over-scope.** If a finding is too big for a single 329 iteration (e.g. "restructure the importer subsystem around polymorphism instead of `match()`"), capture as an Open Refactor Flag with risk + scope + proposed paths. 329 decides whether to absorb a slice or punt to a dedicated session.
- **Long runtime expected.** Five workstreams plus two artifact deliverables. Apply the standard mid-session split check if scope balloons — the workflow handles the split.
- **Bug fixes are out of scope.** Catching a real bug during the audit triggers an Open Refactor Flag, not a fix.

---

## Cycle 1 artifact creation + register validation (first-cycle-only)

This section runs **before** the audit workstreams. The audit references both artifacts throughout.

### Artifact: `sessions/tracks/code-shape-and-fit-finding-template.md`

A reference document agents consult during audit sessions. Contents:

- The four-column row format (*Intent* / *Current shape* / *Deviation* / *Proposed move*) explained, with the W-R5 fifth-column variant (*What breaks if we move it*) noted.
- One worked example per workstream, drawn from real codebase patterns the audit will surface. Use this session's actual findings if they're already clear; otherwise use the example rows in the track doc as placeholders and refine post-audit.
- The Intent-citation rule restated with examples of valid citations and invalid ones ("I think it's trying to..." kind of inference).
- The intent-unrecoverable finding shape explained, with one example.
- The class-finding row shape explained, with the pattern-fix-vs-instance-fix dispatch column.

### Validate the existing Deliberately Kept register

The register at `sessions/tracks/code-shape-and-fit-deliberately-kept.md` exists pre-cycle. This step is **validate-and-extend**, not create:

1. **Read the register cold.** Note every entry's pattern, justification, citation, and workstream coverage.
2. **Spot-check citations.** For each entry, confirm the cited source (session log, doc, memory entry) still resolves to a discoverable artifact and still supports the carve-out's justification. Flag any entry whose citation has rotted (deleted session, superseded doc) or whose justification looks stale given subsequent codebase changes.
3. **Propose additions for the 329 walkthrough.** If the workstream walks below surface patterns that look intentional but aren't in the register, capture them in a *Proposed additions* sub-section of the audit log — with citation. The user ratifies additions at 329; do not edit the register file during the audit.
4. **Propose retirements for the 329 walkthrough.** Stale entries (from step 2) get a *Proposed retirements* sub-section in the audit log, with the staleness rationale. Same discipline: user ratifies at 329; no unilateral edits.

The register's per-entry format the audit expects:

```
### [Pattern name]
- **What it looks like:** [the surface pattern that would trigger a finding]
- **Why it's intentional:** [the design call that established it]
- **Source:** [session number, log file, memory entry, or other recoverable citation]
- **Workstreams this carve-out covers:** [W-R1, W-R3, etc.]
```

If the existing register uses a different format, the audit reads through the format mismatch (no behavior change needed for this session) and the format alignment happens as a separate one-time edit outside the audit cycle.

---

## Pre-loaded findings (validate during the audit)

Carried forward from cleanup Cycle 3's close. Populated at this session's prompt-finalization step, not at draft time. Expected categories:

- **Cleanup Cycle 3 W11 outliers** — long files the cleanup track classified as won't-fix in its dimension. Walk these under the W-R2 (Locality) lens: are the responsibilities inside genuinely locally related, or weakly-connected concerns sharing a lifecycle? `ImportContactsPage` and the other `Import*Page` classes are the canonical class-finding candidate.
- **Cleanup Cycle 3 W7 carry-forwards** — duplicated-logic findings the cleanup apply session declined to extract. Walk these under the W-R1 (Cardinality) lens for structural multiplication that the cleanup W7's textual-duplication frame may have missed.
- **Cleanup Cycle 3 Open Flags** — any architectural calls cleanup deferred. May or may not be refactor-shaped; classify at audit time.
- **Cleanup Cycle 3 Standing improvements** — the integration-seam sweep notes specifically may surface W-R5 (Seam quality) candidates.

These are *seeds*, not the full audit input. The audit walks the codebase cold for everything else.

---

## Before writing any code

Build a mental model oriented to the workstream lenses, not the file tree. Cleanup's audit reads directories; refactor's audit reads *flows*.

- **For W-R1 (Cardinality):** trace a few representative request lifecycles end-to-end. A page-builder save, a contact import row commit, a portal contact view. Note every value that gets computed; note where the same logical value gets computed twice.
- **For W-R2 (Locality):** read the cleanup track's W11 outlier list. For each top-of-list file, read it cold and ask: would a new contributor encountering this file understand the boundary between its responsibilities? If yes, the file's length is principled. If no, the split-out targets are W-R2 candidates.
- **For W-R3 (Directness):** read the public methods of services and controllers under `app/Services/` and `app/Http/Controllers/`. Note single-caller abstractions; note wrapper methods whose body forwards args; note option flags whose call sites all pass the same value.
- **For W-R4 (Necessary vs. accidental work):** read three representative service-class method bodies that handle multi-step flows (suggest: one importer service method, one page-builder service method, one observer). Note values re-derived inside the method that the caller already has.
- **For W-R5 (Seam quality):** read the class pairs where one's primary collaborator is another — `ImportSessionActions` + `InteractsWithImportProgress`, `PageBuilderApiController` + the Vue store, `HealthController` + `BackupController`. For each pair, note what fraction of one's public API exists to feed the other.

**Reference documents:** the track doc at `sessions/tracks/code-shape-and-fit.md`, the finding-template artifact created above, the existing Deliberately Kept register at `sessions/tracks/code-shape-and-fit-deliberately-kept.md`, and `sessions/archived/271. … — Log.md` + `sessions/archived/272. … — Log.md` for the established audit-prompt shape.

---

## Workstream W-R1 — Cardinality

Where work is happening N times when 1 would do, or 1 time where N callers actually need different things.

**Detection patterns:**

- Values computed more than once within a single request lifecycle. Grep for re-derivations of the same expression in different scopes that both have access to the upstream source.
- Query loops where a single batched query would do — `N+1` shape, but also `N+M` and `N+N` shapes (sibling loops fetching parallel data).
- Render passes that walk the same tree twice for related outputs (page builder bootstrap data is the canonical place to look).
- Abstractions serving two callers whose needs have diverged enough that the shared abstraction now exists only to satisfy the abstraction, not the callers. Distinct from cleanup's W7 — which catches *exact* textual duplication. W-R1 catches structural multiplication.

**Distinction from cleanup's W7.** If two functions are nearly textually identical and the obvious move is to merge them, that's W7 in the next cleanup cycle, not W-R1 here. W-R1 catches "the same work is happening twice even though the code expressing it differs." Example: two services that each independently re-derive the active tenant's storage disk path from raw config — same work, different code.

**Output format (per finding row):**

| Intent (with citation) | Current shape | Deviation | Proposed move |

**Skip if matches Deliberately Kept register.** Otherwise land on backlog for 329.

---

## Workstream W-R2 — Locality

State lives close to its users. Code that changes together lives together.

**Detection patterns:**

- State threading through 3+ call layers as bare arguments where the intermediate frames don't read it, only forward it.
- Conceptual units smeared across 4+ files where 1-2 would do. **Test:** look at the last 6 months of commits — when one of the files changed, did the others change in the same commit most of the time? If yes, the smearing is real.
- Helpers living in one namespace but only called from another.
- Tests sitting in a directory whose subject lives elsewhere.

**Note on long files (cleanup W11 carry-forward).** This is the workstream that re-evaluates cleanup's W11 won't-fixes through a different lens. For each W11 outlier, ask: *are the responsibilities locally related, or is this a bag of weakly-connected concerns sharing a lifecycle?* The codebase has two parallel Filament Page cohorts worth walking under this lens:

- **Entry pages** (`Import{Type}Page` × 7) — six in the 230-356 LOC range, with `ImportContactsPage` as the standing 1014-LOC outlier (cleanup Flag B, won't-fix). Use `InteractsWithImportWizard`. Not a clean class-finding shape because of the outlier; treat as one instance finding on `ImportContactsPage` (carry-forward from cleanup) plus a tight-cluster observation on the other six.
- **Progress pages** (`Import{Type}ProgressPage` × 7) — 419-786 LOC range, all clustering in the upper-half. Use `InteractsWithImportProgress`. A clean class-finding candidate because the cohort is genuinely uniform in size and trait usage. **Naming asymmetry note:** `ImportProgressPage` (no entity prefix) IS the contacts-progress page — it carries the legacy unnamespaced filename because Contacts was the first importer to ship. There is no shared base class; it's a sibling of the other six, not a parent. Verified via `model_type => 'contact'` + the `App\Models\Contact` import at the head of the file.

**Class-finding example for the `Import*ProgressPage` cohort:**

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| [cite the session that established the namespaced-importer ProgressPage pattern — likely s202 or B2/B2a per cleanup logs] | Seven Filament `Import{Type}ProgressPage` classes at 419-786 LOC (`ImportInvoiceDetailsProgressPage` 786, `ImportEventsProgressPage` 665, `ImportProgressPage` 657 — the contacts-progress page per the naming-asymmetry note above, `ImportDonationsProgressPage` 594, `ImportMembershipsProgressPage` 579, `ImportOrganizationsProgressPage` 440, `ImportNotesProgressPage` 419), sharing the per-row processing template-method pattern via `InteractsWithImportProgress` | The ProgressPage layer holds per-type processing logic that may or may not be locally related to the page-shell concerns — needs walking to determine whether the LOC reflects necessary per-type domain logic or weakly-connected responsibilities sharing a lifecycle | **Pattern fix:** lift the per-type processing into a per-type config object + shared concrete ProgressPage class (1 iteration, high risk, N-instance failure cost). **Instance fix:** pick the worst-Locality instance (likely `ImportInvoiceDetailsProgressPage` at 786 LOC due to its invoice-group semantics opt-out), extract the most weakly-connected slice, leave the rest (1 iteration, low risk, returns next cycle). **Default: instance fix.** |

**LOC numbers in the example above are illustrative, drawn from a snapshot at prompt-draft time. Refresh them at prompt-finalization** against the codebase as it stands at cleanup Cycle 3's squash close — the cohort's ordering and the worst-instance pick may shift. The naming-asymmetry note (`ImportProgressPage` = contacts-progress) is stable regardless of LOC drift unless a future cleanup-track session renames the file.

**Skip if matches Deliberately Kept register.**

---

## Workstream W-R3 — Directness

The code path between intent and execution should not route through indirection that adds nothing.

**Detection patterns:**

- Abstractions with exactly one caller. **Trap:** the second caller may be coming. Include an *expected second caller* in the output row if any. If the agent cannot name one, the abstraction is unjustified.
- Wrappers whose body is `return $this->inner($sameArgs)` (no semantic weight added).
- Intermediate variables assigned once and read once on the next line.
- Chains of delegators where each layer adds zero semantic weight.
- Option flags called with only one value across the entire call-site list.

**The single-caller trap is the most common false-positive vector.** A `FieldMapper` service for a single namespaced importer type may look like a single-caller abstraction; it isn't — the Deliberately Kept register documents this. Consult the register before landing single-caller findings.

**Output format:** standard four columns. Single-caller findings include an *Expected second caller* note in the *Current shape* column where applicable.

---

## Workstream W-R4 — Necessary vs. accidental work

Work the domain requires vs. work the current shape imposes.

**Diagnostic question for each candidate:** *would this work exist if the system around it were shaped right?* If yes, necessary. If no, the work is a symptom and the surrounding shape is the cause — the finding points at the cause, not the symptom.

**Detection patterns:**

- Values re-derived in function bodies that are already on `$this->` or in scope.
- Re-fetches / re-serializations / re-sorts of data already in the right form upstream.
- Defensive copies of immutable data.
- Conversions between formats that exist because two adjacent layers disagree on representation. **The finding is on the disagreement, not on either layer's conversion.**

**Common false-positive vector for this workstream:** treating "the code converts X to Y" as the finding. The finding is "two layers disagree on the representation"; the conversion is the symptom. Always trace upstream to the disagreement.

---

## Workstream W-R5 — Seam quality

Where the boundaries between modules are drawn. **Distinct from the cleanup track's integration-seam sweep** — that catches *correctness* at seams (silent winners, disagreeing defaults). W-R5 catches whether seams are in the right *place at all*.

**Detection patterns:**

- Class pairs where >50% of one's public API exists to feed the other (the boundary is between them, but the dependency runs through them — the seam is misplaced).
- Pipeline-shaped work expressed as method-on-class instead of stage-on-stream.
- Push interfaces where pull would compose better, or vice versa.
- Observer boundaries firing on the wrong granularity.

**Output format adds a fifth column:** *What breaks if we move it.* Anything the agent cannot fill that column for is **not a finding** — it stays in a "candidate" sub-section that the user reviews at the 329 walkthrough but that doesn't enter the apply backlog. A W-R5 finding without breakage analysis is just an opinion.

**This is the hardest workstream to operationalize and the most exposed to intent-unrecoverable findings.** Many seam decisions are old enough that the original design call doesn't have a recoverable source. Expect a higher proportion of intent-unrecoverable findings here than in the other workstreams; these inform the next cycle's documentation work.

---

## Intent-unrecoverable findings (separate section in log)

Findings where the agent could not establish recoverable intent get their own section in the audit log, separate from the workstream tables. Format per entry:

```
### [Code location, e.g. file:lines]
- **Surface pattern:** [what would have triggered a refactor finding]
- **What's missing:** [no test, no docstring, no commit message, no session log, no memory entry — list what was searched]
- **Why this matters:** [the cost of refactoring without recoverable intent — likely to break something nobody can articulate]
- **Suggested next step:** [usually "document the intent before refactoring"; sometimes "ask the user during 329 walkthrough"]
```

These feed the **next cycle's W-R5 walk** as documentation-gap pre-loaded findings. They are not in scope for 329's apply work.

---

## Open Refactor Flags block

Captured incrementally as findings exceed iteration-scope. Format per flag:

```
### Flag W-Rx/Y — [name]
- **What was found:** [the finding's substance]
- **Risk + scope:** [why it's bigger than a single 329 iteration]
- **Proposed paths:** [the dispatch options the apply session walks]
```

The flag letter increments alphabetically within the workstream (Flag W-R1/A, Flag W-R1/B, …) so apply-session walks can address them in order.

---

## Testing

- **No new tests this session.** Audit produces findings, not behavior changes.
- **Existing suite confirmation:** run `docker compose exec app php artisan test --exclude-group=slow` once at session start to confirm green baseline. Note the count for the 329 prompt. No suite runs expected during the audit itself.
- **Baseline carry-in:** confirm baseline from cleanup Cycle 3's squash close; that's what 329 will measure against.

---

## Security checklist

Before closing:

- [ ] No code changes landed (this is an audit, not an apply — any changes would violate the track rule)
- [ ] `sessions/tracks/code-shape-and-fit-finding-template.md` created and complete
- [ ] Existing Deliberately Kept register at `sessions/tracks/code-shape-and-fit-deliberately-kept.md` read and validated
- [ ] Proposed register additions captured in audit log (not edited into register directly)
- [ ] Proposed register retirements captured in audit log with staleness rationale (not edited into register directly)
- [ ] W-R1 backlog populated with file:line citations + Intent citations for every finding
- [ ] W-R2 backlog populated; class-finding rows used where N-instances-same-shape applies; LOC numbers in any example rows refreshed against the codebase at cleanup Cycle 3's squash close
- [ ] W-R3 backlog populated; single-caller findings include expected-second-caller analysis where applicable
- [ ] W-R4 backlog populated; findings cite the disagreement, not the symptom
- [ ] W-R5 backlog populated; every finding includes the *What breaks if we move it* column or sits in the candidate sub-section
- [ ] Intent-unrecoverable findings landed in their own section, not mixed with workstream rows
- [ ] Open Refactor Flags captured for over-scope findings, with risk/scope/proposed paths
- [ ] Deliberately Kept register consulted before each workstream walk; matched patterns skipped; stale entries flagged for retirement walkthrough

---

## Out of scope

- **Apply phase** — backlog walkthrough, ratification, iteration-sliced refactors. **Deferred to session 329.**
- **New features of any kind**
- **Bug fixes** — surface as Open Refactor Flags, do not fix during audit
- **Cleanup-track work** — convention drift, dead code, permission gates, doc coverage. Surface as suggestions for the next cleanup cycle's pre-loaded-findings block; do not action here.
- **Migration changes / schema work** — fully out of scope; this track does not touch the DB
- **Performance optimization** — only if it surfaces as a W-R1 (Cardinality) or W-R4 (Necessary vs. accidental work) finding by way of the lens; don't go looking for perf wins as a primary goal

---

## Production deployment notes

- **No deploy expected.** Audit produces no code changes.
- The new artifact (`sessions/tracks/code-shape-and-fit-finding-template.md`) is a doc file under `sessions/tracks/`. It ships with the session log commit. The Deliberately Kept register is not edited during this session (validate-and-extend; ratification happens at 329).

---

## Closing steps

Follow the close gate in the base prompt. Session-specific details:

- **Log file:** `sessions/328. Code Shape & Fit (Audit) — Cycle 1 — Log.md`. Include:
  - Per-workstream finding count + summary
  - Intent-unrecoverable findings section (separate)
  - Open Refactor Flags with full context for 329
  - **Proposed Deliberately Kept register additions** surfaced during audit (with citations)
  - **Proposed Deliberately Kept register retirements** flagged during register validation (with staleness rationale)
  - Class-finding rows clearly marked
  - W-R5 candidate sub-section (findings missing breakage analysis)
- **Branch:** `session-328/N` (final iteration). Commits: the finding-template artifact + the audit log. No code changes. **No edits to the Deliberately Kept register file** — register changes are 329 walkthrough work.
- **Draft session 329 documents at close.** Copy `sessions/template-base-prompt.md` to `sessions/329. base-prompt.md`; write `sessions/329. Code Shape & Fit (Apply) — Cycle 1.md` per the 329 draft (separate file). The 329 prompt's *Open Refactor Flags* + *W-R1 through W-R5 backlog* sections carry forward from this session's log; *proposed register additions/retirements* land in 329's walkthrough block.
- **Update the track doc.** `sessions/tracks/code-shape-and-fit.md` § *Cycle Retrospectives* gets a stub entry for Cycle 1 (window covered, sessions list pointing at 328/329). The full retrospective lands at 329's close, not here.
- **Stub closeout:** `sessions/session-outlines.md` § "Code Shape & Fit — 2-session cycle" — mark the 328 child stub as ✅ closed; preserve 329 stub untouched.
