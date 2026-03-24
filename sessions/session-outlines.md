# Nonprofit CRM — Session Outlines

This is the single working reference for all sessions. Completed sessions are listed by title below. Future sessions are organised by group — reorder and rewrite them freely as the project evolves.

---

## Completed Sessions

| # | Title |
|---|-------|
| 001 | Initial Setup |
| 002 | Admin Route Conflict, Contact Model |
| 003 | First Public Page |
| 004 | Admin Information Architecture |
| 005 | Site Settings, Public Frontend, and Blog |
| 006 | Collections |
| 007 | Widget System |
| 008 | Widget Type System + CMS Tags |
| 009 | Page Builder |
| 010 | Widget Config Schema + Page Builder UI Rework |
| 011 | CRM Taxonomy & Contact Model Clarity |
| 012 | User Roles & Permissions |
| 013 | Role Management UI |
| 014 | Events: Foundation |
| 015 | Events: Registration Model, Landing Pages, and Event Widgets |
| 016 | Page Types, Event Routing & Base Pages Seeder |
| 017 | Event Notifications & Registrant Export |
| 018 | Event Model Enhancements & Contact Auto-Creation |
| 019 | Import/Export: Core |
| 020 | Bug Fixes & Planning |
| 021 | Trix Toolbar Bug in Page Builder |
| 022 | Replace Trix with Quill in the Page Builder |
| 023 | Custom Contact Fields |
| 024 | Help System |
| 025 | Migrate Blog Posts into the Pages Table |
| 026 | Deployment — Get Live on a Public Server |
| 030 | List & Table UI Overhaul — Bulk Actions Everywhere |
| 031 | Site Chrome — Named Menus, Header & Footer |
| 032 | Navigation Model and UI Restructure |
| 033 | Admin Branding & Dashboard |
| 034 | Transactional Email — Resend Integration |
| 035 | System Email Templates |
| 036 | Mailing List Manager |
| 037 | MailChimp Integration |
| 038 | MailChimp Webhook Debugging |
| 039 | Admin Dashboard & Branding Polish |
| 040 | Tags — Unified Tag System |
| 041 | Importer Phase 2 — Accountability, Source Mapping & Filter UI |
| 042 | Codebase Audit — Fields, Schema, Permissions & Help Coverage |
| 043 | Importer — Phase 3 |
| 044 | Importer — Phase 4: Staged Updates & Queue Control |
| 045 | Public Frontend Foundation |
| 046 | Site Theme Admin |
| 047 | Web Forms — Foundation |
| 048 | Web Forms — Security Review |
| 049 | Codebase Audit — Fields, Schema, Permissions & Help Coverage |
| 050 | Roadmap Planning & Prioritisation |
| 051 | Minor Tweaks & Polish |
| 052 | CRM Polish — Roles, Contacts & Users |
| 053 | Duplicate Contact Detection |
| 054 | Event Registrant Cleanup |
| 055 | Quill Fix, Page Layout & Event Date Simplification |
| 056 | Secure Public Signup Flows |
| 057 | Portal Chrome & Member Page Type |
| 058 | Routing Consolidation, Page Type Locking & Portal Widgets |
| 059 | Password Reset |
| 060 | Member Portal |
| 061 | System Page Type — Infrastructure and Migration |
| 062 | Codebase Audit & Migration Squash |
| 063 | Admin UI Polish, CMS Navigation Sort & Settings Consolidation |
| 064 | Sample Data Generator Library |
| 065 | Member Event Registration |
| 066 | Promote Contact to Member |
| 067 | Contact Actions & Notes Sub-Page |
| 068 | Roadmap Planning & Help Content |

---

## Next Up — Session 069

### Minor Tweaks, Fixes & Git Hygiene

A mixed bag of small and medium items. Items marked **[small]** should fit comfortably in a single session. Items marked **[discuss]** need a scoping conversation before implementation begins.

**CRM / admin views:**
- **[small]** Remove `EventsSettingsPage` entirely — it is hidden from navigation and its only field (`event_auto_publish`) already lives in CMS Settings. Delete the class and the stub help doc.
- **[small]** `MemberResource` — add a read-only resource providing a focused lens on contacts with active memberships, scoped via `isMember()`. Same pattern as PostResource scoping Page to `type = 'post'`. Edit action redirects to ContactResource. Add `view_any_member` permission, granted to super_admin and any role holding `view_any_contact`.
- **[small]** `HouseholdResource` — review nav visibility. Remove from any top-level nav group it currently occupies if it is not yet useful there; revisit placement when the self-service invite flow is built.
- Additional minor cosmetic and organisational fixes (user will supply a list).

