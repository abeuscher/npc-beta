# Nonprofit CRM — Roadmap

This is the active product roadmap. Forward-looking only — what's coming, what's planned for Beta 1, what's deferred to post-Beta 1. Closed work is not narrated here.

**How the planning artifacts are organized:**

- **Per-session work** — each session has a numbered prompt + log file in `sessions/` (and `sessions/archived/` once it ages out).
- **Completed-sessions index** — flat lookup table in `sessions/completed-sessions.md`. One row per session, title + number.
- **Tracks** — long-running architectural arcs that span multiple sessions and (eventually) multiple releases live in `sessions/tracks/{name}.md`. Each track doc carries a status snapshot, compressed phase retrospectives (history), and the forward plan. **When a phase inside a track closes, its history compresses into the track doc and its entry in this roadmap collapses to a one-liner.**
- **Releases** — per-release scope will eventually live in `sessions/releases/{version}.md`. Not in use yet — emerges when a release approaches.
- **Public Website Complete milestone** ✅ **REACHED at session 323 close** *(lifted at session 282 audit; reached at session 323 close)* — the first pre-Beta milestone, sequenced inside Beta 1 scope. Landed when the CMS surface was polished enough to build a credible-looking public website for the investment-conversation demo. Same total scope as before, just front-loaded. The boundary is marked with a `── PUBLIC WEBSITE COMPLETE ──` divider in `release-plan.md`'s execution-order list and is governed by Rule 12. Closing items: E16 Header/footer defaults overhaul (session 322), E17 Border tool — universal widget appearance borders (session 323).
- **Beta One milestone** — the first shippable, demonstrable version: a live hosted site and a live install demo performable for prospects in real time. All sessions before the Beta 1 marker below are planned for Beta 1 delivery; sessions after the marker are deferred until post-Beta 1.

---

## Post-Beta backlog

Forward-looking items the user has flagged for post-Beta delivery. Logged here for visibility; each needs scoping into a stub-driven session or a release-plan entry when picked up.

### Client CRM screenshot → custom widget UI reproduction *(post-Beta migration scenario)*

A repeatable workflow for the migration scenario where a prospect screenshots their current CRM record view and asks us to reproduce the layout inside the product. Build the matching custom widgets to mirror what they had previously. Engagement-shaped work; will need a per-client iteration cycle. Treat as a service-shape, not a one-shot feature — the per-client variance is the point.

### Slug-change 301 redirect + general redirect management *(post-Beta)*

When a page slug changes, offer to set up a 301 redirect from the old slug to the new one. Also build a general redirect-management admin tool that surfaces existing redirects (with both 301 permanent and 302 temporary variants supported) and lets admins add/edit/remove them outside the slug-change flow. Touches the page editor surface, the admin Tools group, and the public-render routing layer.

### Anchor nav widget — in-page anchors at horizontal widget breaks *(post-Beta — discussion stage)*

A control or new widget that places anchors at horizontal widget breaks down the page, surfaced through a nav-shaped UI. Probably a new widget (because anchors insert at arbitrary points down the page in a way that's structurally column-primitive-like — the nav has to attach to specific widget boundaries). Needs design pass: control vs. widget? How do operators pick anchor points? Per-page layout flexibility vs. uniform site convention? Defer scoping until post-Beta when the use case has matured.

### Nav widget variants — dropdown behaviors + separate mobile/desktop presets *(post-Beta)*

Build out the Nav widget's variant surface. Session 322 added the first layout preset (Horizontal / Columns-footer); there's a lot more here that's cheap to add if tooled correctly. Candidates: additional dropdown behaviors (mega-menu, click-vs-hover, accordion), and decoupling mobile from desktop so an operator can pick a desktop preset and a different mobile preset independently rather than the single shared config today. The win is treating "nav layout" as a small set of composable presets rather than one monolithic config. Needs a design pass on how presets compose (layout × dropdown × mobile) before building.

### Editor↔Public parity — homogenize widget hydration + layer the admin reset *(post-Beta — architectural; surfaced at session 333)*

The page-builder canvas renders **public** widget HTML/CSS/JS inside a **Filament admin** document, so two cascades and two JS lifecycles share one `window`. Every editor↔public parity bug traces to that one fact. Session 333 fixed the symptoms (the uneven-grid CSS, the pager rendering Swiper's default dots instead of the designed pager) and stood up a standing parity harness, but the *cause* is structural: the editor re-implements widget **hydration** (HTML → correctly-layered CSS + libs loaded + Alpine initialized) as a bespoke second copy of what the public site does, and the copies drift. (Session 340 brought the *public* chrome render to parity; **session 354** closed the *editor* chrome-render instance — header/footer wrapper parity — and took the Track-2 interim below.) Two independent tracks, ~3 sessions + a risk buffer:

- **Homogenize widget hydration (~1–2 sessions, knowable).** **→ Scheduled as session 355.** Consolidate the three scattered pieces — the admin head-hook lib emission (`AdminPanelProvider`), `useLibraryLoader`'s `loadLibs`/`reinitAlpine`, and `public.js`'s auto-init — into one shared `hydrate(rootEl)` routine both surfaces call (public once over `document`; editor over each freshly-injected canvas subtree). Single-sourcing the widget-owned half makes the swiper-class and init-race-class bugs structurally impossible. Load-bearing central plumbing → do it against the green 333 parity harness.
- **Layer the admin reset (~1–2 sessions, externally gated).** **→ Interim taken at session 354:** the `layer(filament)` one-liner in `resources/css/filament/admin/theme.css` is this bullet's "manually layer the preflight" hack, broadened to wrap *all* Filament admin CSS in `@layer filament` below `@layer widgets` — it closes the unlayered-preflight-beats-widgets leak class (the pager-arrow-border example + the 354 chrome link-colour leak). The clean Tailwind-v4 upgrade below remains the debt-payoff. The residual host-cascade leak (e.g. the pager nav arrows losing their border to Filament's unlayered Tailwind preflight `*{border-width:0}`) can't be fixed from inside a `@layer` — unlayered always beats layered regardless of specificity. The clean fix is a **Tailwind v3→v4 upgrade** (v4 puts preflight in `@layer base` for free), which is gated on Filament v4 support and needs a full admin-UI visual-regression pass; a narrower "manually layer just the preflight" hack is ~1 session but carried as debt.

Framing (from the 333 discussion): model + view are already single-sourced (one API/`WidgetRenderer`); the divergence is **client-side hydration + interaction**. Split by **widget-owned** (HTML/CSS/libs/init — *must* be single-sourced) vs **surface-owned** (selection/drag/inline-edit/host chrome — legitimately separate). An **iframe canvas was considered and ruled out** — you can't drag blocks across a frame boundary, which this editor's palette-drag requires. Even fully done, this is collision-*resistant* + well-instrumented, not collision-*proof*: the harness is **detection, not prevention** — only host-layering (or the rejected iframe) prevents.

### In-app contextual tours via the help system *(post-Beta — builds on the session 338 driver.js primitives)*

A per-view guided tour as fast in-app onboarding for the tool the user is currently in — **distinct from the 338 *marketing* tour** (a multi-page demo walkthrough launched from the dashboard). Each tour is **tied to a single view** and run from the **help system**, which is already per-view: the `?` slide-over resolves a help article for the current route (`HelpArticleService::forRoute`), so a tour is just one more per-view asset, and the slide-over (or a button beside the `?`) is the natural "Take a tour of this page" launch home.

Because it never leaves the view, it skips almost everything the 338 engine carries: no cross-page navigation, no `localStorage` resume, no page-group controller — that whole `controller.js` + `state.js` layer exists *only* because the marketing tour walks across pages. This is driver.js used natively (an array of `{element, popover}` steps + `.drive()`), reusing two 338 primitives: the **`data-tour` anchor convention** and the **Livewire-morph robustness** (`waitForElement`) from `resources/js/admin/tour/anchors.js`. The role-gated URL map + demo-safe showcases also drop away — they are demo-specific (an in-app user can reach their own views).

Two limits to hold: (1) keep each tour **inside one view** — the moment it changes the URL it re-incurs the full cross-page tax; (2) **sanitize the step copy** if it is authored in editable help content — driver renders popover text as HTML, so author-editable tour text is a stored-XSS surface (the "future caveat" flagged in the 338 security read goes live here, unlike the 338 tour where every word is static and ours). Step-script home (help-article frontmatter vs a companion definition) is a sprint-time design call; the pages to tour are named at scoping, not now.

---

## Active tracks

- **Fleet Data Hygiene** — *Phase 1 ✅ closed (352) — Phase 2 CRM half ✅ shipped (353, v2.4.0); FM-side toggle + subcheck consumption pending* — prevent + detect silent cruft accumulation on live instances (orphan event/post landing pages, leftover scrub data, media creep) under a **hard privacy boundary: FM is never *built* to read raw node data — counts only over the wire; a per-node FM "maintenance/auditable" toggle gates any data-touching op; deep audit is node-local + consent-gated.** **Phase 1 (352)** shipped the in-repo half: a CI keystone "scrub-wipe leaves no residue" test (launch-gate prevention) + the node-local `app:data-hygiene` audit on a build-once `DataHygieneAudit` core (+ a new `media:prune-dead-owner`). **Phase 2 (353, boundary-touching) shipped the CRM half:** its count-only `counts()` surfaced as a `data_hygiene` `/api/health` subcheck (additive **v2.3.0 → v2.4.0**) — informational, never-red, excluded from the worst-of overall status, cached ~10 min, counts-only over the wire. **Remaining Phase-2 work is FM-side** (handed off via `data-hygiene-handoff-from-crm.md`): consume the subcheck + build the per-node maintenance/auditable toggle. Phase 3 (bounded remediation) future. Down-payment at 349 (`pages:prune-orphan-events` / `media:prune-orphans`). Full design, forward plan + Phase Retrospectives: `sessions/tracks/fleet-data-hygiene.md`.
- **Widget Primitive** — *substantially complete* (Phase 6 closed at session 237). See `sessions/tracks/widget-primitive.md` (premise: `widget-primitive-premise.md`). Carry-forwards remain (none scheduled): Forms widget retrofit, `PageContext` full retirement, per-record-type `RecordContextTokens::TOKENS` expansion, `PageContextTokens` namespace migration.
- **Fleet Manager Agent** — *active* — Phase 2 Backup Pipeline closed at session 242; reopened at session 248 for the v2.0.0 mTLS migration (auth handshake swap from bearer to nginx-terminated mTLS); additive `/api/logs` endpoint shipped at session 251 (v2.1.0); CRM-side absorption of FM Security Posture Pivot at session 253 (rotation script `bin/rotate-fm-cert.sh` + compromise-recovery runbook + additive Security Posture spec language; no version bump); additive `/api/backup/trigger` endpoint shipped at session 263 (v2.2.0); additive `/api/backup/blob` endpoint shipped at session 268 (v2.3.0); **Beta-1-blocking work resumes** for node operations parity (install / backup / restore / log-reading) per `sessions/release-plan.md` § A2 once FM 012 has absorbed v2.0.0 + v2.1.0. See `sessions/tracks/fleet-manager-agent.md`. Product spec for both repos: `sessions/fleet-manager-planning-spec.md`. Contract surface live at v2.3.0; spec doc canonical at [`docs/fleet-manager-agent-contract.md`](../docs/fleet-manager-agent-contract.md). Carry-forwards remaining: BackupHasFailed event listener, backup-restore tooling, per-install retention configuration beyond the 14-day default, scheduler runner on the worker container.
- **Code Review & Cleanup** — *Cycle 3 ✅ closed (344 → 348)* — recurring maintenance arc; each ~50 sessions of growth triggers a new cycle. Cycle 1 closed at session 208 (carve-out 207). Cycle 2 closed at session 274 (carve-out 275). **Cycle 3 lifted by cadence at 344** (~70 sessions of drift): **344 audit ✅ → 345 apply ✅ → 346 carve-out A ✅ → 347 carve-out B ✅ → 348 squash ✅**. 345 consumed the W7/W8/W11/W12/Open-Flags backlog (security/correctness flags 344-A…E + F applied; G deferred) and landed the standing convention-drift Pest test; fast Pest 2799 → 2814/0. **346 carve-out A** = behaviour-preserving clean split of the two >1k bundle files into per-domain collaborator classes (ContentExporter 1168→404, ContentImporter 1961→555; new `Export/` + `Import/` collaborators + a shared `SiteSettingsBundlePolicy`); fast Pest 2816/0, round-trip suite the net (119/0). **347 carve-out B** = behaviour-preserving admin-only split of `InlineFormatToolbar.vue` 1768 → 1071 LOC into 4 composables + 3 popover sub-components (`components/inline-toolbar/`); Vue-only, no schema; the toolbar's missing behaviour net re-established as a standing Playwright spec (7/7) + the `design`-group parity guard (259/0); fast Pest 2816/0. **348 squash ✅** = the 13 migrations since the 274 baseline collapsed into the regenerated schema dump (3915 → 4015 lines), `migrate:fresh --seed` identity clean (data phases verified reproduced by seeders), the 4 migration-coupled `MemosTrixToQuillTest` cases dropped as Phase-5 fallout; fast Pest 2816 → 2812/0; non-boundary, v2.3.0, no schema change. **Cycle 3 is fully closed**; its Phase Retrospective + the next-trigger note live in the track doc. Next trigger ≈ session 398 or forcing function. Forward plan, cycle retrospectives, and standing improvements (3-session compressed shape, convention-drift Pest test as permanent inter-cycle gate, carve-out auto-flag past ~6 commits, etc.) live in `sessions/tracks/code-review-and-cleanup.md`; the 344 audit findings live in its log.
- **Code Shape & Fit** — *between cycles* — Cycle 1 closed at session 313 (audit at 312 ✅, apply at 313 ✅); reviewed at A004 ✅. Track doc carries the cycle shape, the seven workstreams (W-R1 Cardinality / W-R2 Locality / W-R3 Directness / W-R4 Necessary-vs-accidental / W-R5 Seam quality / W-R6 Temporal / W-R7 Naming — the last two added at A004), and the standing improvements: `sessions/tracks/code-shape-and-fit.md`; closed-cycle retrospectives split to `sessions/tracks/code-shape-and-fit-retrospectives.md`. Next trigger ≈ session 379 per the +50 cadence rule.
- **Rich-Text Editor (ProseMirror)** — *DEFERRED fast-follow, not started* — replaces Quill 2 with ProseMirror to deliver inline-in-prose tables (legal docs / cookie policies / pasted tabular data) + arbitrary-structure fidelity Quill structurally can't host (it strips non-blot markup on normalization). The true intent of release-plan E15. ~6–8 sessions, POC-gated; front-end-dominated and non-destructive because rich text is stored as HTML (no content migration, no contract bump). Down-payment landed at **session 349** (the block-level Table widget's embedded `prosemirror-tables` editor). Trigger: post-launch fast-follow, or earlier on negative pre-launch feedback about table / long-form authoring. Full scope, surface map, forward plan, and the autonomy/oracle read: `sessions/tracks/rich-text-editor-prosemirror.md`.
- **Test Audit** — *between cycles* — lifted at A003 close. Recurring 50-session cadence; single audit session by default, carve-out shape if dimensions overflow. Four audit dimensions: D1 Playwright spec discipline, D2 Spec-claim integrity, D3 Mutation testing slice (existing `docs/testing/mutation-audits.md`), D4 Pest file relevance + fast/slow refresh. **Cycle 1 closed at A005** (D1 + D2: Playwright suite 28 → 16 specs, s296 `[test-integrity]` backlog drained, `docs/testing/playwright-discipline.md` shipped). D3 and D4 queue for later cycles. Next trigger ≈ session 365 or a forcing function (5+ unresolved `[test-integrity]` entries). Prior partial audits noted in the track doc as precursors: s094 (Pest audit), s241 (first mutation slice). Track doc: `sessions/tracks/test-audit.md`.
- **Page Composition Fidelity** — *active, demand-driven (opened session 339)* — recurring stress-test of the page-composition stack (widgets / theme tokens / page-builder primitives) against real external comps: surface gaps before a client comp does, and rehearse faithful comp execution (comp = source of truth, render-and-compare region-by-region, delineate what can't be matched). Gaps feed the Widget Autonomy backlog. Per-pass methodology in `sessions/tracks/page-composition-fidelity-finding-template.md`; premise / status / gap backlog (first findings G1–G4 from the 339 pass; **G2 closed at 340** — the hero left-flush finish) in `sessions/tracks/page-composition-fidelity.md`. No cadence — runs when a new design (reference site or client comp) is worth validating against.
- **Public Marketing Website** — ✅ **closed at session 293** (full track: PMW1 284 → 285–293). Five-page CMS-built marketing site (Home / About / Pricing / Contact / Demo) + page-capture harness + gap report + build-summary, dogfooded inside the product's own page builder. CRM contract stayed v2.3.0 throughout. History compressed into `sessions/tracks/public-marketing-website.md` (Phase Retrospectives); durable close-out at `sessions/public website/build-summary.md`; per-session detail in the (archived) session logs. **Milestone-close gates remaining** (now per-item user lift decisions, not track-owned — kept visible here because the user tracks them): (a) Privacy Policy + Terms of Use starter pages (Stripe Dashboard requires both URLs; pairs with E16); (b) real photography replacing every sample-image placeholder across all five pages; (c) ✅ **CRM-side closed at session 321** — `/demo/enter` auto-login verified + dress-rehearsed, the `demo` role lockdown proven (standing Pest guard) and widened (events/donations CRUD), mail→log confirmed; the demo public site (a locked landing + editable showcase pages + a driver.js tour) was scoped at **session 324 (Demo Site Plan)** — A006's cloud-autonomous framing was retired at 323 close and replaced by 324's interactive scoping conversation, which produced the canonical plan at `sessions/demo website/demo-site-plan.md`: a **six-session build arc A–F** (A calendar swap → B listing upgrade → C event landing page → D per-page-permission + locked landing → E demo-node assembly → F portal demo, back-end-only v1), plus the v1 cut of both demo-website specs and three settled architecture decisions (per-page `restricted_roles` permission, server-rendered vanilla calendar, reserved `data-tour` tour-anchor convention); **325 = session A ✅ (Events — Calendar Swap, complete: `EventCalendar` retired + server-rendered self-contained `event_mini_calendar` shipped)** — at 325 close the **no-LP model was retired** (it was collecting compensating complexity): every event will instead get a **Simple/Standard landing page** at creation, which reshaped + reordered the arc to **A ✅ → C event landing pages (326) ✅ → B listing upgrade (327) ✅ → D per-page-permission + locked landing (328) ✅ → E ✅ (336, reshaped to demo-node restore-wiring) → F (337) ✅** (337 consolidated the portal onto `/members`; driver.js tour = **338 ✅ → demo v1 complete**) (327 also added the operator `sold_out` event flag; D shipped the page edit lock as a `locked` flag + `edit_locked_pages` permission — enforced in `PagePolicy` + the page-builder save API, with a permission-gated "Published & Locked" toggle on pages **and** posts and `demo:reset` locking every page; the §2a `restricted_roles` design was superseded; a **demo upload-lockdown** hardening session **329 ✅** shipped between D and E — `BlockDemoUploads` middleware blocking new-file uploads for the `demo` role at all three chokepoints, see the 329 log); **the demo-site track is now PAUSED at the owner's call — E (demo-node assembly) + F (portal demo) need owner research + layout-refinement time; the work switched to the Widget Styling Contract arc (session 330+) as lower-creativity groundwork in the interim — **330 ✅** made the widget styling boundary explicit (audit + `ThreeBuckets` deletion + `@container` migration for the two full-width widgets + a consumption-gate test + doc refresh; `EventMiniCalendar` also stopped self-hiding on collapse), **331 ✅** finished the remaining column-capable widgets' container-query migration (+ owner-called MapEmbed heading removal and a LogoGarden rebuild for faithful logo containment), **332 ✅** = `@layer` cascade isolation **plus an owner-directed rollback of the 330/331 container-query migration** (it keyed widget collapse to mobile *viewport* breakpoints applied to *container* width, so column-placed widgets overrode the operator's configured count — reverted to honor user control; the `@layer` work was kept) — the arc's closing session; that rollback surfaced three editor↔public fidelity bugs (Swiper-load race, uneven grid, possible layout-column drop) scoped to **333 ✅ = Page Builder ↔ Public Widget Parity** (editor↔public fidelity — fixed the uneven-grid defect + the real bug: the editor rendered Swiper's *default* pager because the admin head injected vendor CSS **unlayered**, now emitted into `@layer reset`; the C "Swiper race" was a stale-build artifact from 332, unreproducible; E did not reproduce; the structural cause is logged as the **editor↔public architecture stub** in the Post-Beta backlog), then **334 = Mobile Nav** heading the pre-Beta **Mobile Public-Site Readiness** push (see that section below)**; driver.js tour build + real seeded portal login are named downstream follow-ons; (d) **CRM side ships `demo:reset`** (local cron reseed baseline, session 321) — the remaining Fleet-Manager shared-contract arc (demo-node identity, version pin, egress firewall, abuse alerting) is FM-side, handed off via `/home/al/fleetmanager/sessions/demo-node-handoff-from-crm.md`; (e) the real `mailto:` address (gap G4). Open gap rows are individual per-gap lift decisions in `sessions/public website/gap-report.md`.

---

## Cross-Repo: Fleet Manager / CRM

Two parallel agentic workstreams run across two repos (this CRM repo and a separate Fleet Manager repo, to be created). The agent contract surface — the HTTP shape Fleet Manager polls — is governed by a shared spec doc plus this status block. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol") for the discipline.

