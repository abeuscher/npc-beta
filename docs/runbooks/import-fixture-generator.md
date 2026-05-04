# Importer Test-Fixture Generator

Adversarial CSV fixture generator for the seven importers (contacts / events / donations / memberships / invoice_details / notes / organizations). Each generated fixture lands at `storage/app/import-test-fixtures/<importer>-<shape>-<preset>-<encoding>-<seed>.csv` paired with a sibling `<...>.expected.json` manifest describing per-row expected importer outcome.

The generator is dev-only — not exposed via routes, admin UI, or scheduled tasks.

## Why this exists

The project has accumulated two real-world data sets, both real-customer-scrubbed and re-imported repeatedly. Neither generates new findings any more — real data has stopped paying for itself as a test input. Adversarial generated fixtures expand coverage without privacy concerns.

`csv:generate-fake-imports` (the existing command) emits clean baseline CSVs for the seeded "Demo Fake Data" import sources — clean rows that an admin can run through the UI from the dropdown. `import-fixtures:generate` is for adversarial test fixtures consumed by Pest. Different audience, different lifecycle, different output. Both kept.

## CLI

```bash
docker compose exec app php artisan import-fixtures:generate \
  --importer=organizations \
  --shape=messy \
  --source-preset=generic \
  --encoding=utf8 \
  --seed=42
```

| Flag | Values | Default |
|------|--------|---------|
| `--importer` | `contacts \| events \| donations \| memberships \| invoice_details \| notes \| organizations \| all` | `all` |
| `--shape` | `clean \| messy \| corrupt \| pii \| stress` | `clean` |
| `--source-preset` | `generic \| wild_apricot \| bloomerang` | `generic` |
| `--encoding` | `utf8 \| utf8-bom \| windows-1252` | `utf8` |
| `--rows` | row count override | per-shape default |
| `--seed` | seed for determinism | random |
| `--output-dir` | output directory | `storage/app/import-test-fixtures` |

## Shapes

- **`clean`** — well-formed CSV that should fully import. Baseline. Default 10 rows.
- **`messy`** — well-formed but realistic mess: mixed date formats (M/D/Y, ISO, "March 5, 2024", "j-M-Y"), trim issues (leading / trailing whitespace). Should fully import after the importer's normalization layers do their work. Default 25 rows.
- **`corrupt`** — ~30% of rows blank a per-importer required field (deterministic skip / error outcome); ~30% of rows carry permissive corruption (control chars in notes, malformed email, oversized cells, custom-field type mismatch) which the importer accepts today and the manifest documents via `corruption_kinds_by_row`. Default 25 rows.
- **`pii`** — one fixture row per `PiiScanner` rule. Each row plants a violating cell in a specific column. The runner asserts the scanner's output matches the manifest's `pii_violations_by_row`. Catalog is in [PiiTransform::CATALOG](../../app/Services/Import/Fixtures/Transforms/PiiTransform.php).
- **`stress`** — large-row + wide-row variants for sizing tests. Default 1k rows; up to 100k via `--rows=N`. Wide-row variant adds 50 noise custom-field columns. Tagged `->group('slow')` in the Pest runner.

## Manifest format

Each `<fixture>.csv` has a `<fixture>.expected.json` sidecar:

```json
{
  "fixture": "organizations-messy-generic-utf8-42.csv",
  "shape": "messy",
  "importer": "organizations",
  "preset": "generic",
  "encoding": "utf8",
  "seed": 42,
  "rows_total": 25,
  "rows_expected_imported": 25,
  "rows_expected_skipped": 0,
  "rows_expected_errored": 0,
  "rows_expected_pii_rejected": 0,
  "skip_reasons_by_row": {},
  "error_reasons_by_row": {},
  "pii_violations_by_row": {},
  "corruption_kinds_by_row": {},
  "custom_field_columns": [
    {"header": "Industry", "handle": "industry", "type": "text"}
  ],
  "fixture_sha256": "abc..."
}
```

`fixture_sha256` is computed over the CSV file's bytes after encoding is applied — the runner can use it to detect a stale manifest after a regeneration with different output.

## Architecture

- **Per-importer baselines** — `app/Services/Import/Fixtures/Importers/<Importer>FixtureBuilder.php`. Each declares `headers($preset)`, `customFieldColumns($preset)`, `cleanRow($i, $preset, $faker)`, and `columnMap($preset)`. The clean shape uses these directly; transforms mutate clean rows.
- **Importer-agnostic transforms** — `app/Services/Import/Fixtures/Transforms/`. Four transform classes (`MessyTransform`, `CorruptTransform`, `PiiTransform`, `StressTransform`) implement [FixtureTransform](../../app/Services/Import/Fixtures/Transforms/FixtureTransform.php). Adding a new shape is a single transform class.
- **Manifest writer + CSV writer** — `ManifestWriter` centralizes the `.expected.json` shape so the runner reads through the same class. `CsvWriter` handles encoding (UTF-8 / UTF-8 BOM / Windows-1252 with CRLF).
- **Generator** — `FixtureGenerator` is the entry point both the artisan command and the Pest runner use. Stays in lockstep with the per-shape default row counts.
- **Runner** — `App\Services\Import\FixtureRunner` consumes a CSV + manifest, runs the importer's `processOneRow` per row (or invokes `PiiScanner` for `pii`-shape fixtures), and returns per-row outcomes the test compares to the manifest.

