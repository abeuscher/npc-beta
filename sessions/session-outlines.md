# Nonprofit CRM — Session Outlines

This is the single working reference for all sessions. Completed sessions are listed by title below. Future sessions are organised by group — reorder and rewrite them freely as the project evolves.

A **Beta One** milestone is planned as the first shippable, demonstrable version of the product: a live hosted site, anonymous read-only demo access, and a live install demo performable for prospects in real time. All sessions before the milestone marker are planned for Beta 1 delivery. Sessions after the marker are deferred until post-Beta 1.

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
| 069 | Minor Tweaks, Fixes & Git Hygiene |
| 070 | Admin User Invitations |
| 071 | Household Linking |
| 072 | Activity Log |
| 073 | Stripe Foundation |
| 074 | Products & Checkout |
| 075 | Donations — Foundation |
| 076 | Tax Receipts |
| 077 | Mailing List from Donors |
| 078 | Finance Data Boundary |
| 079 | Debug Generator — Donations, Products & Purchases |
| 080 | System Email Preview Wizard |
| 081 | Minor Tweaks & Polish |
| 082 | Codebase Audit & Migration Squash |
| 083 | Bug Fixes & Polish |
| 084 | Widget Render Context |
| 085 | Widget Migration |
| 086 | Column Widget & Widget Picker UX |
| 087 | Inspector Panel & Shared Page Builder Form |
| 088 | Image Optimization & SVG Support |
| 089 | Image & Carousel Widgets |
| 090 | Tailwind Migration — Layouts & Infrastructure |
| 091 | Tailwind Migration — Widget Templates |
| 092 | Media Library Manager |
| 093 | WYSIWYG Toolbar & Inline Image Insert |
| 094 | Test Audit & Bug Fixes |
| 095 | Test Coverage — Portal, Stripe & Integrations |

---

## CMS & Page Builder — Beta 1 Scope

*The following sessions cover the subset of CMS/page builder work targeted for Beta 1. The goal is a page builder compelling enough to demo — column layouts, polished widget picker, header/footer control, a useful set of widget types, basic page templates, and image/SVG support. Full style system, live preview, widget portability, and theming are deferred to post-Beta 1.*

### ~~Session 087 — Inspector Panel & Shared Page Builder Form~~ *(completed)*

### ~~Session 088 — Image Optimization & SVG Support~~ *(completed)*

### ~~Session 089 — Image & Carousel Widgets~~ *(completed)*

### ~~Session 090 — Tailwind Migration — Layouts & Infrastructure~~ *(completed)*

### ~~Session 091 — Tailwind Migration — Widget Templates~~ *(completed)*

### ~~Session 092 — Media Library Manager~~ *(completed)*

### ~~Session 093 — WYSIWYG Toolbar & Inline Image Insert~~ *(completed)*

### ~~Session 094 — Test Audit & Bug Fixes~~ *(completed)*

### ~~Session 095 — Test Coverage — Portal, Stripe & Integrations~~ *(completed)*

### Session 096 — Per-Page SEO & Header Snippets

Wire up existing meta_title/meta_description columns, add OG image and noindex fields, auto-generate JSON-LD structured data (BlogPosting/WebPage), canonical URLs, and sitemap.xml. Per-page and site-wide header/footer code snippet injection with HTML validation. New `edit_page_snippets` permission for per-page snippets. Site-wide snippets (head, body-open, body-close) and default OG image in CMS Settings.

### Header & Footer Widget System

Header and footer areas rendered via purpose-built widget types using the same engine as page widgets. Each area becomes a configurable slot rather than a hard-coded Blade partial. Navigation is handled by exposing NavigationMenu collections through the existing nav system, then building a nav widget that can be added to the header or footer slot — no separate nav infrastructure required. Header widgets and footer widgets are separate registries.

### Additional Widget Types

New widget types for Beta 1: calendar, graph/chart, video embed, navigation menu widget. Each built to the same config schema pattern as existing widget types. Static image and carousel widgets already built in session 089.