**Git workflow:**
- **[small]** Add a git workflow section to `CLAUDE.md` (or project settings): every session, Claude opens a new branch before beginning any work. Single commit per branch. Patch branches follow the pattern `session-###-patch-###`. Claude never pushes or merges; the user pushes and merges.
- **[small]** Add a pre-deploy test step — a CI or pre-push hook that runs the test suite before changes can reach main. Does not need to be exhaustive but should catch obvious breakage. Write and enable now as baseline hygiene.

**[discuss] Admin user invitation flow:**
User sends an invitation by specifying email address, user type (role), and name. The system generates a temporary password, creates the user record, and sends an invitation email with credentials. The temporary password expires on first login and the user is forced to set a new one before proceeding. Standard first-login flow. *Scope: is this a full session on its own, or small enough to fold in?*

**[discuss] Frontend mobile menu:**
The public frontend currently has no mobile menu. This is a priority gap. *Scope: depends on complexity of the existing nav structure. May pull in broader frontend toolset improvements.*

**[discuss] Widget system for header and footer:**
Header and footer areas rendered via purpose-built widget types using the same engine as page widgets. Each area becomes a configurable slot rather than a hard-coded Blade partial. Widgets would be self-contained and autonomous. *This is a meaningful architectural change — plan before building.*

---

## Member Portal & Self-Service

*Sessions in this group are strictly ordered — each depends on the previous.*

### Household & Family Grouping

**Status: Partial** — The admin-facing HouseholdResource is built (list, create, edit, member relation manager, help doc). Nav placement and the self-service invite flow described below remain.

A lightweight household record — name, canonical mailing address, members (linked contacts). Accessed through the contact record. The self-service component: members can invite others to join their household via an email invite flow. Staff approve the linkage. An invite to an unknown email produces a neutral success response (no signal to the sender) and logs a note to the inviting member's contact record.

**Architectural decisions (agreed session 060):**
- Lateral relationship: contacts link to a household, not to each other. No self-referential FK.
- Household record auto-created when an invite is accepted (not pre-created by staff).
- Inviter address is always the canonical household address — never overwritten by the invitee. Security requirement: the invite flow must not reveal one member's address to another.
- If a member's contact address diverges from the household canonical address, show a warning UI — do not silently overwrite either.
- Staff can manually link/unlink on the contact record. Members self-serve via invite with staff approval.

Aggregate giving computed from linked members, not stored. Used for physical mailing deduplication, compound salutations, and household-level event/ticket limits.

### Gated Pages

---

## Communication & Accountability

*Both sessions depend on Member Portal being complete.*

### Communication Log

An auditable activity stream on contact records — tracking who did what and when. Staff edits, system actions (import, auto-created from event registration), and member self-service changes are logged as distinct event types. The notes panel is extended to serve as the unified activity stream; entries are typed (manual note, system event, import action, member action) and filterable. All logging is transparent and accountability-facing — this is not behaviour tracking.

### Admin User Activity Log

An event journal per admin user: who did what, to which record, and when. Surfaced on the user record and optionally as a global filterable log under Settings or Tools. Scope in the planning session: action types to capture, storage (dedicated table vs. `owen-it/laravel-auditing`), and retention/purge policy.

---

## Finance

*Scope boundaries: no grants, no wages, no payroll, no disbursements. No card data stored — Stripe's vault handles all sensitive payment data.*

*Finance Settings will be defined alongside the sessions that need them, not comprehensively ahead of time. API keys already stored in General Settings stay there until a clear grouping emerges.*

### Stripe Webhooks

Receive and record all Stripe payment events. Every payment (donation, membership fee, ticket sale) is captured as a transaction with type, amount, contact, date, Stripe payment intent ID, and QuickBooks sync status. Foundation for all other finance sessions.

*Before beginning: confirm the exact webhook event types and payload structure from the Stripe documentation.*

### Products & Checkout

Lightweight product catalogue backed by Stripe Checkout. A `products` table: name, description, price, capacity, type. Purchase initiates a Stripe Checkout session; buyer redirected to Stripe, pays, redirected back. Webhook records the transaction and decrements inventory. Covers event tickets, membership fees, and any "pay for a thing" scenario. No card data passes through the application.

### Recurring Donations

Stripe Billing subscriptions. Subscription created once; Stripe fires webhooks on each charge cycle. Responsibilities: record transactions, handle failures (notify donor, mark past-due).

### Pledge Tracking

A pledge is a promise. Store the commitment (total amount, schedule, campaign) and track payments against the outstanding balance. Members pay manually or via a Stripe subscription for instalments. Supports board reporting and cash flow forecasting.

### Tax Receipts

Generate and email annual donation summaries. Triggered manually or automatically at fiscal year end. Receipt template configurable via the email template system.

### QuickBooks Sync

