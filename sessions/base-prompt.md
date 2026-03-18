We are about to begin a new session: **[TITLE]**.

Please open `sessions/session-outlines.md` and find the section for this session. Read the stub there, then summarize what you understand the session to involve and ask any clarifying questions before we proceed.

Once we have discussed and you have a clear picture of what needs to be built, write a complete session prompt in your response — covering goals, phases, data model changes if any, and anything else needed to execute. We will review it together.

When I confirm the prompt is correct, proceed to execution.

---

## Process rules for every session

- **Before writing any code**, read every file you intend to modify.
- **Pause and ask** any time a decision point arises that is not covered by the agreed prompt.
- **If any external service is unavailable**, stop and ask — do not attempt to troubleshoot.
- **Run migrations via Docker** after writing them: `docker compose exec app php artisan migrate` (or `migrate:fresh --seed` when appropriate). Do not pause to ask first.
- **If the PostgreSQL container becomes unresponsive** (500/exec errors), ask the user to restart it rather than retrying.
- **When implementation is complete**, run `php artisan test` and fix any failures before proceeding.
- **Pause for manual testing.** Do not proceed past this point without explicit instruction.
- **Ask explicitly whether to close the session** before writing the log or committing.
- **Session log**: write a log file at `sessions/[NNN]. [Title] — Log.md` summarising what was built, what changed, and any deferred decisions.
- **Update session-outlines.md**: after writing the log, move this session's title into the Completed Sessions table and review upcoming stubs — update them if this session's work affects them.
- **Commit**: stage all changed files (including the log and updated outlines), commit to a feature branch named `session-[nnn]`, and notify the user. Do not push — the user will push and merge when ready.
- **Do not begin the next session** until the user explicitly starts it.

---

## Style rules

- Follow existing code conventions exactly.
- Do not add features, refactor, or improve anything beyond what the session specifies.
- Prefer editing existing files to creating new ones.
- No docstrings, comments, or type annotations on code you did not write.
- Simple, correct, well-considered solutions over fast or clever ones.
- When adding a new admin page or resource, create a help doc stub at `resources/docs/[handle].md` following the frontmatter convention of existing docs, and register the route(s) in the `routes:` array.
