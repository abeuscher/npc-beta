# ADR 012 — Collections as the Public Data Surface

**Status:** Accepted
**Session:** 006 (revised post-session)
**Date:** March 2026

---

## Context

The public-facing website needs to display structured, repeatable content: Board Members, Sponsors, FAQs, Staff Profiles, Program Descriptions, and similar lists. These are not blog posts or pages — they are typed, schematised data buckets that page components will query.

At the same time, two other entities — Events and Blog Posts — must also be accessible to public-facing components (event listings, blog rolls), but they are backed by real Eloquent models rather than the generic JSONB item store.

**The governing principle for what belongs in a collection: a collection is public data.** When a collection overlaps with other systems (CRM, payments), only the publicly displayable subset is exposed. Private financial records, donor identities, and registrant details are never part of any collection regardless of settings.

---

## Decision 1: Generic Collections via JSONB Schema

The `collections` table stores a JSONB `fields` array that defines the schema: key, label, type, required, helpText, and (for select fields) options. The `collection_items` table stores item data as a JSONB `data` object keyed by field key.

This matches the WordPress CPT + ACF model and Contentful Content Types pattern. Schema is defined by the admin in the Filament UI with no deployment required.

`Collection::getFormSchema()` converts the fields array into a live Filament form schema, making the item edit form fully dynamic at runtime.

**Why JSONB rather than a traditional EAV table?** See ADR 002.

---

## Decision 2: `source_type` — Generic vs. System Collections

Not all data sources that a widget might query are generic JSONB collections. Blog Posts and Events are backed by real Eloquent models with relational structure. They should not be stuffed into `collection_items`.

The `source_type` column encodes the data backend for a given collection record:

| source_type  | Data backend                                | is_public default |
|--------------|---------------------------------------------|-------------------|
| `custom`     | `collection_items` JSONB (the generic case) | false             |
| `blog_posts` | `Post` model                                | true              |
| `events`     | `Event` model (future session)              | true              |

**Donations are not a system collection.** Donation records are private financial transactions between a person and the organization. There is no meaningful public-facing collection of donation records. If an admin wants to display fundraising campaign progress publicly, they create a generic custom collection (e.g., "Fundraising Campaigns") with display-only fields like name, goal, and description. This is generic content data, not a system collection wired to the finance system.

System collections (`blog_posts`, `events`) are seeded, not user-creatable. Their `fields` array describes the shape of data the widget system will receive — it is a type contract, not a store. They appear in the Collections admin list so visibility (`is_public`) can be managed uniformly.

Users cannot create custom collections with reserved slugs (`blog_posts`, `events`, `pages`). Validation enforces this in `CollectionResource`.

---

## Decision 3: `is_public` as the Security Gate

The CRM data (Contacts, Memberships, Donations, Organizations) is architecturally excluded from the collection system. It cannot be surfaced publicly through this mechanism regardless of settings.

The `is_public` flag, combined with `source_type`, forms the access boundary:

- `is_public = false` — collection is never queryable from public components
- `is_public = true` + `is_active = true` — `Collection::scopePublic()` returns it; session 007 will use this scope as the entry point for all widget data queries
- For system collections, only the public-facing data shape is exposed; underlying relational records (registrants, transactions) are never reachable through this path

---

## Decision 4: Money Siloing

Events touch the payment system (ticketing) and CRM (registrants). The governing rule:

**Payment data is strictly one-directional and never surfaces in the CMS.**

```
CMS → Payment system   (sends intent: "process this transaction")
Payment system → CRM   (confirms result: "transaction recorded")
CRM = record of truth  (all financial and registrant history)
CMS never queries financial records
```

For the Events collection, only genuinely public data is exposed:

- Title, dates, location, description, registration URL
- Derived singleton facts that are safe to make public: tickets remaining, registration open/closed status, capacity
- **Never exposed**: registrant names, registration records, revenue, ticket holder details

The event ID is the join key. CRM and payment systems use it to track registrants and transactions. The CMS uses it for display only — reading forward, never writing back.

Donation financial records have no collection representation at all. The collection system has no `source_type` for donations and no reserved slug.

---

## Decision 5: Query Filtering (Forward-Looking — Session 007)

Every widget will need to express a filtered query: "give me items from source X where field Y = Z, limit N, ordered by W." Examples:

- A blog roll widget: latest 3 published posts
- A careers widget: collection items where `job_open = true`
- An events widget: events starting after today, ordered by `starts_at`

The JSONB structure in `collection_items.data` supports field-level filtering via PostgreSQL JSONB operators. The `Collection::scopePublic()` scope is the foundation; session 007 will extend it to accept filter parameters.

System source types (`blog_posts`, `events`) will expose Eloquent scopes that accept the same filter shape, giving the widget system a uniform interface regardless of underlying data source.

---

## Decision 6: Widget Type Contract (Deferred — Session 007)

The `fields` JSONB schema (each field: key, type, required) is rich enough to generate a JSON Schema type definition describing the shape of data a widget will receive. `Collection::getTypeDefinition()` is flagged for session 007 to implement. The data structure built in session 006 is sufficient to drive it without modification.

---

## Consequences

- Admins can create arbitrary typed content buckets with no deployment
- The widget system (session 007) has a single `Collection::scopePublic()` entry point for all data queries, generic or system
- Events are a public collection by default, surfacing only display-safe fields; CRM and payment data remains isolated
- Donations are not a collection concern — financial records have no public data shape in this system
- Financial data is structurally isolated from the CMS with no special runtime checks needed — the architecture prevents it by design
- Reserved slugs (`blog_posts`, `events`, `pages`) must never be used for user-created custom collections
