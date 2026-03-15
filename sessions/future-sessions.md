# Future Sessions — Topic Index

Sessions 010–019 have written outlines in this folder. This file captures topics for sessions
beyond 019, ordered roughly by dependency. Nothing here is scheduled or scoped yet.

---

## Already Outlined (010–020)

| Session | Topic |
|---------|-------|
| 010 | Widget Config Schema (singleton fields + removes text_block special case) |
| 011 | CRM Taxonomy & Contact Model Clarity |
| 012 | User Roles & Permissions |
| 013 | Role Management UI (super_admin creates/edits roles, assigns permissions) |
| 014 | Events: Foundation (model, registration, admin UI) |
| 015 | Events: Extended (dates, locations, speakers, virtual) |
| 016 | Events: Ticketing & Payment |
| 017 | Events: Notifications & List Integrations |
| 018 | Import/Export: Core (contacts, donations CSV) |
| 019 | Import/Export: Extended (more types, source presets) |
| 020 | Help System (context-sensitive admin help) |

---

## Already Outlined (021+)

| Session | Topic |
|---------|-------|
| 021 | Installer: browser wizard + CLI `php artisan install`, toggleable content packs |

---

## Topics Beyond Session 018

### Settings & Admin Infrastructure

- **Settings > CMS** — Admin UI for `site_settings` table. Logo upload, site name, blog prefix,
  Pico CSS toggle, custom CSS upload, contact email, social links. Currently configured via
  config/env only.
- **Settings > Finance** — Finance-specific admin config: receipt templates, tax ID, fiscal year
  settings, QuickBooks connection status.
- **Admin Dashboard** — Replace the default Filament dashboard widgets with meaningful stats:
  recent contacts, donation totals, upcoming events, recent posts.
- **White-Labelling** — Remove or replace Filament branding. Custom admin logo, custom login
  page (logo, background, copy), custom browser tab title/favicon. Likely lives in
  `AdminPanelProvider` and a custom login page override.
- **Web Color Themes** — Admin primary color configurable via `site_settings` rather than
  hardcoded Amber in `AdminPanelProvider`. Public frontend theme colors (CSS custom properties)
  also driven from settings. Allows each client install to have its own brand palette without
  a code change.
- **Admin Field Grouping** — Audit and improve the layout of dense admin forms (Contacts,
  Donations, Events). Group related fields into collapsible sections, add column layouts where
  appropriate, improve field ordering. UX polish pass — no data model changes.

### Integrations

- **MailChimp** — Sync contacts/members to MailChimp lists. Trigger on membership create/renew.
  Tag mapping (CRM tags → MailChimp tags). One-way push; no pull.
- **QuickBooks** — Transaction mirror sync. Map Funds to QuickBooks classes. Export donations
  as journal entries. OAuth connection flow.
- **Stripe Webhooks** — Keep Transaction mirror current from Stripe events. Handle refunds,
  disputes, and subscription renewals.
- **Other** — Placeholder for future integrations (Salesforce, Eventbrite, Zapier webhook
  endpoint, etc.).

### Finance Extensions

- **Grant Module** — Funder (org), Grant (amount, dates, restrictions), GrantAllocation
  (grant → fund), GrantReport (narrative + financials). Dependency: Fund model mature.
- **Tax Receipts** — Annual giving statements by contact. PDF generation. Batch email.
  Dependency: Donation model complete, MailChimp or SMTP integration.
- **Recurring Donations** — Stripe subscription-backed. Produces monthly Transactions.
  Dependency: Stripe Webhooks session.

### CMS Extensions

- **Post Block Editor** — Posts gain the same ordered block stack as Pages (Session 009).
  Currently posts keep a single rich text body.
- **Media Library UI** — Admin UI for Spatie Media Library. Upload, tag, browse, attach to
  pages/posts/collection items.
- **Form Builder** — Public-facing forms (contact, newsletter signup, donation, event RSVP).
  Configurable fields, email notifications, optional CRM write-back.
- **Member Portal** — Public auth for contacts. Login, profile, membership status, event
  history, donation history. Requires Contact ↔ User link (Session 010).
- **Content Gating** — Restrict pages/posts to logged-in members or specific membership tiers.
  Dependency: Member Portal.

### CRM Extensions

- **Household / Family Grouping** — Group related individual contacts. Relevant for household
  memberships and joint communications.
- **Contact ↔ User Link** — Formal FK between `contacts` and `users`. Required for member
  portal. May be addressed in Session 010.
- **Communication Log** — Track emails sent to contacts (beyond Notes). Email open/click
  tracking if MailChimp is integrated.
- **Duplicate Detection** — Flag potential duplicate contacts on import or create.

### DevOps / Infrastructure

- **Deployment Pipeline** — Automated deploys (GitHub Actions or similar). Environment config
  for staging vs production.
- **CSP Hardening** — Resolve the `eval()` CSP issue that blocks Alpine plugins in production.
  Add proper `Content-Security-Policy` headers.
- **Backups** — Automated DB backups. Spatie Backup package configuration.
- **Audit Log** — Who changed what and when. Spatie Activity Log already in the stack — enable
  and surface it in the admin.
