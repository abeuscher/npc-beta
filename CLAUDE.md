## Session Pacing

- Never advance to the next phase or step without an explicit user instruction. After completing any milestone, stop and wait.
- Never suggest, ask about, or initiate session close. The user initiates close explicitly. Wait in silence until told.

## Git Workflow

- Before writing any code, create a new branch: `git checkout -b session-###`
- All session work — code, session log, outlines update, next-session prompt — is committed on the session branch. One commit at close; stage all changed files and commit together.
- **Patch branches** (`session-###-patch-NNN`) are only used when a mid-session deployment is needed (e.g. testing requires the production server). Cut the patch branch from the session branch, commit the deployable changes, and return to the session branch to continue work.
- Never push or merge — the user handles both.