- **Agent contract version:** `2.4.0`
- **Spec doc:** [`docs/fleet-manager-agent-contract.md`](../docs/fleet-manager-agent-contract.md)
- **Canonical URL (used by FM repo via WebFetch):** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`
- **Last boundary-touching session in this repo:** session 353 (additive — bumped **2.3.0 → 2.4.0**; new **count-only `data_hygiene` `/api/health` subcheck** sourced from session 352's `DataHygieneAudit::counts()` — `value` is the four-category non-PII breakdown `{orphan_event_pages, scrub_records, orphan_media_dirs, dead_owner_media}`; **informational, never red**, soft-yellow above a generous total threshold (default 100), **excluded from the worst-of overall status** so benign cruft never drags node health; counts read through a ~10-min `Cache::remember` to stay cheap on the polled endpoint; **count-only privacy boundary** — no raw rows/records/paths ever cross the wire, the `--deep` records mode stays node-local; v2.3.0 consumers ignore the unknown key and keep working. **FM-side absorption pending** — consume the subcheck + build the per-node maintenance/auditable toggle, handed off via `sessions/data-hygiene-handoff-from-crm.md`). Prior boundary-touching sessions: 268 (additive — bumped 2.2.0 → 2.3.0; new `GET /api/backup/blob` endpoint streams the freshest backup zip from the resolved source disk; `Content-Type: application/zip`, `Content-Disposition: attachment; filename="<spatie-blob-filename>"`, `Content-Length: <bytes>`, `Cache-Control: no-store`; mTLS gate extended to the new location in both `default.conf` and `prod.conf`; per-location `fastcgi_read_timeout 600;`; `throttle:60,1`; disk-fallback rule has two layers — Layer A preference puts `local` first if present, Layer B falls through on empty across the resolved order; 404 `no_backup_available` envelope when all configured disks are empty; 500 `backup_destinations_not_configured` when `BACKUP_DISKS` resolves empty; 500 `backup_disk_error` for synchronous storage exceptions); 263 (additive `/api/backup/trigger` endpoint; bumped 2.1.0 → 2.2.0); 253 (documentation revision under v2.1.0 — no contract surface change, no version bump); 251 (additive `/api/logs` endpoint; bumped 2.0.0 → 2.1.0). **Session 291 (documentation revision under v2.3.0 — no contract surface change, no version bump):** the `/api/health` `version` field's description + examples were corrected to the build-stamped pre-1.0 `0.<session>.<iteration>` form (e.g. `0.291.1`); FM consumes this string for per-client upgrade verification (before→after, rollback). The build pipeline now reads the repo-root `VERSION`, bakes it as `APP_VERSION`, and immutably tags the published GHCR image with it (never overwrites; `latest` still moves). Response shape, status enums, and auth are unchanged — FM picks up the corrected field description on its next WebFetch refresh; no FM-side consumer-code change forced. **Session 335 (documentation revision under v2.3.0 — no contract surface change, no version bump):** records the **demo-node reset coordination** decision (new § Demo-node reset coordination in the contract doc) — the demo node resets by restoring a curated baseline blob (`RestoreFromBlob`) rather than the local `demo:reset` reseed; **FM** owns the loop (provisions the node, writes the reset cron, **pushes** the blob via its provisioning channel so the node needs no outbound egress), **CRM** provides a `isDemoMode()`-hard-gated `demo:restore` command that restores the pushed blob + fixes env-specific values from `.env`. The demo node's `IMAGE_TAG` pin is lifted (upgrades ride the daily reset window). CRM-side `demo:restore` implementation lands in the demo session following 335; no HTTP surface change, contract stays v2.3.0. **Session 336 (CRM-side implementation — non-boundary, contract stays v2.3.0):** built the `demo:restore` command the 335 revision referenced (hard-gated to `isDemoMode()`; restores a locally-pushed blob's DB + media tree; fixes `base_url` from the node's `.env`); a real local round-trip proved the full-backup path round-trips faithfully. No HTTP surface change. **FM-side pickup:** demo-node reset coordination — FM owns provisioning/cron/blob-push + baseline alignment. **Session 341 (deploy-infra — non-boundary, contract stays v2.3.0; no `/api/*` or `CONTRACT_VERSION` change):** added an idempotent `widgets:sync` artisan command and wired it into every CRM-side deploy path after `migrate --force` (`deploy.yml`, `deploy-demo.yml`, the droplet upgrade runbook) so pure-schema widget changes (a new/changed inspector control with no migration) actually reach an upgraded server — the page builder + public render read widget fields from `widget_types.config_schema`, only (re)written by `WidgetRegistry::sync()`, which the deploy path never ran. **FM-side action required (no contract change):** wherever FM's node-upgrade orchestration runs `migrate --force` on a node outside `deploy.yml`, it must also run `php artisan widgets:sync` immediately after, or the bug reproduces on FM-driven upgrades. Manual unstick for an already-stale node: `docker exec nonprofitcrm_app php artisan widgets:sync`.
- **Last boundary-touching session in Fleet Manager repo:** FM session 011 (refreshed local cache from v1.1.0 to v1.2.0; verified no FM-side consumer-code change required — `ContractValidator` already accepts `unknown`, `StatusInterpreter` re-derives `last_backup_at` against FM-side thresholds matching the CRM's `[24, 36]`). FM 012 (drafted: mTLS Migration — Absorb v2.0.0) and FM 013+ (A2 affordance work, including the log-reading slice) absorb v2.0.0 + v2.1.0 together at next FM-side cycle. v2.2.0 absorption is pending at FM session 020 — refreshes the local contract cache, builds a `BackupTriggerClient` mirroring the existing `LogsClient` pattern, and adds the operator-facing "Trigger backup now" Filament action on the FM-side ClientResource view page. v2.3.0 absorption is pending at FM session 021 (`BackupBlobClient` mirroring the `BackupTriggerClient` pattern) + FM session 022 (the operator-facing restore-to-fresh-node affordance composing trigger + blob fetch + manual `pg_restore` drill into a runbook-shape UX). The 253 documentation revision rides along — FM-side WebFetch picks up the new sub-section + revision CHANGELOG entry on the next refresh; no FM-side consumer-code change required for the 253 portion.
- **Pending boundary changes:**
  - **FM v2.4.0 absorption pending — count-only `data_hygiene` subcheck + per-node maintenance toggle (Fleet Data Hygiene track).** CRM-side **shipped at session 353** (additive v2.3.0 → v2.4.0): the `data_hygiene` `/api/health` subcheck carries **aggregate counts only** (`{orphan_event_pages, scrub_records, orphan_media_dirs, dead_owner_media}`) — never raw node data, by the deliberate privacy boundary (FM must not be *built* to read customer data even though mTLS makes it possible; deep audit stays node-local + consent-gated). Informational, never red, excluded from the worst-of overall status. **FM-side absorption pending** via the Two-Repo Coordination Protocol: refresh the local contract cache to v2.4.0, consume the new subcheck (object `value`, informational/excluded-from-worst-of semantics), and build the manual, user-editable per-node **"maintenance/auditable" toggle** gating any data-touching op (deep audit, cleanup, remediation). Handoff: `sessions/data-hygiene-handoff-from-crm.md` (FM repo). Design + boundary: `sessions/tracks/fleet-data-hygiene.md` (and agent memory `project-fleet-data-audit-privacy-boundary`). Phase 3 (bounded remediation) is future.
  - **FM v2.3.0 absorption pending at FM session 021 + FM session 022.** CRM-side authoring shipped at session 268; FM-side absorbs via the standing Two-Repo Coordination Protocol — FM 021 refreshes the local contract cache and builds the `BackupBlobClient`; FM 022 wraps the trigger + blob fetch + manual `pg_restore` drill into the operator-facing restore-to-fresh-node affordance. No further CRM-side action required for either FM 021 or FM 022.
  - **FM v2.2.0 absorption pending at FM session 020.** CRM-side authoring shipped at session 263; FM-side absorbs via the standing Two-Repo Coordination Protocol (refresh `docs/imported/fleet-manager-agent-contract.md` from canonical raw URL, bump FM-side cross-repo block, build `BackupTriggerClient` + Filament action). No further CRM-side action required.
  - **FM Security Posture Pivot — CRM-side absorption complete at session 253.** Rotation script (`bin/rotate-fm-cert.sh`), compromise-recovery runbook (`docs/runbooks/fm-compromise-recovery.md`), and additive Security Posture language (Recovery posture + FM-side trust assumptions sub-section, items 2 + 3 framed as FM-side intended posture per FM-side review at session start) shipped CRM-side. FM-side absorption pending at next FM-side boundary-touching session via the standing Two-Repo Coordination Protocol — promotes items 2 + 3 from "intended posture" to "shipped" via a follow-on documentation revision in the spec doc when the relevant FM-side machinery (off-filesystem-key bootstrap + external append-only audit sink) ships. No contract bump required for the promotion.
  - **`last_backup_at` threshold-derivation ownership** — FM-raised question of whether the CRM's derived `status` should be canonical (FM trusts the string, drops re-derivation) vs FM canonical (CRM emits only the timestamp + threshold). Considered for v2.0.0, v2.1.0, v2.2.0, and v2.3.0 and explicitly deferred at each — lands at a future additive v2.x bump when there's natural impetus.

---

## Housekeeping Inbox

Small items noticed between sessions live in `sessions/housekeeping-inbox.md` until they get folded into a batch session, promoted to a release-plan entry, or dropped. The inbox is the only home for items that are *small enough to bundle but large enough to break scope when absorbed mid-session*. Walk it at session start/close.

---

## Housekeeping & Review — Beta 1 Scope

*Ordered by priority.*

### Mobile Public-Site Readiness *(pre-Beta 1 — mobile-ASAP push; surfaced at the 2026-05-31 review)*

*Preceded by **333 ✅ — Page Builder ↔ Public Widget Parity** (editor↔public fidelity; promoted ahead of this push after the 332 container-query rollback. Closed: the uneven-grid `min-width:0` fix + the real pager-parity bug — the editor rendered Swiper's default pager because the admin head injected vendor CSS unlayered, now in `@layer reset`; the "Swiper race" was a stale-build artifact (unreproducible, guarded defensively); the layout-column drop did not reproduce. See the 333 log; the structural cause is the editor↔public architecture stub in the Post-Beta backlog.) **334 ✅ = Mobile Nav** opened this push (see the Mobile Nav item below). **335 ✅ = Mobile Vertical Spacing** shipped the section-spacing primitive (vertical axis only) — operator top/bottom padding/margin emitted as `--np-*` custom properties, consumed by a host-layer rule that scales them ×0.8 at ≤768 and ×0.64 at ≤576 off a single tunable ratio; desktop unchanged (see the ruleset item below). **At the 335 close the owner refocused off the mobile push** onto the **Demo Site track** (resuming after its post-329 pause) — the demo + driver.js tour is the gate to driving traffic. **336 ✅ = Demo Node Restore Wiring** shipped the reshaped E — the demo-mode-gated `demo:restore` command (restores a locally-pushed baseline blob: `db:wipe` → `gunzip`|`psql` the dump → restore the `storage/app/public` media tree → fix `base_url` from the node's `.env`), proven faithful by a real local round-trip (full backup carries site identity + logo file + background images wholesale; the portability-bundle bug doesn't apply to the full-backup path the demo uses). `demo:reset` retained for local/dev; the node's cron points at `demo:restore`. **337 ✅ = Portal Pages (F — sign-in / dashboard / account styling)** consolidated the portal onto `/members` (auth stays at `/system`) with one branded hero/visual language across sign-in / dashboard / account, retired the duplicate `system/account` + split pages, rewired the `portal` nav, and reserved `portal.*` tour anchors (see the 337 log). **338 ✅ = the driver.js guided-tour framework** — the marketing-ready gate: a multi-page guided walkthrough of the demo CRM in the Filament admin (interactive contact→record→ledger flow, demo-safe Roles/Import showcases that respect the `demo` lockdown, a dashboard launcher). **Demo v1 is complete.** **339 ✅ = the non-roadmapped public-site restyle** (designer comp — site-wide brand accent → orange, cooler backgrounds, mobile spacing; local edits exported by the owner). **340 ✅ = Public Chrome Parity** — a housekeeping batch: brought `ChromeRenderer` to parity with the page-body renderer (header/footer columns collapse on mobile + per-layout appearance honored), plus small public-render fixes (nav landmark, quoted `url()`, `.sr-only`, hero left-flush = Page Composition Fidelity G2), the latent **s335 spacing-inheritance fix** (the `--np-*` properties were inheriting onto children, so a child's 0 padding bled the parent's value — host rule now resets them per-wrapper), and two operator-blocker editing fixes (button link **target** control + **PricingChart CTA** editing surfaced in the inspector). **341 ✅ = Widget Schema Sync in the Deploy Path** (owner-drafted deploy-infra fix) — added the idempotent `widgets:sync` artisan command and wired it into every deploy path (`deploy.yml`, `deploy-demo.yml`, the droplet runbook) after `migrate --force`, closing the gap where pure-schema widget changes (like the 340 PricingChart CTA fix) never reached an upgraded server because the deploy path never re-synced `widget_types.config_schema`; non-boundary, v2.3.0, no schema; Cross-Repo note hands the matching step to FM's node-upgrade routine. **342 ✅ = Housekeeping — Infra Hygiene + Page-Builder Polish** — a design-independent inbox-drain batch: Debugbar removal (proven gone from the public site), `.env.example` audit (dead broadcasting + env Stripe pruned, CP-entered integrations documented), branded `404/403/500` error pages, page-builder safety/copy fixes (column-decrease confirm guard, inline disabled-checkbox hint, page-form terminology helpers, Duplicate action on Organizations/Templates/Navigation/Mailing Lists), and the two A005 test-backfill Pests (invoice first-row-wins, donation checkout-initiation). 9 inbox items folded out; fast Pest 2786 → 2794/0; non-boundary, v2.3.0, no schema. **343 ✅ = Housekeeping — Media Hygiene + Widget/Tooling Polish** — the two stretch items 342 held shipped: EventMiniCalendar (the inbox "internal margins collapse" premise was a misdiagnosis corrected at the walk — the real defect was narrow-rail **horizontal overflow**, fixed by making the widget a `cqi` query container that fluid-scales its font down, capped at today's size, + a `minmax(0,1fr)` grid so it can't exceed its container) and Random Data Generator → Organizations (generate/count/clear, `SCRUB_DATA`-sourced), plus the `media:prune-orphans` orphan-`cas` sweep (dry-run default, `--force` to delete; on-demand, not wired into any deploy path; found 45 orphan dirs / 17.1 MB locally). Also fixed a pre-existing flaky dashboard-builder test. 3 inbox items folded out; non-boundary, v2.3.0, no schema; fast Pest 2794 → 2799/0. **344 ✅ = Code Review & Cleanup (Cycle 3 — Audit)** — the standing cleanup cycle lifted by cadence (Cycle 2 closed 274; ~50-session trigger ≈324; ~70 sessions of drift), an analysis-only audit opening the cycle; folded Cycle 2's audit-pair into one pass + ran three Cycle-3 standing additions (static-reflection scan, boolean-settings + integration-seam sweeps); findings log is the deliverable; Open Flags 344-A…344-G; **two carve-outs scoped before the squash** (346 bundle ContentImporter/ContentExporter split, 347 InlineFormatToolbar extraction). **345 ✅ = Code Review & Cleanup (Cycle 3 — Apply)** — 6 iterations on `session-345/1` consumed the audit backlog: SVG-sanitizer wiring (Flag 344-A, via a media-add listener) + demo-mode hardening (344-D/E), EventRegistration import-email suppression (344-C) + the `getRelationManagers` deletion (344-B), the dead-code sweep, the importer W7 trait-dedup (251/251 parity), the standing **convention-drift Pest test** + boolean-settings fixes (344-F blessed-delegation; 344-G deferred), and the W12/W8 cleanup — plus two owner-requested adds on the branch (a `laravel/tinker` install + a curated Random-Data-Generator Organizations dictionary); non-boundary, v2.3.0, no schema; fast Pest 2799 → 2814/0. **346 ✅ = Code Review & Cleanup (Cycle 3 — Carve-out A: Bundle Import/Export Split)** — behaviour-preserving clean split of the two >1k bundle files into per-domain collaborator classes (`ContentExporter` 1168→404 + an `Export/` set; `ContentImporter` 1961→555 + an `Import/` set; the SiteSettings allow/deny policy lifted to a shared `SiteSettingsBundlePolicy`); the importer's per-call media-root/dup state threaded explicitly via a small `BundleMediaArchive`; non-boundary, v2.3.0, no schema; fast Pest 2816/0 (round-trip suite the net, 119/0 after each extraction). **The cycle now runs 344 audit ✅ → 345 apply ✅ → 346 carve-out A ✅ → 347 carve-out B ✅ → 348 squash** (347 split `InlineFormatToolbar.vue` 1768→1071 into 4 composables + 3 popover sub-components, Vue-only, no schema, behaviour net re-established as a standing Playwright spec; the 348 squash + 349 Table-widget prompts were drafted at the 347 close). *(Mid-346 an emergent FM-surfaced deploy-brick — a stale `bootstrap/cache` package manifest naming the 342-removed Debugbar provider — was fixed on `session-345/2` (entrypoint manifest-clear + baked HEALTHCHECK + dropped the persistent cache volume; VERSION 0.345.07) and merged/deployed by the owner.)* The actual next-session pick remains guided by non-roadmap pressure (designer feedback + external input) now that the roadmapped tracks are largely complete. The remaining mobile items (below) stay **deferred**.*

The public site must render well on phones for Beta 1 — the audience's device is uncontrollable, so this is high priority. Builds on the existing mobile machinery (294 column collapse via `@container`, 295 type scaling via `@media`) rather than replacing it. Deliberately **viewport-only** at this layer so v1 ships without exposing operator-facing mobile controls (the container-aware work stays where it is). A small cluster of sessions, not one:

- **Mobile Collapse Ruleset — Research & Apply.** Brief stored at [`sessions/mobile-collapse-brief.md`](mobile-collapse-brief.md) (can't run as a cloud session — needs live reference-site Playwright capture). Derive desktop→mobile *ratios* from well-built reference sites, review, then apply to existing widget styles. Introduces one sanctioned new primitive: section padding/margin emitted as `--np-*` custom properties so a single viewport `@media` rule can compress them via `calc(var * ratio)` (today they're literal inline values nothing can override). Also: content carousels (`carousel`, `product_carousel`) drop Swiper and stack on mobile — both were explicit 294 carve-outs. **→ Narrowed at the 334 close (owner call):** the full research-and-apply ruleset is **not** being executed; only its **section-spacing primitive, vertical axis only**, was carved into **335 (Mobile Vertical Spacing)** to kill the excess mobile whitespace and ship the public site. **335 ✅ shipped it** — vertical section padding/margin emitted as `--np-*` custom properties (shared `composeVerticalSpacingVars()` helper across widgets / layouts / chrome), consumed + scaled by a host-layer rule (×0.8 ≤768, ×0.64 ≤576) off a single `$section-spacing-ratio`; standing guard `SectionSpacingPrimitiveTest`. The *correct* long-term fix is **per-breakpoint authoring (medium + mobile) in the page editor** — explicitly deferred as too big; 335's custom-property foundation is the down payment for it (those controls would later override the same `--np-*` properties per breakpoint). The owner's **most-pressing remaining mobile item is column-collapse ordering** (captured to the housekeeping inbox at 335 close) — it packages with the per-breakpoint editor work. Type ramps / widget-interior grids / carousels / the multi-site research remain unscheduled here; the whole cluster is **deferred** while the Demo Site track runs.
- **Breakpoint single-sourcing *(precursor)*.** Breakpoints (576/768/992/1200/1400) are currently triplicated — SCSS `_variables.scss`, PHP `TypographyCompiler::BREAKPOINT_MAXWIDTH`, and hardcoded Swiper literals in templates. Consolidate to SCSS-canonical + one PHP mirror (feeding the Swiper literals from PHP), each commenting a pointer to the other, with a Pest drift-guard. Small; lands before or with the ruleset apply.
- **Mobile Nav — hamburger + footer columns *(own session)* — ✅ closed at 334.** Established the live surface is the **CMS Nav widget** (the homepage chrome is a `ChromeRenderer` page), not the hardcoded `site-header` Blade chrome. **Hamburger rebuilt CSS-only** (the owner's morph-don't-swap pattern): a hidden checkbox toggle + `<label>` hamburger that morphs to the X **in place** (no separate close button), revealing an **absolutely-positioned full-width drop-down anchored below the header** (`.widget-nav` is no longer a positioning context, so the drop anchors to the full-width `.page-layout`); **no `position: fixed`, no scroll-lock** — the nav is never fixed/sticky, so the drop scrolls with the page. A full-screen-overlay attempt was rejected (scroll-lock hop + double scrollbar + covered hamburger). **Footer columns** left-align via the existing alignment control (`--nav-justify`, already mapped left/center/right → flex-start/center/flex-end) — a viewport-`@media` collapse was reverted (widget-SCSS `@media` ban / 332 principle). Guard `NavMobileCollapseTest` locks the CSS reveal + absolute-not-fixed. See the 334 log. Distinct from (and a prerequisite feel for) the post-Beta "Nav widget variants — separate mobile/desktop presets" backlog item.
- **BlogPager — "Load more" mobile mode.** The `blog_pager` widget is a numbered pager with no load-more mode; on mobile a numbered pager should become stacked tiles + a "Load more" control (initial payload stays small for lists that can grow). This is a feature build, not a style tune — scoped as its own item here so it isn't smuggled into the ruleset apply.

### Widget Primitive — Remaining Phases *(track substantially complete; carry-forwards unscheduled)*

Track substantially closed at session 237. Carry-forwards (none scheduled): Forms widget retrofit, `PageContext` full retirement, `RecordContextTokens::TOKENS` per-record-type expansion, `PageContextTokens` namespace migration. Forward plan, design decisions, phase retrospectives, and status all live in `sessions/tracks/widget-primitive.md`. Premise lives in `sessions/tracks/widget-primitive-premise.md`.

### Fleet Manager Agent — Remaining Phases *(track active; v2.0.0 mTLS migration shipped at 248)*

Phase 2 Backup Pipeline closed at session 242. Phase 3 mTLS Migration shipped at session 248 (v2.0.0 contract bump, bearer auth retired, nginx-terminated mTLS in production). Carry-forwards (none scheduled): BackupHasFailed event listener, backup-restore tooling, per-install retention configuration beyond the 14-day default, scheduler runner on the worker container. Forward plan, design decisions, phase retrospectives, and status all live in `sessions/tracks/fleet-manager-agent.md`. Product spec for both repos: `sessions/fleet-manager-planning-spec.md`.

---

### Page Builder Focus-Scroll Clamp *(closed at session 269 — no code shipped; 204-rationale reaffirmed)*

Reopened at session 269 to lift the descoped 204 stretch (scroll lock + tall-widget exception). Verify-at-start audits surfaced two factual errors in the prompt's design-decisions block:

- **`paneEl` is not the scroll container.** `.preview-canvas` carries only `style="min-width: 0"`; its parent `.vue-editor__main` declares no overflow. The document scrolls at `window` level; the inspector stays visible via `position: sticky; height: calc(100vh - 2rem)`. Any clamp would need to listen on `window`, not `paneEl`.
- **No `clearSelection` path exists.** The store has no such action; clicking the canvas background does nothing today (overlays use `@click.stop`). "De-selection un-clamps" would have required adding a new background-click affordance.

Closing-decision evaluation surfaced four paths: (A) ship as written retargeted to `window` (predicted jitter on Mac trackpad inertia — the 204 prediction restated), (B) ship only the tall-widget half (drop the short-widget lock that fights inertia), (C) restructure `.preview-canvas` into a real fixed-height `overflow-y: auto` container with `overscroll-behavior: contain` first (right shape, but bigger than this session's scope and interacts with the Filament admin shell + sticky inspector + mobile breakpoint collapse + preview viewport math), (D) don't ship — reaffirm the 204 escape hatch.

User chose **D**: *"This doesn't feel right and every tangential problem to the one we are solving has been resolved and frankly the UI is quite manageable now. I don't like the kind of problems we are running into and I don't like scroll jacking very much anyways."*

Internal-scroll-widget audit came back clean (every widget uses `overflow: hidden`; carousels use Swiper.js gestures, not native scroll). Drag-drop interaction would have hooked naturally (selection only changes via `App.vue`'s `widget-created` handler at drop completion).

Reopen only if user testing surfaces a concrete UX problem the must-have scroll-to-centre (shipped at 204) doesn't resolve. Path C is the structurally clean shape if it ever does — documented as forward context only; not a working-set entry. See `sessions/269. Page Builder Focus-Scroll Clamp — Log.md`.

---

### Page Builder — Inspector/Modal Focus Management *(stub — carved out at session 317 from the E19 Aria sweep)*

The E19 accessibility pass landed names + roles on every page-builder editor control but carved out the **focus-management** half as larger than one iteration:

- **Confirm modals** (`ConfirmDeleteModal.vue`, `ConfirmResetModal.vue`) lack `role="dialog"` / `aria-modal="true"` / `aria-labelledby` and have no **focus trap** — focus can escape to the background while the modal is open, and focus is not returned to the triggering control on close.
- **Inspector panel** does not move focus into itself when a widget is selected / a panel opens — keyboard users must tab in manually; no autofocus on the first control.

Both need a small shared focus-trap composable (trap + restore-on-close + Escape-to-close) wired into the modals, plus a focus-move-on-open for the inspector. Vue-only, `npm run build`, no schema. Sized ~1 iteration once the composable shape is decided. Pairs naturally with any future page-builder accessibility work; not blocking the public-website demo (these are operator-facing editor controls). See `sessions/317. Accessibility Pass — Log.md` (findings B5/B6).

---

### Code Review & Cleanup — 4-session housekeeping cycle ✅ *(closed at session 274; sessions 271 / 272 / 273 / 274 all closed)*

Mid-cycle housekeeping pass, distinct from the **T1 terminal session** below. Lifted at session 269 close after the E11 (Page Builder Focus-Scroll Clamp) work was abandoned per the 204-rationale, opening calendar for a long-overdue cleanup pass. Window covered: **207 → 268** (~60 sessions of growth since the most recent code review at 205/206 and the most recent migration squash at 208). Originally planned as 270 / 271 / 272 / 273; renumbered after session 270 absorbed the PostgreSQL major-version-skew fix (an emergent unblocker for FM 021).

Why four sessions rather than a single audit + apply pair: the window is 2.5× wider than 205/206 covered, with five net-new subsystems for deep walks (Fleet Manager API surface, Organizations / Affiliations / Donation Credits cluster, the importer convergence post-B2/B2a/B2b, the Page Builder Vue file growth, rich-text surfaces). Splitting the audit half across 271 + 272 keeps each session's scope tractable; apply (273) and squash (274) follow as their own sessions per the established pattern.

Procedural precedent: **205 / 206** for the audit→apply pair shape (canonical); **178 / 179** as the older precedent. Squash precedent: **208** (most recent), with the squash recipe lineage **062 → 082 → 108 → 142 → 181 → 208 → 274**.

#### 271 — Audit Part 1 (Foundations + Quantitative) ✅ *(closed at session 271)*

Broad horizontal sweep + the new mechanical audits. Inherits 205's W1 / W5 / W6 / W9 / W10 numbering; subsystem walks deferred to 271.

- **W1** Stale reference audit (post-208 onwards: deleted/renamed services, retired blade partials, broken `use` statements, retired Alpine state, the E10-time `full_width` → `background_full_width` / `content_full_width` rename, the B1c-time namespaced sentinel additions).
- **W5** Dead code and orphaned files (PHP, blade, Vue/TS, SCSS, tests).
- **W6** Permission and security audit (every Filament Resource has `canAccess()`; every action gated; portal `contact_id` scoping intact; new mutation surfaces from FM contract bumps + Organizations + B2 importers all gated).
- **W9** Help docs / schema docs / routes (post-208 delta — Affiliations / Donation Credits / Organizations columns, the FM contract spec evolution, the `appearance_config` split per E10, the rich-text custom fields surface). Also pins the FM contract version-stamp parity (`HealthController::CONTRACT_VERSION` ↔ spec doc's `Contract Version:` field) — drift caught once at 268, worth structural fix at 271 W4.
- **W10** Test health (Pest fast + slow, Playwright suites, builds). Pre-existing failures classified, not chased. Includes the Playwright teardown audit re-walk per the 208 Phase 1d shape (the page-builder spec set has grown).
- **W11** *(new)* **File-length audit.** Average LOC per language (PHP / Blade / Vue / TS / SCSS — split because blades skew long), files >2× average with current LOC. Quantitative output: per-language summary block + numbered outlier list. The apply session targets outliers for extraction.
- **W12** *(new)* **Inline-code-in-markup grep.** Lineage: sessions 142 / 181. Scans for inline `<script>` / `<style>` blocks in blades beyond a trivial-line threshold, multi-line CSS/HTML strings in PHP/TS, inline JS event handlers with non-trivial logic, widget-template inline assets that should live in widget asset declarations. Output: extraction-candidate table for the apply session.

W7 (duplicated logic) + W8 (framework alignment) tables started this session, populated incrementally across 270 + 271, walked at 272.

Output: log with per-workstream findings, starter W7 + W8 tables (broad-workstream rows), Open Flags block, W11 quantitative output, W12 candidate table. Drafts 272 docs at close — 272's "Pre-loaded findings (validate during the audit)" block carries forward subsystem-relevant findings from 271.

#### 272 — Audit Part 2 (Subsystem Deep Walks) ✅ *(closed at session 272)*

Per-subsystem deep passes. Produces the bulk of the W7 / W8 table rows + Open Flags. Numbering picks up 205's W2 / W3 / W4 slots and adds W4b / W4c.

- **W2** CRM Importer subsystem revisit. Has the 206-applied aggregator-base / `ImportSessionActions` / `ImportSessionPreview` extraction held? Did B2a's contacts auto-mapping lift produce new drift? Did B1c's Organizations importer follow the namespaced shape cleanly? `ContactsImportProgressPage` — does it still warrant 206 Flag B's "leave as is" framing or has 11 more sessions of work made the convergence cheaper?
- **W3** Organizations / Affiliations / Donation Credits subsystem. B1a / B1c / B1b are the largest model-shape addition since 205. Walk: Organization vs Contact pattern parity (importers, source policies, scrub inheritance, soft-credit attribution), Affiliations junction lifecycle, deletion-policy interactions.
- **W4** Fleet Manager API surface. Four endpoints (`/api/health`, `/api/logs`, `/api/backup/trigger`, `/api/backup/blob`) — divergence in error envelope shape, `contract_version` field handling on success vs error, `sanitise()` usage, throttle bucket consistency. Plus FM-contract doc / code drift structural fix (the 268-time finding deserves a pin, not just an audit verification).
- **W4b** *(new)* Page Builder Vue subsystem. `PreviewCanvas.vue`, `LayoutRegion.vue`, `editor.ts` store growth (the store's `return {}` block reached ~70 keys post-267). Composables vs utils convention, `useViewport` / `useLibraryLoader` shape uniformity, the appearance-panel polymorphism that landed in 207. Cross-references W11 for size-driven candidates.
- **W4c** *(new)* Rich-text surfaces. E3 added `rich_text` custom-field type. The session-250-time **Rich-Text Surface Sanitization Hardening** stub is still open in this file — confirm no surface added since has worsened the unsanitized-HTML round-trip pattern. Memo collection's Trix→Quill convergence (also stubbed) — verify its scope hasn't grown.

Output: full W7 + W8 tables (271 starter rows + 272 subsystem rows merged), Open Flags block (everything from both audit sessions), handoff for 273.

#### 273 — Code Review & Cleanup (Apply) ✅ *(closed at session 273)*

Six iterations on `session-273/1` consumed the entire W7 / W8 / W11 / W12 / Open Flags backlog. /1 — Flag W10/A FilePond polling + W8 #1 ObservedBy + W7 #1 password-mismatch + W12 4-6. /2 — W12 1-3 SCSS extractions. /3 — Org cluster observers (W8 #2 / Flag W6/B). /4 — FM contract version-stamp consolidation (W8 #3 / Flag W4/A). /5 — `ImportSessionActions::cascadeForType()` + `LayoutColumnSettingsTab.vue` extraction (W7 #3 + W7 #5 / Flag A). /6 — `editor.ts` composable extraction (W7 #4) — 4 composables, 930 → 700 LOC. Plus a `.btn` SCSS specificity bugfix. Fast Pest 2169 / 0 (+5 from 272 baseline); Playwright 42 / 0 (was 40 / 2 — both flakes resolved). One residual cumulative-load FilePond flake carried to 274 as a Phase 1e status check; Flag W4c/A (Rich-Text Sanitization) carved out at close to a dedicated successor session. See `sessions/273. Code Review & Cleanup (Apply) — Log.md`.

#### 274 — Migration Squash & Code Optimization ✅ *(closed at session 274)*

Single iteration on `session-274/1` — 4 commits. Phase 1 inventory → Phase 2 user picks (B1 + C1 approved; B2 formally re-skipped) → Phase 3 apply → Phase 4 squash → Phase 5 verify. /1 — Phase 3 B1 (drop bootstrap `widgets` payload duplicate; resolves 208-deferred B4 — Vue editor store reads only `items`, never `widgets`). /2 — Phase 3 C1 (3 help-doc route registrations: `contacts.edit.view`, `templates.edit-page.chrome`, `templates.edit-page.scss`). Phase 4 squash — 18 migrations collapsed, schema dump 3544 → 3915 lines, `docs/schema/README.md` squash-note bumped 208/2026-04-22 → 274/2026-05-09, `migrate:fresh --seed` identity check clean. Phase 5 follow-up — deleted `BackfillCompletedDonationStatusesMigrationTest.php` (mirrors 208's `WidgetTokenMigrationTest` precedent: file `require`'d the deleted migration). Fast Pest 2166 / 0 (−3 from 273 baseline 2169 = the deleted obsolete test); Playwright 42 / 0 (273 baseline preserved); residual cumulative-load FilePond flake did not reappear. See `sessions/274. Migration Squash & Code Optimization — Log.md`.

#### Distinct from T1 (terminal)

The T1 stub below is the **absolute-final** code review + squash — it runs after every other release-plan entry has closed, per Rule 10. This 4-session cluster is a mid-cycle housekeeping pass; T1 still happens later. T1's scope is whatever has accumulated between this cluster and Beta-1 release.

---

### Code Review & Cleanup + Migration Squash *(stub — pre-Beta 1, terminal session; T1 in `release-plan.md`)*

Final pre-release session combining a code review pass with a migration squash. Per the plan's Rule 10, this is the *terminal* session — every other entry in `release-plan.md` must close first.

Code review portion follows the pattern of sessions 101, 116, 141, 178/179, and the most recent 205/206 pair (audit → apply; both complete). Scope: dead code and unused imports, duplicated logic ripe for extraction, inconsistent naming, outdated comments, drift from framework conventions, test coverage gaps surfaced since the last review.

Migration squash portion: collapse the per-session migration history into a single squashed migration set against the v1 schema baseline.

Both halves land in one branch; no code change after T1 closes.

---

### Notes Permissions (feature half) *(✅ closed at session 276; C1 in `release-plan.md`)*

Feature half of the original 244 stub. Shipped at session 276 — see `sessions/276. Notes Permissions (feature half) — Log.md` for the full landing.

Three concrete pieces landed:
- `edit_others_note` permission registered in `PermissionSeeder.php` and granted to the `developer` role (super_admin gets it via `Gate::before` bypass; crm_editor / volunteer_coordinator / cms_editor / event_manager / treasurer / blogger / no-role do not).
- `notes_edit_only_by_creator` SiteSetting (string `'true'` / `'false'`, default `'false'`) on `GeneralSettingsPage` → "Notes" section, super-admin gated.
- `NotePolicy::update` and `NotePolicy::delete` extended with the three-step shape: outer capability → toggle read → author OR override. Timeline UI rewired so the Contact / Organization Timeline edit/delete affordances compose the policy via `->can('update'|'delete', $note)` rather than capability checks alone.

The audit half of the original 244 stub continues to feed C3 (Permission audit + Concurrent admin editing + Accidental public exposure) — C1 was the prerequisite that builds the surface C3 walks.

---

### Organizations Model Overhaul *(B1a closed at 255; B1c closed at 256; B1b post-B2)*

Lift Organization from "placeholder with a name" to a peer of Contact, modelled in NPSP-shape (separate peers joined via explicit relations rather than NPC's Person-Account-merge pattern). Scoped as a three-stage cluster:

- **B1a — Organizations Model Overhaul (Min)** ✅ *(closed at session 255).* Five nullable transactional FKs, four importer Org-as-source sentinels, Org admin form rebuilt, sponsor field on Event admin form, Notes lifted to a shared polymorphic Timeline pattern, block-with-counts deletion guard. See `sessions/255. Organizations Model Overhaul (Min) — Log.md`.
- **B1c — Organizations Importer** ✅ *(closed at session 256).* Top-level CSV importer for Organizations under the Tools group, mirroring the namespaced importer pattern (Memberships shape — single-entity, no contact-match bucket). Five new schema columns on `organizations` (`source` / `custom_fields` / `import_source_id` / `import_session_id` / `external_id`) + composite index. Three new mapping-save columns on `import_sources`. Three new sentinels (`__custom_organization__` / `__tag_organization__` / `__note_organization__`). `Organization` model gains `EnforcesScrubInheritance` + `HasSourcePolicy` (top-of-graph). `ImportModelType::Organization` enum case + `ImportSessionActions` / `ImportSessionPreview` arms. In-session lifts: pre-existing trait bug fixed (`serializeColumnMaps` was zeroing custom-field sentinels under all five namespaced importers' UI flows); pre-existing `donations-mapping-indicator.spec.ts` fix; Filepond wait hardening; `@on-demand` Playwright tag introduced; Tracks F + G lifted into the release plan; 257 prompts drafted. See `sessions/256. Organizations Importer — Log.md`.
- **B1b — Affiliations Junction & Soft-Credit Layer** — *post-B2 follow-up; release-plan position 14.* Adds the `affiliations` junction (`contact_id`, `organization_id`, `role`, `start_date`, `end_date`, `is_primary`) for multi-employer / multi-role Contact↔Org relationships, plus a `donation_credits` junction for soft-credit attribution. One-time migration of `Contact.organization_id` → `affiliations` rows with `is_primary = true`. Org contact-info parity audit — fill **industry / EIN gaps** (the rest is already on the model). **Junction-aware deletion-policy revision** — folded into B1b. Reconsider whether the ellipsis-menu "View affiliated contacts" link should promote to a proper panel once the junction lands. Out of scope: Org-Org relationships (parent / subsidiary / fiscal sponsor) — defer until forcing function emerges.

All three entries are canonical in [`release-plan.md`](release-plan.md) § B1a / § B1c / § B1b.

---

### Event Ticket Tiers *(✅ closed at session 278; C2 in `release-plan.md`)*

Shipped at session 278. Shape (A) chosen: tier-canonical; `events.price` and `events.capacity` dropped; price + capacity live on `ticket_tiers` only; an event with zero tiers is free and uncapped. `Event::is_free` and `Event::isAtCapacity` walk tiers. Admin EventResource gains a `Repeater::make('ticketTiers')` with name/price/capacity/sort_order + cross-row uniqueness rule. Public registration widget renders three modes (0 tier: no UI; 1 tier: hidden id + price; 2+ tiers: radio picker with name + price + "(sold out)"); single form action per surface, server-side routes free vs. paid by chosen tier's price. Per-tier capacity replaces event-level. Stripe Checkout line-item product name reads `"{event} — {tier}"`. Migration backfills one General tier per priced-or-capped event and retroactively links pre-existing event_registrations with `ticket_type` to matching-or-newly-created tiers. Iteration /4 added a `notes` textarea on the public form (workaround for missing per-attendee data) and dropped the absolute email-uniqueness silent-success dedup (was blocking legitimate repeat registrations and would have blocked multi-quantity purchases the same way). Fast Pest 2341 / 0 (+30 over 277 baseline); +1 Playwright spec covering all three picker modes. See `sessions/278. Event Ticket Tiers — Log.md`.

---

### Multi-Quantity Event Ticket Purchase *(✅ closed at session 279; C2a in `release-plan.md`)*

Shipped at session 279. Shape (A) chosen at 278-close: `event_registrations.quantity smallint default 1`; per-tier capacity aggregate switched from `withCount('registrations')` to `withSum('registrations', 'quantity')`; each registration row is a purchase line (one row per `(buyer, tier)` pair). Mixed-tier purchases produce multiple rows sharing a `stripe_session_id` (paid path) or sharing buyer email + `created_at` window (free path). Stripe Checkout assembles multi-line-item sessions one line per chosen tier; webhook fans out by `stripe_session_id` instead of looking up a single registration by metadata id (metadata discriminator switched from `event_registration_id` to `event_registration_checkout: '1'`). New `App\Services\EventRegistrationQuantities` value-object normalizes the request's `quantities` map, validates per-tier remaining capacity, computes `totalCents` + `isPaid`, and assembles Stripe line items — one service, four caller controllers. Public widget rewritten with native `<input type="number">` per tier, live subtotal beneath the spinners, sold-out tiers disabled at `max=0`. Single-tier mode defaults the spinner to 1 (one-click registration); multi-tier mode defaults each spinner to 0 (buyer picks). Admin "View Registrants" gained a Tickets column (right-aligned, sortable) and an optional Tier column. Iteration /2 fixed a 278-introduced bug surfaced at deploy-test: `EventController::register` was 302-redirecting to `EventCheckoutController` (POST-only route → browser GETs after 302 → 404). Resolution: merged `EventCheckoutController` and `Portal\EventCheckoutController` into the entry controllers; deleted the two checkout controllers + their POST routes. Iteration /3 was a workflow-rule cleanup (cloud-session / parallel-session / fractional-numbering exceptions removed from CLAUDE.md — see 279/2 entry in `template-rationale.md`). Fast Pest 2355 / 0 sequential (+14 net over 278 baseline); Playwright spec rewritten in place. See `sessions/279. Multi-Quantity Event Ticket Purchase — Log.md`.

---

### Permission Audit *(✅ closed at session 280; #32 in `release-plan.md` execution order; audit half of C3)*

Walked 27 Filament resources, 28 Filament pages, all resource sub-pages, and every admin-shaped controller in `app/Http/Controllers/Admin/` from the 8 shipped roles + unauthenticated. Produced `docs/runbooks/permission-matrix.md` with per-group resource matrices, per-page matrices, a role-grants summary, and 7 audit findings (3 OK-by-design, 4 open flags). Codified 16 load-bearing probes at `tests/Feature/PermissionMatrixTest.php`.

Key empirical finding: `Resource::canAccess()` runs as a Livewire mount hook before any per-page authorizeAccess (via Filament's `CanAuthorizeResourceAccess` trait on the base `Resources\Pages\Page`). This means the `canAccess` override is a universal URL gate — covers list, create, edit, all sub-pages. The "no policy + permissive `canCreate`/`canEdit` default" pattern I'd theorized as a possible bypass is NOT a bypass in practice; verified by test.

Bottom line: the gating is structurally sound. No bypass exists for any of the 27 resources audited. All findings are documentation flags rather than security holes. Fast Pest 2371/0 (+15 over 279 baseline, +16 new minus one ledger-drift entry). See `sessions/archived/280. Permission Audit — Log.md`.

---

### Concurrent Admin Editing *(stub — pre-Beta 1; #32b in `release-plan.md` execution order)*

Lifted at 279-close as the deferred half of C3, further split at 280-close, scope refit at 282 Phase C audit to the slim **(b) path**. Shipping shape:

- **Last-write-wins documented** in the admin runbook. Two admins editing the same record simultaneously each see their own work; the later save wins.
- **Lightweight "currently being edited by X (HH:MM ago)" indicator** on edit pages of records currently being edited by another admin — gives admins visibility before they start editing.
- **No pessimistic locking**, no polymorphic lock table, no takeover rules, no `beforeunload` hook. Those were the over-scoped **(a) path** the 282 audit retired in favor of the minimum-viable visibility affordance for a small-team admin context.

Sized 1 session. Small migration (`currently_editing_by_user_id` + `currently_editing_at` columns on admin-editable records or a small global table), Filament trait that updates timestamps on edit-page mount, Pest tests, optional Playwright spec.

**Note:** session 281 was originally scheduled for the (a)-scope work but never executed; no record_locks migration, no RecordLock model, no close commit exists. The (b) refit at 282 audit starts from scratch. Session 281's planning files (`sessions/281. Concurrent Admin Editing.md` + `sessions/281. base-prompt.md`) reflect the abandoned (a) plan and should be archived as historical record of the planned-but-not-executed scope.

---

### Accidental Public Exposure *(stub — pre-Beta 1; #32c in `release-plan.md` execution order)*

Lifted at 280-close as the further-split tail of the original 32b combined stub. Scope refit at 282 Phase C audit to **Path A only** — the original release-plan bullet:

- **Protect endemic non-public fields** from being flipped public. Each protected field carries a warning/confirmation gate or is structurally impossible to flip public.
- **Per-field protection-mechanism documented** in the permission matrix doc's data-classification section.
- **Public-content indicator** visible on every record/widget surface with a public flag (Pages / Posts / Events / Products / Collections / Forms — verify the list against the matrix doc).

**Out of #32c scope** (lifted to C3a as prereq stub at 282 audit): page-action accountability (actor stamped on publish/unpublish), actor notification of action, and the broader page-action audit trail.

May absorb the carry-forward findings from session 280 (`manage_dashboard_config` / `manage_record_detail_views` unassigned to any role, `household` permission family with no admin surface, "board-read-only" persona unfilled). Decision at session start.

Sized 1 session post-C3a. See `sessions/release-plan.md` § C3's "Accidental public exposure" bullet for the success criterion.

**Note:** session 282 was originally scheduled for #32c implementation but was retconned at its audit-time open into the Phase C + D audit + planning-doc cleanup session. Session 282's planning files (`sessions/282. base-prompt.md` + `sessions/282. Accidental Public Exposure.md`) reflect the pre-retcon scope and will be archived alongside the session 282 log.

---

### Page-Action Accountability + Audit Trail *(stub — pre-Beta 1; C3a in `release-plan.md`; prereq stub for #32c)*

Lifted at 282 Phase C audit as the prereq for the #32c accidental-exposure drill. Three pieces ship together:

- **Accountability:** every publish/unpublish action on a public-flip-bearing record (Page, Post, Event, Product, Collection, Form — verify list at session start against the matrix doc) stamps `published_by_user_id` + `published_at` (and corresponding `unpublished_*`) on the record. Surfaced on the record's edit page.
- **Notification:** the acting user receives a transactional email confirming the publish/unpublish they just performed, with timestamp and a direct link to the record.
- **Audit trail:** every page-shape action (create, update, publish flip, delete) writes to `page_action_log` — actor, action type, record type+id, timestamp, optional changed-field summary. Queryable via an admin Tools-group resource. Retention follows existing data-retention policy.

Sized 1–2 sessions. Migration + observer/listener wiring + email template + Tools-group resource + Pest tests + Playwright spec. See `sessions/release-plan.md` § C3a.

---

### Auto Tax Receipt Email *(stub — pre-Beta 1; C3b in `release-plan.md`; prereq stub for C4 rehearsal)*

Lifted at 282 Phase C audit. Successful donation (via Stripe Checkout webhook) automatically dispatches a tax-receipt email — donor name, amount, date, transaction id, fund (if specified), org tax-id/EIN, IRS-compliant language. Email template configurable via existing `manage_email_templates` admin surface. The current manual "Send Receipts" admin action on DonorsPage stays as a backfill/resend affordance.

Sized 1 session. See `sessions/release-plan.md` § C3b.

---

### Comp-Tier Polish + Skip-Stripe-on-Zero-Total *(stub — pre-Beta 1; C3c in `release-plan.md`; prereq stub for C5 rehearsal)*

Lifted at 282 Phase C audit. Event-registration flow handles comp tickets cleanly — when chosen tier(s) total $0, the public flow skips Stripe Checkout entirely and confirms the registration server-side, sending the thank-you email immediately. Admin can mark a tier `is_complimentary` (label in the picker; behavior driven by zero-price). Mixed-tier orders (e.g. 1 comp + 1 paid) continue through Stripe unchanged.

Sized 1 session. See `sessions/release-plan.md` § C3c.

---

### Deploy-Time Admin Behavior — Discussion *(stub — pre-Beta 1, discussion item not yet scheduled)*

Surfaced at 280-close during the concurrent-editing design conversation. Open question: what should happen to active admin sessions during an app deploy?

Current state: deploy interrupts in-flight requests; admin users see whatever browser-level error their request hits (502, connection drop, etc.); reload after deploy works. Admin locks acquired before the deploy persist in the lock table until their idle-expiry sweep.

User's current leaning: shut down the backend gracefully via Fleet Manager during deploys — drain in-flight requests, display a maintenance banner, optionally release locks held by users mid-edit. Mechanism likely sits on the Fleet Manager side (it owns deploy orchestration today).

Lift to a release-plan entry once direction is decided. Likely 1 session of work if the FM-driven drain is the path — maintenance-mode middleware on the CRM side + an FM-side coordination step. Could grow if the desired UX includes per-admin notifications or save-and-restore-form-state work.

---

### Event Registration Depth — Per-Attendee Data, Partial Refunds, Semantic Disambiguation *(stub — post-1.0)*

Lifted from 278-close as the natural home for three related concerns that don't justify pre-beta scope individually but ought to land together once the registration surface has matured:

- **Per-attendee data inside the registration flow.** Today (and after C2a) the registration row is buyer-shaped: name / email / address / notes per purchase, not per attendee. The 278 notes textarea is the near-term workaround. Lift attendee-level fields (name, dietary needs, accessibility, custom registration questions) into proper sub-rows so an event with "3 General tickets" can collect "Alice / Bob / Charlie" + each one's preferences. Schema: an `event_attendees` table FK'd to `event_registrations`, with one row per attendee (quantity attendees per registration). The event's admin can configure which fields to ask per-attendee. Reasonable home for the eventually-needed "nametag print" / "per-seat check-in" features.
- **Partial cancellation and refund.** Today operators can only do "full refund + repurchase remaining" — pain when a buyer of 3 tickets needs to drop 1. Add a "cancel N of M tickets on this purchase" affordance that mutates the registration's `quantity` (or splits into a cancelled-tail row), with Stripe partial-refund integration. Touches the EventCancellation email content (today fires once per registration; under partial it fires with the affected attendee count).
- **`EventRegistration` semantic disambiguation.** Under (A) the model represents a *purchase line* (tier × quantity), not an attendee. The class name keeps fitting in shorthand but reads wrong once per-attendee sub-rows land. Rename — likely `EventTicketPurchase` or `EventOrderLine` — plus a structural pass on the EventRegistration table (`ticket_type` and `ticket_fee` snapshot columns may be obsoleted by then; the new `event_attendees` sub-row carries per-person fields cleanly).

All three want to land together because they share a shape: the data model graduates from "row = purchase" to "row = purchase line + N attendee sub-rows." Post-1.0 because the current shape ships demo-ready and the workarounds (notes field, full-refund-then-rebuy) survive a launch window.

---

### Theme & Template Re-Taxonomy *(✅ COMPLETE — closed at session 302; superseded & fully retired `release-plan.md` § E6; arc of 4 content points: 297 / 300 / 301 / 302)*

**Origin & forcing function.** Grew out of `release-plan.md` § E6 ("Theme Colors Refactor — move colour columns from template to `SiteSetting`, 1 session, scoped small") via a design conversation at session-295 close. E6 as written was only the *relocation slice*. An IA audit (mapped in the 295 conversation) found the design/theme surface is organised **by where a value is stored, not by what the user thinks it is** — conceptually identical things scattered across the "Theme" page, the Templates resource, CMS Settings, General Settings, and a Nav widget; labels naming the *mechanism*, not the user's mental model. The arc is the coherent version of E6. **Prerequisite: session 296** (the post-incident stale-stylesheet drift guard) must land first — the arc massively expands the build-pipeline surface and 296 is the safety net it is built on.

**The three-bucket model (organise by purpose; the user's goal statements are canonical):**

- **Theme = the homogenous skin.** Owns the *entire appearance vocabulary* as site-wide tokens: a real **colour palette** (today there is no colour *system* — only ad-hoc template columns) + typography + buttons + (gap) page background. Goal: a visitor sees consistency; an author worries about content, not appearance. Single light mode — **no public dark mode** (decided: amateur-illegibility footgun; admin dark mode stays Filament's own concern).
- **Template = deliberate structural deviation** for special pages (landing / single-use / "no clutter"). It never *defines* appearance. Two capabilities only: **structure** (header/footer present? which? content shell) and **selection of vetted wholes** (pick a theme-defined scheme; supply its own chrome). Plus `custom_scss` as the explicit bespoke power-user valve.
- **CMS Settings = identity & defaults.** Site name, favicon, SEO, default template, integrations. Not the look, not the structure.

**Edge-#1 rule (resolves every ambiguous case).** *Appearance is always a Theme token, including chrome appearance.* The Template never defines appearance; it makes **structural** choices and **selects vetted wholes** — never edits primitives. Same principle in both halves (colour *and* chrome): deviate by selecting a coherent unit, never by fiddling individual values.

**Colour token contract.** Canonical CSS custom properties, namespaced `--np-color-*`, defined on `.np-site` (the public namespace; its *primary* purpose is page-builder-preview fidelity, the admin-leak guard is the by-product). SCSS `$color-*` demote to build-time fallbacks (`var(--np-color-x, #{$color-x})`) — they are no longer the contract.
- **Tier-1 (user-tunable, ~12):** `brand`, `bg` (page; gap today), `surface`, `text`, `heading`, `text-muted`, `link` (in-content; gap today), `border`, + chrome `header-bg`, `footer-bg`, `nav-link`, `nav-hover`, `nav-active`.
- **Tier-2 (published to widget devs, NOT user-tunable):** `brand-contrast`, `success`/`error`/`warning`, `focus-ring`. The widget-dev contract surface is deliberately wider than the user knob set (keeps the Theme page calm; keeps widgets consistent).
- **The load-bearing 80% is the consumption audit, not the palette.** A widget that hardcodes hex or a `$color-*` literal receives neither the Theme default nor any override — the exact silent-failure class that consumed session 295. The arc's risk lives entirely in migrating widget SCSS to read `var(--np-color-*)`.

**Schemes (the content-region deviation mechanism).** Individual per-token override *is* the dark-mode footgun relocated. So templates **do not** edit content tokens. The Theme defines a tiny, contrast-vetted set of **schemes** (start: **Default + Inverse/dark**); a template **selects** one for its content region. Schemes apply to the page **content region only, not the theme chrome** (the standard header/footer always render in their own vetted colours; a dark landing page that wants a dark header *supplies* one — they compose, they don't bleed). Theme config (power user) may add named schemes; the page/template author only ever selects.

**Chrome model.** Per element, independently: **header slot** empty → theme header · filled → template's own · **"No header" checkbox** → none (wins even if filled). **Footer** the same, on its own. No per-template recolour path — chrome deviation is "supply your own, or none." Mostly formalisation of the existing `header_page_id`/`footer_page_id` + default-inheritance; the only genuinely new plumbing is the two suppression checkboxes and reframing "default template's header" as "the theme header."

**Delivery split (the session-295 lesson, encoded).** Theme defaults → compiled into the public bundle via `AssetBuildService`, scoped `.np-site` (drift-prone → covered by the 296 drift guard). Per-template scheme overrides → **request-time inline custom properties on a page wrapper**, resolved from the Template record — **never compiled into the bundle** (drift-proof by construction; this is exactly the stale-bundle drift that ate session 295). A **single shared resolver** (Template record → inline `--np-*` string) is called by *both* the public layout and the page-builder preview renderer — that one-resolver discipline is what guarantees preview fidelity (the reason `.np-site` exists).

**Naming/IA mismatches to fix along the way (catalogued so they are not lost):** "Theme" page is labelled as theme control but holds only type+buttons (no colour); brand colour lives on a template column with no site-wide home and silent default-template inheritance; page-templates vs content-templates conflated under one resource/name; "Header & Footer Code Snippets" also holds favicon + OG image; Stripe-checkout branding sits under CMS Settings (belongs in Finance); admin-chrome branding and public-site branding both called "branding"; template inheritance is real but invisible in the UI.

**Session breakdown (3–4 sessions; runs after 296):**

1. **297 — Theme Color Foundation** ✅ **closed at session 297.** Shipped: the `--np-color-*` tier-1/tier-2 contract (`docs/theme-color-tokens.md`) + concrete defaults; `ColorTokenResolver`/`ColorTokenCompiler`; Colors editor tab; `.np-site` bundle delivery; `$color-*` demoted to `var(--np-color-x, #{$color-x})` fallbacks + `--color-primary` aliased; relocation migration (6 `templates` colour columns dropped → `theme_colors` SiteSetting, byte-faithful, 0 non-default divergent); default-template-as-brand killed. Mid-session a DB-reseed wiped configured `typography`/`button_styles` (root-caused as a pre-existing DB-state loss, not 297; restored via a new seedable `DesignSettingsSeeder`) — forcing function for the **concrete-values resolver-defaults rule** below (the durable fix: sane non-zero `TypographyResolver` margin defaults, defaults-only, folded into 297). Detail: `sessions/297. … — Log.md`. *Not* widgets, *not* schemes/chrome (interim: no per-template colour 297→301).
2. **300 — Widget Color-Token Consumption Audit & Migration** ✅ **closed at session 300** (the arc's RISK session; renumbered 298→300 at session-297/2 — see the Numbering note below). Survey found the surface smaller than framed: `widget_types.css` DB column empty → the whole corpus is 14 filesystem `app/Widgets/*/styles.scss` files; 44 colour hit-lines (15 already token-backed via 297's `$color-*` demotion / `--color-primary` alias, 27 raw-hex silent-failure, 2 scope-fenced gradient stops). Migrated all 39 class-a + class-b lines across 10 files onto `var(--np-color-*)`; six semantic-mapping judgment calls user-confirmed; 5 reviewed scope-fenced exceptions remain. `tests/Feature/WidgetColorTokenConsumptionTest.php` is the objective oracle + permanent regression gate (in `DesignGroupIntegrityTest`'s pinned list). Fast suite 2465/0; 296 drift guard FRESH; three-surface real-browser computed-value verification (public token-resolve + live-override-follows, admin no-leak, preview faithful) — durable e2e at `tests/e2e/widget-color-tokens.spec.ts` via the isolated stack. One session, no Rule-11 split. Non-colour findings → housekeeping inbox. Detail: `sessions/300. … — Log.md`. *The silent-failure class is closed — 297's palette and 301's schemes now actually reach the widgets.*
3. **301 — Schemes, Template Page-Shell & Chrome, Shared Resolver** ✅ **closed at session 301.** Shipped: Default + Inverse content schemes (contrast-vetted, content-region only — never brand/chrome, compose-not-bleed); the per-template **scheme selector** + `no_header`/`no_footer` chrome suppression (migration, concrete defaults; suppression wins even with a page set; "the Theme header/footer" relabel); the **single shared `TemplateAppearanceResolver`** wiring public (`<main>`) + page-builder preview identically (request-time inline, never bundled, outside the 296 guard by design); template export/import carry-through of the new columns; the catalogued `bg`/`text` content-region gap closed (`.np-site main, .np-site.widget-preview-scope` consume the tokens — bundle-delivered, 296-guarded). Fast suite 2474/0; 5/5 three-surface scheme-switched real-browser e2e (`tests/e2e/template-scheme.spec.ts`); 296 + 300 guards green. One iteration, no Rule-11 split. Detail: `sessions/301. … — Log.md`. *Restored the deliberate per-page deviation 297 removed.*
4. **302 — Naming & IA Polish — Re-Taxonomy Tail** ✅ **closed at session 302** (arc-closing). Shipped, pure relabel/regroup/move/doc — zero behaviour, schema, or contract change: CMS-Settings page relabelled `CMS` → **`Site` / "Site Settings"** (kills the "CMS" double-meaning vs the CMS nav group; names the identity-&-defaults bucket); the mislabeled "Header & Footer Code Snippets" section split into accurately-named **"Site Icon & Social Image"** + **"Custom Code Snippets"** (same fields/keys); Stripe-checkout branding moved verbatim to `FinanceSettingsPage` as **"Checkout & Statement Branding"** (identical `SiteSetting` keys/collections/validation; now behind the `manage_financial_settings` gate — the intended, more-correct alignment); `TemplateResource` page-vs-content IA clarity (type badge surfaced by default + a Page/Content type filter; one resource, routes unchanged); `docs/theme-color-tokens.md` consolidated (stale 298/299 → 300/301; the now-false interim note replaced with the schemes/`TemplateAppearanceResolver` model — no token added/renamed). Fast suite 2476/0 (2474 + 2 net-new relocation-verification tests, deliberately not in the `design` group — `DesignGroupIntegrityTest` count unchanged at 19); no label-driven test edits required. An out-of-session CI/deploy fix was folded in at user request (the `app_vendor_e2e` named volume in `docker-compose.e2e.yml`, protecting the image-baked `vendor/` from the repo bind-mount in the CI Playwright job). One iteration, no Rule-11 split. Detail: `sessions/302. … — Log.md`. *Arc complete; `release-plan.md` § E6 fully retired.* **Note:** the *separate* theme/design export-import slice is **not** this tail — it pairs with the Media Portability draft into its own session; see the **Theme + Media Portability** stub below (drafted at 302 close as session 303, user-elected lift ahead of E8).

**Numbering (reshuffled at session-297/2).** The arc's *content* sessions are **297** (✅ done), **300** (widget consumption — RISK), **301** (schemes/chrome/resolver), + the optional tail. Sessions **298** and **299** are **not** arc sessions — they are the interleaved test-feedback-loop infra sessions, the early-lift of `release-plan.md` § D4 per the sanctioned Rule-11 exception (298 = scoped inner loop + async CI + close-gate shift; 299 = `--parallel` + test-isolation cleanup). Both have prompts drafted. Run order: **298 → 299 → 300 → 301** (298 deliberately slotted right before the RISK session 300, whose heavy build/verify cycles benefit most from the faster loop).

**Discipline carried from session 295 (rules, not guidelines):** verify in a *real browser by computed values* across public + admin + preview — never from emitted CSS/`curl`; Theme defaults in the bundle (296-guarded), per-page overrides request-time inline (drift-proof); the consumption audit (300) is the real work and the real risk; the token list is the agreed contract — do not re-derive it.

**Concrete-values resolver-defaults rule (added at 297 close; forcing function: the typography zero-margin defect).** Every design subsystem's code defaults must be *sane and non-broken*, never zero/empty as a proxy for "unset" — a fresh, unconfigured customer install must render correctly without any seed or stored row. Defaults-only: a stored `SiteSetting` row deep-merges over defaults; never sweep or rewrite stored config (the 295 em-rhythm-revert lesson). `ColorTokenResolver` is the reference shape (concrete non-broken defaults, no seed row needed); `TypographyResolver` was brought to it in 297. A widget hardcoding hex (300) is the same defect class one layer out.

---

### Theme + Media Portability *(✅ RESOLVED — closed at session 303)*

Shipped at session 303 — **both halves, both phases, one iteration, no Rule-11 split, nothing carried forward.** (A) `ContentExporter::exportDesign()` → `payload.design` + a deep-merge-over-defaults import pass that never sweeps (295 lesson) + `AssetBuildService::build()` trigger + a Theme-page Export/Import pair. (B) Phase 1: `BundleArchive` zip primitive (zip-slip + zip-bomb guards) + `ContentImporter` zip-vs-JSON detection + archive-first media resolution + queued export→stored-artifact→bell→gated-download + queued zip/JSON import (sync fallback removed) + sibling zip actions at the six content call sites; Phase 2: `exportMedia()`/`exportAllMedia()` → `payload.media` + posture-B explicit-id `importMedia()` (file at `{id}/{file_name}`, `media_id_seq` reset, collision skip-identical / warn-and-skip-divergent, orphan-owner parking, queued conversion regen) + Media Library export/import actions. `FORMAT_VERSION` stayed `1.1.0`; non-boundary v2.3.0; the one approved scope addition was the standard Laravel `notifications` table + Filament bell (decision #5's mandated delivery surface). The session-301 `scheme`/`no_header`/`no_footer` carry-through was confirmed intact (not rebuilt). Detail: `sessions/303. … — Log.md`. The (B) design source `sessions/media-portability-prompt-draft.md` is now spent (kept for provenance).

The original stub text — preserved for reference:

---

**Origin.** Surfaced at 300/301 planning when the user asked how hard theme export/import would be. Answer: cheap — "the theme" is already JSON. Two portability slices pair naturally into one session because both make the *working → demo* round-trip whole:

- **(A) Theme/design export-import** — the homogeneous skin is exactly three `design`-group JSON `SiteSetting` rows (`theme_colors`, `typography`, `button_styles`) **plus, post-301, the scheme model + per-template scheme/chrome selection on `templates`**. Export/import rides the *existing* `ContentExporter`/`ContentImporter` versioned-envelope + Filament-action pattern (low effort: data is already serialized JSON). Import must honour the **concrete-values rule** (deep-merge over defaults, never sweep stored config — the 295 lesson) and trigger a `build:public` rebuild (these keys feed the bundle). **Sequenced post-301 on purpose:** by then a "theme" = colours + typography + buttons + the new scheme model, so a complete theme exports, not a partial one.
- **(B) Media Portability** — *already drafted*: `sessions/media-portability-prompt-draft.md` (currently on branch `claude/design-media-export-HGt7T`, repo `abeuscher/npc-beta`; lands in `sessions/` when that branch merges). "Self-Contained Content Bundles + ID-Preserving Media Seed": wraps `ContentExporter` envelopes into a zip with the media *bytes* (Phase 1) + a standalone ID-preserving media seed (Phase 2). It explicitly leaves theme/design as a separate surface — **this combined session is where (A) joins it**, so a single portable artifact carries content + media bytes + the full theme.

**Not to be confused with two adjacent things:** (1) the arc's optional naming/IA polish tail (point 4 above) — different work; (2) the **mandatory** carry-through of 301's new `templates` scheme/chrome columns through `ContentExporter`/`ContentImporter` — that is a correctness obligation handled *inside 301* (template export/import must not silently drop the new fields), **not** this feature.

**Sizing.** (B) is the larger half (drafted as a 2-phase session of its own); (A) is a few-hours rider that becomes trivial once it can ride (B)'s zip envelope. Combined session may split per Rule 11 along the drafted (B) phase line with (A) folded into whichever phase carries the envelope work. Non-boundary; CRM stays v2.3.0.

---

### Media Path Generator — UUID Shape *(✅ closed at session 320 — content-addressed storage)*

Resolved at **session 320 (Content-Addressed Storage + Refcounted Delete).** The path generator no longer keys on `media.id`; it keys on `content_hash` (`cas/{hash[0:2]}/{hash}/{file_name}` via `ContentAddressedPathGenerator`), with a one-time `media:relocate-cas` relocation that moved every existing file and collapsed byte-duplicates. This subsumes the original UUID-shape goal (portable media no longer depends on the bigint id) *and* delivers disk reclamation + refcounted delete. The media trilogy (318 → 319 → 320) is complete. Original stub text retained below for reference.

The eventual robust posture-B shape: switch Spatie's `DefaultPathGenerator` from `{media.id}/{file_name}` to a UUID path generator (`{media.uuid}/{file_name}`), so portable media no longer depends on preserving the bigint `media.id` across installs. Session 303 deliberately delivered posture B via explicit-id preservation only — the UUID-path switch is a global change touching every existing media file on disk + a one-time relocation migration, explicitly out of 303's scope. Flagged here as the cleaner long-term form; not scheduled.

### Upload-time Dedup — Filament FileUpload Fields *(stub — post-Beta; deferred at session 319)*

Session 319 shipped upload-time dedup (content-hash + warn-and-offer) on the **page-builder** image surfaces and **rich-text inline images**, but deliberately deferred the **Filament `SpatieMediaLibraryFileUpload` resource fields** (post/event/product thumbnails + headers + OG images, `WidgetTypeResource` thumbnails, `PageResource` details). Reason: those are packaged form components with no idiomatic hook to interrupt a form-save with a blocking three-way "use existing / replace / keep new" choice; a real reuse *picker* there is a custom Livewire component replicated across five resources, and these fields are low-frequency single-purpose slots (one thumbnail per post), not the bloat driver the way page-building re-uploads are. The cheap interim form — a detection-backed inline *warning* on file-select (`afterStateUpdated` → hash → `MediaDedupService` → `Notification::warning`) — was scoped out with the picker. The backend `MediaDedupService` + `media-dedup-check` endpoint already exist and are surface-agnostic, so this stub is a front-end wiring job, not new infrastructure. Lower priority than the CAS/reclamation session.

---

### Rich Text Custom Fields *(complete — closed at session 250)*

`rich_text` custom-field type shipped at session 250. QuillEditor primitive on admin forms, HTML stored in the existing `custom_fields` JSONB column, render via `{!! !!}` matching every other rich-text surface. Importer auto-create + manual mapping both offer `rich_text` as a field type so HTML CSV cells round-trip cleanly. See `sessions/250. Rich Text Custom Fields — Log.md`.

### Rich-Text Surface Sanitization Hardening *(✅ closed at session 275)*

Carve-out from Code Review & Cleanup track Cycle 2's W4c/A flag, ratified at 273-close. Shipped one `App\Support\HtmlSanitizer` utility (DOMDocument-based, Quill-shape allow-list with arbitrary classes + structural HTML5 tags allowed for operator widget templates), 8 model-boundary apply sites with companion regression-guard tests, `ContentImporter::sanitizeWidgetConfig` extension for the import seam, Memos Trix→Quill convergence with one-time data migration, comprehensive 71-case allow-list test suite (round-trip-clean × strip-disallowed × XSS payload neutralisation). Mid-session bug fix: `SanitisesRichTextCustomFields` trait was querying with FQCN; fixed to `Str::snake(class_basename($model::class))` matching the codebase's lowercase short-name convention. See `sessions/275. Rich-Text Surface Sanitization Hardening — Log.md` for the full landing including the surface coverage table and security checklist.

The original stub text — preserved for reference:

---

Today every rich-text surface in the app stores Quill output (or Trix output, in the [Memos collection](app/Models/Collection.php) case) verbatim and renders via `{!! !!}`. [ContentImporter::sanitizeWidgetConfig](app/Services/ImportExport/ContentImporter.php) does collection-handle remap + media-id clearing but **not** HTML sanitization, so HTML in widget config and (post-250) `custom_fields` round-trips byte-for-byte through page export/import. Quill v2 strips `<script>` tags client-side but that is a UX nicety, not a server-side guarantee — direct DB writes, importer payloads, and any future bypass of the editor would land unsanitized HTML.

**Scope:**

- One `App\Support\HtmlSanitizer` utility with a Quill-shape allow-list. Tags: `p`, `br`, `strong`, `em`, `u`, `s`, `ol`, `ul`, `li`, `blockquote`, `pre`, `code`, `h1`–`h6`, `a`, `span`, plus the heroicon-shape `<svg>` children. Class allow-list restricted to `ql-*` regex (covers `ql-align-{center,right,justify}`, `ql-indent-N`, `ql-direction-rtl`, `ql-size-*`, `ql-font-*`, `ql-heroicon`). `data-heroicon` on `span.ql-heroicon`. `href` allow-list `http(s)`/`mailto`/relative. No new Composer dep.
- Apply on save at every model boundary that stores rich-text HTML: `PageWidget` config (rich-text-typed schema fields), `Collection` rich_text fields, `Note.body`, `Event.description` + `meeting_details`, `EmailTemplate.body`, `SiteSetting` rich-text keys, the new `custom_fields` rich_text values, dashboard `welcome_message` and `system_page_content_*`.
- Apply at the import boundary too — `ContentImporter::sanitizeWidgetConfig` walks each widget's schema and re-sanitizes any `richtext` field; `ImportProgressPage` (contacts/events/etc.) sanitizes any `custom_fields` rich_text cell.
- Convert the [Memos `body`](app/Models/Collection.php) editor from Filament's `RichEditor` (Trix) to `App\Forms\Components\QuillEditor`, converging on the dominant convention. One-time data-migration step for existing Trix-shape stored values to Quill-shape.
- Allow-list test suite — every Quill default-toolbar shape round-trips clean; every disallowed tag/attribute is stripped; representative XSS payloads (script tags, `javascript:` href, `on*` handlers, embedded SVG with script) are neutralized.

Folds in the previously-floated "Collection Rich-Text Editor Convergence" stub since the editor swap and the sanitizer rollout are most cleanly done together. Discovered during session 250's read-through of E3 — chose path A (match existing convention) for E3 itself rather than carve custom_fields out as a special-case sanitized surface while the rest of the rich-text surface stayed raw. This stub is the coherent successor.

---

### Mobile Type Scaling — Per-Breakpoint Sizes *(✅ shipped at session 295)*

Per-breakpoint `font.size` `{xl,lg,md,sm}` with a calibration-driven per-class ramp shipped at session 295 — non-destructive `SiteSetting`-shape migration (byte-exact at `xl`), unitless line-height that rides the size, em heading bottom-margin, `TypographyCompiler` `@media` emission, `DesignSystemPage` per-breakpoint fields, `scripts/type-oracle.js` objective oracle at 0 violations (5 pages × 5 widths). Mid-session the runtime inline-`<style>` delivery premise was found wrong: typography **and** buttons moved to the build pipeline `.np-site`-scoped, the real rem→px compiler-truncation bug was fixed, a misdiagnosed em-rhythm scheme was reverted, and a stale-bundle-vs-config drift was found (→ emergent **session 296**). Detail: `sessions/295. Mobile Type Scaling … — Log.md` and `release-plan.md` § E5. The broader theme/typography stabilization continues as the **Theme & Template Re-Taxonomy** arc (stub above).

Calibration ramp — *as shipped* (historical record; gong/attio/clay probe at s294; display phone settled at 0.60 Attio-leaning, not the 0.54 raw mean; section = midpoint of display/body; body 1.0):

| class | sm/phone | md ≤768 | lg ≤992 | xl ≥1200 |
|---|---|---|---|---|
| display / h1 | 0.60 | 0.75 | 0.85 | 1.0 |
| section / h2–h3 | 0.80 | 0.88 | 0.93 | 1.0 |
| body / h4–h6,p,li | 1.0 | 1.0 | 1.0 | 1.0 |
---

### Housekeeping — Batch 2 *(stub — pre-Beta 1, deferred)*

Items that didn't make Batch 1's working set — either scope-flagged (might exceed housekeeping size at scoping time), design-prerequisite (needs a sibling discussion or stub to land first), or lower demo / onboarding priority for the next iteration. Each can be lifted into a Batch 2 session whenever the timing fits, or promoted into a Batch 1 successor if it becomes more pressing. Same crispness rule — if any item turns out to need design-level conversation, lift it into its own stub rather than letting the batch absorb the cost.

- ~~**Text widget vertical alignment.** The `text_block` widget currently renders with text aligned to the vertical middle of its container, which doesn't fit all column-layout use cases. Add a vertical alignment control (top / middle / bottom) to the widget's inspector. Design call to resolve during the session: whether this lives on the widget itself (`align-self` on the wrapper) or as a generalized "widget vertical alignment" control that all widgets inherit — the latter is probably the right shape but the former is the narrower change. Deferred from Batch 1 because the design call is real and may push it past housekeeping size.~~ ✅ Shipped at session 246 with the per-widget shape (option a). Generalized version captured as the new "Universal Appearance — Vertical Alignment" stub in the post-Beta-1 CMS section.
- **In-app actions that should trigger a build.** Sweep the admin UI for actions that change files the front-end bundle depends on (theme SCSS editing on the Design System page, per-template `custom_scss`, any widget asset editing surface) and confirm each one either fires the matching build automatically or surfaces a clear "rebuild required" affordance to the admin. Unconfirmed whether any gap exists — this is an audit, not a known bug. Focus areas: theming, templates, design system, widget manager. Deferred from Batch 1 because audit-shaped work tends to surface findings that themselves want fixing in scope.
- **Dev-environment orphan media cleanup** *(reopened at session 252; original 247 fix reverted)*. The underlying bug is real: `migrate:fresh` truncates tables via raw SQL, bypassing Eloquent's `deleting` events — so Spatie Media Library's "delete file when model deleted" observer never fires, and every reseed cycle leaves the previous run's media files behind. With image conversions multiplying each upload by ~6× (responsive WebP variants), months of `migrate:fresh` cycles compound into multi-GB orphan piles. Concrete severity: session 245 UAT discovered `storage/app/public` had grown to **14.6 GB** for what was actually ~20 MB of distinct content — the rest was duplicated orphan files from repeated reseeds. Session 247 shipped a custom `app:reset` artisan command (refuses on production, wraps `migrate:fresh --seed` with the storage wipe). That fix was reverted at session 252 because its `->group('slow')` happy-path test ran the real `migrate:fresh --seed` from inside Pest, committing the seeded rows outside `RefreshDatabase`'s transaction wrap and breaking 86 downstream tests with a unique-constraint cascade. The artisan-command shape is structurally unfit for this work — any test that exercises `migrate:fresh` from inside Pest fights `RefreshDatabase`'s isolation. **Constraint for the next attempt:** the fix must not introduce a new artisan command that re-runs `migrate:fresh --seed` from inside the application. Possible shapes (not a recommendation — design space):  (a) **Shell script outside Laravel** — `bin/reset.sh` or similar that runs `docker compose exec app php artisan migrate:fresh --seed` and the storage wipe via shell `rm -rf`. No artisan surface, no test pollution. Loses the production-refusal gate but bash can do `[ "$APP_ENV" = "production" ] && exit 1` equally well, and the script can simply not be deployed to production images.  (b) **Hook into the migration lifecycle** — a `migrating` listener that walks the storage tree before raw-SQL truncation runs, calling Spatie's delete observers ourselves. Riskier shape: tightly couples our code to migration internals and may not catch every truncation path.  (c) **Move the public media disk to a Docker volume** that gets wiped via `docker volume rm` rather than artisan command — the operator's "fresh install" recipe becomes one Docker command instead of two. Larger architectural change but the cleanest separation of concerns: storage lifecycle is a container concern, not an application concern.  (d) **Tighten the operator recipe** — accept the multi-GB pile as the cost of `migrate:fresh` and document a manual cleanup recipe (`rm -rf storage/app/public/* storage/media-library/temp/*` after every reseed). The pain is real but small; users typing one extra command after `migrate:fresh --seed` is not a major regression. Pair with the existing daily `media-library:clean` schedule for the slow-drip case.  Out of scope for the eventual session: any fix that re-introduces an artisan command that calls `migrate:fresh` internally — that surface is closed.
- ~~**Heroicon picker in Quill editor.** Add a Quill custom format that lets users insert a heroicon inline in rich text (confirm icon set at session time — heroicons is the assumed set; verify against what the project actually ships). Small custom-format addition; not a feature blowup. Borderline housekeeping — if scope grows past "register one Quill format with a picker UI," lift to a sibling stub. Deferred from Batch 1 because feature additions tend to attract scope creep; revisit once the editor-bug fixes (list rendering + horizontal alignment) confirm the editor surface is well-understood.~~ ✅ Shipped at session 246. Quill v2 confirmed (CDN-loaded via AdminPanelProvider). Heroicon outline set (324 icons) sourced from `vendor/blade-ui-kit/blade-heroicons/resources/svg/o-*.svg` — no npm install needed. Blot + picker extracted into shared modules and wired into both the Filament admin Quill editor AND the page-builder Vue Quill editor (TextBlock, Hero, ThreeBuckets, BlogPager, Memos — every richtext field in either surface).
- ~~**Blog Post Pager — page context not resolving correctly** *(bug surfaced at session 245 UAT)*. The `blog_pager` widget renders on individual blog post detail pages and is supposed to expose previous/next post navigation by reading the current post's page context. During session 245 UAT (generating scrub blog posts and clicking through them), the pager renders but isn't receiving correct page context — needs investigation. Likely a contract-resolution / token-binding issue in the way `BlogPagerDefinition::dataContract` resolves against `RecordContextProjector`. Scope: identify the binding gap, fix or document, add a regression test that walks the pager surface for a post page. Out of scope for this stub: pager visual redesign or new pager features.~~ ✅ Shipped at session 246. Root cause turned out to be **not** a contract/projector issue — `BlogPagerDefinition::dataContract` already returns `SOURCE_SYSTEM_MODEL` correctly. The actual bug: `WidgetRenderer::render` reads `app(PageContext::class)` from the container, but `PostController::renderPage` (and `PageController::renderPage`) `View::share` the PageContext **without** binding it to the container — so production renders saw `currentPage = null` even though tests (which `app()->instance(...)`) saw it correctly. The BlogPager template is the only widget template that reads `$pageContext->currentPage` directly, so it was the only widget surface that exhibited the bug. Fix: reconstruct `$pageContext` from the resolved `$tokenPage` in `WidgetRenderer::render`. Single seam covers all four `WidgetRenderer::render` entry points (PageBlockRenderer, WidgetDemoController, WidgetPreviewRenderer, ChromeRenderer). Regression test at `tests/Feature/BlogPagerEndToEndTest.php`.
- **Widget DemoSeeder rows escape the scrub-data wipe** *(bug surfaced at session 246 manual testing)*. Each widget that declares `demoSeeder()` in its `WidgetDefinition` (EventCalendar, LogoGarden, Carousel, BoardMembers, BarChart, DonationForm) ships a class under `app/Widgets/{Name}/DemoSeeder.php` that writes fixture rows on "Seed Widget Collections" (the super-admin action shipped in 245). The seeders use `updateOrCreate` keyed on slug/handle so they don't multiply across runs, but they do **not** tag their rows with `Source::DEMO` (or `Source::SCRUB_DATA`), so the rows write with the default `Source::HUMAN` — the Random Data Generator's wipe filters by `source = scrub_data` and never sees them. Concrete consequence at 246 UAT: EventCalendar's three demo events (`demo-event-1/2/3`, "Bedrock Community Dig" / "Moose Lodge Annual Meeting" / "Annual Anvil Drop Gala") leaked into the production-ish events list and could not be removed via Wipe. Fix shape: tag each demo-seeder write with `Source::DEMO` (the right semantic — these are demo content, distinct from scrub-data which is "throwaway test data") and either (a) add a parallel "wipe demo data" path to the Random Data Generator, or (b) extend the existing wipe to include `Source::DEMO`. Plus: the parent `seedWidgetCollections — runs each widget definition that declares a demoSeeder` test in `tests/Feature/RandomDataGeneratorServiceTest.php` appears to occasionally leak rows past `RefreshDatabase` rollback when run as part of the full fast suite (rows confirmed in DB with timestamps matching the suite run, despite the test passing in isolation cleanly). Investigation slot for that flake belongs in this stub too — likely a side-effect from `Artisan::call('db:seed', ...)` interacting with the test transaction. Out of scope for this stub: the demo-seeder content itself; the icon library shipped with EventCalendar; any UX of the "Seed Widget Collections" action.
- **Default local-dev accounts per role** *(surfaced at session 276 manual testing)*. The Notes Permissions feature (session 276) and any session that exercises a non-super-admin permission gate (273 W8 #3 trait coverage, the upcoming C3 audit, donation/event role coverage) requires the user to hand-create a non-super-admin user via the admin UI just to confirm the gate behaves. The user has done this many times across past sessions with little durable benefit. Sweep: seed one user per shipped role (`cms_editor`, `crm_editor`, `event_manager`, `volunteer_coordinator`, `treasurer`, `blogger`, `developer`) with deterministic emails (e.g. `crm_editor@local.test`) and a fixed shared password sourced from `.env` (e.g. `LOCAL_TEST_PASSWORD`, defaulted in `.env.example`). Seeder should be **dev-environment-only** — refuse to run in production or staging (mirror the `app:reset` production-refusal pattern, but as a seeder-class guard) and live next to `DatabaseSeeder` so it runs as part of `migrate:fresh --seed`. Pair with a docs note in `docs/app-reference.md` listing the seeded accounts. Out of scope: cycling passwords; SSO test users; portal-account test users (separate concern — covered by portal-specific test fixtures already). Lift early if any cross-role session lands before Batch 2 runs — at that point a dedicated mini-session is cheaper than re-creating the accounts manually one more time.
- **Test-data hygiene sweep — repo + local environment** *(surfaced at session 258 Phase 1 sign-off)*. Ad-hoc test CSVs, scrub-data files, and dated customer exports have accumulated across `/mnt/e/Clients/nonprofitcrm/test_import_data/` and `storage/app/` (root-level CSVs, `fake-csvs-test-*` directories left from past test runs, etc.). The accumulation actively misled session-258's prompt-writing agent — the prompt assumed two real-customer scrubs were available based on directory state, when only one ad-hoc set actually existed. Sweep scope: (a) audit `/mnt/e/Clients/nonprofitcrm/test_import_data/` and decide which files keep, archive, or delete (this directory is outside the repo and persists across sessions); (b) audit `storage/app/` for root-level CSVs (`test-contacts.csv`, etc.) and stray `fake-csvs-test-*` directories that don't belong; (c) confirm `storage/app/import-test-fixtures/` and `storage/app/test-fixtures-runtime/` remain gitignored (they are per session 257 close, but verify). The set we *do* keep going forward is the G1-generated synthetic fixtures under `storage/app/import-test-fixtures/` (regenerated on demand from `import-fixtures:generate`) — no other test data should accumulate in either location. Out of scope: any `/tmp/` scratch (auto-cleared); any `tests/e2e/fixtures/` (committed test inputs, owned by their specs); the `fake-csvs` directory used by the seeded "Demo Fake Data" import sources (legitimate, owned by `csv:generate-fake-imports`).

---

### Page Builder Inline Editing *(arc — ✅ COMPLETE at session 307, 2026-05-20)*

Lifted ahead of the rest of the execution order at session-303 close (occupies the retired § E8 slot, position 38). Emergent forcing function: beta testers cannot author pages effectively with the current select-the-whole-panel + Inspector-only model. Grounded in two research passes (architecture + feature history) + a validated 40-widget safety pass — all folded into the session prompts as canonical (do not re-derive). Inline editing existed at session 137 (Livewire) and was lost by attrition in the 147–152 Vue rewrite; this is the fresh, stronger redo on the single `WidgetRenderer` render path — **not** the old 135–140 builder-vision (a non-starter). Drafted by external planning agents (2026-05-17), rescued from `claude/investigate-stale-stylesheet-ANZJt` and scheduled at session-303 close. The original two-session split (A=304 / B=305 terminal) **extended to four sessions at the 305 close** — the toolbar deliverable proved under-specified on first build; the user produced a complete visual + behaviour design contract (`docs/inline-formatting-toolbar-spec.md`) which 306 builds to, then 307 closes the arc with the parity guard + gate widening originally planned for 305:

- **Session A — 304: Inline Editing Foundation.** Interaction-model rework (real top-left hover-in drag handle; explicit top-right "Edit" affordance opening the Inspector; whole-panel selection removed); in-page contenteditable text editing on a safe widget set behind a **code-declared, per-node, opt-in capability gate** (`data-config-key`/`data-config-type` path annotations; never schema-derived; validated Tier-B exempt set + regression guard); a selection-scoped progressive-disclosure discoverability spec. **Phase 2b (simplified column/repeater control) deferred — replaced in 305 by a much simpler count-dropdown design.** Independently shippable; hard prerequisite for the rest of the arc.
- **Session B — 305: Inline Formatting Toolbar Foundation + Inspector Cleanup (PARTIAL vs original plan).** First-cut on-page formatting toolbar built and discarded as under-specified + lifecycle-buggy; user produced the design contract (`docs/inline-formatting-toolbar-spec.md`) and 305 became a foundation-laying session. Shipped: simplified pricing column control (count dropdowns + `'auto'` default → byte-identical on the deployed marketing page; collapse-and-keep) replacing the deferred 304 Phase 2b; Inspector hides content `text`/`richtext` for inline-editable widgets (RichTextField path kept for non-inline); public-vs-builder render conflation split (`WidgetRenderer` got an explicit `inlineEditing` parameter; public output no longer leaks editing markers); four of five spec §6 companion changes (HtmlSanitizer target/rel allow-list, `refreshPreview` centralised suppression, theme-font + page-URL bootstrap data, lucide-vue-next installed); buttons-inline-label reverted per owner decision (Inspector-only stays this session). The rebuild of the toolbar itself is **306**; parity + widening (the original 305 deliverables) are **307**.
- **Session C — 306: Inline Formatting Toolbar Rebuild.** Build the toolbar to `docs/inline-formatting-toolbar-spec.md` — every rule in §5, every visual in §4, every must-NOT in §M, every companion change in §6 (the one outstanding §6 item, ColorPicker arrow-key grid navigation, is included). Spec §A handle refactor (`{quill, hostEl, widgetId, configPath, getRect}`) + §A14 MutationObserver fallback in `useInlineEdit`. Eighteen controls, two popovers (link + colour), text-style dropdown with WYSIWYG-preview rows, overflow ladder, format-state reflection, full accessibility model. Hard-depends on the 305 foundation.
- **Session D — 307 ✅: Inline-Editing Arc Close (Builder↔Public Parity + Gate Widening).** Shipped both of the deliverables originally on 305 that 305 didn't run: a standing HTML-layer builder↔public render-parity guard in the `design` Pest group (`tests/Feature/BuilderPublicRenderParityTest.php`, 16 cases — complementary to the session-296 served-bundle drift guard, distinct layer); and the eligibility-gate widening from 4 widgets to 13 (added BarChart, BlogListing, BoardMembers, DonationForm, EventCalendar, EventsListing, MapEmbed, ProductCarousel, SocialSharing — each via `inlineEditable() = true` on its Definition + an `inline-prose` partial heading annotation; Tier-B exempt set stayed green throughout). Documented dual-purpose coupling on SocialSharing (heading also feeds share-link text — analogous to Logo.text/alt). Arc-closing. See `sessions/307. Page Builder — Inline-Editing Arc Close — Log.md`.

The arc is **done**. The on-page formatting toolbar exists, persists, looks right, behaves right, is accessible, and stays in sync with the live page across the 13-widget inline-editable set; the HTML-layer render of those widgets is byte-equivalent between builder canvas and public modulo three documented diffs; the eligibility gate is wide enough to cover every Tier-A-clear display-prose heading.

Canonical (all archived under `sessions/archived/` once the close-out sweep lands): `304. *`, `305. *`, `306. *`, `307. *` + `docs/inline-formatting-toolbar-spec.md` + the now-COMPLETE § E8 entry in `release-plan.md`. Non-boundary; CRM stays v2.3.0; no DB schema (the gate is declared in widget code).

**Successor concern, closed at session 308 ✅:** visual / computed-style parity at the rendered-DOM layer surfaced the Quill-injected double-bullet (TypographyCompiler emitted `list-style-type` on Quill items via `ol li`; carve-out `:not([data-list])` added), the canvas-vs-public typography specificity asymmetry (canvas scope dropped redundant `.page-builder` prefix), the Tailwind admin preflight `ol/ul` margin asymmetry (`.np-site` re-asserts `1em 0`), the active-editor double-bullet (`.np-site .ql-editor li[data-list]::before { content: none }`), and Quill snow theme leakage in the active editor (font/li-padding overrides + canvas typography scope bumped to `.ql-snow .ql-editor`). The bullet glyph itself was swapped to a CSS-drawn disc. BarChart + ProductCarousel demoted from inline-eligibility (`heading` removed; roster 13 → 11). The session's harness was diagnostic-only and torn down at close. See `sessions/308. Page Builder ↔ Public View — Visual Parity — Log.md`.

**Content-import/export arc — user-driven side ✅ COMPLETE at session 310 (2026-05-20).** Session 308 surfaced a write-conflict between the Theme/Typography editor and `ContentImporter::importDesign()` — both wrote to `SiteSetting::typography` with implicit-import-wins behaviour. Remediation shipped in two user-driven sessions: **309** ✅ — exporter `with_design`/`with_media` opt flags, `ContentImporter::analyze()` manifest, four-flag import opts (`merge_design` default flipped to FALSE per the session forcing function), single-page live-reveal import UI, four-option export menu on six surfaces, end-to-end queue-path guard; **310** ✅ — unified Import Site / Export Site rollup on a new Filament page under Tools (`SiteImportExportPage`, `manage_cms_settings` gate); `ContentExporter::exportSite()` wrapper + `'site'` kind on `ExportBundleJob`; Import half reuses 309's `ImportBundleAction` live-reveal verbatim via a parameterized-ability action signature. Four architectural calls resolved upfront in the 310 log: button-grade v1, no diff preview, per-entity collision keys pinned in the manifest, no `format_version` bump (stays at 1.1.0). **A001** (agentic backend extension to events, products, navigation, collections — first use of the `session-A###/N` async-agent branch convention) remains queued as a separate async-agent session; when it lands, the rollup automatically covers the four new entity families via the existing analyzer + `exportSite()` wrapper. See `sessions/archived/309. … — Log.md`, `sessions/310. … — Log.md`, `sessions/A001. base-prompt.md`.

---

### UI / UX Sprint *(stub — RETIRED at session-303 close; superseded by the Page Builder Inline Editing arc above)*

**Retired.** The two batched items were band-aids on the form-based / fixed-editor authoring model the inline-editing arc replaces: the Quill height handle solved "the fixed side editor is too small" (moot once you edit text at rendered height in place), and the page-builder full-screen toggle's premise is undercut by the inline/iframe model and was already substantially covered by session 156's admin-wide topbar fullscreen toggle. Not run as § E8; the slot is now the inline-editing arc (A=304 / B=305). Original stub text — preserved for reference:

- **Page builder full-screen toggle.** Add a toggle that maximizes the page-builder canvas + inspector to the full viewport, hiding the Filament admin chrome (sidebar, top bar, breadcrumbs) for the duration. Persists per-user via localStorage. Exit returns to the previous chrome state.
- **Adjustable height handle on the Quill editor.** The rich-text editor renders at a fixed height today; long content forces the wrapping page to scroll while the editor's internal area stays small. Add a drag-resize handle on the bottom edge so admins can grow the editor to whatever height fits the content they're authoring. Persisted height per-user via localStorage is fine — no schema change.

---

### Test Suite Audit — Cost, Coverage, and Shape *(stub — pre-Beta 1)*

**Iteration-speed + parallelization slices lifted early (sessions 298 & 299).** Per the sanctioned Rule-11 carry-forward exception in `release-plan.md` § D4, the inner-loop / async-CI / close-gate slice (session **298** — Test Feedback Loop — Scoped Loop & Async Verification) and the `--parallel` + test-isolation-cleanup slice (session **299** — Test Suite Parallelization & Isolation Cleanup) were carved out as standalone fix-shape sessions; run order 298 → 299, before the colour-arc RISK session 300. This stub / D4 retains **only** the mutation-proven pruning, assertion-density, and coverage-shape work — the *what-runs* questions, not the *when/where/who-waits/how-fast* ones.

**298 closed (session-298/1, 2026-05-17).** Shipped: `->group('design')` over a reviewed 17-file cluster + `DesignGroupIntegrityTest` explicit-list guard + `./dev test:design`/`./dev test` (scoped loop ~64s vs ~11 min); `tests.yml` hardened to fast → slow → aggregate `Tests` gate; **additive** close-gate wording in `template-base-prompt.md`+`CLAUDE.md` (block-and-wait retained as fallback, retirement staged); slow-group re-audit (1 outlier — the heavy `seedWidgetCollections` test — reclassified, 2465→2464 fast, zero coverage loss); `VERSION` 0.298.1. User enabled GitHub branch protection on `main` requiring the `Tests` check. **Open/deferred:** block-and-wait retirement + Open Q2 (CI-status surfacing) deferred to a 1–2 session parallel-run trial. **299 remains and was scope-expanded (user decision, session-298/1):** Pest `--parallel` + isolation **plus the Playwright fix — a second isolated Docker stack** so e2e runs collision-free locally and in CI (the post-parallelization long-pole; memory `project_playwright_ci_cost`, `sessions/299.` Phases 4–5 + Open Q3). May split per Rule 11 if too wide.

**Mutation-testing slice precedent:** session 241 ran the first mutation-testing pass against the Widget Primitive contract-resolution slice. Workflow doc shipped at [`docs/testing/mutation-audits.md`](../docs/testing/mutation-audits.md); slice findings + bucket distribution in [`sessions/241. Test Audit (Mutation Testing) — First Slice — Log.md`](241.%20Test%20Audit%20%28Mutation%20Testing%29%20—%20First%20Slice%20—%20Log.md). Outcome: 100% MSI, 0 surviving mutants, 18 N≥2 redundant cases override-kept with `// guards:` markers (no deletions). Future mutation passes (different module slices) reuse the same toolchain on a fresh checkout.

The Pest + Playwright suite has grown organically and takes ~5 minutes on the fast Pest pass + ~7 minutes on Playwright. Runtime itself is not the concern; the question is whether the time is **earned** — whether each slow test guards something worth the cost, whether coverage maps to real risk, whether test shapes match their subjects. This has been attempted before and produced mediocre findings because the judgment calls don't reduce cleanly to measurement.

**Why it's difficult, and how this session works around it.** Claude is strong at measuring (timing, counts, grep) and weak at judging ("is this test worth its runtime") without explicit rubrics. Previous audits failed because they asked Claude to audit itself — open-ended "find what's wrong." This session flips the pattern: measurement-first, then apply explicit rubrics, then human review of a findings table. The coverage-gap phase is the one Claude underperforms on, so it runs as "Claude checks a user-supplied surface list" rather than "Claude identifies the gaps."

**Three rubrics that convert opinion into measurement:**

1. **Runtime budget per test shape.** Unit: <50ms. Feature: <1s. Integration / DB-heavy: <3s. Anything exceeding its budget is auto-flagged as a finding — not auto-cut, flagged for human judgment.
2. **Assertion density.** Tests with fewer than ~3 assertions are flagged for review against siblings (possible redundancy). Tests with 20+ assertions are flagged the other way (doing too much, candidate for split).
3. **Setup-to-assertion ratio.** Tests where setup is >50% of the body are candidates for extracting setup into a shared factory/helper. Reducing shared setup often reveals N sibling tests that could collapse.

**Phases:**

1. **Measurement.** Run `pest --profile` (or Pest's equivalent timing output) and save as a baseline snapshot committed to the repo. Classify every test by shape (unit / feature / e2e) and subject area (admin / portal / importer / widgets / page builder / chrome / finance / auth).
2. **Rubric application.** Build a findings table — one row per flagged test — with columns: file, shape, runtime, assertion count, setup:assertion ratio, rubric(s) triggered. Add a "notes" column for Claude to flag obvious redundancy or shape mismatch (e.g. "Feature test that could be a unit test — has no DB, no HTTP call").
3. **Coverage gap pass.** User supplies a list of feature surfaces (admin pages, public routes, portal routes, importer services, widgets, page builder, chrome, finance flows). For each, walk through what would break if it regressed and whether a test exists that would catch it. **Claude scaffolds the table but cannot fill it alone — this is a collaborative phase.**
4. **User review.** Present findings + gaps. User picks which to act on: delete, speed up, extract helper, add missing coverage.
5. **Apply picks.** Each pick as its own commit. Re-run `pest --profile` to confirm the baseline shifted in the expected direction and capture the new snapshot.

**Deliverable:** committed baseline timing snapshot, a findings-and-gaps report in `sessions/NNN-test-audit-findings.md` (or similar), a set of applied picks, and measurably improved runtime or coverage (ideally both).

**Parallelization evaluation.** D4 also scopes whether to lift Pest's `--parallel` mode. paratest is transitively present in `composer.lock` (Pest's suggested dep) but not installed as a direct dev dep — `composer require --dev brianium/paratest` lands it. Approach: after the measurement + trim-pass phases, run the cheap experiment — install paratest, run `php artisan test --parallel --processes=4` once, log the failure surface. Failures map exactly to the test-isolation leaks that need fixing for parallel safety (the most concrete known one in this codebase: filesystem-shared paths under `storage/app/private/` like the `fleet/last-backup-at` integrity-record file from A1d / A1d', which need `Storage::fake()` or per-worker path-namespacing; plus the pre-existing `seedWidgetCollections` flake captured in the Housekeeping Batch 2 stub above, which parallelization would aggravate and needs to be squashed first or as part of this work). Decision rubric: (a) if D4's audit-driven trims already recover enough runtime that parallelization isn't worth the isolation-cleanup cost, defer parallelization; (b) if not, fold the isolation-cleanup work into D4 — or, if the surface turns out to be wide, lift it to a follow-on session per Rule 11. Realistic projection: sequential ~560s on the 265 baseline (~2136 tests); 4 workers ~200–250s; 8 workers ~150–180s with diminishing returns from per-worker Laravel/Filament boot cost. CI memory budget — the 256M → 1G bump (session 251 close) was sized for sequential; parallel may need another bump (4 workers ≈ 4× peak memory). Lift sooner as a standalone fix-shape session if iteration friction during the C-track rehearsal sessions starts costing real time before D4's slot lands.

Out of scope: Docker-exec overhead investigation (persistent shell vs fresh-exec-per-run) is fair game if it's an obvious win, otherwise defer.

---

### Column-Layout Mobile Collapse *(complete — closed at session 294)*

Shipped at session 294, run early and merged with the Swiper-widget mobile blanket + global overflow guards as one autonomous Playwright-verified pass. Column track emitted as a single `--layout-cols` custom property; `.layout-grid` collapses to one column via `@container (max-width: $bp-md)` gated on a renderer+Vue-emitted `data-collapse-mobile` attribute (concrete default-on bool). `container-type: inline-size` added to **both** `.page-layout` and `.widget-preview-scope` — neither had one (the original "preview already has it as of 207" note was drift), so a single container-query mechanism drives public and preview identically. `collapse_mobile` toggle on the Vue Column Settings tab (the sole layout-inspector surface — no Filament counterpart exists); round-trips via export/import and the two `layout_config` save allow-lists. Swiper widgets verified mobile-safe; ProductCarousel + BlogPager surfaced as carve-outs; ChromeRenderer's duplicate header/footer path deliberately left non-collapsing (tied to E16). Audit harness `scripts/audit-mobile.js` drove five public pages × five mobile widths to zero violations. The per-widget internal-grid concern (PricingChart et al.) was confirmed already shipped (PricingChart is the model, untouched). See `sessions/release-plan.md` § E7 (✅) and `sessions/294. Column-Layout Mobile Collapse + Swiper Blanket — Playwright-Verified — Log.md`. E5 (Mobile Type Scaling) was split out to session 295 — see the stub above.

---

### Full-Width Architecture Enforcement *(complete — closed at session 267)*

Closed at session 267, lifted alongside the two-knob split fold-in. The session shipped the structural enforcement and the background/content split as one piece because the schema/render-pipeline surfaces overlapped — splitting the toggle would have re-touched every site enforcement hardened. Outcome: single `full_width` toggle replaced by `background_full_width` + `content_full_width` on widgets (appearance_config), column layouts (layout_config), and `widget_types` per-type defaults (column-replace migration). Render pipeline collapsed the prior three full-width read sites into one helper in `AppearanceStyleComposer` (`resolveFullWidthForWidget` + `resolveFullWidthForLayout`) with column-child clamping and `(false, true) → (true, true)` normalization. The renderer separates layout appearance from grid display: `.page-layout` (bg) > optional `.site-container` (content) > `.layout-grid` (display). Bypass audit across all 38 widgets came back clean — no per-template CSS escape patterns; structural enforcement is satisfied entirely by the converged read path + the single emission point in `page-widgets.blade.php`. Editor parity gaps surfaced + closed in-session: `formatLayout()` ships a composed `inline_style` field; both Livewire bootstrap paths (`PageBuilder.php` + `RecordDetailViewBuilder.php`) gained `appearance_config` + `inline_style` on layout items so the editor renders correctly on first load; `LayoutRegion.vue` split into outer `.layout-region__container` + inner `.layout-region__grid` so the two toggles act on independent elements. Permanent regression coverage at `tests/e2e/page-builder/full-width-matrix.spec.ts` (20 Playwright specs). See `sessions/267. Full-Width Architecture Enforcement — Background and Content Split — Log.md` for the full landing.

---

### Widget Help Authoring & Help-System Integration *(complete — closed at session 277)*

Widgets vary in self-evidence — most are obvious from their name and inspector (`text_block`, `image`, `hero`), but some (`bar_chart`, `event_calendar`, `donation_form`, `web_form`, `event_registration`) have configuration surfaces complex enough to warrant explanatory help. Today the help system (`help_articles` + admin help-doc routes) lives separately from the widget definition pipeline. The session needs to resolve **how widgets carry their help info and how it surfaces**:

- **Where does help live?** Per-widget help could live as a markdown file colocated with the widget definition (`app/Widgets/{Name}/help.md`), as a `help` key in the widget definition class, or as a row in `help_articles` keyed off widget handle. Each has tradeoffs (versioning, authorability, search integration).
- **How does it surface?** A "?" affordance in the inspector that opens the widget's help inline, a link out to the help system, a tooltip-on-hover for individual config fields, or some combination.
- **What's the rollup story?** Some widgets need only a one-paragraph "this widget does X" entry; others need multi-section configuration walkthroughs. Open question: do we author one help entry per widget, or do we have a "widget catalog" page in the help system that everyone shares with per-widget anchors?

Bring concrete examples to scope: bar_chart's many `chart_config` knobs, event_calendar's filter rules, donation_form's amount-tier configuration. The session is design + landing the first 3–5 widgets' help entries to validate the chosen pattern. Out of scope: building a content-pipeline or auto-discovery tool; one-off authoring is fine for the first batch.

---

### Stripe Checkout Branding *(complete — closed at session 283)*

Closed at session 283. Audit at session start found the shared helper already lifted (all five Stripe Checkout call sites — Donation / Product / Membership / public Event / portal Event — already route through `StripeCheckoutService`); extended that service rather than building a new one. Per-flow `submit_type`, `custom_text` strings, `payment_intent_data.statement_descriptor[_suffix]` (payment mode only — subscription mode inherits from Stripe Account default per Stripe's API constraints), `consent_collection.terms_of_service` (gated on operator confirming Dashboard ToS URL), per-record / per-flow line-item images. Operator UI on `CmsSettingsPage` (gated by `manage_cms_settings`); manual-acknowledgement Onboarding Checklist item; operator help doc at `resources/docs/stripe-checkout-branding.md` walking both halves (Dashboard + in-app); widget-development.md addition for widget authors. Deploy-server tested with live Stripe. See `sessions/release-plan.md` § E4 (✅) and `sessions/283. Stripe Checkout Branding — Log.md`.

---

### Importer Mapping Page UX *(complete — closed at session 254)*

Closed at session 254. Three landed UX improvements applied as a shared pattern across all six mapping pages — per-row status indicator (red × / green ✓), reduced vertical sprawl via row wrap + Filament's native `inlineLabel()`, and `->searchable()` on each per-column Select. **Optional grouping by entity** was deliberately deferred — programmatic regex-shaped grouping is brittle without LLM/MCP-shape help; revisit on real importer-use feedback, not via stub-and-defer. See `sessions/release-plan.md` § E2 (✅) and `sessions/254. Importer Mapping Page UX — Log.md` for the full landing.

---

### Members & Membership Export — Round-Trip Audit *(stub — pre-Beta 1, surfaced at session 261 close)*

The 261 per-resource export pass deliberately scoped Members out of the 10-list shape and shipped Memberships as a flat list export. That leaves an asymmetry: bringing membership data **in** is well-supported (namespaced importer with tier resolution, contact-match keys, custom-field auto-create, `__org_member__` sentinel) but pulling it **out** in operator-meaningful shapes is not. The project's overall positioning leans on data portability — easy in, easy out — and the easy-out half of the membership story isn't there yet.

The friction surfaced when scoping whether to add an Export action to the Members admin surface ([`MemberResource`](app/Filament/Resources/MemberResource.php)). Members runs against the `Contact` model with `getEloquentQuery()->isMember()` and projects per-row membership data (tier name / status / member_since via the contact's first active membership). Two non-trivial problems with bolting an export onto it:

- **No clean round-trip target.** A Members export would carry contact columns plus the projected membership columns (tier / status / member_since). The Memberships importer wants those keyed on a Membership row, not a Contact-with-membership projection. Re-import becomes ambiguous on update-vs-create. The Contacts importer drops the membership columns entirely. Neither path round-trips.
- **Permission-gate split.** Members gates on `view_any_member`, distinct from `view_any_contact` and `view_any_membership`, so an export there would inherit a permission orthogonal to the source models the data actually comes from. Same shape of friction that surfaced when removing the Import-Contacts link from the contacts ellipsis menu post-261.

**Audit scope:**

- **Question inventory.** Operator-meaningful question shapes the system should answer: "who are my current members?" / "what's the membership history of contact X?" / "who renewed in 2025?" / "who lapsed in 2025?" / "who has tier X expiring this fiscal year?" / "what's the tier-mix breakdown over time?" Map each to current surfaces (Contacts, Members, Memberships, Contact → memberships relation manager) and current 261 exports. Identify which questions can be answered cleanly today and which require the operator to assemble multiple exports themselves.
- **Membership history per contact.** Contacts' edit page has a Memberships relation manager but no export from there; if an operator wants "Alice's full membership history" as a CSV, today they'd have to filter the Memberships list by Alice's email and export that. Decide whether that's good enough or whether per-contact membership-history export needs its own affordance.
- **Members surface decision.** Three options to weigh: (a) add an export to Members with a defined member-centric projection, accepting that it's a one-way analysis export with no round-trip — frame it that way explicitly in the UI; (b) skip a Members export and write a runbook entry teaching operators "to answer X, filter Memberships by Y and export"; (c) replace the Members surface entirely with a Contacts table filter ("has active membership"), eliminating the projection ambiguity at the cost of breaking the existing surface.
- **Permission-isolation discipline.** Whatever lands needs the permission gate to follow the data, not the surface. If the export carries contact data, gate on `view_any_contact`; membership data, `view_any_membership`. If it carries both, require both — same discipline that drove the 261 import-link removal.
- **Round-trip rule.** Any export shape this audit lands either round-trips through an existing importer without ambiguity, or is explicitly labelled "analysis export — not re-importable" in the UI. No quietly-one-way exports.

**Out of scope for this stub:** member-portal-side export (lives in F2 territory); soft-deleted membership history retrieval (its own can of worms — depends on retention-policy decisions); membership-tier-level reporting widgets (dashboard surface, not export). Out of scope for Beta-1 if the audit surfaces a meaningful redesign — the existing flat Memberships export from 261 covers the floor case for the migration-out narrative.

---

### Random Data Generator as Dashboard Widget *(complete — closed at session 245)*

Closed at session 245. See `sessions/release-plan.md` § A1 (✅) and `sessions/245. Random Data Generator as Dashboard Widget — Log.md` for the full landing.

---

### Fleet Manager — Node Operations Parity *(stub — pre-Beta 1; A2 in `release-plan.md`)*

A2 in `sessions/release-plan.md`. Re-opens the Fleet Manager Agent track for a specific Beta-1-blocking capability subset.

The v2.0.0 mTLS prerequisite shipped at session 248 — FM 012 absorbs v2.0.0 next; FM's affordance work begins at FM 013+. The CRM-side companion to the **log-reading** affordance shipped at session 251 (additive `/api/logs` endpoint; v2.1.0 contract). FM 012 absorbs both v2.0.0 and v2.1.0 together. The CRM-side agent contract is at v2.1.0; the four affordances FM 013+ delivers are now all unblocked CRM-side. The FM-side capability set needs:

- **Install** — provision a fresh CRM node from a clean droplet to a working install end-to-end.
- **Backup** — trigger and verify a backup against a node, surfacing failure modes the agent endpoint reports.
- **Restore** — restore a node from a backup blob (CRM-side has the manual procedure documented per sessions 242 + 243; FM-side needs the operator-facing equivalent).
- **Read logs** — fetch and surface application logs from a node without operator SSH. **CRM-side endpoint shipped at session 251**; FM 013+ consumes it.

Likely 2 sessions (install + backup + restore in one; log-reading separately, different surface). Per the plan's Rule 11, may split further.

---

### Multi-Node Operational Readiness *(stub — pre-Beta 1; A3 in `release-plan.md`)*

A3 in `sessions/release-plan.md`. Operational provisioning, mostly not code. Prerequisites: A2 substantially complete; E1 (Onboarding/Install Dashboard Widget).

Four nodes running on production by Beta-1: marketing site, demo install, test/deploy instance, spare-for-first-customer. Each node's purpose + URL + access creds documented. FM monitors all four. Test/deploy instance is the target environment for subsequent rehearsals.

---

### 2FA for Admin Accounts *(stub — pre-Beta 1; A5 in `release-plan.md`)*

A5 in `sessions/release-plan.md`. Foundational security feature; must close before C3 (Permission audit).

Admin login requires a second factor (TOTP via authenticator app) in addition to password. Recovery codes available at enrollment. Existing admin users have a one-time enrollment flow on next login. The FM-agent API key path is unaffected (it's not a user credential, per the contract spec). Tested across the standard Filament admin entry points. Help-doc entry on enrollment.

---

### Stripe Test-Mode Detection & Random Data Generator Production Guard *(stub — pre-Beta 1, release blocker; surfaced at session 245)*

The Random Data Generator (session 245) gives super-admins the ability to generate arbitrary financial-shape data (donations, transactions, memberships, registrations) tagged `source = 'scrub_data'`. This is safe in test installs and rehearsal environments, but **if the Stripe integration is configured against a real (non-test) Stripe account**, an operator clicking "generate donations" against scrub data could in principle create real Stripe customer/subscription IDs in the production Stripe account. The 245 generator does not attach real Stripe IDs to its scrub donations, but the architectural boundary is fragile — any future code path that pushes a scrub donation through Stripe (a rehearsal walkthrough, a misconfigured webhook, a copy-paste between environments) could touch real Stripe.

This stub: detect Stripe test-mode at runtime (Stripe API keys self-identify via `sk_test_` / `sk_live_` prefix; the API also exposes account metadata) and either:

- **(a) Hard refuse-to-render** — the Random Data Generator widget does not render at all when the install is configured against a real Stripe account.
- **(b) Warning + acknowledgment gate** — render the widget but with a strong visible warning and an explicit "I understand this install is connected to real Stripe" gate before any generation action is permitted.

Default lean is (a) for safety; (b) for ops flexibility. Decide at session time. Could pair naturally with a broader "production-mode safety" framework (hide all dev tools when not in test mode), but keep that scope question for the session.

Forcing function: shipped at session 245 close as a known gap. Release-blocker because production installs configured against real Stripe accounts could otherwise incur real Stripe activity from accidental scrub-data generation. Sequencing: should land before any production install is configured against a real Stripe account.

Related but distinct: the existing post-Beta-1 stub *API Key Pattern Validation & Test-Mode Warning* — that stub focuses on form-level validation and Stripe/QB environment-mismatch gates. This new stub is narrower (protect the random data generator specifically) and is pre-release-blocking because the generator ships in 245.

---

### Rehearsal Sessions

The pre-Beta-1 release gate is defined in `sessions/release-plan.md`. Each rehearsal becomes its own session; the plan doc carries success criteria, prerequisites, sequencing, and the artifact each rehearsal must produce. The 11 discipline rules at the top of `release-plan.md` govern how rehearsal sessions interact with the plan.

The stubs below are pointers — `release-plan.md` is the source of truth.

#### A4. DB wipe + backup recovery (Capsize drill — runbook polish)
See `sessions/release-plan.md` § A4.

#### B2. Onboarding rehearsal cluster *(closed at session 258)*
Closed at session 258. **Onboarding — Migration** help doc at [`resources/docs/onboarding-migration.md`](../resources/docs/onboarding-migration.md) (registered in the help system, category `tools`, linked from the main importer help page). Two new working-set entries lifted from the rehearsal: **B2a** (lift Contacts auto-mapping pattern to namespaced importers — the largest architectural finding) and **B2b** (Export CSV actions for non-Contact list resources — Migration-out structural gap). See `sessions/release-plan.md` § B2 / § B2a / § B2b for the canonical entries and `sessions/258. Onboarding Rehearsal Cluster — Log.md` for the full landing including the operator-internal methodology + measurements that don't belong in the customer-facing help doc.

#### C3. Permission audit + Concurrent admin editing + Accidental public exposure *(folded)*
See `sessions/release-plan.md` § C3.

#### C4. Donation-to-acknowledgment loop
See `sessions/release-plan.md` § C4.

#### C5. Event with everything *(scope slim at 282 audit)*
See `sessions/release-plan.md` § C5. Waitlist + per-event custom registration questions + day-of check-in + attendance log lifted to post-Beta at 282 audit; comp-tier polish lifted to C3c prereq stub.

#### C6. Membership renewal cycle *(LIFTED POST-BETA at 282 audit)*
Lifted entirely to post-Beta backlog. See the "Workflows — Post-Beta 1" section below; `release-plan.md` § C6 carries the tombstone.

#### C7. Email at volume *(DROPPED at 282 audit)*
Dropped entirely. Bulk emails go through Mailchimp via the existing webhook integration; Mailchimp-as-an-integration coverage absorbs into D3 (Integration retest).

#### D1. Scale rehearsal
See `sessions/release-plan.md` § D1.

#### D2. Compatibility cluster *(Browser bingo + Accessibility + Flaky connection — folded)*
See `sessions/release-plan.md` § D2.

#### D3. Integration retest *(absolute last rehearsal)*
See `sessions/release-plan.md` § D3.

---

## Infrastructure & Ops — Beta 1 Scope

**Pre-release requirements (non-session)** — tracked in `sessions/release-plan.md` § Pre-release requirements register:

- **Privacy policy live on marketing site** — drafts in process with counsel.
- **Terms of Service live on marketing site** — drafts in process with counsel.
- **Operator master runbook / SOPs** — DEFERRED DECISION: TBD whether this lives in this project or a separate non-technical project.

**Help docs needing body content written** (stubs exist with frontmatter + route mapping):

- `resources/docs/generate-tax-receipts.md` — Generate Tax Receipts page

**Known dev-environment fragilities** (Docker Desktop / WSL2):

- **Single-file bind-mounts can break their own snapshot path on host-side state changes.** `docker/nginx/default.conf`, `docker/nginx/prod.conf`, and `docker/php/local.ini` are all single-file bind-mounts in `docker-compose.yml`. Docker Desktop on WSL2 maps each into a content-addressed snapshot at `/run/desktop/mnt/host/wsl/docker-desktop-bind-mounts/Ubuntu-24.04/<hash>`. When the host inode shifts — Edit-tool atomic-replace, Docker Desktop restart, WSL2 distro restart, Windows update, certain `git checkout` flows — the running container's mount points at the now-defunct snapshot hash. The container keeps running with stale content, but the next `docker compose restart <service>` surfaces it as an OCI mount error: *`unable to start container process: error during container init: error mounting ... no such file or directory`*. **Failure signature:** the dst path in the error matches one of the three single-file mounts. **Fix:** `docker compose up -d --force-recreate <service>` (recreates the container, which reads a fresh snapshot path from Docker Desktop). **Do not retry `restart`** — it will fail with the same error every time. Directory bind-mounts (`./:/var/www/html`, `./nginx-certs/:/etc/nginx/certs`) are unaffected — only single-file mounts have the inode-detachment failure mode. **Long-term fix candidate** (not yet scheduled): convert the three single-file mounts to directory mounts (`./docker/nginx/:/etc/nginx/conf.d/`, `./docker/php/:/usr/local/etc/php/conf.d/`) to retire the failure class; deferred since the workaround is well-known and the conversion has its own scope. Memory note: `feedback_bind_mount_edit_breaks_container.md`.

---

## Public Styles & Form Controls — Beta 1 Scope

---

## Test-Data Generation Infrastructure — Beta 1 Scope

Phase G in [`release-plan.md`](release-plan.md). Multi-session phase for generating adversarial fixtures the importer is tested against. Lifted at session 256 close: the project has only two real-world data sets, both repeatedly scrubbed-and-re-imported, neither generating new findings any more. Real data has stopped paying for itself as a test input. Adversarial generated fixtures expand coverage without privacy concerns.

Track scope (pre-Beta-1):
- **G1** — CSV foundation: per-importer fixtures across five shapes (clean / messy / corrupt / pii / stress), source-preset variance, encoding variance, manifest sidecars, parametrized Pest runner. Lands before B2.
- **G2** — Cross-importer matched pairs, re-import replay pairs, adversarial dedup, false-positive PII coverage. Lands late-cycle (before D4).

Format extensions beyond CSV — XLSX, JSON, source-system-specific shapes — live in the Post-Beta-1 section (see "Salesforce export shape support" below).

### G1 — Importer Test-Fixture Generator: CSV Foundation *(complete — closed at session 257)*

Closed at session 257. See `sessions/release-plan.md` § G1 (✅) and `sessions/257. Importer Test-Fixture Generator — CSV Foundation — Log.md` for the full landing. Artisan command `import-fixtures:generate` ships with all seven importers × five shapes × the three presets `FieldMapper::presets()` actually exposes (Neon dropped during scope-out — preset doesn't exist in code). `App\Services\Import\FixtureRunner` lifted to drive importers off-Livewire. Parametrized Pest runner in `tests/Feature/Generated/`. Authoring doc at `docs/runbooks/import-fixture-generator.md`. Pre-existing fix lifted in-session: Org + auto-created Contact creation paths now write "Imported from X" timeline notes (was missing in four places). Findings recorded for B2 to inherit without re-discovery.

### G2 — Importer Test-Fixture Generator: Cross-importer Pairs, Replay, Adversarial Dedup *(stub — pre-Beta 1)*

Three additional fixture-set modes layered on G1:

- **`--pair=cross-importer`** — coordinated CSV sets where contacts.csv + donations.csv + memberships.csv reference the same external IDs end-to-end. Tests `ImportIdMap` linkage across importers. The org-as-source sentinels (`__org_contact__` / `__org_donor__` / etc.) get a real cross-importer test bed.
- **`--pair=replay`** — pass-1.csv + pass-2.csv pairs for re-import dedup-strategy tests (skip / update / error / duplicate). Manifest describes per-row dedup expectation: "row 3 matches by external_id, will be skipped"; "row 5 is net-new, will be imported"; "row 7 fills blanks under update strategy and the resulting field values are X / Y / Z."
- **Adversarial dedup fixtures** — match keys differ only by case / whitespace / NBSP ( ) / zero-width-space (​). Hardens the case-insensitive trim path. Today the importer uses `LOWER(TRIM(...))`; adversarial fixtures verify it actually catches what it should.
- **False-positive PII coverage** — rows that look PII-shaped but should not be rejected (e.g., a 9-digit external ID in a column the scanner shouldn't apply SSN rules to). Without false-positive fixtures, scanner tightening over time pushes toward over-rejection and you only notice when a real customer's import gets blocked.

Pest runner extended to consume pair manifests and assert dedup behavior.

## On-Demand E2E Coverage — Beta 1 Scope

Phase F in [`release-plan.md`](release-plan.md). Pre-T1 deep Playwright sweeps for surfaces that don't earn full regression-suite coverage but want a one-shot validation pass before release. Each session lands a `tests/e2e/{area}/` spec set tagged `@on-demand`, runnable via `npm run test:e2e:on-demand`. Default `npm run test:e2e` runs exclude these specs.

Track introduced at session 256 close after the Organizations importer's deep Playwright pass surfaced two pre-existing bugs that earlier per-importer tests had missed (a `serializeColumnMaps` regression silently dropping custom-field columns; a Choices.js `selectOption` pattern broken across multiple importer specs). Pattern is: deep, fixture-heavy, judgment-led — better suited to occasional sweeps than per-merge regression. Three slots on the working set: payments, portal, role gates.

### F1 — On-Demand E2E: Donation / payment-flow integration depth pass *(stub — pre-Beta 1)*

Public donation form → Stripe test-mode checkout → webhook → `Donation` + `Transaction` records → tax-receipt email content. Specs simulate signed Stripe webhook payloads and verify idempotency under retries.

Coverage matrix:
- One-time donation happy path (Donation row + Transaction row + receipt email body matches donor name + amount + date verbatim).
- Recurring subscription start (Donation with `frequency='monthly'`, `stripe_subscription_id` set, `started_at` populated).
- Partial refund (Stripe webhook `charge.refunded` with partial amount → Donation status update + corrected receipt sent).
- Full refund (full-amount webhook → Donation status update + corrected receipt sent).
- Failed-payment retry (Stripe webhook `invoice.payment_failed` → no Donation row, alert/admin signal lands).
- Stripe-cancel redirect (donor lands on cancel URL → no Donation row, no receipt sent).
- Webhook-replay idempotency (same webhook event delivered twice → same Donation row, no duplicate; verify on `stripe_id` + `external_id` uniqueness paths).

Stripe is already running in test mode; webhook signing setup is the new infrastructure. Heaviest of the three F-track sessions because of the Stripe plumbing.

Prerequisites per release plan: C4 (donation rehearsal), E4 (Stripe Checkout Branding), D3 (so the surface as it ships is what gets exercised). Artifact: spec suite + `docs/runbooks/payments-on-demand-coverage.md`.

### F2 — On-Demand E2E: Member portal self-service & contact-scoping security *(stub — pre-Beta 1)*

The CLAUDE.md portal-security rule — every portal route and query strictly scoped to the authenticated portal user's own `contact_id` — is the load-bearing invariant. This session walks each portal route from two authenticated contact fixtures (Alice + Bob) and asserts data isolation under URL-fishing attempts.

Coverage:
- Cross-contact isolation: Bob authenticated, attempts to view `/{portal_prefix}/donations/{alice_donation_id}`, `/memberships/{alice_membership_id}`, `/registrations/{alice_event_registration_id}` → 403 / 404 / redirect-to-own-account, never Alice's data.
- Password reset flow: token generation → email send → reset link click → password set → login with new password.
- Email verification: signup → unverified state → verification email → link click → verified state.
- Address update + own-record edit boundaries (Alice can edit her own profile but not Bob's via direct route).
- Token expiration + double-use rejection on password-reset and email-verify tokens.
- `portal_prefix` setting change mid-session: routes adapt, old URLs 404 cleanly.
- Signup → email verify → first-login flow lands a clean `PortalAccount` + `Contact` pair (no orphaned rows on partial-completion paths).

Prerequisites per release plan: C3 (permission audit informs the scoping invariant), A3 (production-shape install for portal mail flows). Artifact: spec suite + `docs/runbooks/portal-security-audit.md`.

### F3 — On-Demand E2E: Permission / role-gate matrix *(stub — pre-Beta 1)*

C3 produces a permission matrix at `docs/runbooks/permission-matrix.md`. F3 mechanically validates that matrix: each role fixture (super-admin, staff-admin, board-read-only, volunteer, public-visitor) walks the admin surface and asserts the matrix's documented expected outcome for every (role × resource × action) cell.

Coverage:
- Role-fixture-driven Playwright sessions: log in as each role, hit each Filament resource list / create / edit / delete page, attempt each row-level action (approve, rollback, force-delete, etc.).
- Both UI assertions (button visible / hidden / disabled) and controller-layer assertions (POST to the resource's action URL → 403 vs 200 as expected).
- Findings that contradict the documented matrix: matrix wins for the in-session code-level fix; Playwright tests confirm the corrected gate.
- Smaller C3 findings deferred under Rule 2 land here as proper code fixes.

Prerequisites per release plan: C3 (matrix doc lands first), E14 (no further structural changes pre-T1). Artifact: spec suite + delta entry in `docs/runbooks/permission-matrix.md`.

---

## User-Testing Plans — Beta 1 Scope *(stub — placeholder for future lift)*

Real-user testing of the onboarding flow, run by people outside the project. Distinct from B2's Claude+Playwright rehearsal: B2 measures system-savvy operator effort (a useful lower bound), this stub plans the honest naïve-operator measurement that B2 explicitly does not claim to be. Lifted at session 257 close after surfacing the methodological framing for B2.

**Why this is separate from B2:**
- B2 is Claude-driven with full system knowledge — its "manual cleanup %" reflects what a code-aware reviewer would call cleanup, not what a fresh operator would.
- Real users face an unfamiliar surface, hit affordance-discoverability issues that Claude's code-aware steering doesn't reproduce, and bring outsider judgment ("does this feel safe to point a real customer at?") that the rehearsal can't.
- The B2 playbook seeds the user-testing scenarios — once the playbook lists the four sub-scenarios and their edge cases, those scenarios become the user-testing task list.

**Shape (TBD — refine before lifting to release-plan):**
- Small focused tasks (5–15 minutes each): "import this contacts file and tell me what you'd do next," "export a report and tell me what's confusing," "set up a custom field and import data into it."
- Sample data: anonymized real-shape exports + G1-generated `messy` fixtures, depending on the task. Filename + content sanitized so the user isn't primed about what to look for.
- Open-ended evaluation prompts — "where did you get stuck?", "what did you expect to find that wasn't there?", "what felt like it was working against you?".
- Recruit from existing network — friends + colleagues comfortable with admin-CRM tooling but without codebase familiarity. Likely 3–6 testers per task.
- Synthesis: collect findings into a delta-doc against the B2 playbook.

**Out of scope right now (resolves before lifting):**
- Recruiting + scheduling logistics.
- Compensation / reward structure.
- NDA / data-handling considerations for non-employees handling sample data.
- Whether testing happens before or after Beta-1 ships (likely some of each — pre-ship for tractable affordance issues, post-ship for the longer-tail discoverability questions).
- Whether to commission a small honorarium budget for testers' time.

**Lift trigger:** once the B2 playbook ships and the four sub-scenarios have documented operator narratives, this stub gets refined and lifted into `release-plan.md` as a new working-set entry — likely a Track-B follow-on.

---

## End of Roadmap — Beta 1

### Onboarding / Install Dashboard Widget *(complete — closed at session 249)*

Closed at session 249. See `sessions/release-plan.md` § E1 (✅) and `sessions/249. Onboarding-Install Dashboard Widget — Log.md` for the full landing.

### Third-Party Licensing Compliance Audit

Before Beta 1 ships: audit all third-party dependencies for license compliance. Known items requiring verification:
- **Swiper.js** — MIT license (copyright Vladimir Kharlampidi). MIT requires the copyright notice and license text be included in distributions. Verify the Vite build or a LICENSES file satisfies this. Swiper Studio (no-code builder) is a separate paid product — confirm we are not using any Studio-only assets.
- Review all other npm and Composer dependencies for license compatibility with a commercial product.

---

## ── BETA ONE ─────────────────────────────────────────────────────────────────

**Beta One** is the first shippable, publicly demonstrable version of the product. Definition of done: a live hosted site is running on the product's own CMS, and a live install demo can be performed during a sales pitch — prospect names a company, picks a logo, imports contacts from a competitor, and receives a URL with their configured install at the end of the meeting. All sessions above this line are planned for Beta 1 delivery.

---

## Post-Beta 1

### Offboarding — Migration Out Guide *(post-Beta 1, surfaced at session 258 close)*

Customer-facing companion to the **Onboarding — Migration** help doc shipped at session 258 ([`resources/docs/onboarding-migration.md`](../resources/docs/onboarding-migration.md)). Covers the workflow of pulling all of an organization's records back out of the system in preparation for switching providers — a separate concern from migrating in, with its own considerations. Lands as a `standalone` help-system doc parallel to the onboarding doc.

Scope (refine before lifting):
- **Record extraction.** Walk-through of the export actions across each list resource. Depends on **B2b** (Export CSV actions for non-Contact list resources) shipping — this guide describes the operator-visible behavior that B2b creates.
- **Settings handoff.** How to keep settings in adjacent integrations intact during a switch — payment-provider records (Stripe customer + subscription IDs that live alongside CRM data), email-tool subscriptions (mailing list memberships in MailChimp / Resend / etc.), QuickBooks-side ledger continuity. What stays where, what migrates with the data, what the receiving system needs to know.
- **Financial records continuity.** Smooth handoff of donation history, recurring subscriptions, refund records, and tax-receipt history. What date ranges to export, how to handle in-flight subscriptions, how to communicate the cutover to donors.
- **Timing + sequencing.** When to freeze writes in the source system, how to test the receiving system before cutover, how to handle late-arriving data (donations that show up after the export).
- **Out of scope.** This is a customer-facing guide, not a destructive-action runbook — actual data deletion / account closure / billing termination is a separate concern.

Not a release gate. Schedules whenever the offboarding workflow earns operator focus, ideally after at least one real-customer offboarding has happened so the guide reflects lived experience rather than theory. Forcing function: a customer asks how they'd leave.

### Test-Data Generator — Salesforce Export Shape Support *(post-Beta 1, deferred from Phase G at session 256 close)*

Salesforce / NPSP exports are a high-value source preset for the importer fixture generator (Phase G). Salesforce's export format is publicly documented (their Reports/Data Loader output schemas are stable and well-defined), so adding a `salesforce_npsp` preset to `import-fixtures:generate` is a tractable post-release addition without depending on us getting our hands on a real export.

Scope:
- New `--source-preset=salesforce_npsp` option emits CSVs with NPSP-canonical headers (`Account.Name` / `Contact.FirstName` / `npe01__One2OneContact__c` / `npo02__OppAmount__c` / etc.) for each importer that has an NPSP analog.
- Preset-specific quirks captured in fixtures: NPSP's compound name fields ("Account: Account Name"), Opportunity-as-Donation mapping shape, recurring-donation flat shape vs subscription shape, household-account vs one-to-one-contact account models.
- `FieldMapper` gets a `salesforce_npsp` preset entry mapping NPSP headers to canonical importer destinations, so a real Salesforce export auto-maps without manual user intervention.
- Pest runner extended with `salesforce_npsp` cases for the same five shapes as G1/G2.

Deferred post-release because (a) the NPSP integration itself is post-release per project priorities, (b) the value of NPSP-shape coverage is largest when sales conversations against Salesforce-using prospects ramp up, which is post-Beta-1, (c) the publicly-documented format means we can author this entirely from spec without waiting for a sample export.

If a real NPSP export shows up in our hands before this session schedules, the shape gets confirmed against reality before being landed (specs and reality diverge in edge cases on most platforms).

### Financial Data Origin & Lifecycle Discipline — Phases B and C *(deferred to post-release at session 244)*

Phase A complete (session 233): `source` column live across all four financial tables (`donations`, `memberships`, `event_registrations`, `transactions`); `HasSourcePolicy` applied; every write path source-aware. Remaining phases deferred to post-release per session 244 vetting:

- **Phase B — Admin gating generalization.** Lift the `! $record->stripe_id` action-visibility pattern from `TransactionResource` into a shared trait keyed off `source`. Apply to Donation and Membership Resources where action surfaces exist or are about to exist. Update Transaction's existing gates to read from `source` rather than `stripe_id` so the same predicate covers manual transactions correctly. No new actions land in this phase; this is the surface that future Refund/Cancel/Sync actions consume.
- **Phase C — QuickBooks sync origin-awareness, email-trigger origin-awareness on admin-driven bulk send actions** (donation receipts, renewal nudges, dunning). Lands when those features arrive.

### Disk-Capacity Attribution & Cleanup Discipline *(stub — post-Beta 1, surfaced at session 262 prompt-write)*

The Fleet Manager contract surface already reports a whole-volume disk subcheck via [`HealthController::checkDisk()`](app/Http/Controllers/Api/Fleet/HealthController.php) — `disk_free_space('/')` / `disk_total_space('/')` with thresholds at 80% (yellow) / 95% (red), delivered through `/api/health` and surfaced fleet-wide by FM. That's the floor — operators get a master alert before the disk fills. What's not in place yet is **source attribution** (when the master alert trips, what's filling the disk?) and **scheduled cleanup discipline** (the only scheduled job today is `events:send-reminders` daily; nothing prunes anything).

The forcing function: long-running operator installs accumulate transient state in several directories that have no enforced budgets and no scheduled sweepers. Likely accumulators: `/tmp/xlsx-*` (XLSX export temp files, post-262 — try/finally cleanup is load-bearing but exceptions during streamDownload's late-stage `readfile` could still leak); `storage/app/import-test-fixtures/` (G1 fixture generator output, retained per-shape per-seed); `storage/framework/cache/` and framework views; backup blobs from `bin/` scripts that didn't unwind on partial failure; media library orphans (the issue resolved earlier in the project that the user has flagged historically — kept this concern visible); Laravel log files under default rotation.

**Scope:**

- **Source attribution in the FM contract surface.** Either (a) extend the `disk` subcheck with a `breakdown` field listing top contributors by directory size, or (b) add a sibling subcheck (`disk_breakdown` or `storage_buckets`) that lists per-area usage. Option (a) keeps the contract narrow but makes the disk subcheck heavier; option (b) keeps subchecks single-purpose at the cost of adding a second subcheck. Decide at session start. Contract bump required either way (additive — minor version per the protocol).
- **Scheduled cleanup commands.** Audit the directories that accumulate state and write artisan commands to sweep them on a sane cadence: `imports:prune-old-fixtures` (drop test fixtures older than N days), `temp:sweep-xlsx` (drop `/tmp/xlsx-*` older than 1 hour, in case streamDownload exceptions slipped past `try/finally`), `media:remove-orphans` (re-run the historical orphan-cleanup as a scheduled job, not a one-shot), `cache:prune-stale` if Laravel's built-in doesn't already cover it adequately. Each command idempotent + logged + permission-checked. Wire to `routes/console.php` Schedule with `->daily()` or `->weekly()` per cadence.
- **Per-directory budgets.** Define soft thresholds per accumulating directory (e.g. `import-test-fixtures` capped at 1GB — warn beyond; `/tmp/xlsx-*` capped at 100MB — auto-sweep beyond). Surface these as additional yellow-status signals before the whole-volume threshold trips, not as hard quotas (no operator wants their export blocked at the kernel level by a soft-budget check).
- **Failure-mode test discipline.** For every cleanup command, a Pest test that injects rows/files past the budget and asserts the sweeper does the right thing. The 262 cleanup-on-exception test for the XLSX writer is the pattern; this stub generalizes it across the cleanup commands.
- **Operator runbook.** A `docs/runbooks/disk-capacity.md` doc explaining what each sweeper does, what the thresholds mean, how to pause/resume scheduled cleanup, and what to do when the FM master alert trips (correlate to the breakdown subcheck → identify the accumulator → run the appropriate sweeper manually if needed).

**Open questions for whoever picks this up:**

- Whether the FM contract bump for source-attribution is additive (minor) or warrants a major bump for clarity. Given the protocol's "Last boundary-touching session" discipline, a clean minor bump per the additive-changes rule is most likely correct.
- Per-area thresholds: 1GB / 100MB / 5GB are starting guesses, not load-bearing. Tune with operator-test feedback once the breakdown subcheck is live.
- Whether media-orphan cleanup belongs in this stub or stays separate — historically orphan-detection has been treated as a content-discipline question (the resolved earlier incident); from a disk-capacity lens it's just another accumulator and folds in cleanly. Lean: fold in.
- Whether to introduce a generic "scheduled-task framework" (per-task-enabled flag in settings, last-run timestamp surface, manual-trigger UI for super-admins) or keep each sweeper as a standalone scheduled command. The framework path is bigger; the standalone path is faster. Lean: standalone for first round; lift to framework when there are 5+ sweepers.

**Forcing function — not currently named.** Lands when (a) an operator install actually trips the master disk alert and the post-mortem reveals which accumulator was responsible, (b) the Fleet Manager dashboard wants per-area usage breakdowns to make the disk subcheck actionable rather than just "something is filling up," or (c) the disk-leak scenario the user has historically flagged recurs in production despite the per-session try/finally discipline.

Not Beta-1 blocking — the master threshold subcheck covers the floor. Lifts post-release if any of the forcing functions surface; otherwise tracks alongside other operational-polish work.

---

### CRM-side Super-Admin Audit Sink *(stub — post-Beta 1, surfaced at session 253)*

Surfaced as a follow-on to the FM Security Posture Pivot's audit-sink discipline (CRM session 253). FM's pivot ships an external append-only audit log of FM-driven actions against the CRM contract surface, mirrored to a write-only object-locked Spaces bucket FM cannot delete from after write. The natural complement is a CRM-side audit log of human-operator super-admin actions on the CRM admin itself — login events, permission changes, sensitive-data exports, irreversible deletions, role/team changes.

Different problem from FM's sink: different threat model (CRM admin compromise vs FM compromise), different consumers (CRM operators reviewing post-incident vs FM-fleet-wide oversight), likely the same write-only-object-lock storage pattern on a separately-keyed bucket.

Open design questions for whoever picks this up: which action set is in scope, retention policy, query/access surface for operators, how it interacts with any existing Filament action logging, whether the bucket lives per-install or fleet-shared, redaction discipline for actions that touch sensitive payloads.

Forcing function — not currently named. Lands when (a) CRM gains multi-admin operations (currently single-operator per the FM track doc's Stance section), (b) compliance/auditability becomes a sales-driven requirement, or (c) a CRM-side security incident reveals gaps in existing post-mortem capability.

Not Beta-1 blocking. Conflation with the FM pivot at session 253 was deliberately avoided to keep both halves narrowly scoped — FM-side audit covers FM-driven actions; CRM-side super-admin audit covers human-operator CRM admin actions. Both sinks live; they don't collapse into one.

### Event Description Widget Removal → PageContext *(deferred to post-1.0 at session 244 — refactor; no rehearsal forces it)*

Replace the dedicated `event_description` widget with a more sensible architecture: pass event details forward through `PageContext` (or `RecordContext`) and have the event detail template use a standard `text_block` widget that references the contextual data via tokens. Justification: event_description's only job is to render a single record's title/description/date — that is exactly the problem `PageContext` and template tokens solve generally. The widget is also one of the test-audit-flagged W-cases from sessions 216–224 — removing it shrinks the widget catalog and tightens the system without losing functionality.

Concrete steps:

1. Extend `RecordContextTokens` (or the equivalent context surface) to expose event fields as tokens consumable by widget templates.
2. Update the event detail template seed to use `text_block` with the new tokens instead of `event_description`.
3. Add a migration that swaps any existing `event_description` widget instances on event detail pages for the equivalent `text_block` config (preserving authored content where it exists).
4. Retire the widget definition and its retrofit test cases.

Touches the `RecordContextTokens` per-record-type expansion carry-forward from the Widget Primitive track. Out of scope: doing the same exercise for other detail-template widgets (product detail, post detail).

### Text Color Hierarchy Rules *(deferred to post-1.0 at session 244 — design discussion that doesn't surface during any rehearsal as currently scoped)*

Resolve the layered text-color decision hierarchy across widget config, WYSIWYG Quill formatting, and theme defaults. Concretely: many widgets expose a "text color" control in the inspector, but the embedded Quill editor *also* exposes inline text-color formatting per character/run. When both are set, who wins? Today the answer is inconsistent. Plus a load-bearing edge case: if the user picks "white" as text color but the surrounding background is also white (default page background), the content becomes invisible — the rendering pipeline needs a safety net.

Open questions:

- **Should widget-level text color be removed from widget configs entirely**, deferring all color decisions to Quill? Pro: single source of truth. Con: forces per-character styling for the "all this widget's text is brand color" use case.
- **What is the contrast safety net?** Authoring-time validation that warns on low contrast? Render-time fallback to theme default if contrast drops below threshold? A WCAG check that blocks save?
- **What is the theme's role?** Theme provides the default text color; widget override layers on top; Quill inline overrides per-run. Codify this stack and document it.

Design discussion first; implementation lands once rules are resolved. Out of scope: a full WCAG contrast checker UI (that is post-Beta-1 in the Site Theme Builder stub).

---

### Sovereign Widget System — Remaining Stages

The Widget Definition Class, registry, config resolver, per-widget colocation, manifest/metadata, demos, static thumbnails, preset inspector, designer drafts, and preset thumbnails all shipped across sessions 170–177. Remaining stages are deferred until post-Beta 1.

- **Stage 5d+ — Per-widget preset authoring.** Batched sessions (4–6 widgets each) writing preset libraries on the widgets where they matter most — three_buckets, carousel, bar_chart, logo_garden, board_members, event_calendar, donation_form, product_carousel, video_embed, web_form, text_block. Fed by the designer draft/export workflow delivered in session 176.
- **Stage 6 — Widget Browser UI (admin).** Admin page listing all registered widgets in a browsable grid — search, filter by category, preview thumbnails + preset count chip. Consumes the static thumbnails and preset data already in place. Prepares the UI surface for later external-registry installs.
- **Stage 7 — Install/Uninstall Mechanics.** Widgets become runtime-installable. Package format (zip with manifest + definition + assets). Install/uninstall commands and UI. Cleanup logic for orphaned instances.
- **Stage 8 — External Registry.** A remote registry service (first-party or Packagist-style) the app browses and installs from.
- **Animated Thumbnails.** Originally planned as Stage 4.5 Phase 2. Revisit after Stage 6 ships, once there is real evidence of whether motion is missed in the browser grid. Static thumbnails remain the committed artifact; `scripts/generate-thumbnails.js` is positioned to grow `--animated` if/when we come back.

### Vue Page Builder Test Coverage

Stand up a JS test runner (vitest + @vue/test-utils + @pinia/testing) for the Vue page builder under `resources/js/page-builder-vue/`. Cover the Pinia store first — reactivity invariants between `widgets`, `layouts`, and `pageItems` (mutations must propagate to the merged page flow without replacing proxy identities), debounced save flow, and the preview-refresh path. Add component-level smoke tests for InspectorPanel, PreviewCanvas, and ApplyChangesButton afterwards. Wire `npm run test:js` into the standard pre-commit / CI flow.

### Custom Field Grouping & Layout

Allow admins to group and arrange custom contact fields into labelled sections with a configurable column count. The form-builder approach is the right model — give users control over column count per section and let fields stack within each column. JSON schema should be extended to support containers/fieldsets when a clean implementation path is available.

### Default Content State

All five starter pages in a published state. Default seed includes one sample event and one sample blog post so all major features are demonstrated on a fresh install. Add an install option to skip default content for users who prefer a blank slate.

**Privacy Policy + Terms of Use as default starter pages** *(lifted at session 283 — must land before Public Website Complete milestone closes)*. Both pages seed in published state with placeholder body copy that's clearly marked for the operator to fill in (boilerplate + "[ORG NAME]" / "[CONTACT EMAIL]" tokens). Both excluded from the main site navigation. Both linked from the footer navigation that the E16 header/footer overhaul is building. Rationale surfaced during session 283 (Stripe Checkout Branding): Stripe Dashboard's Public Details requires a Terms of Service URL and a Privacy Policy URL for the branded checkout experience to render correctly; if those pages don't exist on a fresh install the operator has no URL to paste. Pairs naturally with E16 since the footer-nav structure lands there.

### Installer

Guided first-run setup: database connection, mail provider configuration, admin user creation, initial seed. Must be fast enough to run live during a sales pitch — a prospect-facing demo that ends with their own configured install at a fresh URL.

### Importer Source Presets — Competitor Coverage *(retired from pre-Beta-1 at session 241 close)*

Originally a pre-Beta-1 stub aiming for 9 vendor presets (Blackbaud / Raiser's Edge, Little Green Light, DonorPerfect, Neon CRM, Salesforce NPSP, Network for Good / Bonterra, eTapestry, Kindful, QuickBooks export). Retired during 241 close on the grounds that vendors don't actually adhere to a standardized export format — each org configures custom export templates from inside the vendor's UI, so per-vendor presets need anonymized real exports to write usefully. Lift relative to payoff is not high without those samples in hand. The seeded Generic / Wild Apricot / Bloomerang presets continue to ship at v1; users on other source CRMs use the Generic preset + the importer's `guessDestination()` heuristics + the LLM-Assisted Data Prep path (separate stub below) for shape-shifting messy exports.

When this comes back, the more interesting shape is **API schema discovery** rather than CSV-preset writing: for vendors with heavy per-customer schema customization (Salesforce NPSP, Raiser's Edge NXT, possibly Neon), the customer's API can be introspected at migration time to generate a *customer-specific* preset on the fly — the API tells us "your Salesforce has these custom objects with these fields named these ways," and we map against that rather than against vendor-doc-derived defaults. The data path itself can stay CSV-driven (user uploads, we map); the API does discovery only, sidestepping rate limits and ongoing-maintenance complexity. For vendors with relatively fixed schemas (Bloomerang, LGL), API discovery doesn't add much over docs and CSV samples; the API path is specifically for the customizable-schema vendors. Salesforce NPSP is the most natural first slice if pursued — public schema, well-documented OAuth — but only worth the engineering with a paying customer attached given the surface area.

Forcing function for revisit: a paying client migration where the cost of writing a custom preset against their actual export pays back a multi-thousand-dollar engagement. Until then, the Generic preset + LLM-Assisted Data Prep (post-Beta-1 stub) covers the long tail.

---

## Volunteer Management *(deferred — post-Beta 1)*

*DOB / age fields on contacts are a prerequisite. Volunteer Portal depends on Member Portal being complete. The entire Volunteer Management section is deferred until after Beta 1.*

*Age gating (agreed session 052): public volunteer registration form gates sign-up to the minimum age threshold. Under-13 parental consent is out of scope for v1.*

### Volunteer Profile & Hours Tracking

*Skills, availability, background check status/expiry, training/certifications, hours log, total hours on contact record.*

### Volunteer Scheduling

*Recurring shift slots with capacity. Admin assignment and self-signup. Connects to Events for event-day volunteer roles.*

### Volunteer Communication & Recognition

*Shift reminders, milestone triggers (100 hours, anniversary). Integrates with the email system.*

### Volunteer Portal

*Public self-service: signup, view shifts, log hours pending admin approval. Extends Member Portal patterns.*

---

## Member Portal & Self-Service

### Household — Remaining Features

Core household model built in session 071 (self-referential `contacts.household_id` FK, admin assignment, portal display, address sync). Remaining for future sessions if needed:

- Member-to-member portal invite flow.
- Household dissolution / head transfer when the head contact leaves.
- Household-level aggregate giving and mailing deduplication (Finance sessions).

---

## CMS & Page Builder — Post-Beta 1

### Widget Toolset Tightening — Per-Widget Pass

Every existing widget gets a pass to bring its config schema up to the current standard, so the designer can author preset libraries against stable, fully-featured widget tools.

Scope per widget:

- Replace legacy `color`-as-text-input fields with the proper ColorPicker primitive (swatches + theme palette, session 169).
- Add `alignment` (9-point picker) where layout allows.
- Add `gradient` controls where a color is the current option.
- Review for missing appearance primitives now available in the schema library.
- Confirm each widget renders correctly against the Appearance layer (background, spacing, full-width) from sessions 161–166.

Status at Beta 1: the widget edit surface got substantial structural work during sessions 139–180 (unified preview/edit layout, properties panel split, defaults authoring), which left the widget config tooling presentable without an exhaustive per-widget sweep. Remaining work is mostly paper-cut replacement of legacy text-color inputs and alignment pickers. Deferred until post-Beta 1 because presets (Stage 5d+) are themselves post-Beta, and preset authoring against a 90%-tightened toolset is fine — the last-mile tightening can happen per-widget as presets ship.

Scope estimate when it lands: 2–3 sessions batched across the widget catalogue. Related: the font control primitive needs a tightening pass before being adopted as a `font` field type (compact trigger ergonomics, swatch behaviour, sensible defaults). May be bundled with one of these batches.

---

### Layer Explorer

Add a layer explorer: a simple text-node tree of the page's widget structure (like a DOM inspector outline) — collapsible, sits above or alongside the widget list. Helps users locate deeply nested blocks inside column slots. (Builder-mode auto-collapse and the full-width toggle were delivered admin-wide in session 156's topbar fullscreen button — no longer needs a builder-specific implementation.)

### Column Layout UI Improvements

UX improvements to the column layout construct: better visual affordances for column slots, responsive behaviour controls, drag/resize handles, and any inspector panel refinements needed. Benefits from the builder overhaul — rework to use the new properties panel and preview system. **Note: column layouts (`page_layouts`) are a distinct construct from widgets (`page_widgets`) — the two were deliberately separated because the variance in behaviour required a different model. This stub covers refinements to the layout construct; it does not propose re-unifying columns with widgets.**

### Content Export — Unified Options Modal *(surfaced at session 315)*

Replace the four near-identical "Export …" rows in the secondary-actions ellipsis menu with a single **"Export …"** action that opens a small modal carrying two checkboxes: *Include theme* and *Include media*. Today the menu pre-bakes the 2×2 matrix of those two binary options into four separate items (`exportPage` / `exportPageWithMedia` / `exportPageWithTheme` / `exportPageWithThemeAndMedia`), which (a) bloats the menu and (b) truncates in Filament's narrow dropdown panel because the labels are long. Collapsing to one action with two toggles dissolves both symptoms; a panel-width tweak is the fallback only if any truncation remains after the collapse.

**Decision (s315):** export stays *in* the ellipsis menu on every content type that has it (pages, posts, events, templates, and the site export) — it does not graduate to a primary button. The modal is the unifying surface across all of them.

**Why it's tractable:** the backend already supports both axes — `ContentExporter` and `ExportBundleJob` take `with_design` / `with_media`, and the four current items are just pre-selected combinations. The modal routes by the media toggle: no-media → synchronous `streamDownload` JSON; with-media → queued `ExportBundleJob` zip. So this is consolidation of an existing seam, not new export machinery.

**Shape to resolve at session start:** one shared modal/options component reused across content types vs. per-type actions that share a trait — mirror of the `HasPageSecondaryActions` trait landed in s315, which is what surfaced this. Spans pages/posts/events/templates/site, so it wants its own session.

**Why post-Beta:** the functionality all exists; this is UX consolidation — important but not Beta-blocking, and deliberately deferred to keep the pre-Beta session count from growing near the finish.

### Site Chrome Widgets & Navigation

Build a logo block widget for the site header. Restructure the default header and footer into two-column layouts (logo/content on left, nav on right for header; address/content on left, nav on right for footer). Build a company address widget for the footer. Navigation widget is being built in session 169 — this stub covers the remaining chrome restructuring work.

### SEO — Advanced

Twitter card meta tags. Manual canonical URL override. SEO scoring/audit checklists. Search console integration. Alt-text validation. Builds on the JSON-LD, OG tags, snippets, sitemap, and noindex controls delivered in session 096.

### Site Theme & Public Theme Builder

*Merges "Site Theme Enhancement" and "Public Theme Builder / Custom CSS Tool" — both extend SiteThemePage.*

Extends the existing `SiteThemePage` which already has appearance settings and a live SCSS editor. Adds: colours reorganised into light/dark palette rows, "Mirror" buttons applying HSL-based inversion, live WCAG AA contrast checker with nearest-passing-colour suggestions, preset palettes with swatches. Split-pane SCSS editor with live page preview on the right.

### Theme Kitchen Sink

A "kitchen sink" preview page for theme editing that exposes all major headings, text styles, form elements, and other styled components in a single view. Allows the user to tweak theme settings while seeing the impact across all styled elements simultaneously.

### CMS Style System — Full Widget Styling

Per-widget `style_schema` declaration: each widget type defines a constrained set of CSS properties exposed as configurable controls beyond the universal Appearance layer. Plus arbitrary scoped CSS per widget instance, scoped to `[data-widget="{uuid}"]` at render time. The universal Appearance layer is fully delivered (sessions 161–166): background color, gradient, image with alignment/fit, text color, full-width, padding, and margin — all stored in `appearance_config` and rendered by `AppearanceStyleComposer`. This stub covers only the remaining ambition: per-widget `style_schema` for widget-specific CSS properties beyond what every widget gets for free, and a freeform scoped CSS editor for advanced users.

**Flagged during session 197 — unified CSS override mechanism:** the `GradientPicker` per-layer `css_override` input was removed as a UI surface because layer-level CSS can't be resolved predictably against other background layers or the universal Appearance stack. The broader question — whether user-authored CSS escape hatches belong as per-primitive fields or as a single widget-scoped mechanism rendered server-side — belongs in this session. When scoping, evaluate: (1) should the `[data-widget="{uuid}"]`-scoped approach be the single sanctioned path for freeform CSS, replacing any legacy per-feature inputs? (2) sweep the orphaned `css_override` field on gradient layers — still present in the `GradientLayer` TS interface and `GradientComposer::compose()` for backward compatibility, but no UI creates new values — deciding whether to migrate-and-remove or leave as a back-door for advanced users.

### Hero Full-Bleed Promotion to Universal Layer

Promote the hero-specific `overlap_nav` / `nav_link_color` / `nav_hover_color` config fields into the universal `appearance_config` layer so any widget can go full-bleed behind the navigation bar. Today the feature is coupled to the literal string `'hero'` in `PageController` and `PagePreviewController`. This session genericizes the check, migrates existing hero instances, and moves the SCSS rules from `_hero.scss` to a wrapper-level partial. Originally scoped as session 162a during the Appearance Controls project; deferred because it is an architectural change rather than a Beta 1 requirement.

### Universal Appearance — Vertical Alignment

Promote per-widget `vertical_align` (top / middle / bottom) into the universal `appearance_config` layer so every widget — not just `text_block` — inherits a vertical alignment control in the Appearance inspector panel. Session 246 shipped the narrow per-widget shape on TextBlock as the immediate fix for column-layout authoring (text block next to a taller image). The generalized version requires `AppearanceStyleComposer::defaultAppearanceConfig()` to add `layout.vertical_align`, every widget's appearance composition path to honor it, the inspector to surface the new field, and existing rows to back-fill the default. Lift earlier than post-Beta-1 if a second widget surfaces the same friction (e.g. Image, BlogListing tile content).

### Spacing Controls — Axis Locking & Presets

UX improvement to the Section Layout panel's padding and margin controls. Two features:

1. **Axis locking:** Replace the "All" shorthand with a linked-axes model — "Lock horizontal" (left+right) and "Lock vertical" (top+bottom) toggles. When an axis is locked, changing one side writes both. When both axes are locked, all four sides follow. This matches the most common real-world pattern: horizontal sides are almost always equal, vertical sides are often independent.

2. **Spacing presets:** A preset picker (similar to the gradient/color swatch pickers) that lets users save and recall named spacing configurations. Presets write predefined pixel values to all four sides; the user can then unlock and tweak individual sides. May involve adding default spacing values to the template/theme settings, or allowing widgets to declare their own defaults. A small visual cue in the preset menu should help users understand what each preset applies.

This is a self-contained session focused on the inspector UI layer. No new `appearance_config` keys — the underlying four-side padding/margin store paths are unchanged. The axis locks are local UI state (not persisted), and the presets are a lookup from a new site-settings or template-level configuration.

### Image Widget — Border Controls

Add border config fields (width, color, radius) to the image widget's `config_schema`. Pure config + CSS, no architectural changes.

### Background Image Opacity

Allow the background image in the universal Appearance layer to have a user-controlled opacity, so the background color shows through. CSS does not support per-layer opacity on `background-image`, so this requires either a pseudo-element approach or rendering the image as a positioned `<img>` element inside the widget wrapper. Deferred because it touches the rendering pipeline in `page-widgets.blade.php` and `AppearanceStyleComposer`.

### Page-Level Style Settings

Page-level settings for background color, content width, and chrome outside the widget space on sides. Header and footer should be affected by these styles. Needs design on where these live (page record, template, or a new config column) and how they interact with the existing template system.

### Image & Media Handling — Carousels & Galleries

Full carousel and gallery widget types beyond the basic image slider added in Beta 1. Lightbox, captions, reorder controls.

### Inspector Column — Drag-Resize Splitter

Add a vertical drag-resize splitter between the preview canvas and the inspector column in the page builder (`App.vue` — currently `grid-template-columns: minmax(0, 3fr) min(28rem, 25%)`). Trades canvas width for inspector width at the user's discretion. Motivated by session 197: the dual-pane inspector's tab labels ("Widget Settings", "Margin & Padding") truncate with ellipsis in the default ~260px column, and widening the column is the clean fix once a draggable splitter exists. Should persist per-user via localStorage or site settings. Minimal scope — purely a layout ergonomic, no schema or widget changes.

---

### Google Docs Integration — Import, Widget, and Folder-Backed Authoring

Multi-layered feature converging several user-facing needs, all rooted in the pain of "professional writers draft in Google Docs and want their words on the site without learning a CMS." Discussed in planning conversations on 2026-04-21. Three concrete value cases named by the user:

1. **Legal / counsel authored documents.** Attorneys publish policy docs, bylaws, authoritative references into a Google Doc. Staff never edit — their only role is pressing a refresh button. Single-source-of-truth model: Google Docs is the canonical surface; the CMS is a rendering layer. Quote: *"A huge portion of my life has been spent in service of this problem."*
2. **On-demand team blogging.** A writing team manages blog entries in a Google Docs folder. Each doc becomes a post on the site, with nice typography inherited from the theme, without the team ever touching Filament. Eliminates the CMS learning curve for occasional bloggers.
3. **Ad-hoc long-form authoring.** A "Populate from Google Doc" button on any richtext field lets any widget's long-text content be imported from a Doc. Covers the blog-post body, Hero content, any widget with a richtext field.

#### Four architectural shapes, increasing effort

- **Shape A — "Publish to web" iframe widget.** Google Docs has a built-in "File → Share → Publish to web" feature that produces a public URL. The widget wraps that URL in an `<iframe>`. ~1 session. Automatic sync from Google's side (whatever's published shows up instantly). No OAuth, no API, no dependencies. Trade-off: iframe chrome, typography doesn't match site theme, content not SEO-countable toward the hosting page.
- **Shape B — Google Doc Import button + persistent-doc-id widget.** Full OAuth-based import (per-admin Google credentials, `drive.file` scope + Google Picker API for non-scary consent), Markdown export from Drive API (cleaner than Google's HTML export), content cleanup, image migration to Spatie media library. The widget stores a doc ID + a cached content blob + a manual "Sync now" button. Native rendering in site typography, SEO-countable. ~2–3 sessions (OAuth plumbing + import + image migration + widget wrapper). The QuickBooks OAuth model (sessions 102–105) is the nearest precedent for per-connection token storage.
- **Shape C — Scheduled sync.** Adds cron-driven refresh on top of Shape B. Per-widget cadence config — `manual only` / `hourly` / `daily` / `on-page-load-if-stale` — not a site-wide setting. Staleness badge in the inspector ("Last synced 14 min ago · Sync now") is the load-bearing UX: without it, every admin assumes the widget is live and gets confused when it isn't. ~2 additional sessions atop B (scheduler + sync state model + retry/backoff + UI). Rate-limit budget: Drive API default ~1000 requests per 100s per user, so hourly-on-50-widgets is trivial; per-minute cadence is not.
- **Shape D — Webhook-driven push sync.** Drive API change-notification channels push to the app on doc edit. Near-real-time (seconds-latency). ~3 additional sessions atop C: channel registration, 7-day channel renewal cron, HMAC verification, delivery-failure handling. Cool but mostly a latency improvement, not a new capability.

**Folder-backed blog ingestion** is a parallel shape: a Google Drive folder is watched; each doc in it becomes a `Page` with `type = post`. Same pipeline as Shape B/C but the consumer is blog ingestion, not a widget. `files.list` on a folder ID is the watcher; rest is the existing transform. Maps directly onto the existing post model and media library.

#### Image slot routing via alt-text convention

Google Docs images support alt text (right-click → All image options → Alt text). If an image's alt text starts with a keyword, the sync pipeline extracts the image out of the flowing content and routes it into the named slot on the widget or post:

```
@header       → goes into the header image slot
@thumb        → goes into the thumbnail slot
@hero         → goes into the hero/cover slot
@card         → goes into the card/preview slot
@header Photo by J. Smith  → slot = header, alt text = "Photo by J. Smith"
```

Format: `@<slot>[ <real alt text>]`. Untagged images stay inline in the body. Fallback: first untagged image → `thumb` when no `@thumb` tag exists (helps writers who don't know the convention). Extensible — new slots land as new `@xxx` keywords with no schema change on the Docs side. Implementation is a ~20-line transform step in the import pipeline atop whatever's already handling inline image migration.

#### Paradigm questions to resolve before building past Shape A

- **Source of truth.** Is Google Docs canonical (CMS renders) or is the CMS canonical (Docs is a drafting tool)? Determines whether post-import CMS edits get blown away on the next sync. Likely answer for legal docs: Docs-canonical. For ad-hoc blogging: could go either way.
- **Auth identity.** Per-admin OAuth breaks when admins leave — their token was the only way to read the doc. Long-term answer is a Google Workspace service account that owns doc connections; users share docs with the service account email. Changes onboarding story but solves turnover. Per-admin is acceptable for V1.
- **Multi-doc rate limits.** A page with many doc widgets hits quotas fast on the same admin's token. Sync-job coalescing (one sync per doc regardless of how many widgets reference it) matters once Shape B is in place.
- **Image migration vs proxy.** Migrating images to the media library makes the cached content self-contained but decouples from doc updates to those images. Proxying keeps them coupled but adds a request + auth hop on every page load. Migration is the right call; the sync refresh re-migrates changed images.

#### Paste-and-clean as an orthogonal complement

Improving Quill's paste handler to recognize and sanitize Google-Docs-flavored HTML is a one-session feature that solves ~70% of the "I wrote this in Docs" use case with no infrastructure — no OAuth, no API, no new dependency. Writer copies from Google Docs, pastes into Quill, Quill's paste handler detects Google-Docs-flavored HTML and cleans it inline. Stays useful even after the full button ships. Consider as an early quick-win.

#### Recommended sequencing

1. **Paste-and-clean** (1 session, zero infrastructure) — independent utility, buys time on the rest.
2. **Shape A iframe widget** (1 session, zero infrastructure) — quick win for the legal-doc use case where iframe chrome is acceptable because it signals "official document" anyway.
3. **Shape B import + widget** (2–3 sessions, one-time OAuth investment) — unlocks native-rendering imports and persistent-doc-ID widgets for ad-hoc authoring. Bundles the editor button (previous question) with the widget shape — most infrastructure is shared.
4. **Folder-backed blog ingestion** (1–2 sessions atop B) — the on-demand blogging paradigm. Reuses 90% of B's infrastructure.
5. **Shape C scheduled sync** (2 sessions atop B) — for customers who want "live-ish" cadences.
6. **Shape D webhooks** (3 sessions atop C) — only when someone asks for sub-minute latency.

Items 1–2 are pre-Beta safe if scope allows; 3+ are genuinely post-Beta territory (OAuth, image migration, scheduling infrastructure). The whole feature stack is mentioned by the user as a "nice paradigm if achievable at some point" and is positioned here as a deliberate post-Beta investment.

#### Dependencies and related work

- Requires adding `google/apiclient` (or equivalent) to `composer.json`.
- OAuth token storage patterned on the QuickBooks connection model (sessions 102–105).
- Content cleanup leans on an existing or new Markdown-to-HTML library (`league/commonmark` would be the natural choice).
- Image migration reuses the Spatie media library already in place.
- No schema changes until Shape B (doc-id-and-cached-content columns on `page_widgets` for the widget variant, or a new `google_doc_connections` model for the folder-backed variant).

---

## Help System — Post-Beta 1

### Help System — Navigable Index & Category Browser

Category-based help index page with table of contents grouped by category (CRM, CMS, Finance, Tools, Settings, General). Searchable from the index. Link in the admin left navigation. Foundation for a self-service knowledge base.

### Help System — Tutorials Content Type

A new content type for multi-step instructional content (e.g. "How to set up event registration end-to-end"). Distinct from contextual help articles — tutorials span multiple features and follow a narrative arc. Requires a `type` field on help articles and a tutorial-specific template with step navigation.

### Help System — API Documentation Content Type

Structured reference documentation for the public API (when built). Auto-generated from route definitions and request/response schemas. Distinct rendering template with endpoint listings, parameter tables, and example payloads.

### Help System — Weighted & Semantic Search

Upgrade help search from simple string matching to weighted ranking (TF-IDF or similar) and eventually semantic search using the `embedding` column on `help_articles`. Requires a vector similarity search approach — investigate pgvector or application-level cosine similarity.

### Help System — External Help Site

Move the help system to its own web address as a standalone, publicly accessible knowledge base. The admin panel consumes this endpoint as the source of truth for help content on an update schedule (cache + periodic refresh). Decouples help authoring from product release cycles and enables a single help site to serve multiple product instances.

---

## Infrastructure & Ops — Post-Beta 1

### Integration Setup Wizards — Stripe & Mailchimp

Multi-step guided wizards for connecting Stripe and Mailchimp. Each wizard walks through entering API keys (with the existing high-friction rotation pattern), verifying connectivity, and confirming the integration is live. QuickBooks wizard to follow once the QuickBooks Sync session is scoped. Consider a unified "Integrations" page as the entry point.

### Scheduled System Email Sends

Allow admin-initiated system emails (donor receipts, event notifications, etc.) to be scheduled for a future send-at time rather than sent immediately. Requires a `scheduled_emails` table, a queue/scheduler job, and cancellation UI. Resend does not support native scheduled send — scheduling is handled by the application. Review the System Email Preview Wizard (session 080) before designing this — the wizard's Step 3 is the natural place to surface the scheduler.

### System Email Compliant Footer *(post-Beta 1, surfaced at session 291)*

System emails need a compliant footer — physical postal mailing address and, where applicable, an unsubscribe affordance (CAN-SPAM; the user flagged this as "ICANN-compliant"). The default wrapper (`resources/views/mail/default-wrapper.blade.php`) already has `footer_address` and `footer_reason` slots, but they are operator-optional and unpopulated by default, and a related gap surfaced at 291: `footer_reason` token interpolation does not run (tokens like `{{form_title}}` render literally in the footer because `EmailTemplate::render()` only token-replaces the body/subject, not the wrapper footer fields — at 291 the form-submission template's `footer_reason` line was removed rather than fixed, as an interim). Scope when lifted: a sensible default postal-address source (likely the existing site/contact settings), footer-field token interpolation through the wrapper, and an unsubscribe affordance for any non-transactional sends. Not Beta-1 blocking; transactional system emails currently send without the compliant footer.

### Public API Endpoints

REST or GraphQL API for external integrations. Important long-term — should not be half-baked. Deferred until post-Beta 1 to allow proper design and authentication modelling.

### CDN Integration

Asset delivery via CDN for uploaded images and static files. Pairs with the image optimization pipeline from Beta 1. Provider TBD.

### Deploy-Server Integration Test Suite

A lightweight test suite that runs on the deploy server against real sandbox APIs (QuickBooks sandbox, Stripe test mode, Resend test keys). Catches integration issues that mocked unit tests cannot — token refresh flows, API payload shape changes, webhook delivery. Runs as `php artisan test --group=integration` using `.env.testing` with sandbox credentials. Could also include a post-deploy smoke check (app boots, key routes respond, queue processes a job). Manual SSH trigger for now; hook into CI/CD later if one is added.

### Service-Worker Sweep — Activity-Log Orphans + Dead Media + Scrub Data Aging *(post-Beta 1; surfaced at session 245)*

After session 245's scrub-data wipe ships, three categories of orphan / aged data accumulate over time:

- **Activity-log orphans.** Spatie ActivityLog entries whose subject row has been deleted (by the scrub wipe, by soft-delete cascade, by manual operator action). Benign noise; doesn't affect correctness, only storage hygiene and admin-UI relevance.
- **Media Library orphans.** Spatie Media Library rows whose owning model has been deleted. Pairs with the existing `media-library:clean` artisan command named in *Housekeeping — Batch 2*; this stub is the broader scheduled-sweep concern.
- **Aged scrub data (opt-in only).** Optional retention policy: scrub-data rows that have aged out (e.g., retain N days then auto-wipe). Operator-opt-in only — never default.

Future scheduled cleanup: a service-worker / scheduler-driven sweep that handles all three. Lands as one cohesive operations-hygiene session post-Beta-1. Pairs naturally with the existing Spatie Media Library cleanup artisan command and with the scheduler-runner work surfaced in the Backups/scheduler gap (see `docs/app-reference.md` § Scheduler runner — known gap).

Defer to post-Beta-1: not a release blocker — orphans don't affect correctness, only storage hygiene and admin-list relevance. Forcing function: surfaced when 245 ships; cumulative impact grows with rehearsal frequency.

### API Key Pattern Validation & Test-Mode Warning

Three related features: (1) form-level validation that recognises API key format patterns (e.g. Stripe `sk_test_` vs `sk_live_`, Resend `re_` prefix) and shows an inline hint; (2) a production-context warning surfaced when a test-mode key is detected; (3) **environment mismatch hard gate** — on save, detect whether Stripe and QuickBooks are pointing at different environments (e.g. Stripe live + QB sandbox, or vice versa) and refuse to save with a clear error. The dangerous scenario is Stripe test mode pushing fake transactions into a real QuickBooks company. The gate ensures both integrations are in the same mode before the configuration is accepted. Scope and warning placement to be agreed following the session 081 discussion.

### System Email Preview — Default Sample Record & Full Coverage

A user-editable singleton record that pre-fills the email preview wizard with representative sample data when no real recipient is available (e.g. test sends). Note: the preview wizard already exists (session 080) and is already in use for donation receipts and user invitations — this session extends the same pattern to any remaining system email sends, rather than rebuilding.

### Batch Edit on Admin Tables

Add batch (bulk) edit capability to admin resource tables. Any field exposed in a content type's settings should be available as a batch-edit action. Scope: agree which tables get batch edit, define the UI pattern (inline modal vs dedicated form), and implement. Content type deletability decisions (see separate stub) should be resolved first so batch-delete controls are consistent.

### Surface `source` Field on Records *(post-Beta 1; surfaced at session 245)*

Every source-bearing record (Contact, Donation, Membership, EventRegistration, Transaction, Event, Page) carries a `source` column tagging the row's origin (`human`, `import`, `stripe_webhook`, `scrub_data`, etc.) but the value is not visible anywhere in the admin UI. This makes verification awkward — at session 245 close, the only way to confirm scrub-tagged rows existed pre-wipe was to query the database directly.

Scope: surface `source` on the relevant Filament resource list views (as a column, ideally filterable) and on detail/edit pages. Decide on a consistent presentation pattern (badge, plain text, icon) and apply uniformly. Should ship before C3 (Permission audit) so the auditor can quickly distinguish synthetic vs. real rows; could plausibly land pre-Beta-1 if the priority surfaces during rehearsal sessions.

Out of scope: a global "show me all scrub data" filter view (separate concern; the per-resource filter is sufficient for verification). Operator UX for manually changing source (the `EnforcesScrubInheritance` policy makes this a one-way street for scrub-tagged rows; not a problem for the read-only display).

### Multi-Vendor Mail Support

*Additional sending providers: SMTP, AWS SES, Postmark, Mailgun. Switchable driver pattern already in place.*

### Accessibility — ARIA, ADA & Colour Contrast

ARIA landmark roles, correct states on interactive elements, keyboard navigation, focus styles, skip-to-main. Automated contrast check (axe-core or similar). Colorblind simulation audit. Output: fixes + WCAG AA compliance statement for client ADA documentation.

### Privacy & Legal Footer Example

*Example custom footer component with placeholder slots for privacy policy and terms. Reference implementation for customers.*

---

## Communication & Accountability — Post-Beta 1

*Future additions: global filterable log, field-level diff, observers for Finance and other models.*

### Activity Log Viewer

Filterable admin view of the `activity_logs` table. Who did what, to which record, and when. Covers all logged events including financial key rotations (written in session 073). Simple read-only table — no editing or deletion.

### Mailing List — Field Policy & Targeting Engine

Build a targeting filter UI for mailing lists based on agreed field policy (decided session 081). Allowed filters and rules:

- **Always allowed:** tags, membership status/tier, geographic fields (city, state, postal code), custom fields, event registration history, source + date range.
- **Donor threshold:** "donated at least $X in year Y" — returns a boolean in/out result. No donation amounts or fund details are surfaced on the list record. Cross-Finance boundary only as a boolean gate.
- **Age cutoffs:** preset options only — 13+, 18+, 21+. No free-entry age field. No under-age filters.
- **Household deduplication:** "head of household or solo" filter — includes contacts where `household_id = id` OR `household_id IS NULL`. Excludes non-head household members.
- **`mailing_list_opt_in`:** available as a filter; show a visible warning when a list is being sent without it applied.
- **`do_not_contact`:** hard system exclusion — always enforced, cannot be filtered out by the admin. Help copy must state: set only on explicit opt-out; clear only on explicit re-consent (activity log covers audit trail).
- **Prohibited:** actual donation amounts, fund designation detail, under-age or arbitrary age filters, portal account status (portal communications are a system email concern, not a list concern).

---

### Path to Success — LLM-Assisted Data Prep Tutorial *(stub — post-Beta 1)*

Second path to success for messy incoming data. Publish a help article (plus a linked video walkthrough) titled something like "When your export doesn't match our template: use Claude Code to reshape it." Covers:

- Downloading our canonical CSV template per content type (produced by session 191).
- Opening Claude Code (or similar LLM CLI) inside the folder containing the user's export.
- A short, copy-pasteable prompt that tells the LLM: "reshape this export to match <template>, ask me questions about any ambiguous columns, preserve all data, output a new CSV." Includes guidance on iterating and verifying.
- Common pitfalls: columns the LLM shouldn't guess at, sensitivity of date/currency parsing, how to spot-check the output.
- The philosophy: LLMs crush data-shaping tasks that humans find tedious. Leveraging that as a supported path cuts onboarding friction dramatically, especially for legacy system exports that our importer's presets won't cover on day one.

Context from implementation discussion: a veteran of 5M-contact migrations confirms this is the standard approach the author already uses personally. Making it a first-class supported path — not a hack — is a real competitive advantage against the 6-8-week "data migration consulting" model. Pairs with the downloadable CSV templates from session 191.

Not a code session — a content session. Ships as a help article + a short screencast.

---

## Workflows — Post-Beta 1

*Items lifted out of Phase C scope at the 282 Phase C audit because the underlying features didn't exist or were too large to justify pre-Beta-1 inclusion. Each becomes its own multi-session effort or scheduled session post-Beta.*

### Membership Renewal Cycle *(post-Beta 1; lifted from C6 at 282 audit)*

Full membership-lifecycle state machine: renewal-due → renewal-paid → grace → lapse → reactivation → dues-change → payment-failure-for-memberships. Each transition emits the right system email; portal reflects state; admin UI shows lifecycle. The C6 entry in release-plan.md is the rehearsal of this lifecycle once built; both the build and the rehearsal land post-Beta as a multi-session phase.

Beta 1 memberships work as one-time charges with portal-displayed tier — no lifecycle automation. The lifecycle build is a multi-session effort: schema additions (state machine, grace/lapse columns, dues-change history), webhook handling for subscription invoice events, scheduled jobs for lapse transitions, ~5–7 transition emails, portal lifecycle messaging, admin lifecycle display.

### Donation Year-End Statement *(post-Beta 1; lifted from C4 at 282 audit)*

Admin-side December-cycle workflow: generate per-donor year-end giving statements (total giving by year, itemized list) as PDF or formatted email. Sized 1 session if leaning on Browsershot or similar; somewhat more if cleanly integrating with the donation runbook flow.

### Donation Partial-Refund Corrected Acknowledgment *(post-Beta 1; lifted from C4 at 282 audit)*

When a Stripe partial refund is processed via webhook, automatically emit a corrected acknowledgment to the donor reflecting the new effective amount. Today the refund records correctly but no follow-up email fires. Sized 1 session.

### Event Waitlist + Promotion *(post-Beta 1; lifted from C5 at 282 audit)*

Waitlist for full-capacity events with auto-promotion on cancellation. `WaitlistEntry` model already exists in code but targets Products only; needs wiring for Events with admin-side management UI and email notifications on promotion. Real nonprofit use case but not Beta 1 critical. Sized 1–2 sessions.

### Event Day-of Check-in + Attendance Log *(post-Beta 1; lifted from C5 at 282 audit)*

Mobile-friendly check-in flow (sets `event_registrations.checked_in_at`) + post-event attendance export. Paper check-in is acceptable for Beta 1. Sized 1 session. (Per-event custom registration questions — also lifted from C5 at 282 audit — fold into the existing "Event Registration Depth" post-1.0 stub above.)

### Add-to-Calendar on Event Pages *(post-Beta 1; lifted from D3 audit at 282 Phase D audit)*

iCal/ICS export from public event pages with platform-targeted buttons — Apple Calendar, Microsoft Outlook, Google Calendar, and Proton Calendar if its format is compatible. Public-side feature, not an admin integration. The 282 audit removed Google Calendar sync from D3's integration list (no integration exists in code) and re-scoped this as the public-side affordance the user actually wants. Sized 1 session.

---

## Exploratory & Fun *(post-Beta 1)*

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

---

## Future Projects *(post-management console)*

### Easter Egg & Fun Features
