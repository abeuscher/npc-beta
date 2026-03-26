## Session Pacing

- Never advance to the next phase or step without an explicit user instruction. After completing any milestone, stop and wait.
- Never suggest, ask about, or initiate session close. The user initiates close explicitly. Wait in silence until told.

## Git Workflow

- Before writing any code, create a new branch: `git checkout -b session-###`
- If a patch mid-session requires a separate branch, use the pattern: `session-###-patch-###`
- **Session close always goes on a new patch branch** — create `session-###-patch-NNN` (next increment) for the close commit, even if it contains only the log and outlines. Never commit the close onto an existing branch or go back to the session branch.
- One commit per branch — stage all changed files and commit together
- Never push or merge — the user handles both
