We are about to begin Session [NNN] — [Title].

The outline is in `sessions/[NNN]. [Title].md`. Please read it, summarize the work back to me in a couple of sentences to confirm we are aligned, then write the full session prompt. We will review it together before execution begins.

---

## Process rules for every session

- **Before writing any code**, read every file you intend to modify.
- **Pause and ask** any time a decision point arises that is not covered by the outline.
- **If any external service is unavailable**, stop and ask — do not attempt to troubleshoot.
- **Run migrations via Docker** after writing them: `docker compose exec app php artisan migrate` (or `migrate:fresh --seed` when appropriate). Do not pause to ask first.
- **If the PostgreSQL container becomes unresponsive** (500/exec errors), ask the user to restart it rather than retrying.
- **When implementation is complete**, run `php artisan test` and fix any failures before proceeding.
- **Pause for manual testing.** Do not proceed past this point without explicit instruction.
- **Ask explicitly whether to close the session** before writing the log or committing.
- **Session log**: write a log file at `sessions/[NNN]. [Title] — Log.md` summarising what was built, what changed, and any deferred decisions.
- **Check the roadmap**: after writing the log, review `sessions/000. Table of Contents.md` and any relevant stub files to see if this session's work affects upcoming sessions. Update stubs if needed.
- **Commit**: stage all changed files (including the log), commit to a feature branch named `session-[nnn]`, and notify the user. Do not push — the user will push and merge when ready.
- **Do not begin the next session** until the user explicitly starts it.

---

## Style rules

- Follow existing code conventions exactly.
- Do not add features, refactor, or improve anything beyond what the session specifies.
- Prefer editing existing files to creating new ones.
- No docstrings, comments, or type annotations on code you did not write.
- Simple, correct, well-considered solutions over fast or clever ones.
