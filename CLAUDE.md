## Git Workflow

- Before writing any code, create a new branch: `git checkout -b session-###`
- One commit per branch at session close — stage all changed files and commit together
- If a patch mid-session requires a separate branch, use the pattern: `session-###-patch-###`
- Never push or merge — the user handles both
