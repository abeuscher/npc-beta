# Template: Base Prompt

Copy this file to `NNN. base-prompt.md` at the start of each session. Replace `NNN` with the session number and update the session title.

---

We are about to begin a new session: **NNN. Session Title**.

Before doing anything else:

1. Read `sessions/template-base-prompt.md`, `sessions/template-session-prompt.md`, and `sessions/template-session-log.md`. These files are the **canonical format reference** for all session documents. Do not infer format from previous session logs — the templates take precedence.
2. Read `sessions/session-outlines.md` (the roadmap) for the active-tracks block and the Beta 1 stub for this session.
3. If this session belongs to an active track, read that track's planning doc at `sessions/tracks/{track-name}.md` — status snapshot, phase retrospectives (closed-phase history), and the forward plan all live there.
4. Read `docs/app-reference.md` for environment names, container names, view-to-file mappings, and key dependencies. Read `docs/schema/README.md` for the table index, then read the individual table files under `docs/schema/` relevant to this session's scope.
5. Open `sessions/NNN. Session Title.md` and read the session prompt carefully.
6. Summarise your understanding and confirm you are ready to proceed.

---

## Process rules for every session

- **Before writing any code**, read every file you intend to modify.
- **Before implementing any integration** (email, file storage, authentication, payments, queues, or any other infrastructure), ask the user what existing system handles it. This project has accumulated significant infrastructure — assume something already exists before building new. Do not implement from scratch until you have confirmed no existing system covers the use case.
- **Pause and ask** any time a decision point arises that is not covered by the agreed prompt.
- **If any external service is unavailable**, stop and ask — do not attempt to troubleshoot.
- **Run migrations via Docker** after writing them: `docker compose exec app php artisan migrate` (or `migrate:fresh --seed` when appropriate). Do not pause to ask first.
- **Update `docs/schema/`** after writing any migration — add, modify, or remove the relevant table file(s) under `docs/schema/` to reflect the final column state. If adding a new table, create a new file and add it to `docs/schema/README.md`.
- **If the PostgreSQL container becomes unresponsive** (500/exec errors), ask the user to restart it rather than retrying.
- **When implementation is complete**, run the fast test suite: `docker compose exec app php artisan test --exclude-group=slow`. Fix any failures before proceeding. If the session prompt specifies slow test groups to run, run those separately as well.
- **Front-end builds — when to run which:** Pest does not catch Vue/TS errors or SCSS compile failures, so any change to front-end source must be built before announcing completion. Decision rule: *if this change could affect what ends up in the public-site bundle at runtime (widget CSS/JS, a new or renamed widget asset, a row in `widget_types.assets`, a manifest-lib declaration), run `build:public`; if it's Vite-compiled admin/public/page-builder source, run `npm run build`; if neither, no build.* Match the touched path to its build command:
  - `resources/js/**` or `resources/scss/**` (including `public.scss` and any partial it imports) → `npm run build`. The admin bundle, the public site CSS, and the page-builder Vue app all compile through Vite, so this one command covers all three surfaces.
  - `resources/scss/widgets/**`, the `css` / `js` columns of any `widget_types` row, any file referenced in a `WidgetType.assets` array, or a new/renamed widget folder under `app/Widgets/{PascalName}/` whose definition declares assets → `docker compose exec app php artisan build:public`. This rebuilds the public widget asset bundle on the build server and regenerates `public/build/widgets/manifest.json` (which the admin panel and public layout both read).
  - `public/css/admin.css` is hand-edited and served directly — **no build** required.
  - When in doubt and a widget's runtime output could have changed, run `build:public` — it's cheap, and a stale manifest silently breaks downstream consumers.
