# Nonprofit CRM — Session Outlines

This is the single working reference for all sessions. Completed sessions are listed by title below. Future sessions are organised by group — reorder and rewrite them freely as the project evolves.

A **Beta One** milestone is planned as the first shippable, demonstrable version of the product: a live hosted site and a live install demo performable for prospects in real time. All sessions before the milestone marker are planned for Beta 1 delivery. Sessions after the marker are deferred until post-Beta 1.

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
| 096 | Per-Page SEO & Header Snippets |
| 097 | Header & Footer Widget System |
| 098 | Additional Widget Types — Calendar, Chart & Video |
| 099 | Template System — Data Model & Migration |
| 100 | Template Manager UI & Page Creation Flow |
| 101 | Code Review & Cleanup |
| 102 | Stripe Payment Methods & QuickBooks Connection |
| 103 | QuickBooks Transaction Sync |
| 104 | QuickBooks Customer Matching |
| 105 | QuickBooks Per-Type Account Mapping |
| 106 | Transaction Ledger Cleanup & Seeder |
| 107 | Paid Event Registration & Membership Checkout |
| 108 | Beta-One Bug Fixes & Migration Squash |
| 109 | Deletion Guards & Archive Pattern |
| 110 | Data Retention & Cascading Delete Audit |
| 111 | Trash Management UI |
| 112 | Password Generator & Data Generator Audit |
| 113 | Local Dev Environment — WSL2 Migration |
| 114 | Permissions Audit & Coverage |
| 115 | Housekeeping & Consistency Audit |
| 116 | Code Review & Framework Alignment Audit |
| 117 | Permissions Framework & Coverage Audit |
| 118 | Help System — Search, Standalone Pages & Related Articles |
| 119 | Widget Picker — Categories, Page-Type Filtering & Search |
| 120 | Filament Custom Theme — Admin Panel Tailwind Build |
| 121 | Widget — Hero |
| 122 | Widget — Hero Enhancements (Fullscreen, Video Background, Full Bleed, Semantic Markup) |
| 123 | Tailwind Removal & Build Server Integration |
| 124 | Custom Form Fields & Site Styles |

---

---

## Infrastructure & Ops — Beta 1 Scope

**Help docs needing body content written** (stubs exist with frontmatter + route mapping):

- `resources/docs/generate-tax-receipts.md` — Generate Tax Receipts page

### Code Housekeeping Notes


---

## Public Styles & Form Controls — Beta 1 Scope

### Session 125 — Build Server Integration Settings & CI Build Pipeline

Move build server URL and API key into the admin settings panel (alongside Stripe, MailChimp, QuickBooks keys). Add a build server health check to the dashboard integrations widget — show a warning when the build server is unavailable or unconfigured. Move both the Vite build (admin theme + public SCSS) and `build:public` (widget bundle) into the GitHub Actions deploy pipeline so they run at deploy time. Remove Node/npm from the production Docker image — CI handles all asset compilation.

---

## Widget System — Beta 1 Scope

### Session 123 — Tailwind Removal & Build Server Integration

Remove all Tailwind utilities from public-facing templates (SCSS framework established in session 122). Connect the app to the external build server to produce one CSS and one JS bundle. Document the integration. Discuss fallback options. See `docs/build-server-spec.md`.

### Widget — Logo Garden

Responsive grid of partner/sponsor logos from a custom collection. Configurable columns, optional grayscale-to-colour hover effect. Collection-backed via `WidgetDataResolver`.

### Widget — Board Members

People grid from a custom collection: photo, name, title, optional bio. Grid or list layout. For board of directors, staff, or team pages. Collection-backed.

### Widget — Three Buckets

Three side-by-side content blocks, each with optional image, heading, body text, and CTA link. Config-driven (not collection-backed). Fixed at three.

### Widget — Alternating Panels

Vertical stack of image-plus-text panels that alternate image side (left, right, left, right). Collection-backed. For feature tours and storytelling sections.

### Widget — Product Carousel

Swiper-based carousel of published products: image, name, price, link. Uses the existing `products` data source in `WidgetDataResolver`.

### Widget — Map Embed

Google Maps iframe embed: admin pastes a Maps share URL, widget converts to iframe. Configurable height, optional heading and caption.

### Widget — Headline Patterns

Configurable heading element: heading level (h1–h6), alignment, optional subtitle, decorative styles (underline, overline, rule, none). Section divider / visual break.

### Widget — CTA Patterns

Call-to-action section: heading, body text, one or two styled buttons (solid, outline, pill). Configurable background (transparent, light, dark).

### Widget — Social Sharing

Row of share buttons (Facebook, Twitter/X, LinkedIn, Email, Copy Link). Pure anchor tags with platform share URLs — no third-party scripts. Toggleable per platform.

### Widget — Modal

Button-triggered modal overlay containing child widgets. Nesting container like column_widget but triggered by click. Alpine.js open/close. Configurable trigger button and modal size.

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

## Member Portal & Self-Service

### Household — Remaining Features

Core household model built in session 071 (self-referential `contacts.household_id` FK, admin assignment, portal display, address sync). Remaining for future sessions if needed:

- Member-to-member portal invite flow.
- Household dissolution / head transfer when the head contact leaves.
- Household-level aggregate giving and mailing deduplication (Finance sessions).

---

## CMS & Page Builder — Post-Beta 1

### SEO — Advanced

Twitter card meta tags. Manual canonical URL override. SEO scoring/audit checklists. Search console integration. Alt-text validation. Builds on the JSON-LD, OG tags, snippets, sitemap, and noindex controls delivered in session 096.

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

### Image & Media Handling — Carousels & Galleries

Full carousel and gallery widget types beyond the basic image slider added in Beta 1. Lightbox, captions, reorder controls.

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

### API Key Pattern Validation & Test-Mode Warning

Three related features: (1) form-level validation that recognises API key format patterns (e.g. Stripe `sk_test_` vs `sk_live_`, Resend `re_` prefix) and shows an inline hint; (2) a production-context warning surfaced when a test-mode key is detected; (3) **environment mismatch hard gate** — on save, detect whether Stripe and QuickBooks are pointing at different environments (e.g. Stripe live + QB sandbox, or vice versa) and refuse to save with a clear error. The dangerous scenario is Stripe test mode pushing fake transactions into a real QuickBooks company. The gate ensures both integrations are in the same mode before the configuration is accepted. Scope and warning placement to be agreed following the session 081 discussion.

### System Email Preview — Default Sample Record & Full Coverage

A user-editable singleton record that pre-fills the email preview wizard with representative sample data when no real recipient is available (e.g. test sends). Note: the preview wizard already exists (session 080) and is already in use for donation receipts and user invitations — this session extends the same pattern to any remaining system email sends, rather than rebuilding.

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

---

## Future Projects *(post-management console)*

### Frontend Build Service

**Pulled forward to pre-beta scope as Session 122.** The build server is being built as a separate repo and integrated into the app before the remaining widget sessions. See `docs/build-server-spec.md` for the full specification. The admin panel CSS (Filament theme) remains on Vite — the build server handles only public-facing widget and site styles.

### Easter Egg & Fun Features
