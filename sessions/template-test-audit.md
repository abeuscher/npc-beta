# XXX. Test Audit (Mutation Testing Pass)

Run mutation testing against the existing Pest suite using Infection to get an objective signal on which tests carry weight, then apply deletions and consolidations based on the report. Document the workflow so this becomes a repeatable forcing function rather than a one-off.

---

## Design decisions (resolved before starting)

- Session 094 was a subjective walk-through and surfaced very little. That's the expected outcome — an LLM auditing LLM-generated tests shares the same blind spots as the tests themselves. We need an external signal, not a re-read.
- Infection provides that signal. It mutates production code (flips operators, alters return values, inverts conditionals) and reports which mutants the suite catches. Tests catching **zero** mutants are not actually testing anything. Tests catching only mutants that several other tests also catch are consolidation candidates.
- This is a delete-leaning session. Every deletion needs a one-line rationale tied to mutation evidence — not vibes, not "this looks redundant."
- Run Infection against a bounded slice first, not the whole suite. It's slow. The goal is a workflow that's repeatable, not exhaustive coverage in one pass. Avoid importer-heavy modules for the first slice — they'll skew results and make the report harder to read.

---

## Phase 1 — Install and configure Infection

Add Infection as a dev dependency. Configure it for the project's source tree. Make sure it emits the **XML report** alongside the HTML one — the HTML is for humans, the XML is what we'll analyse. Pick the initial slice (one cohesive module). Confirm a baseline run completes and produces a usable report.

---

## Phase 2 — Analyse the report

From the XML, build a list keyed by test file:

1. **Dead** — catches zero mutants. Deletion candidate.
2. **Redundant** — only catches mutants that ≥N other tests already catch. Consolidation candidate.
3. **Load-bearing** — catches mutants nothing else catches. Keep, no question.
4. **Surviving mutants** — code paths nothing in the suite catches. Note these but do not write new tests here. That's a separate session.

Surface the list before making any changes so we can review the calls together.

---

## Phase 3 — Apply deletions and consolidations

For each approved change, make the edit and re-run the relevant Pest group (fast group should stay green throughout). If a test feels load-bearing despite mutation evidence saying otherwise — e.g. it guards a regression we actually hit — keep it and note the override. The report is a strong signal, not a verdict.

---

## Phase 4 — Document the workflow

Short doc (`docs/testing/mutation-audits.md` or wherever fits). Cover:

- How to run Infection against a slice.
- What the report fields mean.
- The keep/consolidate/delete decision rule.
- Suggested cadence — e.g. one slice per month, or after any session that adds substantial test code.

Point of this doc: the next mutation pass, in a fresh context, shouldn't have to rediscover the workflow.

---

## Out of scope

- Writing new tests for surviving mutants.
- Running Infection across the entire suite in one go.
- Wiring Infection into CI.
- Revisiting the fast/slow Pest split from 094.

---

## Closing steps

Follow the close gate in the base prompt.

- **Log file**: `sessions/XXX. Test Audit (Mutation Testing) — Log.md`
- **Branch**: `session-XXX`