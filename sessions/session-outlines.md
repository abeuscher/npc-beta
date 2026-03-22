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

---

## CRM Core Polish

### 053. Duplicate Contact Detection

Reusable detection service (exact email = hard duplicate, last_name + postal_code = probable duplicate). Two surfaces: import preview step flags each row before records are saved; contact list "Review Duplicates" action presents probable pairs for merge or dismiss. Merge reassigns all related records to the surviving contact and soft-deletes the discarded one. Dismissed pairs are persisted in a `contact_duplicate_dismissals` table so they don't resurface. Full prompt: `sessions/053. Duplicate Contact Detection.md`

---

## Data Hygiene & Privacy

### 054. Event Registrant Cleanup

A manual staff action on the Event edit page that removes contacts who were auto-created solely by registering for a specific event. A contact is eligible only if: their record originated via `source = 'web_form'`, they are linked to this event's registrations, and they have no other connections in the system (no other event registrations, no memberships, no donations). A confirmation modal shows the affected count before proceeding. Matching contacts are soft-deleted; all registration records for the event are removed regardless. Contacts who registered but also exist for other reasons are never touched. Manual trigger only — no scheduled automation in this session. Also fixed an unrelated bug: `PageResource::getEloquentQuery()` excluded event-type pages, causing the "Edit landing page" redirect to 404; fixed via `resolveRecordRouteBinding()` override.

---

## CMS & Admin Polish

### 055. Quill Fix, Page Layout & Event Date Simplification

Three workstreams: (1) Quill overflow fix — `overflow-hidden` + compensating `border-b` on the wrapper. (2) Page and Event edit forms restructured to `columns(3)` / `columnSpan(2)` left / `columnSpan(1)` right, matching ContactResource. (3) `event_dates` table dropped; `starts_at` (NOT NULL) and `ends_at` (nullable) added directly to `events`; date display merged into the `event_description` widget; all references to `EventDate` removed. `starts_at` is required at both form and DB levels. Events list updated: `starts_at` column, default sort ascending, past events hidden by default with an ellipsis-menu toggle. Full log: `sessions/055. Quill Fix, Page Layout & Event Date Simplification — Log.md`

---

## Member Portal & Self-Service

*Sessions in this group are strictly ordered — each depends on the previous.*

### 056. Secure Public Signup Flows

Custom auth guard against a `portal_accounts` table (not Fortify/Breeze). Members and volunteers are contacts with portal access — no separate member model. Signup creates or merges a `Contact` and creates a `portal_account` with `contact_id` FK. Email verification required before portal access is granted. Login/logout. Duplicate signup attempts are silently discarded — no signal to the submitter. Views in `resources/views/portal/`. **Portal security rule:** every portal route and query must be scoped strictly to the authenticated user's own `contact_id` — the portal must never expose PII belonging to anyone other than the logged-in member. Full prompt: `sessions/056. Secure Public Signup Flows.md`

### 057. Portal Chrome & Member Page Type

`layouts/portal.blade.php` with an authenticated header (member name, logout, visual differentiation from public site). `portal_prefix` SiteSetting (default `members`). Member page type added to page builder — slug prefix locked and driven by `portal_prefix`. Member pages require portal auth + verified. Page editor updated to show type prominently in heading. Full prompt: `sessions/057. Portal Chrome & Member Page Type.md`

### 058. Routing Consolidation, Page Type Locking & Portal Widgets

Move `blog_prefix` out of CMS Settings into a Routing section in General Settings alongside `events_prefix` and `portal_prefix`. Page type locked on edit for `event`, `post`, `member`. Slug prefix locked and driven by SiteSetting for all system-typed pages. Portal signup and login widgets added to the page builder. Full prompt: `sessions/058. Routing Consolidation, Page Type Locking & Portal Widgets.md`

### 059. Password Reset

Self-service password reset for portal accounts. Separate `portal_password_reset_tokens` table and `portal_accounts` password broker. Forgot-password and reset-password routes, controllers, and views. Response to forgot-password is identical whether or not the email exists. Reset email editable in the system email editor. Full prompt: `sessions/059. Password Reset.md`

### 060. Member Portal

*Prerequisite for Household & Family Grouping — the household join request flow lives on the member's account editor page.*

Build on the portal foundation from sessions 056–059. Add the `member_profiles` table (member-specific fields — membership tier, join date, renewal date, etc.) linked to contacts via `contact_id` FK. Build the member-facing portal dashboard: view and edit own contact record, view membership status, view event registrations. Admin surface: view/revoke portal access from the Contact edit page.

> **Form email collision for authenticated members:** A web form submission from an email tied to a portal account must offer the user a login prompt rather than silently updating the contact record. Design and implement this collision path in this session.

### Form Builder — Actions Pipeline

### Gated Pages

