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
