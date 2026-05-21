# Playwright diagnostic digest (A003)

- total specs run: **68**
- passed: **57**  ·  failed: **1**  ·  flaky: **1**  ·  skipped: **9**
- wall time: **885.6s**

## Non-passing specs (2)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 17.4s | unexpected | `importer/site-import-export.spec.ts:18` — renders both sections and surfaces snapshot counts on Export Site | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[role="dialog"]:visible').filter({ hasText: 'Export Site' }) / Expected: visible |
| 2.1s | flaky | `page-builder/full-width-matrix.spec.ts:337` — editor (bg:false, content:false): outer container --contained iff !bg, inner gri | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 43.3s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 38.8s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 36.8s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 36.3s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 31.9s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 27.2s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.9s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 19.4s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 19.0s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 18.9s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 18.4s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 18.2s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 17.4s | unexpected | `importer/site-import-export.spec.ts:18` — renders both sections and surfaces snapshot counts on Export Site |
| 16.1s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 15.0s | expected | `importer/donations-happy-path.spec.ts:34` — imports 5 donations with transactions and auto-creates contacts |


