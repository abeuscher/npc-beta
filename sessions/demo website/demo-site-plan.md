# Demo Site — Multi-Session Plan

**Drafted:** session 324 (2026-05-28). Planning session, no application code.
**Status:** the canonical scope + design reference for the demo-site build sessions (A–F below).
**Supersedes:** A006 (cloud Demo Site Page Design — retired and deleted from the repo at 323 close).

This document is the v1 cut of the two demo-website specs, the settled architecture
decisions (per-page permission, calendar, tour anchors), and the session-by-session
build plan. The two source specs — `events-index-spec.md` and `event-lp-spec.md` —
remain in this folder as the aspirational input; **this plan is what gets built.**

---

## 0. Context — what the demo node is

- The **public marketing site is already complete** and lives on its own node (`nonprofitcrm.com`). It is **not** in scope here and is never touched by demo work.
- The **demo node** (`demo.nphelper.com`) is a separate, isolated droplet. It never shares a database, content, or egress with the marketing site. A prospect arrives via `/demo/enter`, is auto-logged into a shared `demo`-role account, and lands on the **demo arrival page** — the locked landing.
- Infrastructure already in place (session 321): `demo:reset` (idempotent baseline reseed, guarded on `isDemoMode()`), `DemoBaselineSeeder` (~40 contacts / 6 events / 25 registrations / 30 donations / 15 memberships / 5 posts / 6 products), `DemoRoleLockdownTest` (standing guard), and the `demo` role widened to events + donations CRUD.
- FM-side controls (node identity, `IMAGE_TAG` pin, egress firewall blocking SMTP/outbound) are handed off separately — not CRM-side, not in these sessions. See `/home/al/fleetmanager/sessions/demo-node-handoff-from-crm.md`.
- **Reference screenshots** (luma index + SFMOMA LP) live in `sessions/demo website/scrap/` — **local-only, gitignored, not committed.** Referenced here by relative path only.

---

## 1. Phase 1 — v1 cut of the two specs

The specs were written as "what an events module *could* be." On the demo node we own
the seed data, so several spec features defend against cases the demo never hits. The
cuts below prune to a credible v1 surface.

### 1a. Events index — v1 cut

| Section | v1? | Note |
|---|---|---|
| Day-grouped vertical list (luma shape) | ✅ v1 | additive list-mode preset on the existing `EventsListing` widget |
| Row anatomy: time / title / host / location / thumbnail | ✅ v1 | host + location optional per row |
| Filter row — Event Type + Date Range | ✅ v1 | 2 controls, no search |
| Featured-event hero | ✅ v1 | one featured event |
| Mini-calendar (right rail, density dots, click-to-scroll) | ✅ v1 | server-rendered, vanilla — see §2b |
| Status badges (Members Only / Waitlist / Sold Out / Free / Registered) | ✅ v1 | partly modeled already |
| Empty / sparse states | ✅ v1 | the demo will hit these |
| `ItemList` / `Event` schema + canonical + OG | ✅ v1 | low cost, high credibility |
| Art fallback chain | ✅ v1 (simplified) | event image → event-type default → **site default thumbnail** → solid color. **No generated text-tile** (that defended the no-art real-org case; the demo owns its seed art). |
| Location → map modal / new tab | ⛔ defer v2 | map embed is JS/perf cost the spec itself flags as opt-in |

**Reference:** `scrap/event-index-example.png` *(local-only, not committed)*.

### 1b. Event landing page — v1 cut

