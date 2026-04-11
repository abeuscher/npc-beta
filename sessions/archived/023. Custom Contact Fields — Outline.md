# Session 023 Outline — Custom Contact Fields

> **Depends on:** Session 018/019 (import wizard); can run parallel to Session 022
> **Unlocks:** No data is silently dropped on import; contact records can carry
>   source-system-specific fields without a schema change

---

## Goal

Allow admins to define custom fields for the Contact model. During import, columns with no
standard mapping get a "Create as custom field" option instead of being silently dropped.
Custom fields appear on the contact detail and edit forms and are included in exports.

---

## Architecture Decision: JSONB vs Separate Table

**Decision to confirm at session start.** Two options:

| | JSONB on `contacts` | Separate `contact_field_values` table |
|-|---------------------|---------------------------------------|
| Simple reads | ✓ | Requires join |
| Queryable/filterable | Partial (JSON operators) | ✓ |
| Schema validation | None at DB level | Possible via constraints |
| Migration cost | Zero | One migration |

**Recommendation for v1:** JSONB column (`custom_fields`) on `contacts`. Filtering/querying
of custom fields is a future enhancement — decide scope at session start. If filtering is
in scope, discuss the table approach.

---

## Custom Field Definitions

A separate model/table to define available custom fields:

### `contact_custom_field_defs`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `handle` | string unique | Machine key, e.g. `wild_apricot_id` |
| `label` | string | Display name, e.g. "Wild Apricot ID" |
| `field_type` | string | `text`, `number`, `date`, `boolean`, `select` |
| `options` | json nullable | For `select` type: array of option strings |
| `sort_order` | int | Order in forms/exports |
| timestamps | | |

**V1 field types in scope:** `text` is required. `number`, `date`, `boolean`, `select`
are desirable — decide at session start whether they're v1 or deferred.

---

## Import Integration

### Map Columns step

For each source column that has no standard field mapping:

- Show a third option alongside "Ignore" and the field list: **"Create as custom field"**
- When selected, reveal a small inline form: Label (required, pre-filled from column name),
  Handle (auto-generated from label, editable), Field Type (default: text)
- On import run: create the `ContactCustomFieldDef` record if it doesn't exist (match by
  handle), then write the value into `contacts.custom_fields[handle]`

---

## Contact Forms

### Detail / Edit view

- After the standard fields section, add a "Custom Fields" section
- Fields rendered dynamically from `ContactCustomFieldDef` definitions
- Values read/written from `contacts.custom_fields` JSONB
- `select` fields render as a dropdown; `boolean` as a toggle; others as text/date inputs

### Contact field management UI

A Filament resource: `ContactCustomFieldDefResource`

- **List:** Label, handle, type, sort order
- **Create / Edit / Delete:** Full CRUD
- **Reorder:** Drag-to-reorder or sort_order field
- **Navigation:** CRM group or Settings group — decide at session start
  **Recommendation:** CRM group, since it's data configuration not system configuration

---

## Export

- Custom field columns appended after standard columns
- Column headers use the field `label`
- Empty values export as blank (not null/false)

---

## Filtering (decide scope at session start)

Querying custom fields via JSONB requires PostgreSQL JSON operators. If the Contacts list
filter panel should support custom fields, this adds moderate complexity. **Recommendation:**
defer filtering to a follow-up; v1 is storage + display + export only.

---

## Open Questions (answer at session start)

1. **JSONB vs table** — confirm above recommendation or choose table approach.
2. **V1 field types** — text only, or include number/date/boolean/select?
3. **Custom field filtering** — in or out of scope for this session?
4. **Custom field definitions scope** — global (one set for the whole install) or
   per-organisation? Global is correct for a single-tenant install.
5. **Handle collisions** — if import creates a field with handle `foo` and one already
   exists, should it reuse the existing definition or error? **Recommendation:** reuse.

---

## Files Expected to Change

| File | Action |
|------|--------|
| `database/migrations/xxxx_add_custom_fields_to_contacts.php` | Add `custom_fields` JSONB |
| `database/migrations/xxxx_create_contact_custom_field_defs.php` | New table |
| `app/Models/ContactCustomFieldDef.php` | New |
| `app/Models/Contact.php` | Cast `custom_fields` as array |
| `app/Filament/Resources/ContactResource.php` | Add custom fields section to form |
| `app/Filament/Resources/ContactCustomFieldDefResource.php` | New CRUD resource |
| `app/Livewire/Import/MapColumnsStep.php` | Add "Create as custom field" option |
| `app/Jobs/ImportJob.php` | Write custom field values on import |
| `app/Exports/ContactExporter.php` | Append custom field columns |

---

## Tests

- Unit: `Contact` correctly reads/writes JSONB `custom_fields`
- Feature: import with unmapped column → creates field def → stores value on contact
- Feature: contact edit form displays and saves custom field values
- Feature: contact export includes custom field columns
- Feature: `ContactCustomFieldDefResource` CRUD
