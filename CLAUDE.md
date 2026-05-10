## Session Pacing

- Never advance to the next milestone without an explicit user instruction. A milestone is "PR ready for review" — implementation complete, tests green, build clean. After completing one, stop and wait. (Cross-track work like Widget Autonomy may carry its own milestone definition; see the relevant track doc.)
- Never suggest, ask about, or initiate session close. The user initiates close explicitly. Wait in silence until told.

## Git Workflow

Branches use the convention `session-NNN/N` where NNN is the session number and N is the iteration (1, 2, 3, …). Each iteration is a self-contained set of changes that can be merged to main independently. (Cross-track work — e.g. the Widget Autonomy track — uses its own prefix per the relevant track doc; the rules below apply uniformly across prefixes.)

**Parallel / out-of-flow sessions** — maintenance passes, cloud-based ad-hoc work, or anything that runs alongside an in-flight numbered session — use a **fractional session number** keyed to the in-flight session: `session-NNN.M/N` (e.g. `session-276.5/1` runs alongside session 276). The fraction signals "parallel to NNN, not in the release plan, not part of the main numbered sequence." The iteration suffix (`/N`) works the same as for numbered sessions. Use sparingly; most work belongs in the numbered sequence.

**At the close of any fractional / browser / parallel session, open a PR against `main`** alongside the push. Because these sessions run out-of-flow, the PR is how the user tracks them and routes them through the same review surface as numbered sessions. Numbered sessions follow the standard "push branch, user opens PR if they want one" pattern; the PR-at-close requirement applies to fractional/parallel sessions only.

- **Starting a session:** `git checkout main && git checkout -b session-NNN/1`
- **Committing:** Commit whenever a set of changes is ready for the user to deploy and test. One or more commits per branch is fine — whatever makes sense for the changeset.
- **Pushing:** push the current branch to its same-named remote when work is ready for the user to review (typically after commit, or at session close). Required preconditions, checked every push:
  - Current branch is **not** `main` or `master`.
  - Push uses **no refspec** (push the current branch to its remote of the same name; never `local:remote` syntax).
  - Push uses **no force flag** (`--force`, `--force-with-lease`, `-f`).
- **Always forbidden, no exceptions, no judgment:**
  - `git push origin main` or any push that targets `main` (or `master`) as the destination ref.
  - `git push --force` / `--force-with-lease` / `-f` to any branch.
  - `git checkout main` except as the immediate prelude to `git checkout -b <new-branch>` (branch, then leave; never commit while on main).
  - `git merge` into main, locally or remotely.
  - `git commit` while on main.
- **Merge to main is the user's job, always.** Open a PR if appropriate; do not merge it.
- **Next iteration:** After the user merges and reports back, start from main: `git checkout main && git pull && git checkout -b session-NNN/2`. Repeat as needed.
- **Session close documents** (log, outlines update, next-session prompt) go on the final iteration branch for the session.
- **Only branch from main.** Never create a branch off another branch.

The forbidden cases above are also enforced mechanically by the pre-push hook at `bin/git-hooks/pre-push`. To activate in a fresh clone: `git config core.hooksPath bin/git-hooks` (one-time, user runs it).