| Section | v1? | Routing of deferred items |
|---|---|---|
| 1. Header (hero / eyebrow / title / date-time / location) | ✅ v1 | mostly exists |
| 2. Registration card | ✅ v1 — **only the variants the widget already does** | free / paid-single / paid-tiered / capacity / closed (existing `EventRegistration` widget) |
| 3. About (rich text) | ✅ v1 | existing `EventDescription` widget |
| 9. Share (copy link + a couple socials) | ✅ v1-lite | no data model |
| 10. Related / next events | ✅ v1 | reuses the index events query |
| `Event` schema + OG/meta | ✅ v1 | low cost |
| 8. Practical info | ➜ folded into About for v1 | structured fields later |
| 4. Hosts / organizers | ⛔ defer | **Organizations model (B1a, shipped)** + B1b affiliations junction — lighter than greenfield |
| 5. Sponsors (tiered) | ⛔ defer | **sponsor field already on Event admin form (B1a)** — only the public tiered display is new |
| 6. Volunteer signup | ⛔ defer | **Volunteer Management** (whole roadmap section, post-Beta — depends on Member Portal + DOB/age) |
| Donation-event variant (giving levels / custom amount / recurring) | ⛔ defer | **C4 (Donation-to-acknowledgment loop) + C3b (Auto tax receipt email)** |
| Member pricing ("$50 · Member $35") | ⛔ defer | **C6 (Membership renewal cycle)** — lifted post-Beta |
| Rich registration depth (comp tickets / zero-total / thank-you email) | ⛔ defer | **C5 (Event with everything)** — Beta-1 rehearsal slot; cousin to the LP work |
| 7. From past years (gallery) | ⛔ defer v2 | manual photo section + lightbox |
| Print stylesheet (flyer + QR) | ⛔ defer v2 | its own polish pass |
| Embedded map | ⛔ defer v2 | perf/JS, opt-in |

**Key routing principle:** the deferred LP sections are **not** new demo-specific promises.
Each is owned by an existing roadmap slot (C4/C3b, C5, C6, Volunteer Management, B1a/B1b).
The demo LP is built against the registration widget **as it exists today** — it does not
wait on those slots, and they do not wait on it. When **C5 (Event with everything)** later
runs, it can rehearse against the demo LP.

**Reference:** `scrap/event-detail-page-example.png` *(local-only, not committed)*.

---

## 2. Phase 2 — settled architecture

### 2a. Per-page permission — `restricted_roles` column

**Decision:** add a `restricted_roles` column to `pages` (jsonb, default `[]`), listing
role names **barred from editing that page**. Chosen over a `page_permissions` join table
(per-action granularity is YAGNI now) and over an `is_locked_from_demo` bool (the
exception shape the owner explicitly rejected).

- **Deny-list, not allow-list.** Default `[]` = unrestricted; every existing page keeps working unchanged. Locking = add a role. (An allow-list would invert the default and break every page.)
- **Concrete value:** default `[]`, never null (concrete-values rule).
- **Enforcement:** in `PagePolicy::update()` (super_admin bypasses via the existing `Gate::before`). The build session must **also wire that policy check into the page-builder API route** (`routes/admin-api.php`, `pages/{owner}/...` via `ResolvePageBuilderOwner`) — today that route does not check the page policy per-page, and the page-builder is the real edit surface. This is the one new bit of wiring; the rest is a column.
- **Admin UI:** a super_admin-only multiselect on the `PageResource` edit form ("Roles barred from editing this page"). Generalizable to any page × any role; the demo arrival page is just the first consumer.
- **demo:reset:** seeds the demo arrival page with `restricted_roles: ['demo']`, re-applied on every reset so it survives the daily wipe.
- **Existing precedent to reuse, not extend:** `type = 'system'` already protects infrastructure pages, but only in the Filament UI (slug lock, delete hidden) and by type, not role. The demo landing is a content page locked from a role — a distinct concern; do **not** overload `type = 'system'` for it.

### 2b. Calendar — server-rendered, vanilla

**Decision:** the events-index mini-calendar is its own small widget dropped into the
index right-rail column. **vue-cal is retired from the plan.** Rationale: the public side
has zero Vue runtime (Vue 3 lives only in the page-builder admin app), the existing
`EventCalendar` already used a vanilla lib, and a density-dot mini-month-grid does not
justify standing up a Vue runtime on the public side.