### Household & Family Grouping

**Depends on: Member Portal existing first.**

A lightweight household record — name, canonical mailing address, members (linked contacts). No nav slot; accessed only through the contact record (a Household panel on the contact edit/view page). No admin-managed assignments.

Self-service flow: a logged-in member can request to join an existing household by searching on a unique identifier (household name + address, or a short code). The request goes into a staff approval queue — no one self-assigns, they only request. On approval the contact is linked. On the contact record, staff can also manually link/unlink. Aggregate giving is computed from linked members, not stored. Used for physical mailing deduplication, compound salutations, and household-level event/ticket limits.

---

## Communication & Accountability

*Both sessions depend on Member Portal being complete, so that member self-service activity is captured from day one.*

### Communication Log

An auditable activity stream on contact records — and eventually other key models — tracking who did what and when. Staff edits, system actions (import, auto-created from event registration), and member self-service changes are logged as distinct event types. Designed for accountability in volunteer organisations with many infrequent users: if a record is changed incorrectly, the trail leads back to the actor. The notes panel is extended to serve as the unified activity stream; entries are typed (manual note, system event, import action, member action) and filterable. The notes panel is lazy-loaded to avoid unnecessary queries. All logging is transparent and accountability-facing — this is not behaviour tracking.

### Admin User Activity Log

