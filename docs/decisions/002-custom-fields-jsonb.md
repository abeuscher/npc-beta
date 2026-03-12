# 002 — Custom Fields via JSONB (Not EAV)

**Date:** March 2026
**Status:** Decided

---

## Context

Nonprofits require the ability to define custom fields on core entities (contacts, donations, events, etc.) to capture data unique to their organization. Two standard approaches exist: Entity-Attribute-Value (EAV) tables, and JSONB columns. A third approach — adding nullable columns per field — was not considered viable at scale.

## Decision

Custom fields are stored in a `custom_data` JSONB column on each core entity table, managed via Spatie Laravel Schemaless Attributes. A `custom_field_definitions` table drives the admin UI for defining fields.

## Rationale

- EAV query performance degrades at scale. Every filtered query on a custom field becomes a multi-join operation that is difficult to optimize
- JSONB columns in PostgreSQL support GIN indexes. Custom field values are filterable with performance comparable to native columns
- Spatie Laravel Schemaless Attributes provides a clean, tested interface for reading and writing JSONB data through Eloquent
- Indexes are created at field definition time, with new additions triggering a transparent background index build surfaced to the admin user
- The approach keeps the schema clean and avoids per-field migrations for every client customization

## Consequences

- PostgreSQL is a hard requirement (this decision and Decision 001 are interdependent)
- Custom fields are not arbitrary at runtime — they must be defined through the admin UI, which creates the index. Unindexed ad-hoc JSONB queries are not permitted
- Custom field values must never be synced to external services (Mailchimp, QuickBooks) directly — they stay within the platform
