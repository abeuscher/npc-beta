# Session 006 Log — Collections

**Date:** March 2026
**Status:** Complete

---

## What Was Built

### Data Layer

- `database/migrations/2026_03_13_200001_create_collections_table.php` — UUID PK, name, handle (unique), description, fields (JSONB), source_type (string, default `custom`), is_public, is_active, timestamps, soft deletes
- `database/migrations/2026_03_13_200002_create_collection_items_table.php` — UUID PK, collection_id (FK cascade), data (JSONB), sort_order, is_published, timestamps, soft deletes
- `database/migrations/2026_03_13_200003_fix_system_collections_initial_seed.php` — data migration: removed donations system collection, set events is_public = true
- `database/migrations/2026_03_13_200004_rename_collections_slug_to_handle.php` — renamed `slug` column to `handle` on collections table
- `app/Models/Collection.php` — HasUuids, SoftDeletes, HasSlug (Spatie, saves to `handle`), `getFormSchema()`, `scopePublic()`, `isSystemCollection()`, `RESERVED_HANDLES`; security and no-routing comment block
- `app/Models/CollectionItem.php` — HasUuids, SoftDeletes, belongs to Collection; data cast as array

### Filament Resources

- `app/Filament/Resources/CollectionResource.php` — Content group, sort 4; schema builder form with nested Repeater for fields and options; table with source_type badge, fields_count, items_count; Edit/Delete hidden for system collections; row click disabled for system collections via `recordUrl()`
- `app/Filament/Resources/CollectionResource/Pages/` — ListCollections, CreateCollection (forces source_type = custom), EditCollection
- `app/Filament/Resources/CollectionItemResource.php` — navigation hidden; dynamic form schema resolved from parent Collection; summary column shows first text/textarea field value
- `app/Filament/Resources/CollectionItemResource/Pages/` — List, Create, Edit
- `app/Filament/Resources/CollectionResource/RelationManagers/CollectionItemsRelationManager.php` — reorderable by sort_order; read-only for system collections; Create/Edit/Delete actions; bulk Delete

### Seeder

- `events_prefix` site setting added (default: `events`) alongside existing `blog_prefix`
- `seedSystemCollections()` — seeds Blog Posts and Events system collections in all environments
- Board Members demo collection + 3 items seeded in local environment

### Tests

- `tests/Feature/CollectionTest.php` — 7 tests: schema creation, handle auto-generation, getFormSchema() for text and select fields, required field enforcement, isSystemCollection()
- `tests/Feature/CollectionItemTest.php` — 3 tests: JSONB storage, array cast round-trip, soft deletes
- `tests/Feature/CollectionPublicFlagTest.php` — 3 tests: scopePublic() excludes private, returns public+active, excludes public+inactive

All 45 tests passing (13 new).

### Documentation

- `docs/information-architecture.md` — Collection and CollectionItem added to Content domain table; Collections added to navigation group diagram
- `docs/decisions/012-collections-as-public-data-surface.md` — 6 decisions: JSONB schema, source_type system, is_public security gate, money siloing principle, query filtering contract (session 007), widget type contract (session 007)

---

## Key Design Decisions Made This Session

### `source_type` — not a slug reservation scheme

Pre-session discussion established that Events and Blog Posts are structurally different from generic collections. A `source_type` column (`custom`, `blog_posts`, `events`) distinguishes JSONB-backed collections from system sources backed by real Eloquent models. The widget system gets a uniform collection-shaped config record for all sources while data backends stay separate.

### Collections have no routing relationship — `handle` not `slug`

Early in implementation the identifier was named `slug`, which implies URL routing. Collections are not routable. They are data sources referenced by handle in widgets. Renamed to `handle` throughout, with helper text updated accordingly. `RESERVED_HANDLES` replaces `RESERVED_SLUGS`. The only reserved handles are `blog_posts` and `events` — reserved because they are system source type identifiers, not for URL collision reasons. `pages` was removed from the reserved list since the routing concern no longer applies.

### Donations removed from the system collections concept

Initial design included a `donations` source type and seeded system collection. On review: donations are private financial transactions with no meaningful public data shape. There is no `donations` collection, no `donations` source_type, and no reserved handle for it. If an admin wants to display fundraising campaign information publicly, they create a generic custom collection with display-only fields.

### Events are public by default

Events surface genuinely public data: title, dates, location, description, registration URL, and derived singleton facts (tickets remaining, capacity status). `is_public: true` in the seed. The event ID is the anchor for CRM registrant and payment records — those never surface through the collection.

### Money siloing principle

Payment data flows CMS → Payment → CRM, never back to CMS. The CMS has no query path to financial records, donor history, or registrant data. This is enforced by architecture, not by runtime checks.

### System collections are display-only in the admin

System collection rows in the Filament table have no click target (`recordUrl` returns null), no Edit action, and no Delete action. The only admin interaction available is toggling `is_public` and `is_active` via inline table actions (if added in future). For now they are fully read-only from the UI perspective.

---

## What Session 007 Will Consume

- `Collection::scopePublic()` — entry point for all widget data queries
- `Collection::getFormSchema()` — pattern for the widget type contract system
- `source_type` — determines data backend resolution (`custom` → `collection_items`; `blog_posts` → `Post` model; `events` → `Event` model)
- `fields` JSONB structure (key, type, required) — sufficient to generate widget type definitions without modification
- `is_public` + `is_active` — combined gate for public access
- `handle` — the identifier a widget uses to specify its data source
