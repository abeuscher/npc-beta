# Session 021 Outline — Saved Import Field Maps

> **SUPERSEDED** — This content was renumbered to **session-022-outline.md** when
> Session 021 was reserved for the Trix toolbar bug fix. See `session-021-prompt.md`
> for the active Session 021 scope. See `session-022-outline.md` for this content.

> **Depends on:** Session 019 (import wizard complete)
> **Unlocks:** Self-service client onboarding — map once, reuse forever

---

## Goal

Replace the hard-coded presets in `FieldMapper.php` with a user-driven `FieldMapProfile`
system. An installer maps the source CSV columns once, saves the profile with a label, and
every subsequent import auto-detects and pre-fills the mapping on an exact header-set match.

---

## Data Model

### `field_map_profiles`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `label` | string | Human-readable name, e.g. "Wild Apricot Contacts" |
| `model_type` | string | `contacts` for now; extensible |
| `headers` | json | Sorted, normalised header array for matching |
| `mapping` | json | `{"First Name": "first_name", …}` |
| `created_by` | FK → users nullable | Audit only |
| timestamps | | |

**Normalisation:** trim + lowercase each header before storing. Apply the same transform on
upload before comparing. Comparison is set-equality (order-independent).

---

## Wizard Changes

### Upload step — profile detection

After reading the uploaded file's headers:

1. Normalise the header set.
2. Query all `FieldMapProfile` records where `model_type = 'contacts'`.
3. **Exact match found:** show a success notice ("Mapping profile 'X' detected — applied
   automatically."), pre-fill the mapping, and advance directly to the Review step. The user
   can click Back to adjust the mapping if needed.
4. **No match:** proceed normally to the Map Columns step.

_Start with exact-match only. Do not implement partial/fuzzy matching in this session._

### Map Columns step — save profile button

- Add a **"Save as profile…"** secondary action at the bottom of the step.
- Opens a small inline form: a single required `label` text field + Save.
- Saves a new `FieldMapProfile` with the normalised headers and current mapping state.
- Shows a success notice. Does not advance the wizard.
- If the same label already exists: create a duplicate (simpler). User can delete the old
  one from the management UI.

---

## Profile Management UI

A minimal Filament resource: `FieldMapProfileResource`

- **List columns:** Label, model type, header count, created by, created at
- **Actions:** Rename (edit label in-line or via edit page), Delete (with confirmation)
- **No create action** — profiles are created through the wizard only
- **Navigation:** `Tools` group alongside Import History

---

## Remove Hard-Coded Presets

Drop the Bloomerang / Wild Apricot / generic presets from `FieldMapper.php`. They were
approximations. The profile system is strictly better. No backwards-compatibility concern —
this is a fresh-install product with no external API consumers of `FieldMapper`.

---

## Open Questions (answer at session start)

1. Should the profile name be editable from the wizard confirmation notice, or only from
   the management UI? (Management UI only is simpler — start there.)
2. Nav group for `FieldMapProfileResource`: `Tools` or `Settings`?
   **Recommendation:** `Tools` — it's operational, not configuration.

---

## Files Expected to Change

| File | Action |
|------|--------|
| `database/migrations/xxxx_create_field_map_profiles_table.php` | New |
| `app/Models/FieldMapProfile.php` | New |
| `app/Filament/Resources/FieldMapProfileResource.php` | New |
| `app/Livewire/Import/UploadStep.php` | Add profile detection after header read |
| `app/Livewire/Import/MapColumnsStep.php` | Add Save Profile action |
| `app/Services/FieldMapper.php` | Remove hard-coded presets |

---

## Tests

- Unit: header normalisation and set-equality comparison
- Feature: exact-match profile detected on upload, mapping pre-filled, Review step shown
- Feature: saving a profile from Map Columns creates DB record with correct headers + mapping
- Feature: `FieldMapProfileResource` list and delete
