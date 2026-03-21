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

---

### Duplicate Contact Detection

Detect probable duplicate contacts at import time and on the contact list. Matching strategy: exact email match (hard duplicate), fuzzy name + postal code match (probable duplicate). At import, flag duplicates in the preview step before any records are saved. On the contact list, a "Review Duplicates" action surfaces probable pairs for admin review with merge or dismiss options.

### Communication Log

### Household & Family Grouping

**Depends on: Member Portal existing first.**

A lightweight household record — name, canonical mailing address, members (linked contacts). No nav slot; accessed only through the contact record (a Household panel on the contact edit/view page). No admin-managed assignments.

Self-service flow: a logged-in member can request to join an existing household by searching on a unique identifier (household name + address, or a short code). The request goes into a staff approval queue — no one self-assigns, they only request. On approval the contact is linked. On the contact record, staff can also manually link/unlink. Aggregate giving is computed from linked members, not stored. Used for physical mailing deduplication, compound salutations, and household-level event/ticket limits.

### Contact ↔ User Link

---


---

## Forms & Membership

### Form Builder — Actions Pipeline

### Secure Public Signup Flows

### Member Portal

*Prerequisite for Household & Family Grouping — the household join request flow lives on the member's account editor page.*

> **Reminder — form email collision for members:** Currently, a web form submission matching an existing contact's email silently updates that contact record (non-destructively) and records a note. This is safe for non-members. Once members have a logged-in state, a form submission from an email tied to a member account must instead offer the user a login prompt to complete the transaction under their authenticated identity — not silently update the record. This needs to be designed and built when the Member Portal lands.

### Gated Pages

---

## Finance

### Finance Settings

### Grant Module — Planning

### Grant Module — Build

### Stripe Webhooks

*Before beginning: obtain the exact payload structure Stripe will POST to our endpoint from the Stripe documentation. Do not begin implementation until this has been provided.*

### Recurring Donations

### Pledge Tracking

*Multi-year pledge commitments with expected payment schedules and outstanding balance tracking. Pledge payments link to donation/transaction records. Distinct from recurring donations — a pledge is a promise, not a subscription.*

### Tax Receipts

### QuickBooks Integration

*Before beginning: obtain the exact payload structure QuickBooks will POST to our endpoint from the QuickBooks documentation. Do not begin implementation until this has been provided.*

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

## CMS & Page Builder Polish

### CMS Tags on Records

Wire the tag system into all CMS content types. Add a tag picker to the blog post, page, and event edit forms using the same multi-select + create-on-confirm behaviour as the contact tag field. The tag manager screen stays where it is.


### SEO & Head Tag Management

Add SEO fields to pages, blog posts, and events: Meta Title, Meta Description, OG / page thumbnail image, Twitter card type, and JSON-LD structured data blocks (Article, Event, Organization). Add a global head injection field in Settings and a per-page head injection field. Add a global footer injection field. Define a sanitization strategy. Gate advanced injection behind a role or setting so it can be hidden for orgs that don't need it. Write help copy covering both basic and power-user workflows.

### CMS Style System, Column Widget & Front-End Build — Planning

Design three interconnected systems before building any of them.

**(1) Widget style surface schema.** Each widget type declares a `style_schema` using CSS property names as keys with a control type and constraints as values — e.g. `display: {type: select, options: [grid, flex, block]}`, `font-size: {type: range, min: 12, max: 72, unit: px}`. This is how a widget restricts what the user can change. All widgets additionally accept an arbitrary CSS field, scoped at render time by wrapping it in a `[data-widget="{uuid}"]` selector generated per instance — no build step needed for scoping. Agree on the schema format before building.

**(2) Column widget.** A widget that holds named slots, each with a declared width. Child widgets are assigned to slots. Exposes `display` restricted to `grid`, `flex`, and `block` as a concrete example of the style schema above. No separate column layout system — columns are just widgets rendered by the same engine. Nesting a column widget inside a slot covers the colspan case without a special model.

**(3) Front-end build system.** Vite with `laravel/vite-plugin`. SCSS via the `sass` package (standard Vite integration). PostCSS with autoprefixer and cssnano for vendor prefixes and minification. Public side targets a single bundled CSS file and a single bundled JS file — minimise HTTP requests. Inline `<style>` blocks (style-guide custom properties output, widget arbitrary CSS) are minified at render time by a lightweight PHP helper or Blade directive, not a build step, so they stay inline and don't add a file call. Decide in this session what is compiled vs. what is generated dynamically at request time.

### Page Builder — Live Preview

### Page Builder — Widget Styling & Basic Interactivity

### Page Templates & Layout Controls

### SVG & Image Optimization

Add SVG support: inline SVG in page builder / rich text areas, and SVG as `<img src>` in image widgets and media fields. Add image optimization on upload: automatic compression and resize with optional manual quality controls.

### Image & Media Handling — Carousels & Galleries

### Media Library UI

### Public Theme Builder / Custom CSS Tool

Split-pane live CSS editor: CSS/SCSS on the left (compiled server-side via the same sass pipeline), live page preview on the right. Gated by user role. Sits in Settings or as a dedicated nav item. Output is minified and written into the inline style block, not a separate file call.

### CDN Integration

---

## API & Integrations

### Public API Endpoints

---

## Infrastructure Finishing

### Accessibility — ARIA, ADA & Colour Contrast

Audit and harden the public-facing frontend for accessibility. Add ARIA landmark roles and labels to the public layout (`<header>`, `<main>`, `<footer>`, `<nav aria-label>`). Ensure all interactive elements (nav dropdowns, form controls, buttons) have correct ARIA states (`aria-expanded`, `aria-current`, `aria-label`). Keyboard navigation audit: tab order, focus styles, skip-to-main link. Colour contrast: integrate an automated contrast checker (e.g. axe-core or similar) into the build or as a standalone audit step; flag any Pico defaults or theme colour combinations that fall below WCAG AA (4.5:1 for text, 3:1 for UI components). Colorblind simulation audit: identify palette choices that fail common colorblindness simulations (deuteranopia, protanopia). Output: a short written report of issues found plus fixes applied, and a WCAG AA compliance statement that can be shared with nonprofit clients for ADA documentation purposes.

### Help System Enhancements

Add a link to the full help system in the left navigation. Build a help index page with a table of contents and a search bar. Audit every primary navigation item and ensure each has a linked help article. Write process articles: Google Analytics / GTM setup, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

### Installer

### Admin User Activity Log

Each admin user needs a record of their significant actions against data — which contact records they edited, which events they created or cancelled, which donations they entered or deleted. The log is not a diff system but an event journal: who did what, to which record, and when. Surface it on the user record in the Users list (a tab or linked sub-page showing that user's recent activity) and optionally as a filterable global log under Settings or Tools. Scope in the planning session: which action types to capture, where to store the log (a dedicated table vs. an existing auditing package like `owen-it/laravel-auditing`), and what the retention/purge policy should be.

### Data Retention & Cascading Delete Audit

*Schedule this after Importer Phase 3 is complete, as the importer adds more cascading relationships.*

Audit every deletion path in the system and define what should happen when a record is removed. Key questions to answer and implement: What happens to notes, tags, and import records when the user who created them is deleted? What happens to contacts linked to a deleted import session — do they persist or cascade? What happens to event registrations when a contact is deleted? What happens to mailing list memberships? What is the intended lifetime of soft-deleted records — is there a purge policy? The output of this session is both code (correct `onDelete` behaviours, cascade or null-out rules in migrations) and a written policy document checked into the repo.

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