- **Tests for new behaviour:** every session that changes application behaviour (new models, controllers, routes, scopes, or logic changes) should include tests unless the session prompt explicitly says otherwise. Pure CSS, template, or copy sessions may skip tests. Follow existing test conventions — Pest syntax, `RefreshDatabase`, factory-based setup.
- **Fast vs slow classification:** tests that individually take >5 seconds belong in a Pest `->group('slow')`. Everything else stays in the default (fast) suite. The fast suite should complete in under 5 minutes.
- **Playwright artifact hygiene:** `resetDatabase()` fires once per spec in `beforeAll`; anything a test creates after that point persists into the next manual-test session. Delete out-of-band rows in `afterAll` — standalone layouts, test-only pages, ad-hoc records not tied to the feature being exercised. Rows that are the natural output of the feature under test (e.g. contacts imported by a happy-path importer spec, notes created by a notes spec) may stay.
- **Cross-repo coordination — Fleet Manager agent contract.** If your work modifies any file or surface that participates in the Fleet Manager agent contract — the `/api/health` endpoint, its auth middleware, its response schema, the VERSION file at deploy time, anything in `app/Http/Controllers/Api/Fleet/*` — pause, update `docs/fleet-manager-agent-contract.md` (body + CHANGELOG + bump the `Contract Version` field), and update the "Cross-Repo: Fleet Manager / CRM" block in `sessions/session-outlines.md` before continuing. The rule is dormant until the agent surface exists; it self-activates the moment it does. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol") for the discipline.
- **Pause for manual testing.** Announce that testing is ready. Then stop. Do not suggest closing the session or take any further action.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below this line happens only when the user explicitly says to close the session.**

Do not ask whether to close. Do not suggest it. Do not pipeline into these steps after tests pass or after manual testing is complete. Wait for the user to initiate each phase.

### Phase 1 — Attenuate and prepare next session

After implementation and manual testing are complete, Claude stops and waits. The user reviews the changes and brings up the next session's prompt when ready. No merge, push, or deploy happens at this stage unless the user explicitly requests it (e.g. for deploy-server testing or structural changes that affect CI).

Claude then drafts the next session's documents:

- **Next session base prompt**: copy `sessions/template-base-prompt.md` to `sessions/(NNN+1). base-prompt.md`, updating the session number, title, and any session-specific read-list items.
- **Next session prompt**: draft `sessions/(NNN+1). Session Title.md` using `sessions/template-session-prompt.md` as the format reference. Base it on the relevant stub in `session-outlines.md`, informed by what was learned during this session. This may take iteration with the user.

### Phase 2 — Close

When the user says to close (after the next session's prompt is agreed):

- **Session log**: write a log file at `sessions/NNN. Session Title — Log.md`. Use `sessions/template-session-log.md` as the format reference — copy its structure exactly, do not base it on previous logs.
- **Update `sessions/completed-sessions.md`**: append this session's row to the index table (number + title).
- **Update `sessions/session-outlines.md`** (the roadmap): if this session resolved any forward stubs, edit or remove them. The Completed Sessions table is **not** here — it lives in `sessions/completed-sessions.md`.
- **If this session belongs to a track:** update the track's `sessions/tracks/{name}.md` — bump the status snapshot (last update date, what's complete, what's active). If this session is the **terminal session of a phase** within the track, also do the **phase-expiry compression** below.
- **Phase-expiry compression** *(only when a session closes a phase within a track)*: lift the phase's session-by-session detail out of the track doc's status snapshot or roadmap roll-up and write a compressed retrospective entry into the track doc's "Phase Retrospectives" section. Shape: phase name, sessions list, key outcomes (1–3 sentences), key decisions or carry-forwards. Per-session detail stays in the matching session log files. The roadmap's track entry stays one line; nothing inflates as the project ages.
- **Archive the previous session**: move all files matching `sessions/(NNN-1). *.md` into `sessions/archived/`. This includes the previous session's base prompt, session prompt, and log. Skip silently if those files don't exist or have already been archived (e.g., the user moved them manually). The current session's files stay in `sessions/` until the *next* session's close.
- **Commit**: stage all changed files (including the log, updated outlines/track docs, the completed-sessions index, and any files moved into `sessions/archived/`), commit on the current `session-NNN/N` branch, and notify the user. Do not push — the user will push and merge when ready.
- **Do not begin the next session** until the user explicitly starts it.

---

## Style rules

- Follow existing code conventions exactly.
- Do not add features, refactor, or improve anything beyond what the session specifies.
- Prefer editing existing files to creating new ones.
- No docstrings, comments, or type annotations on code you did not write.
- Simple, correct, well-considered solutions over fast or clever ones.
- When adding a new admin page or resource, create a help doc stub at `resources/docs/[handle].md` following the frontmatter convention of existing docs, and register the route(s) in the `routes:` array.
- Every new Filament page must include `getBreadcrumbs()` following the established pattern: `[ParentPage::getUrl() => 'Parent', 'Current Page']`.
- **Portal security rule:** every portal route and query that reads contact data must be scoped strictly to the authenticated portal user's own `contact_id`. No exceptions without explicit design review.
