# Future Sessions — Topic Index

Sessions 010–020 have written outlines in this folder. This file captures topics for sessions
beyond 020, ordered roughly by dependency. Nothing here is scheduled or scoped yet unless
an outline file exists.

---

## Already Outlined (021–025)

| Session | Topic | File |
|---------|-------|------|
| 021 | Trix → Quill in page builder; parent/child component split | session-021-prompt.md |
| 022 | Saved Import Field Maps (superseded → moved to 023) | session-022-outline.md |
| 023 | Custom Contact Fields | session-023-outline.md |
| 024 | Help System (context-sensitive admin help) | session-024-outline.md |
| 025 | Migrate blog posts into pages table; page builder for posts | session-025-outline.md |

---

## Queued (not yet outlined)

These topics have been scoped at a high level. Each needs a full outline session or can
be outlined at the start of its build session.

### Admin / Settings

- **Navigation UI Overhaul** — The navigation items list needs per-item Edit and Delete
  buttons, drag-to-reorder (removing the sort order text field), and a redirect to the list
  after a successful save (rather than "save and create another" staying on the same page).
  When a new item is saved (not "save and create another"), redirect to the list with the
  new item at the bottom.

- **Multiple Menus** — The navigation feature only supports a single menu. Admins need to
  create named menus (primary, footer, sidebar, etc.) for all the places a site might need
  navigation. This session should coincide with a discussion about what templating conventions
  the CMS can adopt for injecting menus into theme areas outside the page builder.

- **White-Labelling** — Remove or replace Filament branding. Custom admin logo, custom login
  page (logo, background, copy), custom browser tab title/favicon. Likely lives in
  `AdminPanelProvider` and a custom login page override. The logo upload already exists in
  CMS Settings — surface it in the admin chrome here.

- **Admin Color Themes** — Admin primary color configurable via `site_settings` rather than
  hard-coded Amber in `AdminPanelProvider`. Public frontend theme colors (CSS custom
  properties) also driven from settings. Allows each client install to have its own brand
  palette without a code change.

- **CMS Theming / Templating Convention** — Discuss and adopt an open standard or convention
  for theme areas outside the page builder (header, footer, sidebar). Goal is to pick a
  pattern (e.g. Blade components, slot system, convention-based includes) rather than build
  a custom system. No build work — just a planning/decision session that produces a concrete
  direction.

### CMS

- **Gated Pages** — Restrict pages or page paths to logged-in members. Two approaches to
  evaluate at session start: (a) a toggle on individual pages, or (b) a reserved path prefix
  (e.g. `/members`) where everything beneath it is gated. Depends on Member Portal session.

- **Media Library UI** — Admin UI for Spatie Media Library. Upload, tag, browse, attach to
  pages/posts/collection items.

- **Form Builder** — Public-facing forms (contact, newsletter signup, donation, event RSVP).
  Configurable fields, email notifications, optional CRM write-back.

- **Member Portal** — Public auth for contacts. Login, profile, membership status, event
  history, donation history. Requires Contact ↔ User link.

### Settings & Infrastructure

- **Inactive Form Field Visual Indicator** — When a form field or section is disabled
  (`->disabled()`), the field heading and label should be visually grayed out. CSS-only fix:
  `#ccc` on labels/headings of disabled fields. *(Added admin.css rule in session 020 —
  verify coverage across all forms that use `->disabled()`.)*

- **Admin Dashboard** — Replace the default Filament dashboard widgets with meaningful stats:
  recent contacts, donation totals, upcoming events, recent posts.

- **Settings > Finance** — Finance-specific admin config: receipt templates, tax ID, fiscal
  year settings, QuickBooks connection status.

### Integrations

- **MailChimp** — Sync contacts/members to MailChimp lists. Trigger on membership
  create/renew. Tag mapping. One-way push; no pull.

- **QuickBooks** — Transaction mirror sync. Map Funds to QB classes. Export donations as
  journal entries. OAuth connection flow.

- **Stripe Webhooks** — Keep Transaction mirror current from Stripe events. Handle refunds,
  disputes, and subscription renewals.

### Finance Extensions

- **Grant Module** — Funder, Grant (amount/dates/restrictions), GrantAllocation,
  GrantReport. Dependency: Fund model mature.

- **Tax Receipts** — Annual giving statements by contact. PDF generation. Batch email.
  Dependency: Donation model complete, SMTP integration.

- **Recurring Donations** — Stripe subscription-backed. Dependency: Stripe Webhooks session.

### CRM Extensions

- **Household / Family Grouping** — Group related individual contacts. Relevant for household
  memberships and joint communications.

- **Contact ↔ User Link** — Formal FK between `contacts` and `users`. Required for member
  portal.

- **Communication Log** — Track emails sent to contacts beyond Notes. Email open/click
  tracking if MailChimp is integrated.

- **Duplicate Detection** — Flag potential duplicate contacts on import or create.

### DevOps / Infrastructure

- **Deployment** — Deploy to a real server before building the installer. Deploying will
  surface environment and configuration problems that need to be solved before the installer
  can be designed. Do this before the Installer session.

- **Installer** — Browser wizard + CLI `php artisan install`. Toggleable content packs
  (blog, events, donations). Dependency: Deployment session.

- **Deployment Pipeline** — Automated deploys (GitHub Actions or similar). Environment
  config for staging vs production.

- **CSP Hardening** — Resolve the `eval()` CSP issue that blocks Alpine plugins in
  production. Add proper `Content-Security-Policy` headers.

- **Backups** — Automated DB backups. Spatie Backup package configuration.

- **Audit Log** — Who changed what and when. Spatie Activity Log already in the stack —
  enable and surface it in the admin.
