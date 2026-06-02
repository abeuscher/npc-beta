---
description: Run the session close gate, with the log written strictly from its template
disable-model-invocation: true
---

Close the session. This is the explicit close signal — run the close gate now.

Follow the Session Close Gate in `sessions/template-base-prompt.md`, both phases,
exactly as written:
- Phase 1 — draft the next session's base prompt and session prompt, digest the
  housekeeping capture buffer, then stop for review.
- Phase 2 — write the log, bump VERSION, update completed-sessions and outlines,
  archive the previous session, fold the capture buffer, commit on the session branch
  (do not push). Only once I say to proceed to it.

For the log file specifically: write `sessions/NNN. Session Title — Log.md` from
`sessions/template-session-log.md`. Read that template and copy its structure exactly —
do not base the log on a previous session's log. Drift creeps in when the format gets
reconstructed from the last log instead of the template.

The rest of the close gate is working. This command exists to keep the log honest to
its template.