- **All date math server-side in PHP/Carbon** (month boundaries, weekday alignment, leap years) → rendered as a static Blade month grid. **No date arithmetic in JavaScript** = no "date-exception hell."
- JS handles only the dumb parts: scroll-to-day on click, month-nav.
- Density indicators (dot / fill, not titles) computed server-side from the published-events query.
- Desktop-only; hides on narrow viewports. Navigation, not filtering — the list does not collapse to the picked date.
- **Build-session detail (not blocking):** month-nav either fetches the requested month's grid from a tiny server endpoint (still Carbon-computed) or pre-renders a ±1-month window toggled client-side. The architecture (server-side dates, dumb JS, no Vue) is locked; the nav mechanism is the build session's call.

### 2c. Driver.js tour — anchor convention reserved

**Decision:** reserve a **`data-tour` attribute scheme** now so the later tour build does
not have to retro-annotate every template.

- Dot-namespaced by surface: `data-tour="events-index.list"`, `events-index.featured`, `events-index.mini-calendar`, `event-lp.registration-card`, `nav.primary`, `portal.account`, etc.
- Widget templates declare their own primary anchor (one stable anchor per widget); page-level structural anchors live in layout markup.
- **Contract: anchors are a stable API.** Renaming one breaks a tour — they are added deliberately, not ad hoc.
- The build sessions (A–F) add `data-tour` attributes to the demo-relevant widgets **as they touch them**. No tour code is written until the downstream tour-build session.
- **Why this doesn't contradict session 249** (which ruled driver.js/shepherd/introjs out for the onboarding checklist): 249's concerns were the Filament/Livewire admin DOM — selector instability across Filament version churn. The demo tour drives **our own public CMS pages** — our markup, our selectors, a stable DOM. The 249 concerns do not apply here.

---

## 3. Phase 3 — the build sessions

> **Update (session 325 close):** the "optional / no landing page" model is **retired**. Building the mini-calendar surfaced that it was collecting compensating complexity (dead-end URL fallback, `has_landing_page` conditionals, an inline-detail surface per widget). Resolution, owner-decided at 325: **every event has a real landing page**, created at event-creation time via a **Simple / Standard** choice. This honours the original "don't force art/copy" goal *better* — the simple page needs no image — while killing the dead-end at the root and simplifying every downstream widget to "just link." Consequences: **C (now "Event landing pages") is reshaped and reordered ahead of B**, because the listing fix (B) depends on every event having a resolvable URL. C ships as **session 326**, B as **session 327**.

| # | Session | Scope | Depends on |
|---|---|---|---|
| **A** | **Events — calendar swap** ✅ *(session 325)* | Retired `EventCalendar` (full sweep — §4). Built the server-rendered, self-contained mini-calendar (§2b): day/month list modes, density dots, expandable inline event detail, `data-tour` anchor. | — |
| **C** | **Event landing pages — Simple & Standard** *(session 326; reshaped + moved ahead of B)* | Every event gets an LP at creation via a **Simple / Standard** toggle. **Simple** (free events only): a normal page seeded with hero + event-details (image optional, no reg form). **Standard** (forced once ticketed): hero + event details + registration card; the fuller v1 LP per §1b (About folded in, Share, Related events, `Event` schema/OG). LP is a plain `Page` linked via `events.landing_page_id`, editable in the builder; **deletion guarded via the existing `PageObserver` block-with-counts pattern** (no event left LP-less). Relink the mini-calendar + `EventsListing` rows to the LP URL and strip the dead-end fallback. `data-tour` anchors. | A |
| **B** | **Events — listing upgrade** *(session 327)* | Additive to `EventsListing`: list-mode preset (day-grouped luma shape) alongside the existing grid/swiper; filter row (Event Type + Date Range); featured-event hero; art-fallback chain (§1a). Rows link to the now-always-present LP. `data-tour` anchors. | C (every event has a resolvable LP URL) |
| **D** | **Per-page permission + locked landing** | `restricted_roles` column + migration + `PagePolicy` + page-builder API enforcement + super_admin-only multiselect UI + the demo arrival page seeded with `restricted_roles:['demo']`, re-applied by demo:reset (§2a). | — (independent) |
| **E** | **Demo node assembly** | Header/footer duplication (§5); demo:reset extended to seed the curated showcase pages so they survive the daily wipe; **resolve the Faker/`--no-dev` blocker** (§6). | A, C, B, D |
| **F** | **Portal demo (back-end-only v1)** | Curate the admin demo's portal-management surface; reserve `portal.*` tour anchors; seed portal-shape data. *May fold into E.* | — |

