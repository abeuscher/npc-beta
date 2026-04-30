# Nonprofit CRM — Roadmap

This is the active product roadmap. Forward-looking only — what's coming, what's planned for Beta 1, what's deferred to post-Beta 1. Closed work is not narrated here.

**How the planning artifacts are organized:**

- **Per-session work** — each session has a numbered prompt + log file in `sessions/` (and `sessions/archived/` once it ages out).
- **Completed-sessions index** — flat lookup table in `sessions/completed-sessions.md`. One row per session, title + number.
- **Tracks** — long-running architectural arcs that span multiple sessions and (eventually) multiple releases live in `sessions/tracks/{name}.md`. Each track doc carries a status snapshot, compressed phase retrospectives (history), and the forward plan. **When a phase inside a track closes, its history compresses into the track doc and its entry in this roadmap collapses to a one-liner.**
- **Releases** — per-release scope will eventually live in `sessions/releases/{version}.md`. Not in use yet — emerges when a release approaches.
- **Beta One milestone** — the first shippable, demonstrable version: a live hosted site and a live install demo performable for prospects in real time. All sessions before the Beta 1 marker below are planned for Beta 1 delivery; sessions after the marker are deferred until post-Beta 1.

---

## Active tracks

- **Widget Primitive** — *substantially complete* (Phase 6 closed at session 237). See `sessions/tracks/widget-primitive.md` (premise: `widget-primitive-premise.md`). Carry-forwards remain (none scheduled): Forms widget retrofit, `PageContext` full retirement, per-record-type `RecordContextTokens::TOKENS` expansion, `PageContextTokens` namespace migration.
- **Fleet Manager Agent** — *substantially complete* (Phase 2 closed at session 242); **Beta-1-blocking work resumes** for node operations parity (install / backup / restore / log-reading) per `sessions/release-plan.md` § A2. See `sessions/tracks/fleet-manager-agent.md`. Product spec for both repos: `sessions/fleet-manager-planning-spec.md`. Contract surface live at v1.2.0; spec doc canonical at [`docs/fleet-manager-agent-contract.md`](../docs/fleet-manager-agent-contract.md). Other carry-forwards remain unscheduled: custom artisan command for agent-key generation, Filament UI for agent key, key rotation flow, multi-tenant API key support, BackupHasFailed event listener.

---

## Cross-Repo: Fleet Manager / CRM

Two parallel agentic workstreams run across two repos (this CRM repo and a separate Fleet Manager repo, to be created). The agent contract surface — the HTTP shape Fleet Manager polls — is governed by a shared spec doc plus this status block. See `sessions/fleet-manager-planning-spec.md` ("Two-Repo Coordination Protocol") for the discipline.

- **Agent contract version:** `1.2.0`
- **Spec doc:** [`docs/fleet-manager-agent-contract.md`](../docs/fleet-manager-agent-contract.md)
- **Canonical URL (used by FM repo via WebFetch):** `https://raw.githubusercontent.com/abeuscher/npc-beta/main/docs/fleet-manager-agent-contract.md`
- **Last boundary-touching session in this repo:** session 242 (Phase 2 Backup Pipeline; flipped `last_backup_at` from hardcoded `unknown` to threshold-driven against pg_dump + media → DO Spaces success-record file; bumped 1.1.0 → 1.2.0)
- **Last boundary-touching session in Fleet Manager repo:** FM session 005 (refreshed local cache to v1.1.0; `ContractValidator` widened to accept `unknown`; `StatusInterpreter` rewritten to honour CRM-reported `unknown` directly)
- **Pending boundary changes:** none — v1.2.0 is the current shippable state. The FM-side cache is at v1.1.0; it refreshes on FM's next boundary-touching session and `StatusInterpreter` aligns to the new threshold-driven shape at the same time.

---

## Housekeeping & Review — Beta 1 Scope

*Ordered by priority.*

### Widget Primitive — Remaining Phases *(track substantially complete; carry-forwards unscheduled)*

Track substantially closed at session 237. Carry-forwards (none scheduled): Forms widget retrofit, `PageContext` full retirement, `RecordContextTokens::TOKENS` per-record-type expansion, `PageContextTokens` namespace migration. Forward plan, design decisions, phase retrospectives, and status all live in `sessions/tracks/widget-primitive.md`. Premise lives in `sessions/tracks/widget-primitive-premise.md`.

