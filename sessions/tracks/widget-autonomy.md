# Track: Widget Autonomy

The canonical planning + history doc for the Widget Autonomy track — a parallel-execution experiment that runs the remaining Sovereign Widget work (Stages 5d+, 6, 7) plus the widget-touching release-plan entries E9 and E10 through a multi-agent pipeline rather than the standard single-agent session cadence.

This is a **process experiment** as much as a feature track. Whether it works is itself an open question; the doc carries the discipline that lets the experiment fail safely.

---

## Status snapshot

**Last update:** 2026-05-31 (Widget Styling Contract arc: sessions 330 + 331 landed, run as normal single-agent cadence, not the multi-agent experiment).

**State:** The multi-agent parallel-execution experiment (Stages 5d+/6/7, E9/E10) is still **Planning — not launched** (operational blockers below remain). Separately, the **Widget Styling Contract arc** (source spec `sessions/styles-rework-spec.md`) is running as **normal-cadence single-agent sessions** on this track: **330 ✅** made the widget styling boundary explicit (audit + `ThreeBuckets` deletion + `@container` migration for the two full-width widgets + a consumption-gate test + doc refresh); **331 ✅** finished the remaining column-capable widgets' `@container` migration (BoardMembers / EventsListing / LogoGarden — viewport-`@media` baseline now empty) and, by owner call, removed MapEmbed's inert heading field and rebuilt LogoGarden's layout (bounding-box container for faithful logo containment; flexbox-centered, single-source gap); **332** = `@layer` cascade isolation (prompt drafted — the arc's deferred highest-blast-radius piece, and its closing session). **333 = Mobile Nav** (hamburger + footer columns) follows the arc, heading the new **Mobile Public-Site Readiness** pre-Beta push (see `sessions/session-outlines.md` + `sessions/mobile-collapse-brief.md`). See sessions 330–332 prompts/logs.

**Destination repo for extracted widgets:** `https://github.com/abeuscher/npc-widgets` (currently blank).

**Track owns:** all widget-touching work for the duration of the experiment — `app/Widgets/*`, the widget contract surface (`WidgetDefinition`, `WidgetRegistry`, `WidgetType`), the public widget asset bundle, and the page-builder Vue surfaces that consume widget definitions. Main track stops touching these surfaces while the experiment runs.

**Track does not own:** Vue page-builder UI work that doesn't touch widget shape (E11 focus-scroll clamp stays on main). Fleet Manager surface — explicit tripwire; agents stop and surface if any FM contract file is touched.

---

## Premise

The Widget Primitive track (closed at session 237) shipped a clean architectural boundary — typed data contracts, slot taxonomy, source policy, polymorphic ownership. That discipline makes the widget surface the most parallelizable part of the codebase. Per-widget work (preset authoring, help docs, contract conformance) is genuinely independent: one widget's preset library has no bearing on another's.

Most of the remaining widget work is verifiable mechanically: tests pass, build artifacts regenerate, package boundary respected, manifest valid. That's the kind of work an agent can drive to a green PR without continuous human judgment.

The hypothesis: by lifting widget work onto a parallel track with multiple writer agents, a formal-contract gate, and a clear merge discipline, the user can pursue this work concurrently with main-track sessions (Fleet Manager, finance importers, post-Beta-1 polish) without the two tracks fighting for attention.

The track is also a chance to test multi-agent coordination on a low-risk surface before applying it anywhere else. If the model works, it generalizes; if it doesn't, the abort cost is bounded.

---

## Scope

**In scope:**