**Table and Flexbox widgets** — a table layout widget and a flexbox layout widget would complement the existing column (CSS grid) widget and give users more layout options. These should be straightforward to build following the same `parent_widget_id` / `column_index` nesting model as the column widget. Consider whether the column widget needs usage limits (max nesting depth, max columns) to keep it manageable once alternatives exist.

### Page Templates

Basic page template scaffolding for the demo — not final form. A curated library of named starter layouts (e.g. "Landing Page", "About", "Contact") that a new site can apply with one click. Goal: a prospect can see a polished page without building it from scratch. Full template marketplace and advanced template controls are out of scope for Beta 1.

---

## Member Portal & Self-Service

*Sessions in this group are strictly ordered — each depends on the previous.*

### Household — Remaining Features

Core household model built in session 071 (self-referential `contacts.household_id` FK, admin assignment, portal display, address sync). Remaining for future sessions if needed:

- Member-to-member portal invite flow.
- Household dissolution / head transfer when the head contact leaves.
- Household-level aggregate giving and mailing deduplication (Finance sessions).

---

## Finance

*Scope boundaries: no grants, wages, payroll, or disbursements. No card data stored — Stripe handles all sensitive payment data. The application is deposit-only — it never initiates a refund, payout, or balance transfer. All financial reversals are handled in the Stripe dashboard.*

*A dedicated Financial Settings page (gated to developer/treasurer roles) holds Stripe and QuickBooks API keys. Keys are encrypted at rest.*

### Stripe Payment Method Manager

Allow admin to configure which Stripe payment methods are accepted (e.g. credit cards, ACH). Default to disabling Link and Klarna. At least one card-based method must remain enabled at all times — enforce this in the UI and surface the constraint on the Financial Settings help page. Requires Stripe PaymentMethod Configuration API or Products/Prices settings depending on the checkout flow in use.

### QuickBooks Sync

One-way push of categorised transactions to QuickBooks. Scope for Beta 1: must work correctly and reliably — doesn't need to be fancy. Core sync path and error surfacing only; advanced mapping and reconciliation views can follow post-Beta.

*Before beginning: obtain the exact QuickBooks API payload structure and OAuth flow from the QuickBooks documentation. Also decide the UX treatment for the QuickBooks API key on the Financial Settings page — it is currently stored encrypted but uses no gate UX. Decision deferred to this session: does the QuickBooks key warrant the same high-friction rotation flow as Stripe secrets, or a lighter treatment? Review QuickBooks API key permissions and revocation model before deciding.*

---

## Data Retention & Deletion Policy

### Content Type Deletability Audit & Archive Pattern

Implement the agreed rules from the session 081 discussion. Two parallel concerns:

**Deletion guards:**
- *Contact* — soft-delete in place; hard-delete super_admin only.
- *Event registration (paid)* — block deletion with a warning directing admin to issue the refund in Stripe first; allow after acknowledgement. Do not automate the refund — Stripe webhook will reconcile.
- *Event* — block deletion if any registrations exist; allow for drafts/no registrations.
- *Membership Tier* — block deletion if any active memberships reference it.
- *Fund* — block deletion if any donations reference it.
- *Product* — block deletion if any purchases reference it.
- *Widget Type* — block deletion if any pages reference it.
- *Donation* — not deletable; financial record, Stripe is source of truth.
- *Email Template* — not deletable; already enforced.
- *Page (system/portal)* — audit existing page-type protection system and plug any gaps. Add a second-tier loud warning for portal-system pages: "Deleting this page may render the member portal unusable."
- *Navigation Menu* — general warning on delete ("this menu may be in use on your site"); no dependency check — too unpredictable given widget-level nav use.
- *Form* — warn if submissions exist.
- *Collection* — warn or cascade-confirm if items exist.

**Archive / expiry pattern (new status, not deletion):**
Introduce an explicit "archived" state for types where "retired but historically referenced" is a real operational state. Archived items are hidden from default admin list views and cannot be selected for new associations, but all history is preserved. Apply to:
- *Product* — "no longer for sale"
- *Membership Tier* — "closed to new members"
- *Fund* — "no longer accepting donations"
- *Form* — "retired"