## Custom-field variance

All shapes include custom-field columns where the importer supports them (contacts, organizations, donations, memberships, events, registrations, invoice_details, notes — every importer except contacts in some preset variants declares 2–6 custom-field columns). Field types covered across importers: `text`, `number`, `date`, `boolean`, `select`, `rich_text`.

Per-shape custom-field handling:
- `messy` — applies the same trim/date variance as canonical columns.
- `corrupt` — `cf_type_mismatch` is one of four permissive-corruption strategies. Manifest annotates via `corruption_kinds_by_row` (the importer is permissive about JSONB cell shapes today).
- `pii` — at least one fixture row places its violation in a custom-field column (`ssn_hyphenated_custom_field` per `PiiTransform::CATALOG`).
- `stress` — wide-row variant adds 50 noise CF columns to test JSONB column size + `CustomFieldDef` churn.

## Source-preset variance

Only the contacts importer has multiple preset variants today (`generic` / `wild_apricot` / `bloomerang`). For other importers, all presets fall back to `generic` headers (the command emits a warning).

To add a new preset: extend `FieldMapper::presets()` plus the `supportedPresets()` list on each `FixtureBuilder` whose headers should differ.

Salesforce NPSP support is post-Beta-1 (tracked in `sessions/session-outlines.md` § Test-Data Generator — Salesforce Export Shape Support).

## Encoding

- `utf8` — default. UTF-8 with LF line endings.
- `utf8-bom` — UTF-8 with byte-order mark prefix; LF line endings. Triggers the importer's BOM-stripping path.
- `windows-1252` — Latin-1 transcoded; CRLF line endings (matches actual Excel-export behavior).

## Pest runner

[tests/Feature/Generated/ImportFixtureRunnerTest.php](../../tests/Feature/Generated/ImportFixtureRunnerTest.php) enumerates `(importer, shape, preset, encoding, seed)` tuples via Pest datasets:

- `fixturesFastSuite` — 7 importers × 4 fast shapes (`clean`, `messy`, `corrupt`, `pii`) × `generic` × `utf8` × seed 42 = 28 cases. Runs in the default fast suite.
- `fixturesStressSuite` — 7 importers × `stress` = 7 cases. Tagged `->group('slow')`.

The test:
1. Generates the fixture inline via `FixtureGenerator`.
2. Loads the manifest sidecar.
3. Calls `FixtureRunner::runFixture($importer, $csvPath, $manifest)`.
4. Asserts per-row outcome tally matches manifest expectation.
5. Asserts each `skip_reason` and `error_reason` row matches the manifest's exact reason string.

The runner pre-seeds:
- A `fixture-runner@example.test` user (`importerUserId` for note authorship + event author_id).
- One `Contact` per non-blank email cell in fixtures whose builder has a `contactMatchKey`.
- `CustomFieldDef` rows for every entry in the manifest's `custom_field_columns`.

For `pii`-shape fixtures, the runner invokes `PiiScanner` directly (whole-fixture rejection semantics) and asserts violations match the manifest.

## Adding a new shape

1. Create a class implementing [FixtureTransform](../../app/Services/Import/Fixtures/Transforms/FixtureTransform.php).
2. Add the shape name to `FixtureGenerator::SHAPES` and a default row count to `FixtureGenerator::DEFAULT_ROWS`.
3. Wire it into `FixtureGenerator::resolveTransform()` and the artisan command's `--shape` choices.
4. Add the new shape to `fixturesFastSuite` (or `fixturesStressSuite` if it's slow) in the Pest test.

## Adding a new PII pattern

If `PiiScanner` gains a new rule, append a catalog entry to `PiiTransform::CATALOG` with:
- `id` — stable identifier (used in manifest's `rule_id` field).
- `reason` — the literal string the scanner emits (must match exactly).
- `value_fn` — name of a method on `PiiTransform` that returns a synthetic violation value.
- `target` — `canonical` or `custom_field`.

## When to use which command

- **`csv:generate-fake-imports`** — generates a self-consistent bundle of clean CSVs (contacts + events + donations + memberships + invoice_details + notes) for seeding the Demo Fake Data import sources. Output goes to `storage/app/fake-csvs`. Used by admins running the UI dropdown.
- **`import-fixtures:generate`** — generates per-shape adversarial fixtures for the Pest runner to exercise. Output goes to `storage/app/import-test-fixtures`. Used by automated tests.
