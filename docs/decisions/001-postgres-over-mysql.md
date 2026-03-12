# 001 — PostgreSQL Over MySQL

**Date:** March 2026
**Status:** Decided

---

## Context

The platform requires a relational database. The two primary candidates in the Laravel ecosystem are PostgreSQL and MySQL. The custom fields feature — a first-class requirement for nonprofit CRM flexibility — requires native JSONB column support for filterable, indexable arbitrary field storage at scale.

## Decision

PostgreSQL is the required database engine. MySQL is not supported and will not be supported.

## Rationale

- MySQL has no viable JSONB equivalent. Its JSON column type lacks the indexing and query performance characteristics required by this platform's custom fields design
- PostgreSQL JSONB supports GIN indexes, enabling fast filtering of arbitrary custom field values even at scale
- Spatie Laravel Schemaless Attributes is designed to work with JSONB and performs best on PostgreSQL
- Laravel Forge supports PostgreSQL natively — no deployment friction
- PostgreSQL is more standards-compliant and has stronger support for complex queries needed in financial and grant reporting

## Consequences

- All developers and deployment environments must use PostgreSQL. There is no MySQL fallback path
- SQLite may be used for local development only where JSONB features are not being exercised, but never in staging or production
- This constraint must be communicated clearly in the README and installation documentation