### Fleet Manager Agent — Remaining Phases *(track substantially complete; carry-forwards unscheduled)*

Track substantially closed at session 242. Carry-forwards (none scheduled): custom artisan command for agent-key generation, Filament UI for agent key, key rotation flow, multi-tenant API key support, BackupHasFailed event listener, backup-restore tooling, per-install retention configuration beyond the 14-day default. Forward plan, design decisions, phase retrospectives, and status all live in `sessions/tracks/fleet-manager-agent.md`. Product spec for both repos: `sessions/fleet-manager-planning-spec.md`.

---

### Page Builder Focus-Scroll Clamp *(stub — pre-Beta 1, successor to session 204)*

Session 204 shipped the scroll-to-viewport-centre-on-selection behaviour but descoped the stretch clamp before implementation. Stretch items still open:

- **Scroll lock:** once a widget is focused, the viewport cannot scroll past it on wheel / touch. Implementation path: listen to `wheel` / `touchmove` on the canvas container and short-circuit events that would move the focused widget out of view.
- **Tall-widget exception:** if the focused widget is taller than the viewport, the clamp behaves as a scroll container for that widget only — up-scroll locks at the widget top, down-scroll locks at the widget bottom; the rest of the page is inaccessible while focus holds.

Descoped in 204 on the grounds that wheel/touch interception fights browser inertial scrolling and the nested-scroll case inside column widgets is adversarial. Reopen only if user testing confirms the must-have scroll-to-centre isn't enough.

---

### Code Review & Cleanup + Migration Squash *(stub — pre-Beta 1, terminal session; T1 in `release-plan.md`)*

Final pre-release session combining a code review pass with a migration squash. Per the plan's Rule 10, this is the *terminal* session — every other entry in `release-plan.md` must close first.

Code review portion follows the pattern of sessions 101, 116, 141, 178/179, and the most recent 205/206 pair (audit → apply; both complete). Scope: dead code and unused imports, duplicated logic ripe for extraction, inconsistent naming, outdated comments, drift from framework conventions, test coverage gaps surfaced since the last review.

Migration squash portion: collapse the per-session migration history into a single squashed migration set against the v1 schema baseline.

Both halves land in one branch; no code change after T1 closes.

---

### Notes Permissions (feature half) *(stub — pre-Beta 1; C1 in `release-plan.md`)*

Half-split from the original Notes Permissions & Permissions Audit stub at session 244. The *audit* half is consumed by C3 (Permission audit + Concurrent admin editing + Accidental public exposure) — see `sessions/release-plan.md` § C3. This stub now covers only the feature work.

Session 198's Notes → structured interactions work introduced authoring surfaces (subtype, direction, outcome, participants, meta) that need finer-grained permission gates than the current `create_note` / `update_note` / `delete_note` trio — e.g. who can edit a note authored by someone else, who can change the subtype of an existing note, who can see internal-only notes. A specific shape surfaced during session 203 manual testing: **edit-only-by-creator** (the authenticated user must equal `notes.author_id` to edit) as an opt-in tenant-level setting, with a separate override permission for managers.

C1 must close before C3 runs (the audit walks the surface that this stub builds out).

---

### Organizations Model Overhaul *(stub — pre-Beta 1)*

Surface-level, Organization is a real entity in the CRM; below the surface, it is a placeholder — a row with a name and not much else. Testers noticing this gap will have valid questions. Session should define what Organizations actually are and how they relate to the rest of the model:

- **Relationships**: Contact `belongsTo` Organization (today — employer/member org) is one role; Events may have Sponsor/Host Organizations (many-to-many with role); EventRegistration may carry a distinct "registering organization" for corporate group sign-ups. Donations & memberships may also originate from an Organization rather than a Contact.
- **Importer implications**: session 189 added a narrow `__org_contact__` sentinel that fills `Contact.organization_id` only when blank. That's a pragmatic stopgap, not a real model. Once this session lands, the events importer can offer richer Org destinations (sponsor on Event, employer on Contact, registrant on Registration) without ambiguity.
- **UI**: Organization admin resource today is minimal. Needs an "Events sponsored" / "Members" / "Donations" panel so the entity pays its own way in the nav.
- **Migration**: existing Contacts with `organization_id` set keep their link; new role-scoped pivot tables added; Event's string `company` field on registrations becomes a FK when resolvable.

