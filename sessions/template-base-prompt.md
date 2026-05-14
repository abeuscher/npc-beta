# Template: Base Prompt

Copy this file to `NNN. base-prompt.md` at the start of each session. Replace `NNN` with the session number and update the session title.

Two structural sections are **required** in every session's base prompt and must be filled in (they are not optional, even though the template body marks them with placeholder text):

- The **opening qualifier block** — three paragraphs after the "We are about to begin…" line, covering (1) execution-order position and milestone/track relationship, (2) boundary-touching status and CRM contract version, (3) session shape and one-sentence summary of the work. The agent reads this before the file-by-file reading list and uses it to orient on what kind of session this is.
- The **Starting state inherited from session (NNN-1)** section — between the reading list and Process rules. A short bulleted state-handoff covering CRM contract status, schema baseline, fast Pest baseline, Playwright baseline, any planning-doc changes since last session, and housekeeping inbox posture for this session shape.

These two sections are project canon — every session from 001 forward has carried some form of them, and removing them empirically degrades agent orientation. Update the template body below as conventions evolve; do not strip these sections from individual session prompts.

---

We are about to begin a new session: **NNN. Session Title**.

This is **execution-order position N** in `sessions/release-plan.md` — **{plan-entry ID + title}**, {phase or milestone context if any}. *(Required: this paragraph anchors the agent to where the session sits in the larger plan. Reference Rule numbers, milestone dividers, or track-doc phases as relevant.)*

This session is **{NOT / IS} boundary-touching** — {if NOT: no Fleet Manager surface is in scope; if IS: name the affected contract surface and note the version-bump posture}. CRM contract stays at {current version} {or: bumps from X.Y.Z → A.B.C — describe additive-vs-breaking}. *(Required: explicit boundary-touching statement governs whether the Cross-Repo block in `sessions/session-outlines.md` and `docs/fleet-manager-agent-contract.md` need updating.)*

**Session shape:** {feature-style / audit-style / content-style / housekeeping / track-phase / migration-squash}. {One-sentence summary of the work — what gets built, lifted, audited, or shipped.} *(Required: shape tag drives the test-coverage rule, the housekeeping-inbox rule, and the close-gate documentation pattern.)*

Before doing anything else:

1. Re-read the session templates (`sessions/template-base-prompt.md`, `sessions/template-session-prompt.md`, `sessions/template-session-log.md`) only if the format has changed since your last session or you're uncertain about a structural detail. They remain the **canonical format reference** — do not infer format from previous session logs — but the templates are stable as of session 276 and don't need a re-read every session.
2. Read `sessions/session-outlines.md` (the roadmap) for the active-tracks block and the Beta 1 stub for this session.
3. If this session executes a `sessions/release-plan.md` entry, read that entry. It is canonical for scope, success criterion, prerequisites, and artifact. The session prompt is a delta against the plan entry, not a replacement for it.
4. If this session belongs to an active track, read that track's planning doc at `sessions/tracks/{track-name}.md` — status snapshot, phase retrospectives (closed-phase history), and the forward plan all live there.
5. Read `docs/app-reference.md` for environment names, container names, view-to-file mappings, and key dependencies. Read `docs/schema/README.md` for the table index, then read the individual table files under `docs/schema/` relevant to this session's scope.
6. Open `sessions/NNN. Session Title.md` and read the session prompt carefully.
7. Note any drift between the session prompt and the actual code in a brief work-log entry; proceed unless something requires a decision per the drift and decision-threshold rules below.

---

## Starting state inherited from session (NNN-1)

