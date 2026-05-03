# NNN. Importer Test-Fixture Generator — CSV Foundation (G1)

**Status:** DRAFT (lifted at session 256 close; will be renamed to `NNN. ...md` and paired with a `NNN. base-prompt.md` when the session schedules — replace `NNN` with the assigned session number).

Build an artisan command that generates adversarial CSV fixtures (clean / messy / corrupt / pii / stress shapes × seven importers × encoding + source-preset variance) with manifest sidecars describing expected importer outcomes, and wire a parametrized Pest runner that consumes the manifests to assert importer behavior matches each manifest's per-row expectation.

This session lands **G1** — the first session in the new Track G (Test-Data Generation Infrastructure) lifted at session 256 close per `sessions/release-plan.md` § Track G. Track G's standing motivation: the project has only two real-world data sets, both real-customer-scrubbed and re-imported repeatedly, neither generating new findings any more. Real data has stopped paying for itself as a test input. Adversarial generated fixtures expand coverage without privacy concerns and let us harden the importer ahead of B2 (Onboarding rehearsal cluster) and any future importer-touching session.

This session is **not boundary-touching** — Fleet Manager contract stays at v2.1.0.

---

## Design decisions (resolved before starting)

- **New artisan command, separate from `csv:generate-fake-imports`.** The existing fake-imports command is for the seeded "Demo Fake Data" import sources (clean baseline CSVs that admins can run through the UI from the dropdown). The new command is for adversarial test fixtures (clean / messy / corrupt / pii / stress) consumed by Pest. Different audience, different lifecycle, different output. Don't conflate. New command name: `import-fixtures:generate`.

- **Output location: `storage/app/import-test-fixtures/`.** Gitignored — generated artifacts are reproducible from seed and don't belong in the repo. Override via `--output-dir`.

- **Five shapes per importer:**
  - **`clean`** — well-formed CSV that should fully import. Baseline. Default 10 rows.
  - **`messy`** — well-formed but realistic mess: type-value case variance (`Nonprofit` / `nonprofit` / `NONPROFIT`), mixed date formats (M/D/Y, ISO, "March 5, 2024", Excel-serial integers), trim issues, header noise, system-metadata columns. Should fully import after the importer's normalization layers do their work. Default 25 rows.
  - **`corrupt`** — schema- and value-level corruption that the importer should reject row-by-row (orphan FK reference, unparseable date, malformed email, embedded delimiter without quoting, quote-unbalanced cell, control characters). Manifest describes per-row error reason. Default 25 rows (a mix — most rows valid, ~30% deliberately broken).
  - **`pii`** — rows containing PII patterns the `PiiScanner` should reject. **One fixture row per scanner rule** so coverage is exhaustive. SSN-shaped, credit-card-shaped (Luhn-valid to defeat naive scanners), password-named columns, sensitive-header-named columns. Manifest describes per-row violation reason. Default ~15 rows (one per scanner rule plus a few clean controls).
  - **`stress`** — large-row + wide-row + heavy-cell variants for sizing tests. Tagged `->group('slow')` in the Pest runner. Default 1k rows; up to 100k via `--rows=N`.

- **Seven importers covered:** contacts, events, donations, memberships, invoice_details, notes, organizations.

- **Source-preset flag.** `--source-preset=generic|wild_apricot|neon|bloomerang`. Reuses `FieldMapper::presets()` to vary header naming so the importer's auto-detect path gets exercised against each preset. For importers that don't have a meaningful preset variant (e.g., Notes is contact-side and reuses contact preset; Invoice Details is generic-only), the flag is accepted but logged as "preset has no variant for this importer" rather than erroring.

- **Encoding flag.** `--encoding=utf8|utf8-bom|windows-1252`. Default `utf8`. The non-default encodings exercise the importer's BOM-stripping path and Windows-1252 transcoding. Each fixture's manifest records the encoding so the runner can pre-decode appropriately.