Evaluate *Mailing List* and *Collection* against the same criteria during this session. Implement archive toggle, default-view filter, and guard on new associations for each approved type.

### Data Retention & Cascading Delete Audit

Audit every deletion path. Define and implement what happens when: a user is deleted (notes, tags, import records); a contact is deleted (registrations, memberships, mailing list memberships); an import session is deleted. Define a soft-delete purge policy. Output: code changes (migrations, `onDelete` rules) and a written policy document in the repo.

---

## Infrastructure & Ops — Beta 1 Scope

### Admin User — Secure Password Generator

Add a "Generate secure password" button to the admin user create/edit form, below the password field. Client-side Alpine.js only — generates a cryptographically random string, fills both password and confirm-password fields, and copies to clipboard. No server round-trip. Admin pastes into their password manager and hands it to the user.

### Sandbox / Demo Data Mode

A mode or toggle that lets the admin act on a small set of controllable test records without touching real data. For Beta 1: anonymous read-only login to the marketing site admin panel, backed by full dummy data from the sample data generator. A server-side demo-mode flag (`APP_DEMO=true` or equivalent) gates all write operations for safety. Scope and UX to be agreed before building. Related: default sample record for the system email preview wizard — resolve both in the same session if possible.

### Help System Enhancements

Help index page with table of contents and search bar. Link in the left navigation. Process articles: Google Analytics / GTM, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

### Code Housekeeping Notes

Items spotted during other sessions that need cleanup but don't warrant their own session:

- **Orphaned `WidgetRegistry` import** — `PageWidgetsRelationManager.php` imports `App\Widgets\WidgetRegistry`, which does not exist. The relation manager itself appears unused (widget editing goes through the Livewire `PageBuilder` / `PageBuilderBlock` path). Confirm the relation manager is dead code, remove the import, and delete the file if it is no longer referenced anywhere. Spotted session 075.

---

## End of Roadmap — Beta 1

### Onboarding / Install Dashboard Widget

First-run widget: detects unconfigured install, walks admin through minimum viable setup steps (mail, Stripe, branding). Disappears once confirmed. Could double as an ongoing health-check widget for production installs.

### Demo

Anonymous read-only login to the marketing site admin panel backed by full dummy data. A server-side demo-mode flag (`APP_DEMO=true` or equivalent) blocks all write operations. Pitch demo flow: prospect names a company and picks a logo → install runs during the pitch → prospect receives a URL at the end with their company name, logo, and contacts imported from a competitor CSV. The demo is presented on the marketing site; the server flag prevents any data manipulation if someone tries to poke around. The Demo and Installer sessions are the final two pieces of Beta 1.

### Third-Party Licensing Compliance Audit

Before Beta 1 ships: audit all third-party dependencies for license compliance. Known items requiring verification:
- **Swiper.js** — MIT license (copyright Vladimir Kharlampidi). MIT requires the copyright notice and license text be included in distributions. Verify the Vite build or a LICENSES file satisfies this. Swiper Studio (no-code builder) is a separate paid product — confirm we are not using any Studio-only assets.
- Review all other npm and Composer dependencies for license compatibility with a commercial product.

---

## ── BETA ONE ─────────────────────────────────────────────────────────────────

**Beta One** is the first shippable, publicly demonstrable version of the product. Definition of done: a live hosted site is running on the product's own CMS, anonymous read-only demo access is available for prospects, and a live install demo can be performed during a sales pitch — prospect names a company, picks a logo, imports contacts from a competitor, and receives a URL with their configured install at the end of the meeting. All sessions above this line are planned for Beta 1 delivery.

---

## Post-Beta 1

### Control Pattern Homogenisation Audit

Audit the admin UI for combination input controls (file upload + preview, logo pickers, image fields, etc.) that exist in more than one place but behave differently without a documented reason. Define the canonical pattern for each control type and bring all instances into conformance. The logo field inconsistency between Site Theme and Admin Settings is the known starting point.

### Custom Field Grouping & Layout

