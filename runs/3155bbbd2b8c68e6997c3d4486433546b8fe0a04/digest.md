# Playwright diagnostic digest (A003)

- total specs run: **68**
- passed: **55**  ·  failed: **1**  ·  flaky: **3**  ·  skipped: **9**
- wall time: **877.3s**

## Non-passing specs (4)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 91.3s | flaky | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them | Error: expect(received).toBeGreaterThan(expected) / Expected: > 0 / Received:   0 |
| 19.2s | unexpected | `importer/site-import-export.spec.ts:18` — renders both sections and surfaces snapshot counts on Export Site | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[role="dialog"]:visible').filter({ hasText: 'Export Site' }) / Expected: visible |
| 2.1s | flaky | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |
| 1.7s | flaky | `page-builder/full-width-matrix.spec.ts:337` — editor (bg:false, content:false): outer container --contained iff !bg, inner gri | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 91.3s | flaky | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 32.8s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 30.3s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 29.5s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 25.8s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 21.6s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 19.7s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 19.2s | unexpected | `importer/site-import-export.spec.ts:18` — renders both sections and surfaces snapshot counts on Export Site |
| 17.1s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 16.7s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 15.6s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 14.6s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 14.6s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 14.1s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 12.4s | expected | `importer/donations-happy-path.spec.ts:34` — imports 5 donations with transactions and auto-creates contacts |