- **Determinism via `--seed=N`.** Same seed + same flags = byte-identical CSV. Faker is seeded per-call so output is reproducible. Seed defaults to a random value if unset (so undirected runs don't all collide on one seed).

- **Manifest sidecar shape (`<fixture>.expected.json`):**
  ```json
  {
    "fixture": "organizations-messy-generic-utf8-42.csv",
    "shape": "messy",
    "importer": "organizations",
    "preset": "generic",
    "encoding": "utf8",
    "seed": 42,
    "rows_total": 25,
    "rows_expected_imported": 22,
    "rows_expected_skipped": 2,
    "rows_expected_errored": 1,
    "skip_reasons_by_row": { "12": "blank_match_value", "18": "duplicate_skipped" },
    "error_reasons_by_row": { "5": "date_unparseable" },
    "pii_violations_by_row": {},
    "fixture_sha256": "abc..."
  }
  ```
  `fixture_sha256` is computed over the CSV bytes — lets the runner detect that a fixture has been regenerated with different output and refuse to assert against a stale manifest.

- **Architecture: per-importer baselines + importer-agnostic transforms.** Per importer, implement only the **clean** baseline (canonical header set + plausible row generator). The other four shapes (`messy` / `corrupt` / `pii` / `stress`) are **transforms** that mutate a clean fixture's rows + manifest entries. This keeps per-importer code small and makes adding the eighth importer (when one arrives) a single-file change. Transforms live at `app/Services/Import/Fixtures/Transforms/`.

- **Pest runner pattern:** `tests/Feature/Generated/ImportFixtureRunnerTest.php` enumerates `(importer, shape, preset, encoding, seed)` tuples via Pest datasets. For each tuple, the test calls the generator, loads the fixture + manifest, runs the importer's `processOneRow` against each CSV row in a transaction-rolled-back loop (mirroring the dry-run path), and asserts per-row outcome matches manifest expectation. Stress shape lives under `->group('slow')`.

- **Per-importer entrypoint extraction.** Filament's importer pages are Livewire-bound; the runner can't trivially construct one off-Livewire. **Lift a thin `App\Services\Import\FixtureRunner` service** in this session that takes a CSV path + manifest + importer name and returns per-row outcomes by replaying the existing `processOneRow` logic. Cleaner than instantiating Livewire components in tests; reusable for future on-demand fixture work.

- **Out of scope this session:**
  - Cross-importer matched pairs (G2).
  - Replay / re-import pairs (G2).
  - Adversarial dedup fixtures — case-only / NBSP / zero-width-space match-key variance (G2).
  - False-positive PII coverage (G2 — rows that look PII-shaped but should not be rejected).
  - XLSX / JSON / other file formats (post-release per Track G's scope split).
  - Salesforce export shape (post-release; tracked in `session-outlines.md` § Post-Beta 1).
  - Stress-shape rows beyond 100k (the upper bound; if a real onboarding surfaces a need for higher, lift in a follow-on).

---

## Phase 1 — Read-through, audit, sign-off

- Read `app/Services/Import/PiiScanner.php` end-to-end. **Catalog every rule it enforces** — header-blocked patterns, value-shape patterns, allowlists, anything else. The `pii` shape needs to generate one fixture row per rule, so this catalog is the source of truth for the fixture matrix.
- Read `app/Services/Import/FieldMapper.php` and confirm which presets exist (`presets()` plus `presetMap()` per preset). The `--source-preset` flag emits headers that match each preset's canonical mapping.
- Read `app/Services/Import/CsvTemplateService.php` for the canonical header lists per importer. The `clean` shape's headers reuse these.
- Read `app/Console/Commands/GenerateFakeImports.php` (the existing `csv:generate-fake-imports` command) for the existing CSV-emission pattern. New command stays separate but mirrors file-write conventions.
- Read each importer's `processOneRow` end-to-end (contacts / events / donations / memberships / invoice_details / notes / organizations) to understand what "imported / skipped / errored" actually means per importer — manifest semantics are importer-specific. Write down the `skipReason` / `error` enums each importer surfaces; these are what the manifest's `skip_reasons_by_row` / `error_reasons_by_row` entries reference.
- Confirm with the user: shapes, knobs, manifest format, output directory. Sign off before Phase 2.

---

## Phase 2 — Command scaffolding + dispatcher

New command class at `app/Console/Commands/GenerateImportFixtures.php` with signature:

```
import-fixtures:generate
    {--importer= : contacts|events|donations|memberships|invoice_details|notes|organizations|all (default: all)}
    {--shape=clean : clean|messy|corrupt|pii|stress}
    {--source-preset=generic : generic|wild_apricot|neon|bloomerang}
    {--encoding=utf8 : utf8|utf8-bom|windows-1252}
    {--rows= : row count override (defaults per shape: clean=10, messy=25, corrupt=25, pii=~15, stress=1000)}
    {--seed= : seed for determinism (default: random)}
    {--output-dir= : output directory (default: storage/app/import-test-fixtures)}
```

Top-level dispatcher loops over selected importer(s), calls the per-importer `clean` generator, then applies the selected shape transform (or returns clean directly), then writes the CSV + manifest.

Output filenames: `<importer>-<shape>-<preset>-<encoding>-<seed>.csv` plus the matching `.expected.json` sidecar.

Validation: invalid combinations (e.g., `--shape=pii --importer=organizations` if Organizations doesn't surface PII via its scanner rules — check the catalog from Phase 1) error with a clear message rather than silently emitting an empty fixture.

---

## Phase 3 — Per-importer clean generators

For each of the seven importers, implement a class under `app/Services/Import/Fixtures/Importers/<Importer>FixtureBuilder.php`:

- `headers(string $preset): array<int, string>` — returns the column header list for the given preset.
- `cleanRows(int $rows, int $seed): array<int, array<string, scalar|null>>` — returns row data keyed by header.

Use Faker (already a dev dep via Laravel) for plausible names / emails / addresses / amounts. Seed Faker per-call (`fake()->seed($seed)`) so output is reproducible.

The `clean` baseline values are well-formed: dates parse, emails are valid, types match the importer's accepted enums, no PII patterns, no schema corruption. Manifest entries are all `outcome=imported`.

---

## Phase 4 — Shape transforms

Implement four transform classes under `app/Services/Import/Fixtures/Transforms/`:

- **`MessyTransform`** — applies importer-agnostic mess to clean rows: random case-flipping on enum values, date format swapping (round-robin through formats), trim padding (random leading/trailing whitespace on string fields), occasional empty cells in optional columns. **All rows still expected to import.** Manifest entries unchanged from clean.

- **`CorruptTransform`** — injects per-row corruption deterministically (seeded so the same seed always corrupts the same row indices). For ~30% of rows, replaces a value with corruption: unparseable date, malformed email, orphan FK reference, embedded unquoted delimiter, control character. Manifest entries flip from `imported` to `errored` with the appropriate `error_reason`.

- **`PiiTransform`** — injects PII patterns into one row per `PiiScanner` rule (catalog from Phase 1). Each row gets exactly one violation; the rest of the row is clean so the runner can isolate the rejection cause. Manifest entries set `outcome=pii_rejected` with the violation reason.

- **`StressTransform`** — multiplies row count to `--rows` (default 1k); optionally widens columns to 100+ by adding noise columns; optionally inflates cell sizes for rich-text fields. Manifest's `rows_total` reflects the multiplied count.

Each transform's `apply(array $rows, array $manifestEntries, int $seed): [array, array]` returns mutated rows + mutated manifest entries.

---

## Phase 5 — Source-preset variance

Use `FieldMapper::presetMap($preset)` to select headers per preset. Generic uses canonical importer header names (matches `CsvTemplateService`). Wild Apricot, Neon, Bloomerang use their respective source-system header names per the existing `FieldMapper` presets.

Per-importer preset support is declarative: each importer's `FixtureBuilder` declares which presets it supports via a `supportedPresets(): array` method. Unsupported preset combinations emit a warning and fall back to `generic`.

---

## Phase 6 — Encoding + line-ending variance

`utf8` — default. UTF-8 with LF line endings.
`utf8-bom` — UTF-8 with byte-order mark prefix. Triggers the importer's BOM-stripping path.
`windows-1252` — Latin-1-ish encoding common in Excel exports. The CSV writer transcodes from UTF-8 string data to Windows-1252 on output. Triggers the importer's transcoding path.

Line endings: utf8/utf8-bom = LF; windows-1252 = CRLF (matches actual Windows-export behavior).

Each fixture's manifest records the chosen encoding so the runner can pre-decode if needed before passing to the importer.

---

## Phase 7 — Manifest generation

After rows + manifest entries are built (Phase 3 + transforms in Phase 4), serialize the manifest to `<fixture>.expected.json`. Fields per the design decisions above. `fixture_sha256` is computed over the CSV file's bytes after encoding is applied (so the hash detects encoding-only changes too).

Manifest writer is a small dedicated class — `App\Services\Import\Fixtures\ManifestWriter` — so the format is centralized and the runner reads through the same class.

---

## Phase 8 — `FixtureRunner` service + Pest runner

**Lift `App\Services\Import\FixtureRunner`:**
- `runFixture(string $importer, string $csvPath, array $manifest): array<int, array{outcome: string, reason?: string}>`
- Internally: opens the CSV, decodes per the manifest's encoding, loops rows, calls the importer's `processOneRow` (or the closest equivalent service-layer entry — extract one if needed), wraps in a transaction that rolls back at the end (so the test suite's DB stays clean).
- Returns a per-row outcome list the test can compare to the manifest's expected outcome.

This service is the bridge between the generator and the test suite; it should be useful in any future session that wants to drive the importer programmatically.

**Pest runner:** `tests/Feature/Generated/ImportFixtureRunnerTest.php`. Pattern:

```php
dataset('fixturesFastSuite', /* enumerate (importer, shape∈{clean,messy,corrupt,pii}, preset, encoding, seed) */);
dataset('fixturesStressSuite', /* (importer, 'stress', preset, encoding, seed) */);

it('importer {0} / shape {1} / preset {2} / encoding {3} matches manifest expectation', function (
    string $importer, string $shape, string $preset, string $encoding, int $seed
) {
    [$csv, $manifest] = generateFixture($importer, $shape, $preset, $encoding, $seed);
    $outcomes = app(FixtureRunner::class)->runFixture($importer, $csv, $manifest);
    assertOutcomesMatchManifest($outcomes, $manifest);
})->with('fixturesFastSuite');

it('importer {0} stress shape', function (...) {
    // same body as above
})->with('fixturesStressSuite')->group('slow');
```

The fast-suite dataset enumerates ~28 cases (7 importers × 4 shapes × 1 preset × 1 encoding); the stress-suite dataset is 7 cases (one per importer). Modest growth from the 1889 baseline.

---

## Phase 9 — Documentation

New runbook at `docs/runbooks/import-fixture-generator.md`:
- What the tool generates and why (the Track G motivation, in brief).
- CLI surface — every flag, every default.
- Manifest format reference — full schema with field semantics.
- Architecture overview: per-importer baselines + transforms.
- How to add a new shape (subclass the transform interface).
- How to add a new source preset (extend `FieldMapper::presets()` + each `FixtureBuilder::supportedPresets()`).
- How to add a new PII pattern test (PiiTransform consumes the scanner catalog automatically; if a new scanner rule lands, the catalog updates and one new fixture row appears).
- How the Pest runner consumes manifests.
- When to use `import-fixtures:generate` vs `csv:generate-fake-imports` (different purposes; both kept).

Add a section to `tests/e2e/README.md` linking to the runbook for authors who want to consume generated fixtures from Playwright.

Update `docs/app-reference.md` — add a row for the new artisan command in the dev tooling section.

Add `storage/app/import-test-fixtures/` to `.gitignore`.

---

## Phase 10 — Manual verification

After fast suite passes:
1. Run `php artisan import-fixtures:generate --importer=organizations --shape=messy --seed=42`. Confirm `storage/app/import-test-fixtures/organizations-messy-generic-utf8-42.csv` lands plus its `.expected.json`.
2. Open the CSV in a text viewer — sanity-check that "messy" actually looks plausibly messy (mixed-case enums, varied date formats, trim issues, noise columns). The user judges whether it feels real-world adversarial or synthetic-only.
3. Open the manifest — sanity-check `rows_expected_imported / skipped / errored` matches a manual count of intentionally-broken rows.
4. Run `php artisan import-fixtures:generate --importer=contacts --shape=pii --seed=1`. Confirm each row's `pii_violations_by_row` entry corresponds to a known `PiiScanner` rule, and the rule's reason matches the manifest's reason.
5. Run a small generated fixture through the actual UI importer (Org importer, since session 256 just shipped it). Confirm the importer's actual outcome matches the manifest's expected outcome end-to-end. **Pull the user in for visual judgment** — the messy/corrupt/pii fixtures are partly aesthetic ("does this look like a real-world Wild Apricot export?") and the user is better-positioned to judge.
6. Run `php artisan import-fixtures:generate --importer=donations --shape=stress --rows=10000 --seed=99`. Confirm output lands and Pest's `slow`-grouped runner can consume it under reasonable memory.

Stop and wait for user feedback after step 6. Do not suggest closing.

---

## Testing

- **Slow test groups to run this session:** `slow` (the stress-shape fixtures live there). Run via `docker compose exec app php -d memory_limit=2G vendor/bin/pest --group=slow`.
- **New tests expected:** yes — the parametrized `ImportFixtureRunnerTest`. Conservatively: 7 importers × 4 fast shapes × 1 preset × 1 encoding = ~28 cases in fast suite. Stress shape adds 7 cases under `->group('slow')`. Modest growth from the 1889 baseline.

---

## Security checklist

Before closing:

- [ ] Generated PII fixtures contain only **synthetic** PII patterns (Faker-generated) — no real-shape data that could be confused with real PII. Specifically: SSNs are in the SSA-reserved range (`9XX-XX-XXXX` is invalid by issuance rules); credit cards use the official Stripe/Visa/MC test-card numbers (already public, already non-functional).
- [ ] `storage/app/import-test-fixtures/` is gitignored.
- [ ] The `FixtureRunner` service's transaction wrap correctly rolls back per-test; no leaked rows pollute downstream tests in the suite.
- [ ] `PiiScanner` rule coverage matches `PiiTransform` 1:1 — every scanner rule has a fixture row that fires it; no fixture row fires a rule that doesn't exist (would yield a manifest entry with no scanner-side counterpart).
- [ ] Stress shape doesn't accidentally exhaust memory under default flags. 1k rows is the default; 100k is the explicit opt-in.
- [ ] Generator command is dev-only — not registered as a route, not exposed via the admin UI, not scheduled.

---

## Out of scope

- **Cross-importer matched pairs** (G2 — coordinated CSV sets where contacts.csv + donations.csv reference the same external IDs to exercise `ImportIdMap`).
- **Replay / re-import pairs** (G2 — pass-1.csv + pass-2.csv for re-import dedup-strategy tests).
- **Adversarial dedup fixtures** (G2 — match keys differing only by case / whitespace / NBSP / zero-width-space).
- **False-positive PII coverage** (G2 — rows that look PII-shaped but should not be rejected).
- **XLSX / JSON / other file formats** (post-release per Track G's scope split — listed in `session-outlines.md` § Post-Beta 1).
- **Salesforce export shape support** (post-release; tracked in `session-outlines.md` § Post-Beta 1).
- **Stress-shape rows beyond 100k** (out of scope unless real onboarding surfaces a need; lift in a follow-on if so).
- **Wiring generated fixtures into Playwright on-demand specs** (deferred per user direction at session 256 close; revisit once we know what reasonable looks like).

---

## Closing steps

Follow the close gate in the base prompt. Session-specific details:

- **Log file:** `sessions/NNN. Importer Test-Fixture Generator — CSV Foundation — Log.md`.
- **Branch:** `session-NNN/N` (final iteration).
- **Release plan:** check off **G1** (✅) at session NNN with a one-line summary (artisan command + 7 importers × 5 shapes + manifest sidecars + `FixtureRunner` service + parametrized Pest runner). G2 stays open. T1 prereqs (which require all of A-G closed) gain one more checked entry.
- **Session outlines:** fold the G1 stub into a closure note under "Test-Data Generation Infrastructure — Beta 1 Scope". G2 stub stays open.
- **No track-doc update** — Track G doesn't have a separate planning doc beyond `release-plan.md` and `session-outlines.md` (carry-forward decision: lift one if Phase 2 work surfaces enough cross-session decisions to warrant it).
- **No Fleet Manager contract change** — boundary not touched.
- **Production deployment notes:** none — the command is dev-only; not exposed as a route or scheduled task.
- **Archive previous session:** move all files matching `sessions/256. *.md` into `sessions/archived/` (assuming session 256 is the prior session at the time NNN closes).
