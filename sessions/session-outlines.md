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
| 069 | Minor Tweaks, Fixes & Git Hygiene |
| 070 | Admin User Invitations |
| 071 | Household Linking |
| 072 | Activity Log |
| 073 | Stripe Foundation |
| 074 | Products & Checkout |
| 075 | Donations — Foundation |
| 076 | Tax Receipts |
| 077 | Mailing List from Donors |

---

## Member Portal & Self-Service

*Sessions in this group are strictly ordered — each depends on the previous.*

### Household — Remaining Features

Core household model built in session 071 (self-referential `contacts.household_id` FK, admin assignment, portal display, address sync). Remaining for future sessions if needed:

- Member-to-member portal invite flow with staff approval.
- Household dissolution / head transfer when the head contact leaves.
- Household-level aggregate giving and mailing deduplication (Finance sessions).

---

## Communication & Accountability

*Activity log built in session 072. Future additions: global filterable log, field-level diff, observers for Finance and other models.*

### Activity Log Viewer

Filterable admin view of the `activity_logs` table. Who did what, to which record, and when. Covers all logged events including financial key rotations (written in session 073). Simple read-only table — no editing or deletion.

---

## Finance

*Scope boundaries: no grants, wages, payroll, or disbursements. No card data stored — Stripe handles all sensitive payment data. The application is deposit-only — it never initiates a refund, payout, or balance transfer. All financial reversals are handled in the Stripe dashboard.*

*A dedicated Financial Settings page (gated to developer/treasurer roles) holds Stripe and QuickBooks API keys. Keys are encrypted at rest.*

### ~~Stripe Foundation~~ *(completed session 073)*

Webhook receiver, encrypted key storage, polymorphic transaction table, and restricted API key scoped to minimum permissions. The Stripe Checkout pattern established in session 074 is the template for event ticketing and membership payment in future sessions.

### ~~Products & Checkout~~ *(completed session 074)*

Stripe Checkout for one-off and waitlist-gated products. Admin product/price management, purchase tracking, contact auto-creation on webhook.

### ~~Donations — Foundation~~ *(completed session 075)*

One-off and recurring Stripe donations. Donation widget with configurable preset amounts and frequency toggles. Webhook handling for checkout completion and recurring billing cycles. Contact auto-creation. Activity logging on status transitions. Admin view/audit resource with transaction history.

### ~~Tax Receipts~~ *(completed session 076)*

Fund designation on donations (`fund_id` FK, `restriction_type` on funds). Donors page with year/threshold filter table. `donation_receipts` table for audit trail. `DonationReceipt` mailable through existing email system. Admin send and force-resend actions.

*Pledge tracking was considered and deliberately excluded. Pledges that don't flow through Stripe belong in QuickBooks, not here.*

### Finance Data Boundary

Enforce a clean separation between Finance and the rest of the admin. Financial amounts and Stripe links are removed from CRM and CMS views. The Transactions table becomes the single place where dollar values and Stripe dashboard links live. Non-Finance views replace inline financial data with navigation affordances (count badges + "View in Finance →" buttons). No backwards compatibility needed — data can be wiped, and removing views and code is explicitly part of this work.

### Debug Generator — Donations, Products & Purchases

Extend `DashboardDebugGeneratorWidget` to support generating products (with prices), purchases, and realistic donations with tax year control and fund/contact linkage. New factories: `ProductFactory`, `ProductPriceFactory`, `PurchaseFactory`. Improved `DonationFactory` with `started_at`. Widget gains a Tax Year input and two new type options (products, purchases). Existing contacts/products are reused if present; otherwise created fresh. Wipe extended for new types.

### System Email Preview Wizard

Multi-step confirmation modal for admin-initiated system email sends. Step 1: confirm recipient count and email type. Step 2: preview merged message (first recipient for bulk sends) in an iframe. Step 3: send now. Retrofit to donor receipts, user invitations, and event cancellation. Reusable Filament action so future send points can adopt it cheaply.

### Minor Tweaks & Polish

Accumulated minor fixes and polish items assembled over recent sessions. Details to be shared and planned at end of session 080.

### Codebase Audit & Migration Squash

Periodic codebase audit and migration squash, following the patterns established in sessions 042 (Codebase Audit — Fields, Schema, Permissions & Help Coverage) and 062 (Codebase Audit & Migration Squash). Details to be filled in at end of session 081.

### QuickBooks Sync

One-way push of categorised transactions to QuickBooks. No reconciliation, no pulling from QB.

*Before beginning: obtain the exact QuickBooks API payload structure and OAuth flow from the QuickBooks documentation. Also decide the UX treatment for the QuickBooks API key on the Financial Settings page — it is currently stored encrypted but uses no gate UX. Decision deferred to this session: does the QuickBooks key warrant the same high-friction rotation flow as Stripe secrets, or a lighter treatment? Review QuickBooks API key permissions and revocation model before deciding.*

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

### Code Housekeeping Notes

Items spotted during other sessions that need cleanup but don't warrant their own session:

- **Orphaned `WidgetRegistry` import** — `PageWidgetsRelationManager.php` imports `App\Widgets\WidgetRegistry`, which does not exist. The relation manager itself appears unused (widget editing goes through the Livewire `PageBuilder` / `PageBuilderBlock` path). Confirm the relation manager is dead code, remove the import, and delete the file if it is no longer referenced anywhere. Spotted session 075.

### Help System Enhancements

Help index page with table of contents and search bar. Link in the left navigation. Process articles: Google Analytics / GTM, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

### Accessibility — ARIA, ADA & Colour Contrast

ARIA landmark roles, correct states on interactive elements, keyboard navigation, focus styles, skip-to-main. Automated contrast check (axe-core or similar). Colorblind simulation audit. Output: fixes + WCAG AA compliance statement for client ADA documentation.

### Privacy & Legal Footer Example

*Example custom footer component with placeholder slots for privacy policy and terms. Reference implementation for customers.*

### Scheduled System Email Sends

Allow admin-initiated system emails (donor receipts, event notifications, etc.) to be scheduled for a future send-at time rather than sent immediately. Requires a `scheduled_emails` table, a queue/scheduler job, and cancellation UI. Resend does not support native scheduled send — scheduling is handled by the application. Review the System Email Preview Wizard (session 080) before designing this — the wizard's Step 3 is the natural place to surface the scheduler.

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
