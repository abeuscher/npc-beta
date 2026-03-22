# Claude Code — Initial Setup Prompt
## Nonprofit Platform Project

---

Paste this prompt in full at the start of a Claude Code session.

---

```
You are helping build an open source nonprofit CRM/CMS platform called NonProfitCRM. 
This project is in its initial setup phase. Nothing has been built yet.

Before writing any code, read the architecture document provided below in full. 
Every technical decision in that document has been made deliberately. 
Do not deviate from the stack, the database choice, or the named packages without 
flagging it explicitly and explaining why.

---

## Project Context

This is a Laravel application combining:
- Twill (Area 17) as the CMS layer
- Filament PHP as the CRM admin panel
- PostgreSQL as the database (required — not MySQL)
- The Spatie package ecosystem for permissions, activity logging, media, and custom fields
- Stripe + Laravel Cashier for payments
- Pest PHP for testing
- Laravel Forge + Envoyer for deployment (not your concern yet — just don't fight them)

One installation per client. No multi-tenancy. No WordPress dependency.
MIT licensed. AI-assisted development is declared in the README.

---

## Your First Task

Set up the initial project scaffold. Specifically:

### 1. README.md
Create a README.md in the project root containing:
- Project name and one-paragraph description
- A transparency notice stating the project is being developed with agentic AI assistance
- License declaration (MIT)
- Sections (initially mostly empty): Requirements, Installation, Configuration, 
  Development, Testing, Deployment, Contributing
- A link placeholder for full documentation

### 2. composer.json
Create a composer.json that declares:
- Project name, description, license (MIT), type (project)
- PHP requirement: ^8.2
- The following require dependencies (use current stable versions, 
  not bleeding edge, not abandoned):
  - laravel/framework
  - area17/twill
  - filament/filament
  - laravel/cashier (Stripe)
  - spatie/laravel-permission
  - spatie/laravel-activitylog
  - spatie/laravel-medialibrary
  - spatie/laravel-schemaless-attributes
  - spatie/laravel-sluggable
  - laravel/horizon
  - predis/predis
- The following require-dev dependencies:
  - pestphp/pest
  - pestphp/pest-plugin-laravel
  - laravel/pint (code style)
  - barryvdh/laravel-debugbar
- Standard Laravel autoloading configuration
- Standard Laravel scripts (post-autoload-dump etc.)

### 3. Folder Structure
Scaffold the following directory structure with .gitkeep files 
where needed to preserve empty directories. 
Follow Laravel conventions exactly — do not invent structure.

```
/app
  /Console
  /Exceptions
  /Http
    /Controllers
      /Admin        ← Filament overrides and custom admin controllers
      /Api          ← API controllers (future use)
      /Portal       ← Member portal controllers
    /Middleware
    /Requests
  /Models           ← All Eloquent models live here, flat, no subdirectories yet
  /Policies
  /Providers
  /Services         ← Business logic layer, one service class per domain
    /Grants
    /Mailchimp
    /QuickBooks
    /Stripe
  /Filament         ← Filament resources, pages, and widgets
    /Resources
    /Pages
    /Widgets
/bootstrap
/config
/database
  /factories
  /migrations
  /seeders
/docs               ← Project documentation, markdown files
  /decisions        ← One markdown file per major architectural decision (see below)
  /schema           ← Database schema documentation, kept current as migrations are written
/public
/resources
  /css
  /js
  /views
    /admin          ← Filament/admin blade overrides
    /portal         ← Member portal views
    /twill          ← Twill view overrides
    /emails
    /components
/routes
  api.php
  web.php
  channels.php
  console.php
/storage
/tests
  /Feature
  /Unit
