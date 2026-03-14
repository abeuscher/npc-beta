---
name: Session close routine
description: Standard steps to perform at the end of every coding session
type: feedback
---

At the end of every session, after the user confirms testing is complete, always perform these steps in order:

1. Write the session log file (`sessions/session-0XX-log.md`) summarising what was built, files changed, and any decisions made
2. Create a branch named `session-0XX`
3. Stage and commit all session changes with a clear commit message
4. Push the branch to origin

**Why:** User wants a clean git history with one branch per session and the log captured in the commit.

**How to apply:** Do not wait to be asked. When the user says the session is done or testing is good, run through this routine automatically.
