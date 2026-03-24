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

## Session 069 — Minor Tweaks, Fixes & Git Hygiene

*User will supply a list of additional minor cosmetic and organisational fixes at the start of the session.*

**Admin views:**
- Remove `EventsSettingsPage` entirely — it is hidden from navigation and its only field (`event_auto_publish`) already lives in CMS Settings. Delete the class, deregister the route, remove the help doc.
- Add a read-only `MemberResource` providing a focused lens on contacts with active memberships, scoped via `isMember()` — same pattern as PostResource scoping Page to `type = 'post'`. Edit action redirects to ContactResource; no duplicate form. Add `view_any_member` permission; grant it to super_admin and any role already holding `view_any_contact`.
- Review `HouseholdResource` nav placement. Confirm it is visible and sensibly grouped; adjust if needed. Full nav placement finalised when the self-service invite flow is built.

**Public frontend:**
- Add a mobile hamburger menu to the public site. Simple CSS-based collapse — no JS framework, no animation library. Hamburger icon toggles the nav open/closed. Target: functional on small screens, consistent with existing styles.

**Git hygiene:**
- Add a git workflow section to `CLAUDE.md` (create the file at the project root if it does not exist): every session Claude opens a new branch before touching any code; single commit per branch at close; patch branches follow the pattern `session-###-patch-###`; Claude never pushes or merges — the user handles both.
- Add a CI test step — a GitHub Actions workflow (or pre-push hook if CI is not yet configured) that runs `php artisan test` before changes can reach main. Does not need to be exhaustive; the goal is baseline hygiene.

---

## Session 070 — Admin User Invitations

Staff invite new admin users by supplying name, email, and role. The system creates the user record, generates a secure temporary password, and sends an invitation email. The temporary password expires on first login: the user is forced to set a new password before accessing the admin panel. Standard first-login forced-reset flow.

Key decisions to resolve at session start:
- Does the invitation land the user directly in the admin panel after they set a password, or do they click a link in email → set password → then log in?
- Should invitation tokens have a configurable TTL (e.g. 48 hours), or just expire on first use?
- Is there a UI for resending or revoking a pending invitation?

---

## Member Portal & Self-Service

*Sessions in this group are strictly ordered — each depends on the previous.*

### Household & Family Grouping

**Status: Partial** — Admin-facing HouseholdResource is built (list, create, edit, member relation manager, help doc). Nav placement is reviewed in session 069. The self-service invite flow described below remains.

A lightweight household record — name, canonical mailing address, members (linked contacts). Accessed through the contact record. Members can invite others via an email invite flow; staff approve the linkage. An invite to an unknown email produces a neutral success response (no signal to the sender) and logs a note to the inviting member's contact record.

**Architectural decisions (agreed session 060):**
- Lateral relationship: contacts link to a household, not to each other. No self-referential FK.
- Household auto-created when an invite is accepted (not pre-created by staff).
- Inviter address is always the canonical household address — never overwritten by the invitee. Security requirement.
- If a member's contact address diverges from the household canonical address, show a warning UI — do not silently overwrite either.
- Staff can manually link/unlink on the contact record. Members self-serve via invite with staff approval.

Aggregate giving computed from linked members, not stored. Used for physical mailing deduplication, compound salutations, and household-level event/ticket limits.

### Gated Pages

---

## Communication & Accountability

*Both sessions depend on Member Portal being complete.*

### Communication Log

An auditable activity stream on contact records — tracking who did what and when. Staff edits, system actions (import, auto-created from event registration), and member self-service changes are logged as distinct event types. The notes panel is extended to serve as the unified activity stream; entries are typed (manual note, system event, import action, member action) and filterable. All logging is transparent and accountability-facing — not behaviour tracking.

### Admin User Activity Log

Event journal per admin user: who did what, to which record, and when. Surfaced on the user record and optionally as a global filterable log. Scope in the planning session: action types, storage (dedicated table vs. `owen-it/laravel-auditing`), retention/purge policy.

---

## Finance

*Scope boundaries: no grants, wages, payroll, or disbursements. No card data stored — Stripe handles all sensitive payment data.*

*Finance settings will be defined alongside the sessions that need them. API keys already in General Settings stay there until a clear grouping emerges from the actual integration work.*

### Stripe Webhooks

Receive and record all Stripe payment events. Every payment captured as a transaction: type, amount, contact, date, Stripe payment intent ID, QuickBooks sync status. Foundation for all other finance sessions.

*Before beginning: confirm the exact webhook event types and payload structure from the Stripe documentation.*

### Products & Checkout

Lightweight product catalogue backed by Stripe Checkout. `products` table: name, description, price, capacity, type. Purchase initiates a Stripe Checkout session — buyer pays on Stripe's hosted page, redirected back, webhook records the transaction. Covers event tickets, membership fees, and any "pay for a thing" scenario. No card data passes through the application.

