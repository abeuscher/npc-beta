# Playwright diagnostic digest

- total specs run: **62**
- passed: **60**  ·  failed: **0**  ·  flaky: **2**  ·  skipped: **0**
- wall time: **836.2s**

## Non-passing specs (2)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 18.4s | flaky | `page-builder/inline-editing-phase2.spec.ts:129` — round-trips a nested richtext path (columns.0.attribute_rows.0.value) | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="5c3b9ddf-ef81-4321-8f03-cca401ae0c4c"]').locator('[data-config-key="columns.0.attribute_rows.0.value"]').locator('.ql-editor') / Expected: visible |
| 2.0s | flaky | `page-builder/full-width-matrix.spec.ts:337` — editor (bg:false, content:false): outer container --contained iff !bg, inner gri | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 39.8s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 35.7s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 35.6s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 33.5s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 31.3s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 25.0s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.5s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 18.5s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 18.4s | flaky | `page-builder/inline-editing-phase2.spec.ts:129` — round-trips a nested richtext path (columns.0.attribute_rows.0.value) |
| 18.4s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 18.0s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 17.3s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 16.9s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 15.2s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 14.5s | expected | `importer/donations-happy-path.spec.ts:34` — imports 5 donations with transactions and auto-creates contacts |