- **Sovereign Widget Stage 5d+** — per-widget preset authoring batches (carousel, bar_chart, logo_garden, board_members, donation_form, product_carousel, video_embed, web_form, text_block — nine widgets, batched 4–6 per session). *(List predates sessions 325/330: `event_calendar` was retired at 325, `three_buckets` deleted at 330 — both removed here.)*
- **Sovereign Widget Stage 6** — Widget Browser UI admin page (search, filter, thumbnails, preset chips). New admin surface; no schema impact.
- **Sovereign Widget Stage 7** — Install/Uninstall Mechanics. Two phases: a **design session with the user** (security model, package format, trust posture, semver, dependency resolution) followed by **implementation** (commands, admin UI, orphan cleanup) once design is locked. Stage 7 implementation does **not** merge to main during the experiment — see Reversibility posture.
- **E9 — Widget Help Authoring & Help-System Integration.** Contract-layer change; lands as the **first** contract-touching work in the track so per-widget agents have a stable surface to write against.
- **E10 — Full-Width Architecture Enforcement.** Contract-layer change; sequences alongside or after E9.
- **Optional: registration decoupling.** Auto-discovery from the filesystem (or a lighter-weight registration hook) so adding a widget no longer requires editing `WidgetServiceProvider::boot()`. Pre-stage to Stage 7; may land as part of extraction, may be its own session.

**Out of scope (stays on main or stays deferred):**

- **E11 — Page Builder Focus-Scroll Clamp.** Vue page-builder surface, not widget surface. Stays on main.
- **Sovereign Widget Stage 8 — External Registry.** Multi-system architecture, trust model, business-model questions. Not autonomy-friendly. Deferred to a later, less-autonomous track.
- **Widget Primitive carry-forwards** (Forms widget retrofit, `PageContext` retirement, `RecordContextTokens::TOKENS` per-record-type expansion, `PageContextTokens` namespace migration). Stay on main; lift independently if a forcing function appears.
- **Animated thumbnails.** Stays deferred per the existing stub.

---

## Sequence

1. **Extraction (single session, main track, normal cadence).** Move `app/Widgets/*` to the `abeuscher/npc-widgets` composer package living at `https://github.com/abeuscher/npc-widgets`; update autoload, namespaces, `WidgetServiceProvider`; wire host `composer.json` to consume the new package. Held on a long-lived branch (`widget-autonomy/extraction`) until the experiment validates — does **not** merge to main during the experiment.
2. **E9 + E10 (contract-layer changes).** Single writer agent on each, sequenced not parallel. Establishes the contract surface per-widget agents will write against.
3. **Stage 5d+ batches + Stage 6 browser UI.** Embarrassingly parallel. Multiple writer agents, one per widget batch or per Stage 6 phase. Gate agent enforces formal contract on every PR.
4. **Stage 7 design session.** User in the loop. Security model, package format, trust posture nailed. Single session, normal cadence.
5. **Stage 7 implementation.** Lands on the autonomy branch; does not merge to main until the experiment concludes.
6. **Experiment conclusion.** Either merge accumulated work to main in coherent chunks (success) or abandon all autonomy branches (abort). See Exit / Suspend criteria.

Per-widget work (step 4) is the only step that runs multiple agents concurrently. Steps 2, 3, 5 are single-agent work that happens to live on the autonomy track for sequencing reasons.

---

## Invariants

Hard rules. Agents stop and surface to the user if any of these would be violated.

- **Tests pass on the main branch always.** Baseline-as-found at session start, not baseline-as-prompted. Failures present at session start get noted and surfaced before any new work begins.
- **Package boundary respected.** No edits outside `app/Widgets/{Name}/` (or the equivalent extracted package path) without explicit user sign-off. If a widget needs a shared-file change, see Conflict protocol.
- **Build regenerated when widget assets change.** `build:public` runs and the resulting `public/build/widgets/manifest.json` is included in the commit. Stale manifest is a failed gate.
- **No edits to release-plan or session-outlines stub-closing rows without user sign-off.** The autonomy track owns its own track doc; the release plan gets a one-line pointer ("Widget Autonomy track in progress; reconcile at close"). Dual-writer conflicts on the release plan are avoided structurally.
- **No Fleet Manager contract surface touches.** Any change to `app/Http/Controllers/Api/Fleet/*`, `/api/health`, `docs/fleet-manager-agent-contract.md`, or related auth/cert infrastructure stops the agent immediately.
- **No schema migrations without user sign-off.** Migrations are forward-only by convention; the gate cannot fully validate schema impact. User stays in the loop on every migration.
- **No widget removals or `widget_types` row deletions** during the experiment. Adding is fine; removing risks orphaning live `page_widgets` references.
- **Closing or modifying a `session-outlines.md` stub requires user sign-off.** Stubs are user-curated; agents propose, user disposes.

