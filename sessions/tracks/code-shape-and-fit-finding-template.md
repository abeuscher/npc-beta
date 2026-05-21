# Code Shape & Fit — Finding template

Reference doc for agents running an audit session on the Code Shape & Fit track. The track doc at [`code-shape-and-fit.md`](code-shape-and-fit.md) is canonical for workstream definitions and discipline. This doc covers row format, intent-citation rules, class-finding rows, and intent-unrecoverable findings, with one worked example per workstream.

First-cycle deliverable per the track doc § "Permanent inter-cycle artifacts." Lands with the session 312 close commit and is consulted by every subsequent Code Shape & Fit audit.

---

## Row format

Every finding produces one row in its workstream's backlog table. Four columns for W-R1 through W-R4; W-R5 carries a fifth.

| Column | What it carries |
|--------|-----------------|
| **Intent (with citation)** | What the code is trying to do, in one phrase, followed by a recoverable citation (test name, docstring quote, commit message reference, session-log line, memory entry slug). "I think it's trying to..." is not Intent — see the intent-unrecoverable section. |
| **Current shape** | The structural shape today, with file:line citations. Specific enough that a reader can land in the right file and see what the row describes. |
| **Deviation** | The specific way the current shape diverges from the simplest expression of intent. Phrased in the workstream's vocabulary (multiplication / smearing / indirection / accidental work / misplaced seam). |
| **Proposed move** | The concrete change. Includes LOC delta, file count delta, and risk class. For class-findings, names both dispatch options (pattern fix / instance fix). |
| **What breaks if we move it** *(W-R5 only)* | The breakage analysis. Anything where this column can't be filled in is a candidate, not a finding — see the W-R5 candidate sub-section rule below. |

### Citation forms (valid Intent sources)

- Test name + assertion (`Test\Feature\…::it_does_X`).
- Quoted docstring or top-of-file comment (verbatim, in quotes).
- Commit message reference (`commit abc1234 — "lift X to Y"`).
- Session-log reference (`sessions/259. Importer Auto-Mapping Pattern Lift — Log.md` § decisions).
- Memory entry slug from `MEMORY.md` (`feedback_uniform_ux_over_per_type_plumbing`).

### Citation forms (invalid — file as intent-unrecoverable)

- "I think it's trying to…" / "looks like…" / "presumably…"
- Inferred intent from variable names alone with no other supporting source.
- Cargo-culted from a Google-able pattern ("this is the Strategy pattern, intent is to…") without a citation establishing the codebase's specific reason for that shape.

---

## Worked examples (one per workstream)

These are illustrative starting points. Real cycle-1 findings refine these.

### W-R1 (Cardinality) — example

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Compute donor cohort timestamps once per report build (cited from `tests/Feature/Reports/CohortBuilderTest::it_does_not_re_derive_period_start`) | `CohortBuilder` derives `$cohort->meta['period_start']` at `app/Services/Reports/CohortBuilder.php:84` (build phase) and again at `app/Services/Reports/CohortBuilder.php:141` (render phase) from raw membership rows | Derivation runs twice in a single request lifecycle; upstream caller already holds the value | Pass derived value through render phase; delete second derivation. ~−8 LOC, 1 file, low risk. |

### W-R2 (Locality) — class-finding example

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Namespaced importer ProgressPage per entity (cited from `sessions/archived/202. … — Log.md` § decisions) | N Filament `Import{Type}ProgressPage` classes at 400–800 LOC, sharing the per-row processing template-method pattern via `InteractsWithImportProgress` | Per-type processing logic may be locally related to page-shell concerns OR weakly-connected concerns sharing a lifecycle — needs walking to determine | **Pattern fix:** lift per-type processing into a per-type config object + shared concrete ProgressPage class (1 iteration, high risk, N-instance failure cost). **Instance fix:** pick the worst-Locality instance, extract the most weakly-connected slice, leave the rest (1 iteration, low risk, returns next cycle). **Default: instance fix.** |

### W-R3 (Directness) — example

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Resolve active tenant's storage disk (cited from `app/Services/TenantContext.php` docstring "Returns the active tenant's storage disk name. Single source of truth.") | `TenantContext::resolveStorageDisk()` calls `$this->getStorageDiskName()` which returns `$this->storage_disk` | Single-caller getter with no semantic weight; the property is already public-readable | Inline `$this->storage_disk` at the call site; delete `getStorageDiskName()`. **Expected second caller:** none. ~−4 LOC, 1 file, low risk. |

### W-R4 (Necessary vs. accidental work) — example

| Intent | Current shape | Deviation | Proposed move |
|--------|---------------|-----------|---------------|
| Importer commit receives one CSV row, writes one entity (cited from `app/Services/Import/Concerns/InteractsWithImportProgress::processOneRow` docstring) | `processOneRow` re-derives `$entityTypeKey` via `static::importModelType()->value` despite `$this->modelType` already on the page instance | Value re-derived inside method body that the caller already holds | Use `$this->modelType->value` directly; drop the static dispatch. ~−2 LOC per importer × 7 importers, 7 files, low risk. The finding points at the symptom; root-cause finding is class-finding "static `importModelType()` dispatch is dead code post-namespaced-importer lift." |

### W-R5 (Seam quality) — example (note the fifth column)