Allow admins to group and arrange custom contact fields into labelled sections with a configurable column count. The form-builder approach is the right model — give users control over column count per section and let fields stack within each column. JSON schema should be extended to support containers/fieldsets when a clean implementation path is available.

### Default Content State

All five starter pages in a published state. Default seed includes one sample event and one sample blog post so all major features are demonstrated on a fresh install. Add an install option to skip default content for users who prefer a blank slate.

### Installer

Guided first-run setup: database connection, mail provider configuration, admin user creation, initial seed. Must be fast enough to run live during a sales pitch — a prospect-facing demo that ends with their own configured install at a fresh URL.

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

## CMS & Page Builder — Post-Beta 1

### SEO — Advanced

JSON-LD structured data (Article, Event, Organization). Global and per-page custom head injection. Global footer injection. Twitter card type. Sanitization strategy. Gate advanced injection by role. Builds on the per-page meta fields added in Beta 1.

### Site Theme & Public Theme Builder

*Merges "Site Theme Enhancement" and "Public Theme Builder / Custom CSS Tool" — both extend SiteThemePage.*

Extends the existing `SiteThemePage` which already has appearance settings and a live SCSS editor. Adds: colours reorganised into light/dark palette rows, "Mirror" buttons applying HSL-based inversion, live WCAG AA contrast checker with nearest-passing-colour suggestions, preset palettes with swatches. Split-pane SCSS editor with live page preview on the right.

### Theme Kitchen Sink

A "kitchen sink" preview page for theme editing that exposes all major headings, text styles, form elements, and other styled components in a single view. Allows the user to tweak theme settings while seeing the impact across all styled elements simultaneously.

### CMS Style System — Full Widget Styling

Full widget style surface schema: each widget type declares a `style_schema` (CSS property → control type + constraints). All widgets accept arbitrary CSS scoped to `[data-widget="{uuid}"]` at render time. Builds on the front-end pipeline and column widget infrastructure from Beta 1. The `style_config` column and universal padding/margin controls are laid in session 086 — this session extends that foundation. Planning for this system was partly in scope for Beta 1 sessions — defer the implementation until post-Beta.

### Widget Portability & Distribution

Each widget becomes a self-describing class: handle, config schema, render logic, JS/CSS asset manifest, optional collection type definitions. Foundation for future widget distribution.

### Page Builder — Live Preview

Split-pane or overlay preview of page changes before saving. Requires the front-end build pipeline to be stable.

### Page Builder — Layer Explorer

A simple text-node tree representation of the page's widget structure (similar to a DOM inspector). Helps users locate deeply nested blocks inside column slots without expanding every level manually. Not front-and-centre — a collapsible sidebar or panel overlay. Needs design discussion before building; deferred to post-Beta 1.

### ~~Media Library UI~~ *(moved to session 092, Beta 1 scope)*

### Image & Media Handling — Carousels & Galleries

Full carousel and gallery widget types beyond the basic image slider added in Beta 1. Lightbox, captions, reorder controls.

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

### API Key Pattern Validation & Test-Mode Warning

Two related features: (1) form-level validation that recognises API key format patterns (e.g. Stripe `sk_test_` vs `sk_live_`, Resend `re_` prefix) and shows an inline hint; (2) a production-context warning surfaced when a test-mode key is detected. Scope and warning placement to be agreed following the session 081 discussion.

### System Email Preview — Default Sample Record & Full Coverage

A user-editable singleton record that pre-fills the email preview wizard with representative sample data when no real recipient is available (e.g. test sends). Note: the preview wizard already exists (session 080) and is already in use for donation receipts and user invitations — this session extends the same pattern to any remaining system email sends, rather than rebuilding. Resolve alongside Sandbox / Demo Data Mode if possible.

### Batch Edit on Admin Tables

Add batch (bulk) edit capability to admin resource tables. Any field exposed in a content type's settings should be available as a batch-edit action. Scope: agree which tables get batch edit, define the UI pattern (inline modal vs dedicated form), and implement. Content type deletability decisions (see separate stub) should be resolved first so batch-delete controls are consistent.

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

## Exploratory & Fun *(post-Beta 1)*

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

### Easter Egg & Fun Features
