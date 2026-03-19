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

---

## Email

### 038 — MailChimp Webhook Debugging

Webhook POST from MailChimp's servers returns HTTP 500. Sync and curl simulation work correctly. Full brief in `sessions/038. MailChimp Webhook Debugging.md`. A `Log::error()` diagnostic was deployed at end of session 037 — start by triggering a fresh unsubscribe and checking whether the log entry appears.

### MailChimp Integration (future)

---

## Admin & Dashboard

### Admin UI Polish

Change admin accent colour from orange to a deep blue or teal (exact value chosen by the designer before the session starts). Lighten form label colour ~20% relative to field input text to create clearer visual hierarchy between labels and values.

### Dashboard Enhancements

Restructure the admin dashboard. Left column: welcome message from site settings. Right column: help search box above a list of main help topics. Below the welcome message: quick-action buttons (New Blog Post, Create Event). Below that: vendor connection status links — MailChimp, Resend, QuickBooks, Stripe (display only configured integrations). Below that: popular help article links covering the most-asked setup topics (Google Analytics / GTM, site verification, custom CSS, custom collections, custom widgets, Google Fonts).

---

## CRM & Importer

### Tags in Contact Record

Add an inline multi-select tag field to the contact edit form. Typing a value not already in the list triggers a confirmation popup asking whether to create it as a new tag; on confirm the tag is created and attached immediately. This field pattern and behaviour is used across all content types that support tags (contacts, pages, blog posts, events) — implement consistently.

### Importer — Phase 2

Move the importer to the Tools section of admin navigation. Extend it to support all field types — standard and custom contact fields — with no separate import path needed. Import History: remove it from the navigation; surface it via a prominent link in the importer page header instead.

### Duplicate Contact Detection

### Communication Log

### Household & Family Grouping

When a contact is created, a household record is created automatically (or optionally). Other contacts can be added to a household via an email-based invite handshake — the household owner approves additions. Design principle: simple and frictionless, not complicated.

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

### CMS Tags on Records

Wire the tag system into all CMS content types. Add a tag picker to the blog post, page, and event edit forms using the same multi-select + create-on-confirm behaviour as the contact tag field. The tag manager screen stays where it is.

### Public Frontend Foundation

Build a clean, attractive public-facing template: well-structured HTML, a starter CSS file with sensible defaults, and a unique body class on every public page (`page-{slug}`, `post-{slug}`, `event-{slug}`). The goal is a public face that looks professional out of the box before further theming.

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

### Help System Enhancements

Add a link to the full help system in the left navigation. Build a help index page with a table of contents and a search bar. Audit every primary navigation item and ensure each has a linked help article. Write process articles: Google Analytics / GTM setup, Google site verification, custom CSS, custom collections, custom widgets, Google Fonts.

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