| Intent | Current shape | Deviation | Proposed move | What breaks if we move it |
|--------|---------------|-----------|---------------|---------------------------|
| Page-builder Vue store owns editor state, controller owns persistence (cited from `sessions/273. … — Log.md` § /6 editor.ts composable extraction) | `PageBuilderApiController` exposes 11 endpoints; >50% are thin pass-through against `App\Services\PageBuilder\…` services that the Vue store could call via a single bulk endpoint | The boundary between controller and Vue store is drawn at "one endpoint per editor action"; the actual seam is "one bulk operation per editor save" | Collapse 11 single-action endpoints into one `POST /api/page-builder/save` taking the full edit transcript; service layer fans out. ~−250 LOC, ~6 files, **high risk.** | Per-action API consumers outside the editor (if any) — verify before lifting. The page-builder Playwright spec exercises the surface end-to-end and would need rework. |

---

## Class-finding rows

When a workstream surfaces **N instances of the same structural shape**, collapse to a single row, not N rows. The *Proposed move* column carries both dispatch options:

- **Pattern fix.** Single iteration lifts the shared shape across all instances. More efficient; higher per-iteration risk; only viable when the pattern is genuinely identical and a single shared abstraction lifts cleanly.
- **Instance fix.** Pick the worst instance, land that iteration, leave the rest. More conservative; preserves iteration budget; the default when instances have meaningfully diverged or the lift isn't clearly clean.

The apply session picks based on iteration budget and risk tolerance. **Default leaning is instance-fix unless the user explicitly opts into pattern-fix** — the anti-performative-change discipline is uncomfortable with "let's refactor seven things at once," and the cost of a failed pattern fix is N times higher than the cost of a failed instance fix.

The W-R2 worked example above is the canonical class-finding shape.

---

## Intent-unrecoverable findings

Distinct from workstream findings. File when:

- The surface pattern would have triggered a refactor finding (matches W-R1 / W-R2 / W-R3 / W-R4 / W-R5 detection patterns).
- AND no recoverable Intent citation exists. Tests have been searched, docstrings read, commit messages walked, session logs grepped, memory entries checked — nothing supports an Intent claim.

These do **not** land on the workstream backlog. They land in a dedicated *Intent-unrecoverable* section of the audit log.

### Row format

| Column | What it carries |
|--------|-----------------|
| **Code location** | file:line. |
| **Surface pattern** | What would have triggered a refactor finding (which workstream it would have landed in). |
| **What's missing** | List what was searched (no test, no docstring, no commit message, no session log, no memory entry). |
| **Why this matters** | The cost of refactoring without recoverable intent — likely to break something nobody can articulate. |
| **Suggested next step** | Usually "document the intent before refactoring"; sometimes "ask the user during apply-session walkthrough." |

### Example

| Code location | Surface pattern | What's missing | Why this matters | Suggested next step |
|---------------|-----------------|----------------|------------------|---------------------|
| `app/Services/Foo/LegacyBarShim.php:1-280` | Looks like a W-R3 (Directness) finding — a 280-LOC shim with one caller. | No tests reference `LegacyBarShim`; no docstring on the class; the introducing commit `abc1234` says only "add LegacyBarShim"; no session log references it; no memory entry. | This file was the only mention of "Bar" anywhere in the codebase. Removing it might be safe or might break a production-only code path that no test covers. | Document the intent before refactoring — ask the user during 313 walkthrough whether `LegacyBarShim` is load-bearing for any deploy-time concern. |

### Why these matter

Intent-unrecoverable findings inform the **next cycle's W-R5 (Seam quality) walk** — they surface boundaries that need documenting before any refactor lands. They are not in scope for the current cycle's apply session.

Expect a higher proportion of intent-unrecoverable findings in W-R5 than in the other workstreams (per the track doc) — many seam decisions are old enough that the original design call doesn't have a recoverable source.

---

## Deliberately Kept register interaction

Before landing a finding, consult [`refactor-track-deliberately-kept.md`](../refactor-track-deliberately-kept.md). If the surface pattern matches a registered carve-out:

- **Skip the finding.** Don't land it on the workstream backlog and don't relitigate the carve-out at apply walkthrough.
- **If the carve-out's citation looks stale** (the architectural reason no longer holds, the cited session's decision has been superseded, the code the carve-out protects has materially changed), surface the entry as a *Proposed retirement* in the audit log. The retirement decision belongs to the apply walkthrough, not the audit.

New carve-outs surfaced during the audit get proposed for register addition in the audit log; the addition itself happens at apply walkthrough, not unilaterally during the audit.

---

## Open Refactor Flags

When a finding's *Proposed move* is too big for a single apply-session iteration (e.g. "restructure the importer subsystem around polymorphism instead of `match()`"), it becomes an Open Refactor Flag instead of a workstream-table row.

### Row format

```
### Flag W-Rx/Y — [name]
- **What was found:** [the finding's substance]
- **Risk + scope:** [why it's bigger than a single iteration]
- **Proposed paths:** [the dispatch options the apply session walks]
```

The flag letter increments alphabetically within the workstream (`Flag W-R1/A`, `Flag W-R1/B`, …) so apply-session walks can address them in order.

---

## W-R5 candidate sub-section

Special discipline for W-R5: a finding without breakage analysis ("what breaks if we move it") is **not a finding** — it's a candidate. Candidates land in a `W-R5 candidates (no breakage analysis)` sub-section of the audit log. They do not enter the apply-session backlog. The user reviews candidates at apply walkthrough and either ratifies (the agent finishes the breakage analysis) or drops.

A W-R5 finding without breakage analysis is just an opinion.
