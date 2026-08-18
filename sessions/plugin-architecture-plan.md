# Plugin Architecture — Plan

**Status:** living document, written during session 376 as the discussion happens. Owner-corrected as it goes. This doc is the canonical output of the session and the spine of the sessions that follow it.

**Arc progress (last update: session 380 close, 2026-08-18):** **P4 ✅ complete** (380) — the Stripe rails live in `plugins/Payments/` (checkout service, webhook controller, price observer, live-mode sniff), the first shipping foundation plugin; core `CapabilityRegistry` (surface 13 **PROVEN** by five vertical call sites + the dashboard widget) with core-owned `CheckoutProvider`/`PaymentModeProvider` contracts; one-directional boundary guard (`PaymentModuleBoundaryTest`, zero allowlist — no SDK, no plugin namespace, no `services.stripe.*` reads in `app/`); route parity 274/274; vanish proven (strip-the-line mirror; free/$0 paths untouched); Cashier removed, stripe-php direct; contract doc **v0.4.0**; fast Pest 3189/0. **P5 next (381, Events — owner decision at 380 close):** the Events behavioral surface into `plugins/Events/` (controllers/routes, quantities service, observers, mails, reminders, policy, `EventResource` → surfaces 3/4 PROVEN, seven widgets, events importer via a new importer socket → surface 8), the typed `CheckoutSettled` inbound inversion (surface 10, events slice), Events×Payments presence-matrix tests; models/schema stay core until P7; event permissions stay in core's role matrix (extracted-vertical nuance, recorded in the contract doc at 381 close). Earlier:  **P1 ✅** (377 — LogoGarden pilot + contract v0.1.0; see the 377 log). **P2 ✅** (378 — widget boundary real across all 41; `dataContracts()` multi-contract seam; standing guard `WidgetTemplateBoundaryTest`, zero allowlist; contract v0.2.0; see the 378 log). **P3 ✅ complete** (379) — the admin shell is a socket: `AdminPanelProvider`'s inline `->routes()` closure decomposed into eleven per-feature `routes/admin/` files with **exact route-list parity** (274 routes byte-identical, verified twice); core `app/Plugins/` registry (`AdminContribution` + `PluginAdminRegistry`, declared at plugin `register()`, consumed by the panel provider) carrying Filament discovery paths, an in-panel route file (force-wrapped in `Authenticate` by core — no opt-out), and permission names (swept idempotently by `PermissionSeeder`, granted to no shipped role; super-admin via the gate bypass); the three session-376 Filament conventions mechanical, convention 3 enforced by standing guard `PluginAdminCssGuardTest`; proven by the SocketProbe test fixture with enabled + remove-the-line mirror tests; contract doc **v0.3.0** (surfaces 3/4 DECLARED-with-mechanics per the honesty rule — promotion waits for the first shipping admin-page plugin, P5+); fast Pest 3156/0. **P4 next** (session 380, prompts drafted off a fresh Stripe-surface survey, decisions front-loaded): the ~1,100-line Stripe rails (checkout service, webhook controller, price observer, live-mode check) carve into `plugins/Payments/`, the first foundation plugin; core `CapabilityRegistry` (surface 13, expected PROVEN — real vertical call sites consume it) + core-owned `CheckoutProvider` contract; webhook→vertical inversion deferred to P5 by decision; `laravel/cashier` removal owner-approved; P5's Donations-vs-Events recommendation due in the 380 log.

---

## 1. Motivation (settled)

The owner's founding idea: make plugins out of everything possible within the app — separate repos with a central build over a minimal core. It is the one architectural conviction carried through the whole project, and it has never been fully implemented; this plan is the thinking-through it has always been owed. (The inspiration is the mod architecture of moddable games; the design here targets a very different use case and diverges deliberately — the analogy informs the shape, not the contract.)

Three motivations surfaced in discussion, in increasing order of consequence:

1. **The architecture itself** — minimal core, everything else a plugin, assembled by a central build.
2. **The LLM context-window problem.** A bounded piece plus a written contract lets an agent work without loading the whole application. This project already proves the pattern in-house: the CRM↔Fleet Manager relationship (two repos, a versioned contract doc at v2.7.0, ~50 FM sessions run without ever loading the CRM codebase). Important correction settled in discussion: **the context savings come from the boundary and the contract, not from the repo split.** Repo separation adds coordination cost and pays only where a piece changes independently at its own rhythm.
3. **The business premise** — vertical SaaS on a shared platform: one engine, several niche configurations, each marketed to a small audience in its own vocabulary. What differs per niche is mostly vocabulary, defaults, seed content, feature selection, and the demo site — so **a niche is a *manifest* (core + chosen plugin set + config/seed pack), a modpack, not new engineering.** This rests on the cheapest modularity property (per-install composition), not the expensive one (plugins patching plugins).