**Order:** A ✅ → **C (326)** → **B (327)** → D → E → F (D still unblocked, can interleave; E gates on everything as it assembles the curated pages).

**Count:** ~6 sessions for demo v1, possibly 5 if E+F merge. Not pinned harder than that.

### Downstream follow-ons (hooks reserved now, not in v1 sequence)

- **Driver.js tour build** — consumes the `data-tour` anchors A–F lay down. Its own session.
- **Real seeded portal login** — the authentic portal experience: a verified seeded portal user + demo-mode gating of the email-dependent portal flows (signup / forgot-password / email-change dead-end under `mail=log`) + a portal-side lockdown audit (mirror of `DemoRoleLockdownTest`). ~1 session. v1 ships portal back-end-only.

---

## 4. EventCalendar retirement surface (for session A)

Known references to sweep (enumerated at 324; the build agent should re-verify):

- `app/Widgets/EventCalendar/` — whole folder (Definition, DemoSeeder, script.js, styles.scss, template.blade.php, thumbnails).
- `app/Providers/WidgetServiceProvider.php` — the `use` import + `$registry->register(new EventCalendarDefinition())`.
- `app/Services/AssetBuildService.php` — the `jcalendar` lib definition.
- `resources/js/public.js` — the jcalendar import + CSS import.
- `app/Providers/Filament/AdminPanelProvider.php` — a comment mentioning jcalendar (cosmetic).
- The **`widget_types` seeder** — removes the `event_calendar` row (decide: migration vs seeder edit + whether existing demo DBs need a data migration to drop placed instances).
- **`DemoBaselineSeeder` / any seeded page** placing an `event_calendar` widget — must not reference the retired type.
- Tests referencing `event_calendar` / `jcalendar` — update/remove: `WidgetManifestTest`, `InlineEditingFoundationSession304Test`, `WidgetJsLibsSession138Test`, `BuilderPublicRenderParityTest`, `WidgetTypesSession098Test`, `WidgetAssetResolverTest`.
- After removal, rebuild the public bundle (`build:public`) — the manifest must no longer list `jcalendar` or `event_calendar`.

The back-end calendar use case (scheduling, recurring events) is **deferred to post-Beta scheduling work**, not preserved as an admin-only widget.

---

## 5. Header/footer duplication (for session E)

The public-facing portion of the demo node (the locked landing page) carries a duplicated
header + footer from the main public site and links back to it.

**Decision:** **point-in-time import via the demo:reset cycle from a versioned export**
(option b). Fits the existing demo:reset wiring, refreshes for free on each reset, and
avoids the live-fetch coupling (rejected — the demo must never call the marketing site at
render). A manual re-export refreshes the snapshot when the marketing chrome changes.

---

## 6. Faker / `--no-dev` blocker (must resolve in session E, before the demo node goes live)

`demo:reset`'s baseline step drives `RandomDataGenerator` → model factories → `fakerphp/faker`,
which sits in `require-dev`. The production image builds `--no-dev`, so on the real demo
droplet the baseline step fails with `Class "Faker\Factory" not found` (surfaced in the 321
dress-rehearsal). The `migrate:fresh --seed` half is unaffected — only the curated CRM
baseline needs faker. Resolution is a deliberate decision for session E: build the demo node
from a dev-deps image, move `fakerphp/faker` to `require`, or seed the baseline without
factories. Must land before the live demo relies on `demo:reset`.

---

## 7. Open questions carried forward

- **Calendar month-nav mechanism** (endpoint-fetch vs pre-rendered ±1-month window) — session A's call.
- **widget_types removal mechanism** (migration vs seeder edit; data migration for existing placed instances) — session A's call.
- **E/F merge** — decide once D lands and the portal-management surface is scoped.
- **Faker resolution** — session E (see §6).
