# Session 019 Prompt — Import/Export: Core

## Context

The CRM now has a solid Contact model, Event model with registrations, Donations, Memberships,
and supporting structures. New clients have no way to get existing data in — they must enter
every Contact by hand. This session builds the migration path: a CSV importer for Contacts
and a CSV exporter for Contacts, with enough architecture that session 020 can extend it to
other data types without rebuilding the core.

---

## Pre-Session Checks

Before writing any code, read:

- `app/Models/Contact.php` — confirm every fillable field and its exact name; the importer
  must map to real column names
- `database/migrations/*_create_contacts_table.php` — confirm nullable constraints,
  defaults, and any unique indexes (particularly on `email`)
- `app/Filament/Resources/ContactResource.php` — understand the existing resource structure
  so the import/export actions fit naturally into the UI
- `app/Models/EventRegistration.php` — note the `mailing_list_opt_in` boolean field added
  in session 018; relevant if the exporter is ever extended to registrations

---

## Decisions for This Session

The outline left several questions open. Answers are fixed here:

| Question | Decision |
|---|---|
| Import scope | Contacts only |
| Field mapping UI | Multi-step: upload → preview → map → confirm → import |
| Custom field creation on import | Out of scope |
| Duplicate handling | Configurable per import run: skip or update (no "create duplicate") |
| Error handling | Collect all row errors; report at the end. Do not stop on first error. |
| Queue | Use `dispatch()` for the import job; assume queue worker is available |
| Source presets | Generic CSV + Bloomerang contacts export |

---

## Goals for This Session

1. Build a multi-step Contact CSV importer accessible from the Contacts admin list
2. Build a CSV exporter for the Contacts list (selected columns, respects active filters)
3. Create an `ImportJob` that processes a CSV, maps columns, and creates/updates Contacts
4. Create a `FieldMapper` class that maps source column names to Contact attributes, with preset support
5. Create an `ImportResult` value object that tracks counts and errors
6. Seed two presets: generic CSV and Bloomerang
7. Store import history so admins can see what was imported and when
8. Write tests for duplicate handling, field mapping, and error collection

---

## Implementation Plan

### 1. Database: Import History

Create a migration for `import_logs` table:

```
id (uuid)
user_id (nullable uuid, foreign to users)
model_type (string) — e.g. 'contact'
filename (string)
row_count (integer)
imported_count (integer)
updated_count (integer)
skipped_count (integer)
error_count (integer)
errors (json, nullable) — array of {row, message}
duplicate_strategy (string) — 'skip' or 'update'
status (string) — 'pending', 'processing', 'complete', 'failed'
started_at (nullable timestamp)
completed_at (nullable timestamp)
timestamps
```

Create a corresponding `ImportLog` model with appropriate fillable fields and json cast
on `errors`.

---

### 2. FieldMapper Class

Create `app/Services/Import/FieldMapper.php`:

```php
class FieldMapper
{
    /**
     * Return the canonical destination field for a source column header.
     * Returns null if the column should be ignored.
     */
    public function map(string $sourceColumn, string $preset = 'generic'): ?string

    /**
     * Return all available preset names.
     */
    public static function presets(): array

    /**
     * Return the full mapping array for a preset.
     * Keys = source column headers (lowercase, trimmed), values = Contact field names.
     */
    public static function presetMap(string $preset): array
}
```

**Generic preset** — maps common column names to Contact fields. Include at minimum:

| Source column | Contact field |
|---|---|
| first_name, first name | first_name |
| last_name, last name, surname | last_name |
| email, email address | email |
| phone, phone number, mobile | phone |
| company, organization, employer | company |
| address, address_line_1, street | address_line_1 |
| address_line_2, address 2 | address_line_2 |
| city, town | city |
| state, province, region | state |
| zip, postal_code, postcode | postal_code |
| notes | notes |

**Bloomerang preset** — maps Bloomerang's exported CSV headers to Contact fields. Bloomerang's
standard contact export columns include: `First`, `Last`, `Email`, `Mobile Phone`,
`Home Phone`, `Employer`, `Address Line 1`, `Address Line 2`, `City`, `State`, `Zip`,
`Notes`. Map these appropriately.

The mapper should normalise source column names (lowercase, trim whitespace) before matching.
Unrecognised columns should map to `null` (ignored).

---

### 3. ImportResult Value Object

Create `app/Services/Import/ImportResult.php`:

```php
readonly class ImportResult
{
    public int $imported;
    public int $updated;
    public int $skipped;
    public array $errors; // array of ['row' => int, 'message' => string]

    public function errorCount(): int
    public function hasErrors(): bool
    public function toArray(): array
}
```

---

### 4. ImportJob

Create `app/Jobs/ImportContactsJob.php`:

```php
class ImportContactsJob implements ShouldQueue
{
    public function __construct(
        public string $importLogId,
        public string $storagePath,      // path to uploaded CSV on disk
        public array  $columnMap,        // ['source_col' => 'contact_field|null', ...]
        public string $duplicateStrategy // 'skip' or 'update'
    ) {}

    public function handle(): void
    {
        // 1. Mark ImportLog status = 'processing', started_at = now()
        // 2. Open CSV, skip header row
        // 3. For each row:
        //    a. Map columns using $columnMap
        //    b. Skip rows with no email AND no first_name (not enough to identify)
        //    c. If email present, look for existing Contact by email
        //       - If found and strategy = 'skip': increment skipped, continue
        //       - If found and strategy = 'update': update fillable fields, increment updated
        //       - If not found: create Contact, increment imported
        //    d. On exception: append to errors array with row number and message
        // 4. Mark ImportLog status = 'complete', completed_at = now(), write counts + errors
    }
}
```

