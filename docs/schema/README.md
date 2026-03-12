# Database Schema Documentation

This document is maintained alongside migrations. Every time a migration is written, the table it creates or modifies must be added or updated here. This is a non-negotiable project convention.

**PostgreSQL is required.** MySQL is not supported. The custom fields implementation relies on JSONB columns, which have no viable equivalent in MySQL.

---

## Table Index

| Table | Description | Migration File | Last Updated |
|-------|-------------|----------------|--------------|
| _(none yet — populated as migrations are written)_ | | | |

---

## Conventions

- All tables use `snake_case` naming
- All primary keys are UUIDs (not auto-incrementing integers). Models use `$keyType = 'string'` and `$incrementing = false`
- All models use soft deletes (`deleted_at`) unless explicitly decided otherwise and documented in `/docs/decisions/`
- Every table has `created_at` and `updated_at` timestamps
- Every table using soft deletes has a `deleted_at` timestamp
- Custom fields are stored in a `custom_data` JSONB column via Spatie Schemaless Attributes