Priority: before Beta 1 demo — prospects testing the import flows will hit the gap, and the answer shouldn't be "yeah, it's a placeholder."

---

### Event Ticket Tiers *(stub — pre-Beta 1)*

Promote ticket pricing from a single `price` field on Events into a tiered structure. Events hasMany `TicketTier` (name, price, capacity, sort order). EventRegistration picks up a `ticket_tier_id` FK. Admin event form gets a repeater for tiers. Public registration flow shows tier options. Data migration: existing events with a non-zero price get a single "General" tier created on migrate. Session 189's Events importer already carries `ticket_type` + `ticket_fee` on registrations; this session retroactively links those to Tier rows where the names match and back-fills where they don't. Priority: needed before event-registration imports become truly first-class, and before any nonprofit with tiered memberships (almost all of them) can demo the product.

---

### Theme Colors Refactor *(stub — pre-Beta 1)*

Complete the theme/template split started in session 182 by moving colour-related template columns into the theme (`SiteSetting`). `primary_color` is clearly theme-level; `header_bg_color` / `footer_bg_color` / `nav_*_color` are ambiguous (template-level header/footer chrome vs site-wide branding). Decide per-column placement with the benefit of lived experience from session 182 and migrate accordingly.

---

### Rich Text Custom Fields *(stub — pre-Beta 1)*

Custom fields currently support `text`, `number`, `date`, `boolean`, and `select` types. Add a `rich_text` type so admins can capture formatted content (bios, descriptions, multi-paragraph notes) as a custom field. Uses the existing Filament rich editor primitive on admin forms; renders HTML on detail views and in widget output. Importer treats rich-text cells as plain strings (no HTML parsing) — same shape as `textarea` today.

---

### Mobile Type Scaling — Per-Breakpoint Sizes in the Design System *(stub — pre-Beta 1, lifted from session 243 Phase 6)*

Page text doesn't shrink correctly on narrow viewports — typography stays at desktop sizes even when layouts collapse. The clean fix surfaces a typography-system architectural concern that doesn't fit a small CSS-edit envelope: `TypographyCompiler` emits user-configured per-element font sizes into an inline `<style>` block that loads after the Vite-compiled bundle, so any SCSS-only fix is overridden by the user's typography config on installs that have configured typography (the encouraged path).

User-supplied design (captured at session 243 close):

- **Per-element breakpoint fields in the design system.** In each font/text-style control on `DesignSystemPage` (Theme → Text Styles), add 3 fields beneath the size picker — text sizes for the font at the lg / md / sm breakpoints below xl (the existing `$bp-xl: 1200px`, `$bp-lg: 992px`, `$bp-md: 768px` from `_variables.scss`).
- **25%-per-step default.** Each breakpoint defaults to 75% of the next-larger breakpoint's value, rounded to 1 decimal point. So an h1 of 2rem at xl defaults to lg = 1.5rem, md = 1.1rem, sm = 0.8rem. User can override any value.
- **Storage migration.** Each element's `font.size` becomes `{ xl: {value, unit}, lg, md, sm }` instead of a flat single value. Existing rows on first read/save copy current value into `xl` and auto-derive lg/md/sm via the 25%-per-step rule.
- **Compiler change.** `TypographyCompiler::elementDeclarations()` emits one `font-size` declaration today; in the new shape it emits the xl declaration plus three `@media (max-width: $bp-X)` blocks per element. The clamp-vs-mediaqueries question disappears — media queries with fixed steps, user-controllable per element.

Out of scope: a separate "responsive scale" toggle (the per-breakpoint fields are always present); per-element opt-out (set the breakpoint values equal to the xl value if no scaling wanted); custom breakpoint widths beyond the existing three.

The user noted at 243 close: "the type situation in general is not good yet" — this stub is one piece of a broader typography-system stabilization that may surface additional design discussion when scheduled.

---

### Housekeeping — Batch 2 *(stub — pre-Beta 1, deferred)*