A violation is not a failure — it's the gate working. The agent surfaces, the user decides.

## Priorities

Soft guidance. Agents use judgment within these when invariants hold.

- **Match existing widget conventions before inventing new ones.** Look at how other widgets in the same category (content / data / portal) handle the same problem. Convention beats novelty.
- **Smaller PRs over larger ones.** Single widget per PR is the default. If a change spans multiple widgets, it probably belongs in its own contract-layer PR.
- **Tests before refactor.** Lock current behavior with a test before changing it.
- **Single-widget scope over multi-widget refactors.** A pattern that wants to apply across widgets surfaces as a separate contract-layer PR, not a fold-in.
- **Read the relevant track doc + premise at session start.** `sessions/tracks/widget-primitive.md`, `sessions/tracks/widget-primitive-premise.md`, this doc. Architectural drift between tracks is a real risk; the re-read is the mitigation.
- **Prefer editing existing files.** Codebase-wide rule; reinforced here because per-widget work tempts new-file proliferation.

---

## Pipeline shape

Three roles, distinct responsibilities, distinct agents.

- **Writer agent.** One per work item (a widget batch, a Stage 6 phase, an E9/E10 sub-task). Owns its branch. Reads the track doc + relevant primitives at session start; produces a green PR; surfaces invariant violations.
- **Gate agent.** Single, queue-shaped. Runs on every PR opened against the autonomy branch. Enforces the formal-contract invariants above: tests pass, build regenerated, package boundary respected, no forbidden surface touches. Pure rule-checking; no judgment. Posts pass/fail back to the PR. A PR doesn't reach the user's queue until the gate is green.
- **Reviewer agent (optional, second iteration).** Semantic quality — convention-matching, simplicity, scope discipline. Posts findings the writer reads on its next turn. Defer until the writer/gate loop is stable; reviewer adds value only if its findings are real.

The user is the merge gate. PRs reach the user only after the gate is green. Most reads should be rubber-stamps; the user engages on the genuinely contentious stuff.

---

## Branching + merge discipline

- **Track branch:** `widget-autonomy/extraction` (long-lived, holds the architectural extraction; does not merge to main during the experiment).
- **Per-session writer branches:** `widget-autonomy/session-NNN/N` for sessions you start in the normal cadence (e.g. E9, E10, Stage 7 design).
- **Per-work-item agent branches:** `widget-autonomy/{widget-handle}-N` or `widget-autonomy/{phase}-N` for agent-initiated parallel work. Two flavors, both prefixed, distinguishable at a glance.
- **All autonomy branches branch from `widget-autonomy/extraction`** (or from main if extract-first is rejected), not from each other. No nested branches.
- **Merges into the autonomy branch happen continuously** as PRs go green. Merges from the autonomy branch into main happen only at experiment conclusion, in coherent chunks the user reviews.

---

## Conflict protocol

When a widget change requires touching shared code (the contract base class, a manifest field shape, a Vue page-builder file that consumes widget definitions):

