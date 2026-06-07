---
description: Run the session close gate, with the log written strictly from its template
disable-model-invocation: true
---

Close the session. This is the explicit close signal — run the close gate to completion now, in one pass.

Drafting and reviewing the next session's prompt(s) happens in conversation *before* this command is invoked — often a session or several are planned ahead. By the time I run `/close-session`, those prompts are already written and approved. So treat them as a **precondition to verify, not a step to redo**: do not re-pause to ask whether they're okay. My invocation of this command is the only gate; once it runs, the close completes without a second confirmation.

Run the close gate in `sessions/template-base-prompt.md` like this:

1. **Verify the next session's prompts exist.** Confirm `sessions/(NNN+1). base-prompt.md` and `sessions/(NNN+1). <Title>.md` are on disk (a session may have queued several ahead — verify at least the immediate next one). **Missing prompts are the only condition that interrupts the close** — if they're absent, stop and say so. If they're present, continue straight through; do not ask for approval of them.
2. **Digest the housekeeping capture buffer** (`sessions/housekeeping-incoming.md`): validity-check each item against current code, then fold the survivors into `sessions/housekeeping-inbox.md` as part of the close steps below. Flag any routing or drop/keep judgment inline rather than pausing for it.
3. **Run the close steps in one pass** (Phase 2 of the gate): write the log, bump `VERSION`, update `completed-sessions.md` + `session-outlines.md` + the track doc (and phase-expiry compression if this closes a phase), archive the previous session's files, fold the capture buffer + clear the incoming buffer, then commit on the session branch. **Never push, never merge to main.**

For the log file specifically: write `sessions/NNN. Session Title — Log.md` from `sessions/template-session-log.md`. Read that template and copy its structure exactly — do not base the log on a previous session's log. Drift creeps in when the format gets reconstructed from the last log instead of the template.
