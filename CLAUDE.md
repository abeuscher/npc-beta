## Session Pacing

- Never advance to the next milestone without an explicit user instruction. A milestone is "iteration ready for the user to deploy and test" — implementation complete, tests green, build clean. After completing one, stop and wait. (Cross-track work like Widget Autonomy may carry its own milestone definition; see the relevant track doc.)
- Never suggest, ask about, or initiate session close. The user initiates close explicitly. Wait in silence until told.

## Git Workflow

Branches use the convention `session-NNN/N` where NNN is the session number and N is the iteration (1, 2, 3, …). Each iteration is a self-contained set of changes that can be merged to main independently. (Cross-track work — e.g. the Widget Autonomy track — uses its own prefix per the relevant track doc; the rules below apply uniformly across prefixes.)

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
- **Merge to main is the user's job, always.** Do not merge to main yourself.
- **Bump `VERSION` before any merge-to-main that will deploy — including a mid-session deploy-to-test, not only at the close gate.** Every push to `main` runs `.github/workflows/deploy.yml`, whose *Enforce immutable version tag* step rejects a `VERSION` already published to GHCR ("Version tags are immutable — bump VERSION before merging to main"), so an unbumped `VERSION` fails the deploy. When the user signals they want to deploy or see work on the deployed server mid-session, proactively bump `VERSION` to `0.<session>.<iteration>` on the session branch and tell them — don't make them do a version-bump branch dance. (The demo-droplet workflow `deploy-demo.yml` is SHA-tagged and exempt; only the `main` deploy enforces this.)
- **Next iteration:** After the user merges and reports back, start from main: `git checkout main && git pull && git checkout -b session-NNN/2`. Repeat as needed.
- **Session close documents** (log, outlines update, next-session prompt) go on the final iteration branch for the session.
- **Only branch from main.** Never create a branch off another branch.

The forbidden cases above are also enforced mechanically by the pre-push hook at `bin/git-hooks/pre-push`. To activate in a fresh clone: `git config core.hooksPath bin/git-hooks` (one-time, user runs it).

## Testing — scoped inner loop

For scoped design / drift work (typography, colour/appearance, the build-pipeline / stale-bundle cluster), the inner-loop signal is the `design` Pest group, not the full fast suite:

- **`./dev test:design`** → `php artisan test --parallel --group=design` — the reviewed typography + colour/appearance + build-pipeline/drift cluster. ~10× faster than the full fast suite; use it as the iterate-and-recheck signal during design work.
- **`./dev test`** → `php artisan test --parallel --exclude-group=slow` — the full fast suite (the close-gate command). `--parallel` (paratest, session 299) runs the same ~2,460 tests across worker processes — ≈675s serial → ≈105s on an 8-core host, identical pass count. Per-process test databases are framework-managed; tests own their own filesystem/fixtures so order is independent of worker assignment.

Group membership is pinned to an explicit reviewed list by `tests/Feature/DesignGroupIntegrityTest.php` — it fails if a `->group('design')` tag is added or dropped without updating that list. Editing the cluster means editing the list in the same reviewed pass. The scoped loop is an inner-loop accelerator only — it does not replace the full suite.

CI (`.github/workflows/tests.yml`) runs the full suite on every push as fast → slow jobs; the aggregate **`Tests`** check is green only when both pass. Once `Tests` is green on the pushed branch it is the authoritative full-suite signal — this is **additive**, the local block-and-wait step in `sessions/template-base-prompt.md` remains the documented fallback and is not retired. `main`-green is only guaranteed if a repo admin enables GitHub branch protection on `main` requiring the `Tests` check (a non-committable handoff, surfaced in the session-298 log).