Use chunked reading (e.g. `League\Csv` or plain `fgetcsv`) rather than loading the entire
file into memory. Check whether `league/csv` is already a dependency before adding it;
if not, use plain PHP `fgetcsv`.

---

### 5. Multi-Step Import UI

Create a custom Filament page `app/Filament/Pages/ImportContactsPage.php`. Register it in
the Filament panel and add an "Import contacts" button to `ContactResource`'s table header
actions that links to this page.

The page uses a Filament Wizard with three steps:

**Step 1 — Upload**
- File upload (CSV only, max 10MB)
- Preset selector: `Select::make('preset')` with options from `FieldMapper::presets()`
  plus a "Custom mapping" option
- On submit: store the file to `storage/app/imports/`, read the header row, advance to step 2

**Step 2 — Map Columns**
- Display a table of source column headers (read from the uploaded CSV header row)
- For each source column, show a `Select` with Contact field options (plus "— ignore —")
- Pre-populate from the chosen preset using `FieldMapper::presetMap()`
- Duplicate strategy radio: "Skip duplicate emails" / "Update existing contacts"
- On submit: validate at least one column is mapped to `email` or `first_name`

**Step 3 — Preview & Confirm**
- Show first 5 rows of the CSV with the proposed mapping applied
- Show column: source value → mapped field → destination value
- "Run import" button dispatches `ImportContactsJob` and redirects to the Import History page

**Design note**: Filament's `Wizard` component handles step state within a single Livewire
component. Store intermediate state (uploaded file path, parsed headers, column map) in
public component properties between steps.

---

### 6. Import History Page

Create `app/Filament/Pages/ImportHistoryPage.php` — a simple Filament page that displays
a table of `ImportLog` records:

Columns: Date, File, Type, Imported, Updated, Skipped, Errors, Status, Imported by (user name).

Include a row action "View errors" that opens a modal with the errors JSON displayed as a
readable list (row number + message per line). Only show this action when `error_count > 0`.

Register this page in the Filament panel. Link to it from the ImportContactsPage after
dispatching the job ("Your import is running — view progress here →").

---

### 7. Contact CSV Exporter

Add a `HeaderAction` to `ContactResource::table()`:

```php
Tables\Actions\Action::make('exportContacts')
    ->label('Export CSV')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('gray')
    ->action(function (Tables\Contracts\HasTable $livewire): StreamedResponse {
        // Use $livewire->getFilteredSortedTableQuery() to respect active filters
        // Stream CSV with Contact fields as headers
    })
```

Export columns: `first_name`, `last_name`, `email`, `phone`, `company`, `address_line_1`,
`address_line_2`, `city`, `state`, `postal_code`, `notes`, `created_at`.

Filename format: `contacts-{Y-m-d}.csv`.

Read `EditEvent.php` for the existing streaming CSV pattern already in the codebase —
use the same `response()->streamDownload()` approach.

---

## Tests to Write

**`ContactImportFieldMapperTest`**:
- Generic preset maps `email address` → `email`
- Generic preset maps `first name` → `first_name`
- Generic preset maps `zip` → `postal_code`
- Unknown column maps to `null`
- Bloomerang preset maps `First` → `first_name`
- Bloomerang preset maps `Zip` → `postal_code`
- Mapper normalises column names (trims whitespace, lowercases before matching)

**`ImportContactsJobTest`**:
- New email creates a Contact record
- Duplicate email with strategy `skip` does not update the existing Contact
- Duplicate email with strategy `update` updates the existing Contact's fields
- Row with no email and no first_name is skipped (not an error)
- Malformed row (e.g. mismatched column count) is recorded in errors, import continues
- `ImportLog` counts match actual outcomes after job completes
- Empty email field skips Contact creation without error

**`ContactExportTest`**:
- Export response has Content-Type `text/csv`
- Export contains header row with expected column names
- Export contains one data row per Contact in the database
- Filename contains today's date

---

## Acceptance Criteria

- [ ] "Import contacts" button appears on the Contacts list page header
- [ ] Upload step accepts CSV files only; rejects non-CSV with a validation error
- [ ] Preset auto-populates the column mapping step for generic and Bloomerang
- [ ] Preview step shows first 5 rows with mapped values
- [ ] Job dispatches and `ImportLog` record is created immediately with status `pending`
- [ ] After job completes, `ImportLog` has correct imported / updated / skipped / error counts
- [ ] Duplicate `skip` strategy: second import of same email does not alter the Contact
- [ ] Duplicate `update` strategy: second import of same email updates Contact fields
- [ ] Import errors are stored per-row and viewable in the Import History modal
- [ ] "Export CSV" action on Contacts list downloads a valid CSV with correct headers
- [ ] Export respects active table filters (filtered view exports filtered rows)
- [ ] `php artisan test` passes with 0 failures