Items that didn't make Batch 1's working set — either scope-flagged (might exceed housekeeping size at scoping time), design-prerequisite (needs a sibling discussion or stub to land first), or lower demo / onboarding priority for the next iteration. Each can be lifted into a Batch 2 session whenever the timing fits, or promoted into a Batch 1 successor if it becomes more pressing. Same crispness rule — if any item turns out to need design-level conversation, lift it into its own stub rather than letting the batch absorb the cost.

- **Text widget vertical alignment.** The `text_block` widget currently renders with text aligned to the vertical middle of its container, which doesn't fit all column-layout use cases. Add a vertical alignment control (top / middle / bottom) to the widget's inspector. Design call to resolve during the session: whether this lives on the widget itself (`align-self` on the wrapper) or as a generalized "widget vertical alignment" control that all widgets inherit — the latter is probably the right shape but the former is the narrower change. Deferred from Batch 1 because the design call is real and may push it past housekeeping size.
- **In-app actions that should trigger a build.** Sweep the admin UI for actions that change files the front-end bundle depends on (theme SCSS editing on the Design System page, per-template `custom_scss`, any widget asset editing surface) and confirm each one either fires the matching build automatically or surfaces a clear "rebuild required" affordance to the admin. Unconfirmed whether any gap exists — this is an audit, not a known bug. Focus areas: theming, templates, design system, widget manager. Deferred from Batch 1 because audit-shaped work tends to surface findings that themselves want fixing in scope.
- **Dev-environment orphan media cleanup.** Custom `php artisan app:reset` (refuses to run when `APP_ENV=production`) that wraps `migrate:fresh --seed` with `rm -rf storage/app/public/* storage/media-library/temp/*` so the on-disk media tree stays synchronized with the DB. Root cause surfaced at session 242 close: `migrate:fresh` truncates tables via raw SQL, which bypasses Eloquent's `deleting` events — Spatie Media Library's "delete file when model deleted" observer never fires, so every dev-cycle reseed leaves the previous run's media files behind. Image conversions multiply the leak by ~6× per original (responsive WebP breakpoints). Months of `migrate:fresh` cycles compounded into ~4.7 GB of orphan files in `storage/app/public/` at the time of discovery (only ~10 MB of which was referenced by current `media` rows); manual `rm -rf + migrate:fresh` cleared it during the session 242 manual-test pass. Pair the artisan command with scheduling Spatie's built-in `media-library:clean` (the 242 close added the `withSchedule` block — natural slot) to handle the slow-drip orphan-conversion path that normal admin usage produces. Deferred from Batch 1 because building a real artisan command (with the production-refusal gate, confirmation prompt, and pairing with scheduled `media-library:clean`) is closer to a small-feature session than housekeeping. Out of scope, by design: a `Storage::fake('public')` audit of tests that may be writing real media to disk — that lands more naturally as a Test Audit slice item.
- **Heroicon picker in Quill editor.** Add a Quill custom format that lets users insert a heroicon inline in rich text (confirm icon set at session time — heroicons is the assumed set; verify against what the project actually ships). Small custom-format addition; not a feature blowup. Borderline housekeeping — if scope grows past "register one Quill format with a picker UI," lift to a sibling stub. Deferred from Batch 1 because feature additions tend to attract scope creep; revisit once the editor-bug fixes (list rendering + horizontal alignment) confirm the editor surface is well-understood.
- **Blog Post Pager — page context not resolving correctly** *(bug surfaced at session 245 UAT)*. The `blog_pager` widget renders on individual blog post detail pages and is supposed to expose previous/next post navigation by reading the current post's page context. During session 245 UAT (generating scrub blog posts and clicking through them), the pager renders but isn't receiving correct page context — needs investigation. Likely a contract-resolution / token-binding issue in the way `BlogPagerDefinition::dataContract` resolves against `RecordContextProjector`. Scope: identify the binding gap, fix or document, add a regression test that walks the pager surface for a post page. Out of scope for this stub: pager visual redesign or new pager features.

---

### UI / UX Sprint *(stub — pre-Beta 1)*

