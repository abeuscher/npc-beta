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

---

## Email

### 036 — Mailing List Manager

Dynamic audience lists defined by filter rules against the contacts table. Simple mode: repeater UI with AND/OR conjunction, standard contact fields and tags, full operator set (equals/not equals, contains/not contains, includes/not includes, is empty/is not empty). Advanced mode: raw PostgreSQL WHERE clause textarea, gated behind a `use_advanced_list_filters` permission that is off by default for all roles. Read-only Postgres connection with statement timeout and keyword blocklist. Live contact count, CSV export. Help article includes contacts table schema and custom_fields JSON format for advanced use.

### MailChimp Integration

---

## CRM & Importer

### Importer — Phase 2

### Duplicate Contact Detection

### Communication Log

### Household & Family Grouping

### Contact ↔ User Link

---

## Forms & Membership

### Form Builder

### Secure Public Signup Flows

### Member Portal

### Gated Pages

---

## Volunteer Management

*A volunteer is a contact — same record, contact type tag. No separate model needed. Document handling (waivers, agreements) is explicitly out of scope; use an external tool for that.*

### Contact Record — Birthday & Age Fields

*Applies to all contacts, not just volunteers. Required for age verification for working volunteers. Small focused session or foldable into another CRM session.*

### Volunteer Profile & Hours Tracking

*Skills, availability, background check status and expiry, training/certifications held. Hours log tied to an event or a standalone activity. Total hours summary on the contact record.*

### Volunteer Scheduling

*Recurring shift slots with capacity. Admin assignment and self-signup. Connects to the Events module for event-day volunteer roles.*

### Volunteer Communication & Recognition

*Shift reminder emails, milestone triggers (100 hours, anniversary, etc.). Integrates with the email system.*

### Volunteer Portal

*Public-facing self-service: signup, view upcoming shifts, log hours pending admin approval. Extends or reuses the Member Portal patterns.*

---

## Finance

### Finance Settings

### Grant Module — Planning

### Grant Module — Build

### Stripe Webhooks

### Recurring Donations

### Pledge Tracking

*Multi-year pledge commitments with expected payment schedules and outstanding balance tracking. Pledge payments link to donation/transaction records. Distinct from recurring donations — a pledge is a promise, not a subscription.*

### Tax Receipts

### QuickBooks Integration

---

## CMS & Page Builder Polish

### Page Builder — Column Layout System — Planning

### Page Builder — Column Layout System — Build

### Page Builder — Live Preview

### Page Builder — Widget Styling & Basic Interactivity

### Page Templates & Layout Controls

### Image & Media Handling — Carousels & Galleries

### Media Library UI

### Public Theme Builder

### CDN Integration

---

## API & Integrations

### Public API Endpoints

---

## Infrastructure Finishing

### Installer

### Audit Log

### Privacy & Legal Footer Example

*No analytics, no cookie consent system — those are out of scope by design. This session produces a well-structured example custom footer component with sensible placeholder slots for privacy policy, terms, and any legal copy the organization needs. Ships as a reference implementation customers can drop in and edit.*

---

## Exploratory & Fun

### LLM Integration — Planning & Brainstorm

### Wow Features Brainstorm

### Easter Egg & Fun Features


---
# Future potential items

### Multi-Vendor Mail Support

*Add additional sending providers to the Mail Settings page: SMTP, AWS SES, Postmark, Mailgun. Each provider adds its own credential fields (visible when that driver is selected) and a config branch in `AppServiceProvider`. No architectural changes required — the switchable driver pattern from session 034 is already in place.*