- **Mechanical shared changes** (rename a method, add a manifest field, change a signature): writer agent **stops** and surfaces a separate PR for the shared change. The shared PR lands first; per-widget PRs resume against the new surface. This is a hard rule — no shared-file edits inside a widget PR.
- **Semantic shared changes** (this widget surfaces a need for a new appearance primitive, or a contract extension that didn't exist): writer agent **stops and asks**. Design call belongs to the user.

The gate enforces the mechanical rule by detecting touched files outside the widget package boundary. The semantic case relies on writer judgment — a priority, not an invariant.

---

## Cross-track coordination

- **Main track stops touching widget surfaces** for the duration of the experiment. E9, E10, and any unforeseen widget-touching stub gets pulled into the autonomy track or held until conclusion.
- **Fleet Manager track is fully orthogonal.** No expected surface overlap. The FM tripwire in Invariants protects against accidental crossover.
- **Test baseline drifts** as both tracks add tests. Discipline: each track reports baseline-as-found at session start, not baseline-as-prompted.
- **Architectural drift between tracks** is a real risk if a main-track session introduces a contract-layer wrinkle (permissions, ambient context, slot taxonomy) the autonomy track doesn't know about. Mitigation: writer agents re-read `widget-primitive.md` at every session start, not just initial spec.
- **Release-plan coherence:** the autonomy track owns its own track doc. The release plan gets a single one-line pointer; checkmarks against autonomy-track entries land at experiment conclusion, not piecemeal. Avoids dual-writer conflicts on the release plan.

---

## Exit criterion

The experiment closes successfully when **all** of the following hold:

- Stages 5d+ and 6 closed (per-widget presets shipped on the eleven scoped widgets; Widget Browser UI in production-shape on the autonomy branch).
- E9 (widget help authoring) and E10 (full-width contract enforcement) closed.
- Stage 7 design session concluded with the user; package format, security model, trust posture documented.
- Stage 7 implementation either complete or explicitly punted to a subsequent autonomy phase.
- Accumulated autonomy-branch work merged to main in coherent chunks the user has reviewed.

Stage 8 is **not** in the exit criterion. It belongs to a later, less-autonomous track decided independently.

---

## Suspend / abort criterion

The experiment aborts (autonomy branches dropped, work returns to main-track cadence) if any of the following hold:

- **Calendar abort budget exceeded.** Two calendar weeks from track open. If the experiment runs longer, it isn't winning. When the calendar trigger fires, abort is the default and re-commit is the explicit decision, not the other way around.
- **Merge-gate backlog grows past five green PRs.** If green PRs queue up faster than the user can review them, the experiment isn't reducing user load — it's deferring it.
- **Two contract-layer-conflict events in a single week.** Indicates the boundary isn't holding and agents are stepping on each other or on main-track work.
- **The user finds themselves reviewing more agent output than they would have written themselves.** Subjective but real. If the experiment isn't a multiplier, it's a tax.
- **A safety incident.** An agent ships something the gate didn't catch and the user catches it post-merge. One is data; two is abort.

Abort is not failure — it's the experiment producing a result. The architectural artifacts (extraction, E9, E10) can be evaluated independently and kept on their merits.

---

## Reversibility posture

The experiment is designed to fail cheaply.

- **Extraction held on long-lived branch.** Does not merge to main during the experiment. If aborted, drop the branch.
- **Stage 7 implementation does not merge to main during the experiment.** The Stage 7 cliff (live user installs through the new path) is held off until the experiment concludes pass or fail.
- **No schema migrations during the experiment** without explicit user sign-off (an invariant). Forward-only migrations are the only true cliff in this codebase; the experiment doesn't approach it.
- **Process artifacts (this doc, CLAUDE.md rephrasing, separate track doc)** are text edits — trivially reverted.
- **Architectural artifacts can be retained even if process fails.** Extraction, E9, and E10 are good engineering on their own merits. If the multi-agent process fails but the artifacts are sound, keep the artifacts and revert only the process.

The cliff to watch: anything user-data-shaped that lands via Stage 7 implementation. Holding Stage 7 implementation off main for the experiment's duration prevents the cliff from arriving.

---

## Per-session discipline

Each writer-agent session starts with:

1. Read this doc, `sessions/tracks/widget-primitive.md`, `sessions/tracks/widget-primitive-premise.md`. Architectural context is non-negotiable.
2. Read the specific work item's spec (a stub in `session-outlines.md`, a phase in this doc, or a session prompt if one was authored).
3. Run the fast test suite. Record baseline-as-found.
4. Run `build:public` if the work touches widget assets. Record manifest hash.
5. Confirm understanding to the surface that spawned the agent before any code change.

Each session ends with:

1. Tests pass at or above baseline-as-found.
2. Build artifact regenerated and committed if widget assets changed.
3. PR opened against the autonomy branch.
4. Gate agent invoked or notified.
5. Stop. Wait for gate result + user merge. Do not advance to the next work item without explicit instruction.

---

## Resolved decisions

Captured at track open (2026-05-06), informed by the planning conversation.

- **Extract-first.** `app/Widgets/*` lifts to `abeuscher/npc-widgets` (`https://github.com/abeuscher/npc-widgets`, currently blank) as a composer-installed package. Architecturally cleaner and matches the long-term marketplace direction.
- **Calendar abort budget: two calendar weeks.** If the experiment runs longer, it isn't winning. Calendar trigger flips abort to default; re-commit is the explicit decision.
- **Merge-gate backlog threshold: 5 green PRs.** Past that, abort triggers.
- **Reviewer agent: deferred.** Start with writer + gate; add reviewer in a later iteration if the writer/gate loop is stable. The reviewer's eventual brief carries explicit user concerns: do not duplicate functionality, do not put code in markup when it can live in PHP/JS, do not let files run overly long, prune occasional test-doc and orphan-file accumulation. Worth doing eventually; not a first-iteration blocker.
- **CLAUDE.md milestone rephrasing landed.** "Phase advancement requires explicit instruction" → "Milestone advancement requires explicit instruction," with milestone defined as "PR ready for review." Cross-track carve-out clause references this doc.

## Pre-launch blockers

Operational, not architectural. Surface and resolve before the first extraction session opens.

- **`npc-widgets` repo access from this environment.** Repo is blank. Need clone + push credentials configured (SSH key or HTTPS credential helper) so the extraction session can land commits on it. User-side setup task.
- **Composer linkage strategy during development.** Path repository (`composer.json` `repositories` entry pointing at a local checkout) for hot iteration vs VCS repository (commits to the new repo, slower iteration). Recommended: path repo until the package shape is stable, then switch to VCS. Decision lands inside the extraction session.
- **Namespace strategy.** Either keep `App\Widgets` (host-tied namespace; complicates package portability) or rename to a package-owned namespace like `Npc\Widgets` (cleaner; cascade-renames ~40 widget folders + every `use App\Widgets\…` in the host repo). Recommended: rename. Real lift — every consumer in the Vue page-builder, every test, every service-provider boot line. Decision lands inside the extraction session, but worth flagging now because it expands the extraction's surface meaningfully.
- **Auto-discovery vs hardcoded boot.** `WidgetServiceProvider::boot()` currently has 39 hardcoded `$registry->register(new XDefinition())` lines. Extraction either preserves that pattern (and the package's own service provider carries the list) or moves to filesystem auto-discovery (cleaner long-term; pre-stage to Stage 7's install/uninstall mechanics). Recommended: auto-discovery, since the package already implies a registration boundary.

---

## The honest framing

This is a process experiment dressed up as a feature track. The widget work is well-defined enough that doing it sequentially on the main track would also work — slower, but with the discipline the user already trusts. The reason to try the parallel approach is to learn whether multi-agent coordination is a viable amplifier for this codebase. If it is, it generalizes. If it isn't, the widget work shipped anyway and the user knows something they didn't before.

The discipline in this doc is the load-bearing part. Without it, parallel agents produce parallel chaos. With it, the experiment either succeeds and the model is provably useful, or fails and the abort is clean. Either outcome is a good outcome.
