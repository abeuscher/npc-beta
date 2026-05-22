# Playwright Discipline

The rule for what belongs in `tests/e2e/` and how to author a spec without shipping one that has never run. Companion to `docs/testing/mutation-audits.md`; the D1 dimension of the Test Audit track (`sessions/tracks/test-audit.md`).

---

## Why Playwright

Playwright is the only suite that drives a real browser, so it is the only place that can verify what a browser does and Pest cannot: visible appearance, JS-driven UI behavior, user-interaction flows, CSS rendering, and browser-mediated side effects (file downloads, FilePond uploads, drag-drop, Choices.js init). It is slow (a full CI run is ~10–15 minutes against the isolated stack) and non-gating by design. Every spec it carries must earn that cost by testing something only a browser can see.

---

## The decision rule

Every spec gets one of three verdicts:

1. **Keep — browser-required.** Its assertions exercise a browser-only surface. Computed-value rendering, contenteditable editors, drag-reorder, FilePond/Choices.js, multi-step Livewire wizards, file downloads, permission-gated UI affordances. Example: `template-scheme.spec.ts` asserts `getComputedStyle` on rendered paint across public/admin/preview — there is no non-browser way to prove the scheme is *painted*, not merely set.

2. **Redundant — data covered by Pest.** Its assertions are data/business-logic outcomes that a Pest Feature or Unit test covers (or should cover). Delete it. Example: the per-importer happy-path/update-strategy specs re-drove the same generic wizard purely to assert row counts and field values that `ImportEventsTest` / `ImportFinancialTest` / `ImportSession194Test` already assert at the service layer. The wizard UI is browser-required and worth **one** representative; the data repetition is not.

3. **Mixed — split or rewrite.** Some assertions are browser-required, some are data. Keep the browser-only assertions; drop the data assertions (they belong in Pest). The post-rewrite spec reads as "exercises the browser-only contract" and nothing more. If the title overclaims what the trimmed body asserts, rename it (see D2 — spec-claim integrity).

**Default lean:** when a spec's browser value is fully covered by another kept spec exercising the same UI shape, it is Redundant even if its data path is unique — relocate the data assertion to Pest rather than keep a slow browser spec alive for it. Audit sessions deliver fewer, sharper specs, not net-new coverage.

---

## FixtureRunner is the safety net for importer dispositions

Before deleting an importer spec as Redundant, confirm the data path is covered off-Livewire. Two layers cover importer data:

- **`tests/Feature/Generated/ImportFixtureRunnerTest.php`** runs all 7 importers (`BuilderRegistry::IMPORTERS`) × 4 shapes (clean / messy / corrupt / pii) and asserts per-row outcome **tallies** (imported / skipped / errored / pii_rejected) and skip/error reasons. It does **not** assert field-value round-trips or related-entity creation.
- **The importer Feature suite** (`ImportEventsTest`, `ImportFinancialTest`, `ImportOrganizationsTest`, `NotesImporterTest`, `ImportSession194Test` for update-strategy, `ImportSessionActionsTest` for approve/rollback, `ImportReviewTest` for review findings) covers the field-level and relation-level outcomes.

If neither layer covers the path the spec asserts, the verdict re-evaluates: the spec is genuine coverage. Move the assertion to Pest (or, if that is out of session scope, file a `[test-integrity]` forward-queue note and delete the spec only when the Pest gap is closed). The A005 sweep surfaced exactly one such gap — invoice-details first-row-wins parent-field conflict resolution — and recorded it rather than silently dropping it.

---

## Local-first authoring discipline

A Playwright spec is not committed until its author has run it locally against the isolated stack and seen it green at least once.

```bash
npm run e2e:up                                   # isolated stack (port 8090, separate DB)
npm run test:e2e -- --grep "<spec or test name>" # the inner loop
npm run e2e:down
```

This is the inner-loop change that retires "CI Playwright is a 15-minute outer feedback loop." A spec that has never run locally is the smoking-gun bug class this discipline exists to kill (see anti-patterns below). Never push a spec on the assumption CI will tell you whether it works — CI is the *regression* signal, not the *authoring* signal.

---

## Anti-patterns this discipline catches

All four were found in real specs that had been committed without ever running (A003 forensics):

- **Wrong URL.** A spec navigated `page.goto('/admin/site-import-export')`; the actual Filament auto-slug for `SiteImportExportPage` is `/admin/site-import-export-page`. The spec could not have passed on any environment. Mechanically resolve every `page.goto` against the Filament route registry (`*Page` classes route at `/admin/{kebab-case-class-name-including-Page-suffix}`; no slug overrides in this app).
- **Ambiguous role selectors.** `getByRole('button', { name: 'Export Site' })` matched both the trigger button and the modal's `modalSubmitActionLabel('Export Site')`. Prefer a stable `data-testid` over a role+name that a modal can collide with.
- **`.first()` on `[role="dialog"]`.** Filament pre-renders hidden modal placeholders; `.first()` picks the hidden one. Scope to `[role="dialog"]:visible` and assert visibility explicitly.
- **`requiresConfirmation()` header-action modals on a custom `Filament\Pages\Page`.** The action mounts server-side (modal HTML renders with correct content) but Alpine never toggles `isOpen` to `true` in headless chromium, so the modal stays hidden. The same pattern works for table actions and for `->form([...])` header actions — only this specific combination fails in CI. Avoid asserting against it until the underlying Filament wiring is understood; the `->form([...])` shape is the working alternative.

---

## Artifact hygiene

`resetDatabase()` fires once per spec in `beforeAll`; rows a test creates after that persist into the next run. Delete out-of-band rows in `afterAll` (standalone layouts, test-only pages, ad-hoc records). Rows that are the natural output of the feature under test (contacts imported by a happy-path spec) may stay. See `tests/e2e/README.md`.

---

## Forward queue

The other audit dimensions live in `sessions/tracks/test-audit.md`:

- **D2 — Spec-claim integrity.** Every test title accurately describes what its body asserts. Drains the `[test-integrity]` backlog; default lean is rename-to-match over expand-the-test.
- **D3 — Mutation testing slice.** `docs/testing/mutation-audits.md`.
- **D4 — Pest file relevance + fast/slow classification.** The s094-style per-file walk.

Cadence: approximately every 50 sessions of growth, or sooner on a forcing function (a class-of-bug that audits would catch, or 5+ unresolved `[test-integrity]` entries).
