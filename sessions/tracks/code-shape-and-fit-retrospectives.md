# Code Shape & Fit — Cycle Retrospectives

Compressed history of closed cycles for the Code Shape & Fit track: sessions, outcomes, load-bearing decisions, blind spots, process incidents, and carry-forwards. Split out from the track doc so the main doc stays focused on forward operation; this companion is read alongside it, not instead of it.

The track doc at [`code-shape-and-fit.md`](code-shape-and-fit.md) is canonical for cycle shape, cadence, the workstream definitions, and the standing improvements. **Read this retrospectives doc before planning a new cycle** — the carry-forwards and blind spots here shape what the next cycle pre-loads. Per-session detail stays in `sessions/(archived/)NNN. … — Log.md` files.

---

## Cycle 1 — sessions 312 + 313 (closed 2026-05-21)

**Window covered:** sessions 274 → 311 (~37 sessions of growth since cleanup Cycle 2 closed). Pulled forward at user direction from the originally scheduled trailing-pair-after-cleanup-Cycle-3 slot (≈ session 327+); calibration delta documented in 312's audit log.

**Sessions:** 312 (audit, no code), 313 (apply, two iterations + close).

**Quantitative outcomes:**

| Workstream | Inline findings | Open flags | Intent-unrecoverable | Landed at apply |
|------------|-----------------|------------|----------------------|-----------------|
| W-R1 Cardinality | 1 | 1 | 2 | 1 inline + 1 flag (pattern fix) |
| W-R2 Locality | 0 | 1 | 0 | 0 (flag promoted to register) |
| W-R3 Directness | 0 | 0 | 0 | 0 |
| W-R4 Necessary vs accidental | 1 | 1 | 0 | 1 inline (instance fix; pattern fix deferred) |
| W-R5 Seam quality | 0 | 0 | 0 | 0 |

Code changes at apply: 2 iterations on `session-313/1`. Net effect: `-112 / +32` controller LOC, +127 LOC new service, byte-equivalent JSON response shapes. Fast Pest baseline carried at 2586 passed across both iterations; Playwright page-builder set 28 passed / 4 skipped at the seam-move iteration's gate.

**Load-bearing decisions:**

1. **Pattern fix elected over instance fix on Flag W-R1/A** (the controller-returns-triple-key-superset finding). Default leaning at 312 was instance-fix; user opted into the pattern lift at 313 walkthrough. Result: `PageTreeBuilder` service exposing `tree()` / `items()` / `requiredLibs()` as separately memoized computed methods. Each of the seven callers asks for what it needs; the two layout endpoints now skip the legacy widgets array entirely.
2. **Standing won't-fix on `ImportContactsPage` LOC promoted** from the floating "Flag B" status to a Deliberately Kept register entry. Originally cleanup Cycle 1 (session 206), re-affirmed at cleanup Cycle 2 (273), re-affirmed under the Locality lens at 312, ratified for register at 313. Retires the rolling won't-fix; future audits skip it.
3. **Per-action endpoint shape on `PageBuilderApiController` landed as a register entry** (the 22-endpoint REST surface). Walked as a W-R5 candidate at 312; rejected as a finding because the per-action shape supports optimistic UI / retry / revert and aligns with the rest of the admin surface. The register entry pre-empts future audits relitigating.
4. **Pattern-fix on Flag W-R4/A deferred.** The duplicated context-gathering in three description-generator methods on `ImportSessionActions` was identified at 312 as the same shape as the W-R4-1 instance. User accepted the default — ship the instance fix, leave the description-method consolidation for a future cycle.
5. **Both intent-unrecoverable findings carried forward.** User did not recall original intent for either the `WidgetType::requiredForPage()` re-derivation across `destroy()` + `buildTree()` or the dual-loop `collectLibs()` accumulator. Both stay structurally preserved inside the new `PageTreeBuilder` service; both carry into the next cycle's documentation-gap walk.

**Blind spots:**

- **Citation-discipline failure mode is real.** Multiple W-R1 / W-R4 walking agents at 312 produced findings with docstring "citations" that didn't match file content. Spot-checks caught the inventions and either dropped findings or salvaged them with corrected citations; one reclassified to intent-unrecoverable on closer inspection. Cycle 2 audit-agent prompt scaffolding should require quoting citations verbatim with line numbers of the quoted text.
- **No cleanup Cycle 3 outputs to draw from.** The pulled-forward decision meant the W-R2 Locality walk consumed stale cleanup Cycle 2 outputs instead of fresh Cycle 3 ones. Cycle 1's ratio of stale-touched findings was below the 30% calibration threshold (1 of 2 inline findings touched importer code that may move in cleanup Cycle 3), so the pull-forward was defensible — but the default ordering is still trailing-pair-after-cleanup.

**Process incidents:**

- **Path mismatch in the 313 prompt.** The session prompt referenced a placeholder path `sessions/tracks/code-shape-and-fit-deliberately-kept.md`; the actual file is at `sessions/refactor-track-deliberately-kept.md`. Noted in the 312 log's drift section; not chased.
- **Mid-session re-coaching on PM-level tone.** User flagged audit-track bookkeeping codes ("W-R2/A promotion", "FieldMapper carve-out") as impenetrable in user-facing prose during the close walkthrough. Memory entry `feedback_pm_level_tone` updated to call out project-internal jargon explicitly. Status reports from this session forward should keep the codes in the log and the commit messages, not in user-facing summaries.
- **Session-end handoff steps missed initially.** Agent jumped from final-iteration commit straight into close-out artifact writing, skipping both the smoke-test handoff and the next-session prompt draft. User flagged it; agent reverted the premature edits and resumed correctly. Memory entry `feedback_session_end_steps` written. Template-base-prompt edits (Open Gate naming, removal of the "templates stable as of 276" carveout) landed in the same session to structurally prevent recurrence.

**Won't-fixes reaffirmed (and now promoted to register):**

- `ImportContactsPage` long file → register entry.
- `PageBuilderApiController` per-action endpoint shape → register entry.
- Per-namespace mapper helpers (the pre-seeded entry) — citation accuracy fix landed; the missing Organizations helper filed in `sessions/housekeeping-inbox.md` for a future low-risk batch.

**Carry-forwards:**

- Both intent-unrecoverable findings (the two `PageBuilderApiController` cardinality patterns) carry into the next cycle's documentation-gap walk.
- Flag W-R4/A pattern fix (the three description-generator methods on `ImportSessionActions`) deferred to the next cycle's pre-loaded findings.
- Citation-discipline calibration (quote-verbatim-with-line-numbers rule) folds into the Cycle 2 audit-agent scaffolding.
- **A004 review session** queued out-of-band to evaluate Cycle 1 against stated goals before Cycle 2 plans land.
