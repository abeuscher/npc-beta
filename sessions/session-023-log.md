# Session 023 Log — Polymorphic Custom Fields

**Date:** 2026-03-16
**Branch:** ft-session-023

---

## What Was Built

A polymorphic custom field system that allows admins to define arbitrary additional fields for Contacts, Events, and Pages. All three models store values in a `custom_fields` JSONB column. Definitions live in a single `custom_field_defs` table keyed by `model_type` + `handle`.

---

## Architecture Decisions

- **JSONB confirmed** over separate `contact_field_values` table. Fast for read-heavy display; filtering deferred to a future session (will add GIN index if needed before considering a table migration).
- **Polymorphic from day one** — `custom_field_defs.model_type` (`contact`, `event`, `page`) avoids building the same system twice when Events and Pages need fields.
- **Field types in scope:** `text`, `number`, `date`, `boolean`, `select`.
- **Custom field filtering** deferred — v1 is storage, display, and export only.
- **Handle collisions on import:** reuse existing definition silently; surface the action (created vs. reused) in the import completion screen.
- **Navigation:** Custom Fields under Tools, sort=3 (after Widget Manager=1, Collection Manager=2).

---

## Files Changed

### Migrations (5)
| File | Purpose |
|------|---------|
| `2026_03_15_230001_add_custom_fields_to_contacts.php` | `custom_fields` JSONB on contacts |
| `2026_03_15_230002_add_custom_fields_to_events.php` | `custom_fields` JSONB on events |
| `2026_03_15_230003_add_custom_fields_to_pages.php` | `custom_fields` JSONB on pages |
| `2026_03_15_230004_create_custom_field_defs_table.php` | `custom_field_defs` table (id, model_type, handle, label, field_type, options, sort_order) with unique(model_type, handle) |
| `2026_03_15_230005_add_custom_field_columns_to_import_logs.php` | `custom_field_map` + `custom_field_log` JSONB on import_logs |

### New Files
- `app/Models/CustomFieldDef.php` — model with `forModel()` scope and `toFilamentFormComponent()` helper that returns the right Filament component per field type
- `app/Filament/Resources/CustomFieldDefResource.php` — CRUD resource (Tools, sort=3); handles all three model types; `select` type shows a repeater for options; handle auto-generated from label, locked on edit
- `app/Filament/Resources/CustomFieldDefResource/Pages/ListCustomFieldDefs.php`
- `app/Filament/Resources/CustomFieldDefResource/Pages/CreateCustomFieldDef.php`
- `app/Filament/Resources/CustomFieldDefResource/Pages/EditCustomFieldDef.php`
- `tests/Feature/CustomFieldTest.php`
- `tests/Feature/CustomFieldDefResourceTest.php`

### Modified Files
- `app/Models/Contact.php` — added `custom_fields` to fillable and casts (`array`)
- `app/Models/Event.php` — same
- `app/Models/Page.php` — same
- `app/Models/ImportLog.php` — added `custom_field_map` and `custom_field_log` to fillable/casts
- `app/Filament/Resources/ContactResource.php` — Custom Fields section appended to form; export updated to append custom field columns after standard columns
- `app/Filament/Resources/EventResource.php` — Custom Fields section appended to form (after the Split layout)
- `app/Filament/Resources/PageResource.php` — Custom Fields section appended to form (after SEO)
- `app/Filament/Pages/ImportContactsPage.php` — Map Columns step: `__custom__` sentinel option added to each column Select; `afterStateUpdated` auto-populates label (from column header) and handle (Str::slug of label) when selected; inline label/handle/type sub-form shown conditionally; `runImport()` separates standard map from `customFieldMap` and stores both on ImportLog
- `app/Filament/Pages/ImportProgressPage.php` — `mount()` calls `resolveCustomFieldDefs()` to create/reuse field defs before any rows are processed, stores log on ImportLog; `tick()` extracts custom field values per row and writes them to `contact->custom_fields` (merging on update)
- `resources/views/filament/pages/import-progress.blade.php` — Custom Field Definitions card shown after completion, listing each field as created or reused with handle

---

## Post-Session Fix

After manual testing: auto-populate label and handle when user selects "Create as custom field" — `afterStateUpdated` on the Select pushes the column header name and Str::slug handle into the inline fields immediately.

---

## Known Deferred Items

- **Boolean import value mapping:** CSV sources commonly export `yes`/`no` rather than `1`/`0`. Boolean custom fields will store the raw string. Future fix: add `import_value_map` JSON column to `custom_field_defs` and apply it at import time.
- **Custom field filtering** on the Contacts list — PostgreSQL JSON operators + GIN index, future session.

---

## Test Results

176 tests, 439 assertions, all passing.