### Recurring Donations

Stripe Billing subscriptions. Subscription created once; Stripe fires webhooks each charge cycle. Responsibilities: record transactions, handle failures (notify donor, mark past-due).

### Pledge Tracking

A pledge is a promise. Store the commitment (total, schedule, campaign) and track payments against the outstanding balance. Supports board reporting and cash flow forecasting.

### Tax Receipts

Generate and email annual donation summaries. Triggered manually or automatically at fiscal year end. Receipt template configurable via the email template system.

### QuickBooks Sync

One-way push of categorised transactions to QuickBooks. No reconciliation, no pulling from QB.

*Before beginning: obtain the exact QuickBooks API payload structure and OAuth flow from the QuickBooks documentation.*

---

## Volunteer Management

*DOB / age fields on contacts are a prerequisite. Volunteer Portal depends on Member Portal being complete.*

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

## Data Retention & Deletion Policy

### Data Retention & Cascading Delete Audit

Audit every deletion path. Define and implement what happens when: a user is deleted (notes, tags, import records); a contact is deleted (registrations, memberships, mailing list memberships); an import session is deleted. Define a soft-delete purge policy. Output: code changes (migrations, `onDelete` rules) and a written policy document in the repo.

---

## CMS & Page Builder

### SEO & Head Tag Management

Meta Title, Meta Description, OG image, Twitter card type, JSON-LD structured data (Article, Event, Organization). Global and per-page head injection fields. Global footer injection. Sanitization strategy. Gate advanced injection by role.

### Site Theme & Public Theme Builder

*Merges "Site Theme Enhancement" and "Public Theme Builder / Custom CSS Tool" — both extend SiteThemePage.*

Extends the existing `SiteThemePage` which already has appearance settings and a live SCSS editor. Adds: colours reorganised into light/dark palette rows, "Mirror" buttons applying HSL-based inversion, live WCAG AA contrast checker with nearest-passing-colour suggestions, preset palettes with swatches. Split-pane SCSS editor with live page preview on the right.

### CMS Style System, Column Widget & Front-End Build — Planning

Plan three interconnected systems before building:

1. **Widget style surface schema** — each widget type declares a `style_schema` (CSS property → control type + constraints). All widgets accept arbitrary CSS scoped to `[data-widget="{uuid}"]` at render time.
2. **Column widget** — named slots with declared widths, child widgets assigned to slots, `display` restricted to grid/flex/block.
3. **Front-end build** — Vite, SCSS, PostCSS + autoprefixer + cssnano. Single bundled CSS and JS. Inline `<style>` blocks minified at render time by a PHP helper.

### Widget Portability & Distribution

Each widget becomes a self-describing class: handle, config schema, render logic, JS/CSS asset manifest, optional collection type definitions. Foundation for future widget distribution.

### Header & Footer Widget System

Header and footer areas rendered via purpose-built widget types using the same engine as page widgets. Each area becomes a configurable slot rather than a hard-coded Blade partial. Widgets are self-contained — adding a new header or footer layout requires no changes to the application itself. Header widgets and footer widgets are separate registries (a header widget is not available in the footer slot and vice versa). Planning questions to resolve: how are active header/footer widgets selected (one per area, or stacked/sequential)? How does this interact with the existing NavigationMenu model? Does the page builder editor extend to these areas, or is there a separate admin surface?

### Page Builder — Widget Styling & Basic Interactivity

### Page Builder — Live Preview

### Page Templates & Layout Controls

### SVG & Image Optimization

SVG support: inline in page builder / rich text, as `<img src>` in image widgets. Image optimization on upload: compression and resize with optional quality controls.

### Image & Media Handling — Carousels & Galleries

### Media Library UI

---

## Infrastructure & Ops

### Help System Enhancements

Help index page with table of contents and search bar. Link in the left navigation. Process articles: Google Analytics / GTM, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

### Accessibility — ARIA, ADA & Colour Contrast

ARIA landmark roles, correct states on interactive elements, keyboard navigation, focus styles, skip-to-main. Automated contrast check (axe-core or similar). Colorblind simulation audit. Output: fixes + WCAG AA compliance statement for client ADA documentation.

### Privacy & Legal Footer Example

*Example custom footer component with placeholder slots for privacy policy and terms. Reference implementation for customers.*

### Multi-Vendor Mail Support

*Additional sending providers: SMTP, AWS SES, Postmark, Mailgun. Switchable driver pattern already in place.*

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

*First-run widget: detects unconfigured install, walks admin through minimum viable setup. Disappears once confirmed. Could double as a health-check widget.*

### Installer

### Demo