**Retrofit over greenfield (settled).** The goal is something worth showing — "this architecture survives contact with a real 375-session application" is the rarer and more persuasive claim than a greenfield toy, there is no specific greenfield product in mind (scope-creep risk), and this codebase is a scoped, built product with known quantities.

---

## 2. Principles (settled in discussion)

1. **The contract is the load-bearing artifact.** A module boundary only saves context if an agent can substitute a short document for the code on the other side. Missing, stale, or leaky contracts mean the modularity tax is paid for nothing.
2. **Thin core buys a small shared surface; modularity buys a small working set.** The core's size sets the floor on what every agent must understand regardless of which module it works in. If the core is fat, most changes are core changes and the modularity never pays.
3. **Litmus test for plugin-hood: a piece is a plugin only if some plausible install would exclude or replace it.** Every boundary is a contract maintained forever; pay for swappability only where variation actually occurs. Pieces that every install needs are core — boundary-izing them can still buy context benefits, but not composition benefits.
4. **Sockets vs. parts.** Composition points every install needs (admin nav, the page canvas, the settings surface) are core *sockets*; plugins contribute into them. The nav is not a plugin; nav entries are.
5. **Plugins never patch plugins.** Plugins extend core through published surfaces; they do not modify each other. Mature plugin ecosystems concentrate their compatibility pain exactly where two extensions meet with no controlled contract to resolve against — it is the property whose cost explodes. Explicitly out.

## 3. Modularity properties — which are the point (working ranking)

| Property | Verdict for this project |
|---|---|
| 1. Minimal core, maximal periphery | **The point.** Drives everything below. |
| 2. Declarative definitions (data over code) | Already rhymes with the widget system (`config_schema`, definition classes). Adopted where it falls out naturally; not pursued as an end in itself. |
| 3. Separate distribution (own repos, own versions) | **The stated vision** — end state, reached last (Stage C), only where change-rhythm justifies it. |
| 4. Load order + plugins patching plugins | **Out** (Principle 5). Composer resolves acyclic dependencies; that is the ceiling. |
| 5. Per-install composition | **The business payoff** — niche = manifest. Note: the session prompt assumed `widget_types.is_active` exists; it does not (no per-install activation mechanism anywhere today). This property is *less* built than assumed. |
| 6. Community authorship | Aspirational; recorded as a later-stage line item only. |

---

## 4. The full-vision end state (proposed, under discussion)

### 4a. Core (the engine)

| Piece | Today | Notes |
|---|---|---|
| **Identity & access** — `User`, `Contact`, auth, permissions (Spatie), 2FA | `app/Models/Contact.php` is the hub every feature writes to | Contacts are core: the identity layer. How plugins *extend* a contact is the #1 open contract question (§6). |
| **Admin shell** — Filament panel, nav groups as sockets, settings framework | `app/Providers/Filament/AdminPanelProvider.php` — path-based discovery, 5 hard-coded nav groups, plus a large inline route closure | The inline route closure is a known obstacle; Filament supports per-package discovery paths, so the socket model is feasible. |
| **Page/widget engine** — pages, layouts, widget primitive, `WidgetDefinition` contract, registry, `ContractResolver`, public renderer, theme/tokens | `app/Widgets/Contracts/WidgetDefinition.php`, `app/Services/WidgetRegistry.php`, `app/WidgetPrimitive/ContractResolver.php` | The product's actual engine per the owner ("a niche-specific page builder"). The data-contract path is the proven miniature of the whole architecture. |
| **Plumbing** — media library, email transport + template mechanism, queues/scheduler, activity log, backups | Spatie medialibrary; `EmailTemplate::forHandle()` (defaults hard-coded on the model — needs a registry to be plugin-usable) | |
| **Fleet/ops surface** — `/api/health` + FM contract, deploy spine, VERSION | `app/Http/Controllers/Api/Fleet/*`, `deploy.yml`, GHCR immutable tags | Already a working central build to dock into. |

### 4b. Plugins (candidate decomposition)

