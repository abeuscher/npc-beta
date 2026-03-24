---
title: System Taxonomy
description: Reference document for all enumerated values, role vocabulary, status strings, and architectural terms used throughout the application.
version: "0.25"
updated: 2026-03-23
standalone: true
tags: [reference, taxonomy, vocabulary]
---

# System Taxonomy

Internal reference. Not tied to a specific admin page. Update this file as vocabulary evolves.

---

## Contact Roles

Contacts are not assigned a single role — they are identified by presence of related records.

| Role | Condition |
|------|-----------|
| Member | Has at least one `Membership` with `status = active` |
| Donor | Has at least one `Donation` record |
| Volunteer | Future — no model yet |
| Contact | Default when none of the above apply |

---

## Membership

### Status values (`memberships.status`)
- `active` — current, valid membership
- `expired` — past the `expires_on` date
- `cancelled` — manually cancelled before expiry

### Billing intervals (`membership_tiers.billing_interval`)
- `monthly`
- `annual`
- `one_time`
- `lifetime`

---

## Contact Sources (`contacts.source`)

Set at creation. Never updated after the fact.

- `manual` — created by a staff member in the admin
- `import` — created by the CSV importer
- `form` — created by a web form submission
- `api` — created via API (future)

---

## Page Types (`pages.type`)

Controls routing, widget availability, and landing page behaviour.

- `default` — a standard public CMS page
- `post` — a blog post (slug prefixed with blog prefix setting)
- `event` — an event landing page (slug prefixed with `events/`)
- `portal` — a member portal page (served under `/portal/`)
- `system` — reserved infrastructure pages (home, blog index, events index, portal home); type is locked and cannot be changed in the UI

---

## Event Registration Modes (`events.registration_mode`)

- `open` — public registration form is live
- `closed` — registration form is hidden; closed message shown
- `external` — redirects to an external URL; link shown in widget
- `none` — walk-in only; no registration form or link

---

## Import

### Duplicate strategies
- `skip` — keep the existing contact, ignore the incoming row
- `update` — overwrite existing contact fields with incoming values

### Import session statuses (`import_sessions.status`)
- `pending` — uploaded, awaiting review
- `approved` — reviewed and accepted; contacts are visible
- `rejected` — reviewed and discarded

---

## Permissions

### Verb prefixes (Spatie pattern)
- `view_any_` — list/index access
- `view_` — single record access
- `create_` — create new records
- `update_` — edit existing records
- `delete_` — soft-delete, restore, and force-delete

### Standalone permissions (not tied to a resource)
- `use_advanced_list_filters`
- `import_data`
- `review_imports`
- `edit_theme_scss`
- `manage_routing_prefixes`
- `view_any_member`
- `view_any_form_submission`, `view_form_submission`, `delete_form_submission`

### Roles
| Role | Summary |
|------|---------|
| `super_admin` | Full access via Gate bypass. No explicit permissions needed. |
| `crm_editor` | Full CRM, memberships, donations, import. |
| `event_manager` | Full events, read-only contacts. |
| `volunteer_coordinator` | Full contacts, tags, notes; read-only events. |
| `treasurer` | Full finance; read-only contacts. |
| `cms_editor` | Full CMS (pages, posts, collections, tags). |
| `blogger` | Full CMS + navigation items. |

---

## Admin Navigation Groups

Ordered as they appear in the sidebar.

1. CRM — Contacts, Members, Organizations, Households
2. Finance — Donations, Transactions (future)
3. CMS — Pages, Blog Posts, Collections, Navigation, Site Theme, Settings
4. Tools — Widget Manager, Collection Manager, Custom Fields, Import Contacts, Importer, Membership Tiers, Tag Manager
5. Settings — General, CMS, Mailing Lists, Integrations

---

## Collections

### Source types (`collections.source`)
- `custom` — user-defined collection with arbitrary field schema
- `blog_posts` — system collection; maps to published `Page` records of type `post`
- `events` — system collection; maps to published upcoming `Event` records

System collections (`source != 'custom'`) are read-only in the UI. Their items are resolved dynamically at render time.

---

## Tags

Tags are polymorphic. The same `Tag` record can be attached to:
- Contacts
- Events
- Pages / Posts

Tag types are not explicitly stored — the `taggable_type` column on the pivot table holds the model class name.

---

## Web Forms

### Field types
- `text`, `textarea`, `email`, `phone`, `number`
- `select` (requires `options` array)
- `checkbox`
- `hidden` (carries a `default_value`; never shown to the user)
- `honeypot` (anti-spam; must be empty on submission)

---

## Portal

### Portal account statuses
- Active — `portal_accounts.is_active = true`
- Suspended — `is_active = false`

Portal accounts are linked 1-to-1 with a Contact. Login is by email + password. Portal is separate from the admin panel and uses a separate auth guard.

---

## Mailing Lists

Contacts opt in via:
- Manual toggle in the admin
- Web form with `mailing_list_opt_in` field
- MailChimp sync (bidirectional)

`contacts.mailing_list_opt_in` is the canonical field. MailChimp is treated as a downstream sync target, not the source of truth.
