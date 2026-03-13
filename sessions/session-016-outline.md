# Session 016 Outline — Import/Export: Core

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the finalised Contact model (session 010), the CRM
> taxonomy, and what fields a canonical Contact record now has. The importer must map to the
> real schema, not a theoretical one. Also confirm what source systems clients are actually
> migrating from — this determines which presets to build.

---

## Goal

Give admins the ability to import Contact records from external systems via CSV upload with field mapping, and export core data types to CSV. This is the migration path for new clients and the audit path for existing data.

---

## Key Decisions to Make at Session Start

- **Import scope for this session**: Contacts only, or also Organizations and Donations? Contacts are the highest priority — decide whether to expand scope here or in session 017.
- **Field mapping UI**: How does the admin map source columns to destination fields? Options: a multi-step wizard (upload → preview → map → import), or a simpler fixed-template approach. The wizard is better UX but more work. Decide MVP approach.
- **Custom field creation**: If a source file has columns that don't map to any standard field, can the admin create a new contact field on the fly? This intersects with a potential custom fields system. Decide scope.
- **Duplicate handling**: What happens when an imported contact matches an existing record (by email)? Options: skip, update, create duplicate, ask. This is a critical decision — getting it wrong loses data.
- **Error handling**: Does the import stop on first error, or collect all errors and report at the end?
- **Queue**: Large imports should run in the background. Is the queue worker available?
- **Source system presets**: Which systems should have pre-built column maps? (e.g. Bloomerang, Salesforce, Mailchimp contacts export, generic CSV)

---

## Scope (draft — refine at session start)

**In:**
- CSV import for Contacts (and Organizations if scope allows)
- Multi-step import UI: upload → column preview → field mapping → dry run → confirm → import
- Duplicate detection by email with configurable strategy (skip / update / flag)
- Import result report: X imported, Y updated, Z skipped, errors listed
- CSV export for Contacts with selected fields
- At least one source system preset (generic CSV + one named system)
- Background job for large imports (queue-dependent)

**Out:**
- Import for financial data (Donations, Transactions) — assess for session 017
- XLSX support (CSV only for now)
- Real-time import progress (websockets/polling) — use simple job status page for now
- API-based sync (e.g. live Mailchimp → CRM sync)

---

## Rough Build List

- ImportJob: processes CSV, maps fields, creates/updates records
- FieldMapper: maps source column names to model attributes; supports presets
- ImportResult: value object tracking counts and errors
- Filament import wizard UI (multi-step form or custom Filament page)
- Export action on ContactResource: download CSV with selected columns
- Source preset: generic CSV, one named system
- Tests: duplicate handling, field mapping, error collection

---

## Open Questions at Planning Time

- What source systems are clients actually migrating from? This determines preset priority.
- Is there a need to import Tags as part of a Contact import?
- Should exported CSVs be available to download later (stored), or generated on-demand?

---

## What This Unlocks

- New clients can get data in from day one
- Data audits and backups are possible
- Session 017 can extend to other data types and more presets
