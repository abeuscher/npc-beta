# 006 — UUID Primary Keys on All Tables

**Date:** March 2026
**Status:** Decided

---

## Context

Laravel defaults to auto-incrementing integer primary keys. For this platform, primary keys appear in URLs, API responses, webhook payloads, and external system references. Sequential integers expose record counts, enable enumeration attacks, and create friction when merging data from multiple sources or migrating records.

## Decision

All primary keys are UUIDs. No table uses auto-incrementing integer primary keys. Models are configured with `$keyType = 'string'` and `$incrementing = false`.

## Rationale

- UUIDs are non-enumerable — a contact's ID in a URL does not reveal how many contacts exist or allow sequential guessing
- UUIDs are safe to expose in webhooks, API responses, and external system references without information leakage
- UUIDs enable record creation outside the database (e.g., in background jobs or external systems) without requiring a database round-trip to get the assigned ID
- UUIDs are collision-resistant across data migrations and imports
- PostgreSQL handles UUID storage efficiently with native `uuid` column type

## Consequences

- All foreign key columns are also UUID strings, not integers
- Migrations must use `$table->uuid('id')->primary()` and `$table->foreignUuid(...)` consistently
- Eloquent models must declare `protected $keyType = 'string'` and `public $incrementing = false`
- Join performance on UUID foreign keys is slightly lower than integer joins, but acceptable at the scale this platform targets