Each admin user needs a record of their significant actions against data — which contact records they edited, which events they created or cancelled, which donations they entered or deleted. The log is not a diff system but an event journal: who did what, to which record, and when. Surface it on the user record in the Users list (a tab or linked sub-page showing that user's recent activity) and optionally as a filterable global log under Settings or Tools. Scope in the planning session: which action types to capture, where to store the log (a dedicated table vs. an existing auditing package like `owen-it/laravel-auditing`), and what the retention/purge policy should be.

---

## Finance

### Finance Settings

### Stripe Webhooks

*Before beginning: obtain the exact payload structure Stripe will POST to our endpoint from the Stripe documentation. Do not begin implementation until this has been provided.*

### Recurring Donations

### Pledge Tracking

*Multi-year pledge commitments with expected payment schedules and outstanding balance tracking. Pledge payments link to donation/transaction records. Distinct from recurring donations — a pledge is a promise, not a subscription.*

### Tax Receipts

### Grant Module — Planning

### Grant Module — Build

### QuickBooks Integration

*Before beginning: obtain the exact payload structure QuickBooks will POST to our endpoint from the QuickBooks documentation. Do not begin implementation until this has been provided.*

---

## Volunteer Management

*Contact Record — Birthday & Age Fields (CRM Core) is a prerequisite. Volunteer Portal depends on Member Portal being complete.*

**Age gating — legal note (agreed session 052):** The volunteer system will collect date of birth because it is operationally required (work, liability, safety). Rather than building parental consent flows, the public-facing volunteer registration form must gate sign-up to applicants who meet the minimum age threshold (≥ 13 at minimum under COPPA; consider ≥ 18 depending on jurisdiction and the nature of the work). Anyone below the threshold is rejected at the form level, keeping the system outside COPPA's scope for this pathway. Under-13 handling via verifiable parental consent is explicitly out of scope for v1 and is left to a future session or a fork of the project.

### Volunteer Profile & Hours Tracking

*Skills, availability, background check status and expiry, training/certifications held. Hours log tied to an event or a standalone activity. Total hours summary on the contact record.*

### Volunteer Scheduling

*Recurring shift slots with capacity. Admin assignment and self-signup. Connects to the Events module for event-day volunteer roles.*

### Volunteer Communication & Recognition

*Shift reminder emails, milestone triggers (100 hours, anniversary, etc.). Integrates with the email system.*

### Volunteer Portal

*Public-facing self-service: signup, view upcoming shifts, log hours pending admin approval. Extends or reuses the Member Portal patterns.*

---

## Data Retention & Deletion Policy

### Data Retention & Cascading Delete Audit

Audit every deletion path in the system and define what should happen when a record is removed. Key questions to answer and implement: What happens to notes, tags, and import records when the user who created them is deleted? What happens to contacts linked to a deleted import session — do they persist or cascade? What happens to event registrations when a contact is deleted? What happens to mailing list memberships? What is the intended lifetime of soft-deleted records — is there a purge policy? The output of this session is both code (correct `onDelete` behaviours, cascade or null-out rules in migrations) and a written policy document checked into the repo.

---

## CMS & Page Builder

### SEO & Head Tag Management

Add SEO fields to pages, blog posts, and events: Meta Title, Meta Description, OG / page thumbnail image, Twitter card type, and JSON-LD structured data blocks (Article, Event, Organization). Add a global head injection field in Settings and a per-page head injection field. Add a global footer injection field. Define a sanitization strategy. Gate advanced injection behind a role or setting so it can be hidden for orgs that don't need it. Write help copy covering both basic and power-user workflows.

### Site Theme Enhancement

Extends the existing Site Theme admin page. Colours are reorganised into two rows — light palette and dark palette — each with independent pickers. A "Mirror light → dark" button on the light row and a "Mirror dark → light" button on the dark row apply an HSL-based inversion as a starting point, clearly labelled as a suggestion. A live WCAG AA contrast checker runs against all foreground/background colour pairs; failing combinations display a warning with the nearest passing colour as a suggestion. Preset palettes with preview swatches are available as a starting point for both rows.

### CMS Style System, Column Widget & Front-End Build — Planning

Design three interconnected systems before building any of them.

**(1) Page widget style surface schema.** Each page widget type declares a `style_schema` using CSS property names as keys with a control type and constraints as values — e.g. `display: {type: select, options: [grid, flex, block]}`, `font-size: {type: range, min: 12, max: 72, unit: px}`. This is how a page widget restricts what the user can change. All page widgets additionally accept an arbitrary CSS field, scoped at render time by wrapping it in a `[data-widget="{uuid}"]` selector generated per instance — no build step needed for scoping. Agree on the schema format before building.

**(2) Column widget.** A page widget that holds named slots, each with a declared width. Child page widgets are assigned to slots. Exposes `display` restricted to `grid`, `flex`, and `block` as a concrete example of the style schema above. No separate column layout system — columns are just page widgets rendered by the same engine. Nesting a column widget inside a slot covers the colspan case without a special model.

**(3) Front-end build system.** Vite with `laravel/vite-plugin`. SCSS via the `sass` package (standard Vite integration). PostCSS with autoprefixer and cssnano for vendor prefixes and minification. Public side targets a single bundled CSS file and a single bundled JS file — minimise HTTP requests. Inline `<style>` blocks (style-guide custom properties output, page widget arbitrary CSS) are minified at render time by a lightweight PHP helper or Blade directive, not a build step, so they stay inline and don't add a file call. Decide in this session what is compiled vs. what is generated dynamically at request time.

### Page Builder — Widget Styling & Basic Interactivity

### Page Builder — Live Preview

### Page Templates & Layout Controls

### SVG & Image Optimization

Add SVG support: inline SVG in page builder / rich text areas, and SVG as `<img src>` in image page widgets and media fields. Add image optimization on upload: automatic compression and resize with optional manual quality controls.

### Image & Media Handling — Carousels & Galleries

### Media Library UI

### Public Theme Builder / Custom CSS Tool

Split-pane live CSS editor: CSS/SCSS on the left (compiled server-side via the same sass pipeline), live page preview on the right. Gated by user role. Sits in Settings or as a dedicated nav item. Output is minified and written into the inline style block, not a separate file call.

---

## Infrastructure & Ops

### Help System Enhancements

Add a link to the full help system in the left navigation. Build a help index page with a table of contents and a search bar. Audit every primary navigation item and ensure each has a linked help article. Write process articles: Google Analytics / GTM setup, Google site verification, custom CSS, custom collections, custom page widgets, Google Fonts.

### Accessibility — ARIA, ADA & Colour Contrast

Audit and harden the public-facing frontend for accessibility. Add ARIA landmark roles and labels to the public layout (`<header>`, `<main>`, `<footer>`, `<nav aria-label>`). Ensure all interactive elements (nav dropdowns, form controls, buttons) have correct ARIA states (`aria-expanded`, `aria-current`, `aria-label`). Keyboard navigation audit: tab order, focus styles, skip-to-main link. Colour contrast: integrate an automated contrast checker (e.g. axe-core or similar) into the build or as a standalone audit step; flag any Pico defaults or theme colour combinations that fall below WCAG AA (4.5:1 for text, 3:1 for UI components). Colorblind simulation audit: identify palette choices that fail common colorblindness simulations (deuteranopia, protanopia). Output: a short written report of issues found plus fixes applied, and a WCAG AA compliance statement that can be shared with nonprofit clients for ADA documentation purposes.

### Privacy & Legal Footer Example

*No analytics, no cookie consent system — those are out of scope by design. This session produces a well-structured example custom footer component with sensible placeholder slots for privacy policy, terms, and any legal copy the organization needs. Ships as a reference implementation customers can drop in and edit.*

### Multi-Vendor Mail Support

*Add additional sending providers to the Mail Settings page: SMTP, AWS SES, Postmark, Mailgun. Each provider adds its own credential fields (visible when that driver is selected) and a config branch in `AppServiceProvider`. No architectural changes required — the switchable driver pattern from session 034 is already in place.*

### Public API Endpoints

### CDN Integration

---

## Exploratory & Fun

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

### Easter Egg & Fun Features

---

## End of Roadmap

### Installer

### Demo