/vendor             ← gitignored
```

### 4. Core Documentation Files

Create the following files in /docs:

**/docs/ARCHITECTURE.md**
Paste in the full architecture document provided to you 
(included at the end of this prompt). This is the source of truth.

**/docs/schema/README.md**
Create a schema documentation index with this content:
- Header: "Database Schema Documentation"  
- Note that this document is maintained alongside migrations
- Note that PostgreSQL is required
- A table with columns: Table | Description | Migration File | Last Updated
- Initially empty rows — populated as migrations are written
- A section called "Conventions" noting: 
  - All tables use snake_case
  - All primary keys are UUIDs (not auto-increment integers)
  - All models use soft deletes unless explicitly noted otherwise
  - created_at and updated_at on every table
  - deleted_at on every table using soft deletes

**/docs/decisions/**
Create one file per decision already logged in the architecture document.
Name them 001-postgres-over-mysql.md, 002-custom-fields-jsonb.md, etc.
Each file should have: 
- Title
- Date (March 2026)
- Status (Decided)
- Context (one paragraph)
- Decision (one sentence)
- Rationale (bullet points from the architecture doc)
- Consequences (brief)

### 5. .gitignore
Standard Laravel .gitignore plus:
- .env
- /vendor
- /node_modules
- .DS_Store
- *.log
- /storage/app/public
- /storage/logs

### 6. .env.example
Standard Laravel .env.example with added sections and placeholder keys for:
- APP (standard)
- DATABASE (PostgreSQL — DB_CONNECTION=pgsql, standard PG vars)
- REDIS (standard)
- STRIPE (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET)
- MAILCHIMP (MAILCHIMP_API_KEY, MAILCHIMP_LIST_ID)
- QUICKBOOKS (QB_CLIENT_ID, QB_CLIENT_SECRET, QB_REDIRECT_URI, QB_ENVIRONMENT)
- MAIL (standard)
- QUEUE_CONNECTION=redis (not sync)
- BROADCAST_DRIVER=pusher (for Soketi compatibility)

---

## Conventions to Follow Throughout This Project

- PSR-12 coding standard enforced by Laravel Pint
- All primary keys are UUIDs. Use $model->getKeyType() = 'string' and $incrementing = false.
- Soft deletes on all models unless explicitly decided otherwise
- All business logic lives in /app/Services — controllers are thin
- Every migration gets a corresponding entry in /docs/schema/README.md
- Every significant architectural decision gets a file in /docs/decisions/
- No raw SQL — Eloquent ORM only, unless there is a documented performance reason
- Feature tests for every user-facing action before the feature is considered done
- Full unit test coverage on all financial and grant logic — non-negotiable
- Commit messages follow Conventional Commits format (feat:, fix:, docs:, test:, chore:)

---

## What NOT to Do

- Do not install or suggest MySQL. PostgreSQL only.
- Do not add WordPress, any WordPress packages, or any WordPress compatibility layer.
- Do not add any LLM or AI integration packages.
- Do not use auto-incrementing integer primary keys.
- Do not put business logic in controllers.
- Do not use bleeding-edge package versions that have not reached a stable release.
- Do not create database migrations without updating /docs/schema/README.md.
- Do not make architectural decisions that conflict with the architecture document 
  without flagging them explicitly first.

---

## When You Are Done With This Task

Confirm the following:
1. composer.json is valid JSON and all named packages exist on Packagist
2. All directories exist or have .gitkeep files
3. All documentation files are created with correct content
4. .env.example contains all required keys
5. README.md contains the AI transparency notice

Then stop and wait for the next instruction. 
Do not begin writing models, migrations, or application code yet.

---

## Architecture Document

# Nonprofit Platform — Technical Overview
*Working Document — Nothing built yet. March 2026.*

> This document and the software it describes are being developed with agentic AI assistance. This will be declared in the project README.

---

## Status Key
- ✅ Decided
- 🔄 In Progress
- ⬜ Pending

---

## Architecture ✅

One installation per client. One server, one database, one codebase instance. No multi-tenancy. Each client is fully isolated by infrastructure, not by application logic. Clean data separation, simple deployment, easy offboarding.

---

## Stack ✅

| Layer | Choice |
|-------|--------|
| Framework | Laravel |
| CMS | Twill (Laravel-native, Area 17) |
| Admin Panel | Filament PHP (Livewire-based) |
| Member Auth | Laravel Breeze or Jetstream |
| Database | PostgreSQL |
| Caching | Redis |
| Real-time | Laravel Echo + Soketi |
| Job Queue | Laravel Horizon |
| Payments | Stripe + Laravel Cashier |
| Email Marketing | Mailchimp API (outbound only) |
| Accounting | QuickBooks API (outbound only) |
| Custom Fields | Spatie Laravel Schemaless Attributes |
| Activity Logging | Spatie Laravel Activity Log |
| Media | Spatie Laravel Media Library |
| Permissions | Spatie Laravel Permission |
| Performance Monitoring | Spatie Slow Query Log + DB::listen() |
| Testing | Pest PHP |
| Deployment | Laravel Forge + Envoyer |

---

## Application Structure ✅

**Twill** — content layer. Pages, posts, navigation, media. Content editors live here.

**Filament** — CRM admin layer. Contacts, members, donations, grants, events, reporting. Admin users live here.

**Member portal** — Laravel-rendered, separately authenticated. Members see gated content and their own records. They never enter the admin panel.

**Integration boundaries are narrow and explicit.** Events and forms span both Twill and Filament. That boundary is documented per module as it is built.

---

## User Tiers ✅

Three layers on one person record:

| Tier | Access |
|------|--------|
| Contact | No login. A person record. |
| Member | Portal only. Gated pages and forms. |
| Admin User | Backend. Tiered privileges via RBAC. |

---

## Permissions ✅

Spatie Laravel Permission. Roles stack — a user can hold multiple simultaneously. Sensitive domains use action-level permissions (`payments.view`, `payments.refund`). Lower-stakes domains use module-level (`cms.*`).

Default roles: Super Admin, CRM Manager, CMS Editor, Finance Manager, Events Manager, Read Only. All customizable per installation.

---

## Core Entities 🔄

### People & Organizations
Contact, Household, Family, Organization, Relationship, Volunteer

### Membership
Membership (with full history), Membership Tier, Role

### Financial
Transaction (Stripe mirror), Donation, Grant, Grant Fund/Budget Line, Grant Allocation, Grant Report, Funder, Invoice, Subscription, Refund, Tax Receipt, Fiscal Year/Fund

### Events
Event, Event Registration, Event Ticket/Tier, Waitlist Entry, Waiver

### Commerce
Product, Product Category, Order, Order Line Item

### Content (Twill)
Page, Post, Navigation Menu, Media/Asset, Form, Form Submission

### Infrastructure
Address (multi, labeled, defaults to single), Tag, Note, Audit Log, Custom Field Definition, Attachment, System Activity Event

---

## Key Relationships 🔄

- Contact → Membership: one active, many historical. Membership is a record, not a flag.
- Contact ↔ Contact: through a named Relationship entity.
- Transaction → Grant: many-to-many through Grant Allocation. One transaction can split across multiple grants. Required for audit compliance.
- Event Registration: always an individual Contact. Org affiliation stored on the Contact record.

---

## Custom Fields ✅

JSONB column (`custom_data`) on core entity tables via Spatie Schemaless Attributes. A `custom_field_definitions` table drives the admin UI. Fields are defined at setup time and indexes are created then. Post-launch additions trigger a transparent index build shown to the admin in plain language. All custom fields are filterable. PostgreSQL is required — MySQL is not supported.

---

## Integrations ✅

**Stripe** is the source of financial truth. Local tables mirror Stripe state for reporting and QuickBooks sync. Webhooks keep the mirror current.

**QuickBooks** receives outbound sync from the local transaction mirror. It does not write back. Category mappings are configuration, not schema.

**Mailchimp** receives outbound contact lists resolved and filtered in the platform. Custom fields never leave the platform directly.

---

## Grant Module ✅

Grant financial structure is stable. Core mechanics will not require structural revision.

- Transaction splitting across grants supported from day one
- Indirect cost rate is per-grant configuration
- Reimbursement-based and advance-based grant types supported via status flags
- Funder is a first-class entity

---

## System Transparency ✅

All async operations — database queries, external API calls, background jobs — broadcast their state in real time via Laravel Echo to a persistent activity panel visible in the admin layout. The user always knows what the system is doing.

Spatie Activity Log captures meaningful events only: writes, system operations, data leaving the system, failures, slow queries. Not page views, not reads. 90-day live retention with scheduled pruning.

Two surfaces: a live session feed in the persistent panel, and a searchable full audit log page for admins.

---

## Testing ✅

Pest PHP. Feature tests for all user-facing actions. Full unit test coverage on all financial and grant logic — this ships with tests or it does not ship. Integration tests for all external service boundaries.

A seeded demo environment stands up a complete installation for sales and onboarding.

---

## Design Principles ✅

1. Default to simplicity and performance.
2. Default to intercompatibility where it conflicts with simplicity.
3. When they conflict, decide explicitly and log the decision.
4. Always communicate system state to the user in plain language.
5. All async operations broadcast state in real time.
6. Extensibility is a first-class concern from day one.
7. The system advocates for itself and for the user.
8. No LLM integration in v1. Revisit at product maturity.

---

## Rejected Approaches

| Approach | Reason |
|----------|--------|
| WordPress as foundation | Security surface area, overhead, architectural conflict with Laravel |
| Laravel as WordPress plugin | Two full stacks fighting each other |
| Headless CMS + SPA | Adds integration surface, worse editor experience for target users |
| Multi-tenancy shared database | Risk of data bleed; per-client server is cleaner |
| EAV for custom fields | Query performance degrades at scale |
| MySQL | No viable JSONB support |
| LLM help assistant | User sentiment; easy to add later |

---

## License ✅

MIT. Maximum openness. Managed hosting is the business, not the software.


```