One-way push of categorised transaction records to QuickBooks. No reconciliation, no pulling from QB, no chart-of-accounts management.

*Before beginning: obtain the exact QuickBooks API payload structure and OAuth flow from the QuickBooks documentation.*

---

## Volunteer Management

*DOB / age fields on contacts are a prerequisite. Volunteer Portal depends on Member Portal being complete.*

*Age gating (agreed session 052): the public volunteer registration form gates sign-up to applicants meeting the minimum age threshold. Under-13 parental consent is out of scope for v1.*

### Volunteer Profile & Hours Tracking

*Skills, availability, background check status and expiry, training/certifications, hours log, total hours on contact record.*

### Volunteer Scheduling

*Recurring shift slots with capacity. Admin assignment and self-signup. Connects to Events for event-day volunteer roles.*

### Volunteer Communication & Recognition

*Shift reminders, milestone triggers (100 hours, anniversary). Integrates with the email system.*

### Volunteer Portal

*Public self-service: signup, view shifts, log hours pending admin approval. Extends Member Portal patterns.*

---

## Data Retention & Deletion Policy

### Data Retention & Cascading Delete Audit

Audit every deletion path. Define and implement what happens when: a user is deleted (notes, tags, import records); a contact is deleted (registrations, memberships, mailing list memberships); an import session is deleted. Define a soft-delete purge policy. Output: code changes (migrations, `onDelete` rules) and a written policy document in the repo.

---

## CMS & Page Builder

### SEO & Head Tag Management

Meta Title, Meta Description, OG image, Twitter card type, JSON-LD structured data (Article, Event, Organization). Global and per-page head injection fields. Global footer injection. Sanitization strategy. Gate advanced injection by role.

### Site Theme & Public Theme Builder

*Merges "Site Theme Enhancement" and "Public Theme Builder / Custom CSS Tool" — both extend the same page.*

Extends the existing `SiteThemePage` which already has appearance settings and a live SCSS editor. Adds: colours reorganised into light/dark palette rows, "Mirror" buttons applying HSL-based inversion as a starting point, live WCAG AA contrast checker with nearest-passing-colour suggestions, preset palettes with swatches. Split-pane SCSS editor with live page preview on the right side.

### CMS Style System, Column Widget & Front-End Build — Planning

Plan three interconnected systems before building:

1. **Widget style surface schema** — each widget type declares a `style_schema` (CSS property → control type + constraints). All widgets also accept arbitrary CSS scoped to `[data-widget="{uuid}"]` at render time, no build step needed.
2. **Column widget** — holds named slots with declared widths. Child widgets assigned to slots. `display` restricted to `grid`, `flex`, `block`. Nesting handles colspan.
3. **Front-end build** — Vite with `laravel/vite-plugin`, SCSS via `sass`, PostCSS + autoprefixer + cssnano. Single bundled CSS and JS files. Inline `<style>` blocks minified at render time by a PHP helper.

### Widget Portability & Distribution

Each widget becomes a self-describing class: handle, config schema, render logic, JS/CSS asset manifest, optional collection type definitions. Manifests roll up into the Vite build. Foundation for future widget distribution — no marketplace UI in scope here.

### Page Builder — Widget Styling & Basic Interactivity

### Page Builder — Live Preview

### Page Templates & Layout Controls

### SVG & Image Optimization

SVG support: inline in page builder / rich text, as `<img src>` in image widgets. Image optimization on upload: automatic compression and resize with optional quality controls.

### Image & Media Handling — Carousels & Galleries

### Media Library UI

---

## Infrastructure & Ops

### Help System Enhancements

Help index page with table of contents and search bar. Link in the left navigation. Process articles: Google Analytics / GTM setup, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

### Accessibility — ARIA, ADA & Colour Contrast

ARIA landmark roles, correct states on interactive elements, keyboard navigation audit, focus styles, skip-to-main. Automated contrast check (axe-core or similar). Colorblind simulation audit. Output: fixes applied + WCAG AA compliance statement for client ADA documentation.

### Privacy & Legal Footer Example

*Example custom footer component with placeholder slots for privacy policy and terms. Reference implementation for customers to drop in and edit. No analytics, no cookie consent — out of scope by design.*

### Multi-Vendor Mail Support

*Additional sending providers: SMTP, AWS SES, Postmark, Mailgun. Each adds credential fields and a config branch. Switchable driver pattern already in place.*

### Public API Endpoints

### CDN Integration

---

## Exploratory & Fun

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

### Easter Egg & Fun Features

---

## End of Roadmap

### Onboarding / Install Dashboard Widget

*First-run widget that detects an unconfigured install and walks the admin through minimum viable setup: base URL, site name, logo. Disappears once confirmed. Could double as a health-check widget.*

### Installer

### Demo