| Family | Candidates | Litmus-test read |
|---|---|---|
| **Domain verticals** | Donations (funds, checkout, receipts), Events (registration, ticketing, calendars), Memberships (tiers), Member Portal, Forms, Blog/Posts | Each individually excludable — a hobby-club niche has no donations; an internal CRM has no portal. "The CRM section" is not one plugin; it dissolves into identity-core + these. Analog (owner's): first-party DLC — modules we author and ship, distinct from any future third-party surface. |
| **Foundation plugins** | Payments (Stripe rails: customer records, checkout, webhooks, receipt plumbing) | A plugin other plugins may *optionally* use. Passes the litmus test (a calendar-only install excludes it). Verticals degrade gracefully without it — Events without Payments = free events only; the existing $0/comp registration path proves the shape works. |
| **Widgets** | The 41 widget folders; each or in packs | The most plugin-shaped code in the app (own folder, contract class, assets, demo seeder). Blockers: hardcoded 41-class registration in `WidgetServiceProvider`, DB-mediated asset paths, and model-reaching templates (Nav → `NavigationMenu::find()`, DonationForm → `Fund::where()`, Portal* → tiers/settings/auth guard). Clean-today pilots: LogoGarden, BarChart, TextBlock (pure render over `dataContract()` data). |
| **Integrations** | QuickBooks, Mailchimp, Resend, (Stripe — see §6) | The classic, easiest plugin family: already fairly isolated, no install needs all of them. |
| **Importers** | Per-domain importers ship *with their domain plugin* | Registries exist (`app/Importers/*FieldRegistry.php`) but wiring is static (~14 hard-coded Filament page pairs). |

### 4c. The plugin API — the contract core publishes

Each surface below is something a plugin needs and core must guarantee. Current-code grounding in parentheses.

1. **Registration** — one service provider per plugin; Laravel package auto-discovery. (Precedent: `WidgetServiceProvider` already registers 41 definitions + Blade namespace + slots — the proto-plugin registry.)
2. **Widget contribution** — register `WidgetDefinition`s into the registry; `widgets:sync` stays the DB path. (Exists; needs to accept external packages.)
3. **Admin contribution** — Filament resources/pages via per-package discovery paths; nav-group self-assignment. (Filament supports this; the panel provider's inline route closure must be broken up.)
4. **Routes** — per-plugin route files. (Precedent: the three admin-builder API route files pulled in by the panel provider.)
5. **Migrations** — `loadMigrationsFrom` per plugin; interaction with the squash discipline is an open hard problem (§6).
6. **Seed/config packs** — per-plugin seeders + the niche configuration layer.
7. **Email templates** — handle registration replaces the hard-coded defaults array on the model.
8. **Importer registration** — registry entries + the Filament import-page pairing.
9. **Contact extension** — how a plugin adds data to the identity core without owning it (candidates: relations + the already-installed `spatie/laravel-schemaless-attributes`; see §6).
10. **Events/hooks** — Laravel events for cross-plugin reaction (e.g. Donations fires, Mailchimp listens).
11. **Front-end assets** — per-plugin widget CSS/JS through the existing out-of-band build server (`build:public` already reads asset paths from `widget_types` and builds outside Vite/Docker — a central asset build already exists).
12. **Versioning** — the contract doc itself, versioned like the FM contract, with the same discipline.
13. **Capability detection / soft dependencies** — a plugin can ask "is capability X present and enabled?" and degrade gracefully when it isn't. Dependency rules (owner guideline, settled): verticals never depend on each other; hard dependencies point only at core; optional dependencies point at foundation plugins (composer `suggests`, not `require`).

### 4d. Distribution — the niche/manifest layer (Stage C end state)

- **Plugin repos**: each plugin a composer package (+ its widget assets), own version, own tests.
- **Slim core repo**: the engine + the published plugin API contract doc.
- **Distribution repo per niche** ("the modpack"): a `composer.json` requiring core + the plugin set, a config/seed pack (vocabulary, defaults, theme, demo content), and the manifest the central build resolves into a deployable image.
- **Central build**: the existing `deploy.yml` → GHCR → immutable VERSION pipeline, generalized to build a distribution instead of one repo. FM polls `/api/health` per install exactly as today; a plugin manifest in the health payload is a future FM-contract item (Two-Repo Coordination Protocol — not this session).

**Changeability — the three-tier model (settled in discussion).** When a client needs something their niche configuration excluded, the fix depends on which tier the feature sits at: (1) *in the image, switched off* → flip a per-install activation flag (minutes; FM's existing config-push channel is the delivery mechanism); (2) *exists but not in the niche image* → add it to the distribution manifest, version-bump, existing CI rebuilds and ships (an hour, no code); (3) *doesn't exist* → build it, as a self-contained package. Recommendation: **niche images are supersets** — include every plugin a client in the vertical might plausibly want, let per-install activation decide visibility — so the common case stays at tier 1. Consequences: the per-install activation layer becomes **load-bearing for the business model** (and is today the least-built part of the vision — no activation mechanism exists anywhere); and enable/disable are asymmetric — enabling later is cheap, disabling must mean "hidden and inert, writes stopped," never "data deleted" (a rule the plugin contract must state).

---

## 5. What already exists vs. what must be built (inventory summary, session 376)

**Exists:** the widget contract + registry + deploy-integrated sync; the data-contract/ContractResolver path (proven plugin-safe boundary); an out-of-band central asset build server; an immutable-versioned central image build; a working two-repo contract discipline (FM, v2.7.0); consistent registry patterns (importers, email handles, definitions-in-provider).

**Missing:** any stated definition of "core"; runtime pluggability anywhere (every seam is a hardcoded list); per-install activation of anything; composer path-repos / npm workspaces (single monolithic package); a migrations-per-plugin slot (squash + monolithic seeder); a plugin API contract doc.

---

## 6. Question dispositions (running list)

**Resolved in session:**

1. **Contact extension: relations on id, only** *(owner ruling)*. Plugins attach data via plugin-owned tables keyed by `contact_id` — no columns added to core tables, no schemaless-blob co-mingling. This is also the compliance-friendly direction: plugin-owned tables can be independently encrypted (per-column casts), access-scoped, audited, and even moved to their own database connection later — exactly the doors a future HIPAA-class requirement needs open. Corollary: core `Contact` stays minimal identity (name/contact info). UI half: the contact admin screen exposes a socket where plugins contribute their own panels.
2. **Payments is a foundation plugin; verticals never depend on each other** *(owner guideline)*. Hard dependencies point only at core; foundation plugins are optional capabilities a vertical detects and degrades without (Events without Payments = free events/calendar only — a common real use case). See §4b and §4c-13.
3. **Tests: per-plugin suites; the monolith is dismantled** *(owner ruling)*. Each plugin carries its own suite; core keeps engine tests plus a thin composition smoke suite; the central build runs core + the distribution's plugin suites. Extraction doubles as the audit moment — reduce and de-duplicate while pulling apart. Add-a-test discipline becomes a stated rule in the plugin contract.
4. **Composition mechanics: both layers, superset images** *(from the three-tier changeability model, §4d)*. The niche manifest decides what is *in* the image at build time; per-install activation decides what is *on* at runtime — the fast, common path. Remaining detail: the contract's definition of "disabled" (inert — no routes registered, no nav, no scheduler jobs, writes stopped, data kept).

5. **Filament coherence dissolves into three contract conventions** *(owner-approved)*: (a) core owns the nav-group list; a plugin declares which group its pages join plus a sort weight; (b) a plugin declares its permissions, core seeds them at enable time, granted to no shipped role by default except super-admin (the existing `manage_account` precedent); (c) plugin admin pages inherit core's admin theme — plugins ship no admin CSS.

**Open:**

6. **The real niches** — deferred to owner research. Needed only ahead of distribution-layer work, not before; the core line drawn here does not wait on it.
7. **Migrations vs. the squash discipline** — mechanics understood; the work is (a) redrawing the squash boundary so core's schema dump excludes plugin tables while each plugin owns its own migration list, (b) deterministic install order: core schema → enabled plugins' migrations → seeders (enabling a plugin later runs its migrations then), (c) a per-composition fresh-install identity check replacing today's single fixed-schema assumption. Cost class: about a session, landing with the first domain-vertical extraction. Discipline problem, not a danger problem.

---

## 7. Scope ladder, session arc & feasibility (proposed — under discussion)

The ladder is the de-risking order: each stage is independently valuable and independently stoppable, and each is where a specific class of problem is cheapest to discover.

### Stage A — module boundaries in-repo (~4–6 sessions)

No repo split, no build change. Formalize the contract and prove the boundaries are real; this is where entanglements surface cheaply.

- **P1 (session 377) ✅ — Pilot widget as a self-registering module + plugin contract v0.1.** Done: LogoGarden (carried both JS and SCSS — asset seam exercised end to end) extracted to `plugins/LogoGarden/`, registered solely via its own provider through `config/plugins.php`; `docs/plugin-contract.md` v0.1.0 drafted from what the extraction proved. See the 377 log.
- **P2 (378) — Make the widget boundary real across all 41.** The data-contract path becomes mandatory; the model-reaching templates (Nav, DonationForm, Portal set) are routed through declared contracts; a standing guard test (the `DesignGroupIntegrityTest` pattern) bans model calls in widget templates from then on.
- **P3 (379) — Admin-shell sockets.** Decompose `AdminPanelProvider`'s inline route closure into per-feature route files; implement the three approved Filament conventions (nav groups + sort weight; permissions seeded at enable, super-admin-only by default; core-owned admin theme).
- **P4 (380) — Payments foundation module.** Carve the Stripe rails (customer records, checkout, webhooks, receipt plumbing) into an in-repo module behind the capability-detection API; verticals call "is payments enabled?" instead of reaching for Stripe directly.
- **P5 (381) — First domain vertical in-repo.** Donations or Events (decide at P4 close, informed by how the payments carve-out went). Exercises the whole contract at once: contact-relation socket, own migrations-to-be, admin panel socket, routes, optional payments dependency.

### Stage B — real packages, one workspace (~3–5 sessions)

Packaging mechanics proven while iteration stays cheap (everything still one repo, one CI).

- **P6** — composer path-repository mechanics; the pilot widget (or a widget pack) becomes the first true package with its own `composer.json`; the squash boundary is redrawn (core schema dump excludes plugin tables; per-composition fresh-install check). Absorbs the parked launch item T1's squash.
- **P7** — the Stage-A vertical becomes a package: own migrations, own test suite (the test-monolith dismantling begins here, with the reduction pass riding along).
- **P8** — the central build assembles the workspace; the **per-install activation layer** lands (the tier-1 flag-flip path — currently the least-built, most business-load-bearing piece).

### Stage C — separate repos, central build (**C-lite**, ~2–4 sessions)

The full vision says *everything* eventually lives in its own repo; the demonstration needs only **one** plugin to make the round trip. C-lite proves the distribution story end to end without migrating the world.

- **P9** — one plugin package moves to its own repository, versioned and contract-disciplined exactly like the FM relationship (the in-house precedent). **Deliverable includes the extraction runbook** — the repeatable step-by-step for taking a package to its own repo. The owner intends to run the remaining extractions rapidly after the first, so the process write-up is as much the product as the split itself *(owner directive, 376)*.
- **P10** — a distribution manifest + the central build resolving core + the external plugin into a deployable image. (Touches `deploy.yml` → future FM coordination items under the Two-Repo Coordination Protocol.)
- **P11 (optional, gated on the owner's niche research)** — the first niche distribution pack: manifest + config/seed/vocabulary pack.

### Feasibility verdict (draft)

- **Total to a demonstrable full-vision-in-miniature: ~9–15 sessions** (A+B+C-lite). Against a few-weeks owner budget at the historical pace, that is comfortably reachable with headroom for splits.
- The claim it supports: *"a real, 375-session production application, decomposed into a thin core and plugins with a published contract; features arrive as packages; one plugin lives in a separate repo and is composed in by a central build; a niche product is a manifest."* Every clause is demonstrable in code, not slideware.
- **Stopping points are honest:** stopping after A leaves a better-factored monolith with a real contract doc; after B, an app assembled from packages; after C-lite, the separate-repos story proven. No stage strands the codebase mid-migration — un-extracted code simply stays core until its turn.
- Full Stage C (every plugin its own repo) and the niche business layer remain open-ended future work, deliberately unscheduled — but P9's runbook is written so the owner can run those extractions rapidly on their own cadence.
- **Owner commitment (session 376): the arc through C-lite is the plan.** Sessions carry labels P1–P11; session numbers are assigned at session start per house rule.

### Execution posture (owner directive, session 376)

This arc runs faster and more autonomously than the fine-tuning era, because its success criteria are binary:

- **Binary scoping.** Every session's success criterion is objectively checkable: fast suite green, standing guards green, fresh-install identity clean, build clean, app boots. Extractions are behavior-preserving by definition, so the existing test and parity nets are the oracle. **No UAT step** unless a session genuinely changes a visual surface (most don't); the owner confirms at merge points, not mid-session.
- **Decisions are front-loaded into prompts.** Design details get resolved when the session prompt is drafted, not discovered mid-session. Mid-session, the default is decide-per-plan-and-log; pause-and-ask is reserved for contract-surface changes that are expensive to reverse. The goal: near-zero between-session open questions that amount to the owner saying yes.
- **Scope creep is structurally blocked.** Each session's out-of-scope list is binding. Adjacent work discovered mid-extraction goes to the arc backlog or the housekeeping inbox — never absorbed into the running session.
- **The contract doc is the arbiter.** Once `docs/plugin-contract.md` exists (P1), "does this follow the contract?" replaces most judgment calls.

---

## 8. Non-goals

- Plugins patching plugins (Principle 5).
- Community/third-party authorship tooling — later-stage line item only.
- Any FM-boundary change this session; Stage-C touches to `/api/health` or `deploy.yml` are future coordination items under the Two-Repo Coordination Protocol.