Batched UI/UX improvements that don't justify a dedicated session each but together earn one. Distinct from Housekeeping in that the items are explicitly authoring-ergonomics rather than visual polish or audits — they shape how it feels to use the admin tools, not how the surface looks.

- **Page builder full-screen toggle.** Add a toggle that maximizes the page-builder canvas + inspector to the full viewport, hiding the Filament admin chrome (sidebar, top bar, breadcrumbs) for the duration. Persists per-user via localStorage. Exit returns to the previous chrome state.
- **Adjustable height handle on the Quill editor.** The rich-text editor renders at a fixed height today; long content forces the wrapping page to scroll while the editor's internal area stays small. Add a drag-resize handle on the bottom edge so admins can grow the editor to whatever height fits the content they're authoring. Persisted height per-user via localStorage is fine — no schema change.

---

### Test Suite Audit — Cost, Coverage, and Shape *(stub — pre-Beta 1)*

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

Out of scope: parallel-test infrastructure (Pest parallel with `--parallel`) unless a pick specifically calls for it. Docker-exec overhead investigation (persistent shell vs fresh-exec-per-run) is fair game if it's an obvious win, otherwise defer.

---

### Column-Layout Mobile Collapse *(stub — pre-Beta 1)*

Column layouts don't collapse at narrow viewport widths today, which causes widget content to overflow its track (addressed partially in 207 with `min-width: 0` but the root cause — columns stay side-by-side at mobile widths regardless of layout — remains). Add a per-layout toggle for mobile-collapse behavior, defaulting to **on** but explicitly overridable to **off** for patterns that genuinely want to keep columns at every viewport (e.g. small side-by-side logo+nav header bars, icon-and-label rows).

Implementation notes captured while fresh:

