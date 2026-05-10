# Template: Session Prompt

Copy this file to `NNN. Session Title.md`. Replace the title and placeholder sections with the session's actual content. The base-prompt rules (process, close gate, style) apply to every session and do not need to be repeated here.

Two shapes for this template:

- **Plan-backed session** (the common case). The session executes an entry in `sessions/release-plan.md`. That entry is canonical for scope, success criterion, prerequisites, and artifact. The session prompt is a **delta** — anything specific to this session that the plan entry doesn't already say. Most sections below can shrink or be omitted.
- **Stub-driven or emergent session.** No release-plan entry — driven by a `session-outlines.md` stub, a track-doc step, or an emergent forcing function (carve-out, unblocker, maintenance pass, design conversation). Use the fuller shape: explicit phases, design decisions, out-of-scope.

Pick the shape that fits. The headings below cover both; omit any section that doesn't apply.

---

# NNN. Session Title

One-sentence description of what this session builds and why.

---

## Plan reference

This session executes `sessions/release-plan.md` § XX. Read that entry first; it is canonical for scope, success criterion, prerequisites, and artifact.

*(For non-plan sessions, replace with a "Stub reference" pointer to the relevant `sessions/session-outlines.md` entry or track-doc step, or a short description of the emergent forcing function.)*

---

## Session-specific deltas

Anything the plan entry doesn't cover:

- Constraints surfaced since the plan was written.
- In-session-only design choices the plan defers to session time.
- References to fresh context (other sessions, recent findings, related stubs).

*(Omit this section if there are no deltas — the plan entry stands alone.)*

---

## Open questions to resolve at session start

The small set of decisions that need to be made before implementation. Not exhaustive design-decisions-resolved bookkeeping — only what genuinely needs settling.

*(Omit if the plan entry resolves everything.)*

---

## Phases *(stub-driven sessions only)*

For sessions without a plan entry, describe what is built in each phase. For plan-backed sessions, the plan entry's success criterion and artifact carry this — omit.

### Phase 1 — ...

Describe what is built in this phase.

### Phase 2 — ...

Describe what is built in this phase.

---

## Out of scope

Only when the plan entry doesn't already say. Most plan entries carry their own out-of-scope statement; don't duplicate.

---

## Testing

- **Slow test groups to run this session:** none (or list specific groups)
- **New tests expected:** yes / no — if yes, describe scope briefly

---

## Closing steps

Follow the close gate in the base prompt. Session-specific details:

- **Log file:** `sessions/NNN. Session Title — Log.md`
- **Branch:** `session-NNN/N` (final iteration)
