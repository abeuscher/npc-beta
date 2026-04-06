## Session Pacing

- Never advance to the next phase or step without an explicit user instruction. After completing any milestone, stop and wait.
- Never suggest, ask about, or initiate session close. The user initiates close explicitly. Wait in silence until told.

## Git Workflow

Branches use the convention `session-NNN/N` where NNN is the session number and N is the iteration (1, 2, 3, …). Each iteration is a self-contained set of changes that can be merged to main independently.

- **Starting a session:** `git checkout main && git checkout -b session-NNN/1`
- **Committing:** Commit whenever a set of changes is ready for the user to deploy and test. One or more commits per branch is fine — whatever makes sense for the changeset.
- **After commit:** Stop and notify the user. The user merges to main, pushes, deploys, and tests.
- **Next iteration:** After the user merges and reports back, start from main: `git checkout main && git pull && git checkout -b session-NNN/2`. Repeat as needed.
- **Session close documents** (log, outlines update, next-session prompt) go on the final iteration branch for the session.
- **Never push or merge** — the user handles both.
- **Never commit directly to main.** All commits go on session branches.
- **Only branch from main.** Never create a branch off another branch.