- **Use container queries, not media queries.** The page-builder preview uses a simulated viewport width (`.widget-preview-scope` has `container-type: inline-size` as of 207's LayoutRegion work). A container query on `.page-layout` at some threshold will correctly collapse the layout in the preview at mobile preset, while a media query would only fire off the real browser viewport and miss the simulation. Same container the handle-chrome shrinking already targets.
- **Toggle placement.** The toggle belongs on the Column Settings tab of the layout inspector, near the full-width toggle. Probably a third checkbox: "Collapse columns on mobile". Default checked.
- **Schema.** Add `layout_config.collapse_mobile: bool` (default true). `layout_config`, not `appearance_config` — this is a layout-behavior concern, parallel to `full_width`.
- **Breakpoint.** Pick one value for now (768px is reasonable, matching the tablet/desktop boundary). Don't make it configurable per-layout in this session; if demand emerges, revisit.
- **Public-side parity.** The public site emits `<div class="page-layout" style="...">` inline. The inline style approach doesn't play nicely with container queries. Two options: (a) emit a `data-collapse-mobile` attribute and let a static `@container` rule in `_layout.scss` key off it; (b) switch to a stylesheet rule keyed off the class and a data attribute. Option (a) is cleaner — implementation detail for the session.

Out of scope: per-layout-configurable breakpoints, per-column (as opposed to per-layout) collapse control, reordering columns on collapse (columns stack in authoring order — whatever the user arranged is what they get on mobile).

Related but distinct, surfaced post-241: many widgets contain their *own* internal grid systems (`board_members`, `logo_garden`, `product_carousel` grid mode, etc.) that need mobile-collapse behavior independent of the page-layout-level collapse this stub covers. If the per-widget grid-collapse work is small enough to fold into this session, do so; otherwise lift it to a sibling stub at scoping time. The implementation pattern is likely identical (container queries against the widget wrapper), so a unified session is the natural shape.

---

### Full-Width Architecture Enforcement *(stub — pre-Beta 1, architectural)*

The universal Appearance layer's `full_width` setting (delivered in sessions 161–166) is honored by most widgets but not all — there are still rendering paths where a widget escapes the layout's `full_width` instruction or fails to honor it cleanly. The ask: bind widgets to the page-layout's full-width contract at the architecture level, so individual widget templates cannot accidentally bypass it. Implementation candidates: (a) wrap every widget in a layout-aware wrapper that asserts the full-width style at render, (b) add a contract-level guard in `AppearanceStyleComposer` that enforces full-width regardless of per-widget background/padding when the layout asserts `full_width: true`, (c) audit the widget templates for direct CSS that escapes the wrapper. The user notes "mostly works perfectly" — the goal is to make compliance structural rather than per-widget discipline. Out of scope: adding *new* full-bleed primitives; that lives in the post-Beta-1 "Hero Full-Bleed Promotion" stub.

---

### Widget Help Authoring & Help-System Integration *(stub — pre-Beta 1, design discussion)*

Widgets vary in self-evidence — most are obvious from their name and inspector (`text_block`, `image`, `hero`), but some (`bar_chart`, `event_calendar`, `donation_form`, `web_form`, `event_registration`) have configuration surfaces complex enough to warrant explanatory help. Today the help system (`help_articles` + admin help-doc routes) lives separately from the widget definition pipeline. The session needs to resolve **how widgets carry their help info and how it surfaces**:

- **Where does help live?** Per-widget help could live as a markdown file colocated with the widget definition (`app/Widgets/{Name}/help.md`), as a `help` key in the widget definition class, or as a row in `help_articles` keyed off widget handle. Each has tradeoffs (versioning, authorability, search integration).
- **How does it surface?** A "?" affordance in the inspector that opens the widget's help inline, a link out to the help system, a tooltip-on-hover for individual config fields, or some combination.
- **What's the rollup story?** Some widgets need only a one-paragraph "this widget does X" entry; others need multi-section configuration walkthroughs. Open question: do we author one help entry per widget, or do we have a "widget catalog" page in the help system that everyone shares with per-widget anchors?

Bring concrete examples to scope: bar_chart's many `chart_config` knobs, event_calendar's filter rules, donation_form's amount-tier configuration. The session is design + landing the first 3–5 widgets' help entries to validate the chosen pattern. Out of scope: building a content-pipeline or auto-discovery tool; one-off authoring is fine for the first batch.

---

### Stripe Checkout Branding *(stub — pre-Beta 1, standalone session)*

The product checkout, donation checkout, event checkout, and membership checkout flows all redirect to Stripe-hosted checkout pages today. These pages carry minimal CRM-side branding — Stripe controls the surface, with limited customization available via the Stripe Dashboard (logo, primary color, business name) and via API parameters at checkout-session creation time. The ask: figure out how much additional branding we can inject given Stripe's API constraints, then implement it consistently across all four checkout flows.

Touch points: `ProductCheckoutController`, `DonationCheckoutController`, `EventCheckoutController`, `MembershipCheckoutController`, plus the Stripe-account-level settings the operator configures. Possible levers: `custom_text` on session creation (header / submit / shipping-address / terms), `payment_method_options.card.statement_descriptor`, `customer_update` for stronger customer-facing identity, line-item description/image overrides. Standalone session because it requires Stripe-API exploration to know what is actually possible before committing scope. Out of scope: building a Stripe-account-onboarding wizard (post-Beta-1 in the Integration Setup Wizards stub).

---

### Importer Mapping Page UX *(stub — pre-Beta 1, UX session)*

The CSV importer's column-mapping page is the most stressful UI surface in the admin today — wide dropdowns, vertical sprawl across many rows, no visual cue for which mappings are completed vs. still needed. Concrete improvements:

- **Completed-row visual indicator** (checkmark, color stripe, opacity reduction) so the user can scan for unmapped rows at a glance.
- **Reduce vertical space per row.** Today each mapping row takes ~2 lines of vertical real estate; trim padding and label sizing.
- **Friendlier dropdowns.** Currently too wide; trim to fit typical destination-field-name length plus padding. Possibly switch to a search-as-you-type combobox if the dropdown count justifies it.
- **Optional grouping.** Group destination fields by entity (Contact, Address, CustomFields, …) so the dropdown is not a flat ~40-item list.

Touches `Filament/Pages/ImportContactsPage.php` and the other importer mapping pages (events, donations, memberships, invoice details, notes). Five mapping pages share enough structure that improvements should land as a shared pattern, not a per-page reskin. Out of scope: rebuilding the importer pipeline; the session is UI-only on the existing mapping shape.

---

### Random Data Generator as Dashboard Widget *(complete — closed at session 245)*

Closed at session 245. See `sessions/release-plan.md` § A1 (✅) and `sessions/245. Random Data Generator as Dashboard Widget — Log.md` for the full landing.

---

### Fleet Manager — Node Operations Parity *(stub — pre-Beta 1; A2 in `release-plan.md`)*

A2 in `sessions/release-plan.md`. Re-opens the Fleet Manager Agent track for a specific Beta-1-blocking capability subset.

The CRM-side agent contract is at v1.2.0 and substantially complete. The FM-side capability set that consumes the contract and drives node operations needs four capabilities operator-accessible from the FM admin UI:

- **Install** — provision a fresh CRM node from a clean droplet to a working install end-to-end.
- **Backup** — trigger and verify a backup against a node, surfacing failure modes the agent endpoint reports.
- **Restore** — restore a node from a backup blob (CRM-side has the manual procedure documented per sessions 242 + 243; FM-side needs the operator-facing equivalent).
- **Read logs** — fetch and surface application logs from a node without operator SSH.

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

#### B2. Onboarding rehearsal cluster *(Migration in/out + Custom fields + public collection + custom-field-with-lookup edge case)*
See `sessions/release-plan.md` § B2.

#### C3. Permission audit + Concurrent admin editing + Accidental public exposure *(folded)*
See `sessions/release-plan.md` § C3.

#### C4. Donation-to-acknowledgment loop
See `sessions/release-plan.md` § C4.

#### C5. Event with everything
See `sessions/release-plan.md` § C5.

#### C6. Membership renewal cycle
See `sessions/release-plan.md` § C6.

#### C7. Email at volume
See `sessions/release-plan.md` § C7.

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

---

## Public Styles & Form Controls — Beta 1 Scope

---

## End of Roadmap — Beta 1

### Onboarding / Install Dashboard Widget

First-run widget: detects unconfigured install, walks admin through minimum viable setup steps (mail, Stripe, branding). Disappears once confirmed. Could double as an ongoing health-check widget for production installs.

### Third-Party Licensing Compliance Audit

Before Beta 1 ships: audit all third-party dependencies for license compliance. Known items requiring verification:
- **Swiper.js** — MIT license (copyright Vladimir Kharlampidi). MIT requires the copyright notice and license text be included in distributions. Verify the Vite build or a LICENSES file satisfies this. Swiper Studio (no-code builder) is a separate paid product — confirm we are not using any Studio-only assets.
- Review all other npm and Composer dependencies for license compatibility with a commercial product.

---

## ── BETA ONE ─────────────────────────────────────────────────────────────────

**Beta One** is the first shippable, publicly demonstrable version of the product. Definition of done: a live hosted site is running on the product's own CMS, and a live install demo can be performed during a sales pitch — prospect names a company, picks a logo, imports contacts from a competitor, and receives a URL with their configured install at the end of the meeting. All sessions above this line are planned for Beta 1 delivery.

---

## Post-Beta 1

### Financial Data Origin & Lifecycle Discipline — Phases B and C *(deferred to post-release at session 244)*

Phase A complete (session 233): `source` column live across all four financial tables (`donations`, `memberships`, `event_registrations`, `transactions`); `HasSourcePolicy` applied; every write path source-aware. Remaining phases deferred to post-release per session 244 vetting:

- **Phase B — Admin gating generalization.** Lift the `! $record->stripe_id` action-visibility pattern from `TransactionResource` into a shared trait keyed off `source`. Apply to Donation and Membership Resources where action surfaces exist or are about to exist. Update Transaction's existing gates to read from `source` rather than `stripe_id` so the same predicate covers manual transactions correctly. No new actions land in this phase; this is the surface that future Refund/Cancel/Sync actions consume.
- **Phase C — QuickBooks sync origin-awareness, email-trigger origin-awareness on admin-driven bulk send actions** (donation receipts, renewal nudges, dunning). Lands when those features arrive.

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

### Page Copy with Guardrails

Add a "Copy Page" action with safety guardrails: confirmation dialog, auto-generated slug with `-copy` suffix, new page created in draft state, media references shared (not duplicated). Scope includes defining which page types are copyable and what gets carried over vs. reset.

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

## Exploratory & Fun *(post-Beta 1)*

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

---

## Future Projects *(post-management console)*

### Easter Egg & Fun Features