*(Required section. Bulleted state-handoff that distills what last session left for this one. Each bullet is one-line; don't narrate, just state the fact and any session-N consequence.)*

- **CRM contract at vX.Y.Z** — {unchanged / bumped from prior; one-sentence summary of what session (NNN-1) did and whether it touched the boundary}.
- **Schema baseline:** {no schema changes since session NNN / new tables since session NNN — list them}.
- **Fast test suite green at N / 0 sequential** ({last-close baseline}). {Expected delta this session, e.g. "no new tests expected — content-only work" or "new tests expected for the X / Y / Z surfaces"}.
- **Playwright suite** — {baseline preserved from session NNN / N specs added at session NNN — list them}. {Whether Playwright runs this session and why / why not}.
- **Planning state:** {anything that changed in `sessions/release-plan.md` or `sessions/session-outlines.md` since last session — milestone refits, phase splits, track lifts, etc. Omit if no planning changes}.
- **Housekeeping inbox** at `sessions/housekeeping-inbox.md` — {whether this session shape absorbs items per Rule 2, and which items if so}.

---

## Process rules for every session

- **Before writing any code**, read every file you intend to modify.
- **Before implementing any integration** (email, file storage, authentication, payments, queues, or any other infrastructure), ask the user what existing system handles it. This project has accumulated significant infrastructure — assume something already exists before building new. Do not implement from scratch until you have confirmed no existing system covers the use case.
- **Pause and ask** any time a decision point arises that is not covered by the agreed prompt. *See the drift and decision-threshold rules below for the calibration of what counts as a decision point worth surfacing.*
- **Surface architectural choices before going deep.** "Should I use X or Y here" is a question for the user, not something to commit to and unwind later. This includes data model shape, new abstractions, framework patterns that will propagate, and any decision that's expensive to reverse. Cheap and local — just decide.
- **Adapt to drift; don't ask about it.** A session prompt is a snapshot of the code at time of writing. Expect small inaccuracies in field names, payload shapes, method signatures, and similar surface details vs the actual code. Default to adapting silently to match what's there and note the adaptation in one line in your work log. Only flag drift when it requires a decision: two reasonable interpretations would produce meaningfully different behavior, the drift reveals a likely bug in spec or code, resolving it would expand scope beyond the session, or the spec describes behavior the system doesn't have and you can't tell whether to build it or treat it as a misread. Heuristic: *fix vs. decide — if you can resolve it by editing your own output without changing the system's intent, just fix it.*
- **Decision threshold scales with project maturity.** This project is well past its loose phase — conventions are established, the app's shape is settled, phases are well-defined. Reserve "pause and ask" for decisions that are genuinely expensive to reverse: data model shape, new abstractions, framework patterns that will propagate, anything cross-cutting. For local decisions with an obvious answer given existing code (naming, file placement, which existing helper to use, structural mirror of nearby code), just decide and note it. Heuristic: *if a reasonable reading of the surrounding code would land on the same answer, you don't need to ask.*
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
- **Verify objective outcomes yourself; pull the user in for judgment.** When you can observe the result directly, do — don't ask the user to run it and report back. Close the loop yourself for CLI output, file contents, exit codes, logs, and anything Playwright can verify. The importer is the model: success is "the right rows exist," and Playwright sees that better than the user does. Console errors, network responses, data round-trips — same idea. **Playwright is a verification mechanism, not just an existing test suite** — when an objective outcome lives in the browser (form submissions, list rendering, data round-trips), a quick spec written for the moment is faster than a user round-trip and stays in the suite afterward; reach for it without being told. If the success criterion is objective, the user doesn't need to be in the loop. Pull them in for things only a human can judge: visual design, UX, whether an interaction feels right. Heuristic: *"does this work?"* you can usually answer; *"is this right?"* usually needs the user.
- **Pause for manual testing only when human judgment is required.** If a session's success criterion is objective and self-verifiable, run the verification yourself and report results — don't punt to the user. If a visual or UX surface is part of the change, announce that testing is ready, then stop and wait. Either way, do not suggest closing the session or take any further action until the user initiates close.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below this line happens only when the user explicitly says to close the session.**

Do not ask whether to close. Do not suggest it. Do not pipeline into these steps after tests pass or after manual testing is complete. Wait for the user to initiate each phase.

### Phase 1 — Attenuate and prepare next session

After implementation and manual testing are complete, draft the next session's documents:

- **Next session base prompt**: copy `sessions/template-base-prompt.md` to `sessions/(NNN+1). base-prompt.md`, updating the session number, title, and any session-specific read-list items.
- **Next session prompt**: draft `sessions/(NNN+1). Session Title.md` using `sessions/template-session-prompt.md` as the format reference. Base it on the relevant entry in `sessions/release-plan.md` (canonical) and the relevant stub in `sessions/session-outlines.md`, informed by what was learned during this session.

Then stop. The user reviews the work and the next session's drafts, and initiates close when ready. No merge, push, or deploy happens at this stage unless the user explicitly requests it (e.g. for deploy-server testing or structural changes that affect CI).

### Phase 2 — Close

When the user says to close (after the next session's prompt is agreed):

- **Session log**: write a log file at `sessions/NNN. Session Title — Log.md`. Use `sessions/template-session-log.md` as the format reference — copy its structure exactly, do not base it on previous logs.
- **Update `sessions/completed-sessions.md`**: append this session's row to the index table (number + title).
- **Update `sessions/session-outlines.md`** (the roadmap): if this session resolved any forward stubs, edit or remove them. The Completed Sessions table is **not** here — it lives in `sessions/completed-sessions.md`.
- **If this session belongs to a track:** update the track's `sessions/tracks/{name}.md` — bump the status snapshot (last update date, what's complete, what's active). If this session is the **terminal session of a phase** within the track, also do the **phase-expiry compression** below.
- **Phase-expiry compression** *(only when a session closes a phase within a track)*: lift the phase's session-by-session detail out of the track doc's status snapshot or roadmap roll-up and write a compressed retrospective entry into the track doc's "Phase Retrospectives" section. Shape: phase name, sessions list, key outcomes (1–3 sentences), key decisions or carry-forwards. Per-session detail stays in the matching session log files. The roadmap's track entry stays one line; nothing inflates as the project ages.
- **Archive the previous session**: move all files matching `sessions/(NNN-1). *.md` into `sessions/archived/`. This includes the previous session's base prompt, session prompt, and log. Skip silently if those files don't exist or have already been archived (e.g., the user moved them manually). The current session's files stay in `sessions/` until the *next* session's close.
- **Commit**: stage all changed files (including the log, updated outlines/track docs, the completed-sessions index, and any files moved into `sessions/archived/`), commit on the current `session-NNN/N` branch, and notify the user. Do not push — the user pushes (or doesn't) on their own cadence. Never push to main, never force-push, never merge to main yourself (see `CLAUDE.md` Git Workflow for the full forbidden list).
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
