# Playwright diagnostic digest (A003)

- total specs run: **60**
- passed: **50**  ·  failed: **0**  ·  flaky: **2**  ·  skipped: **8**
- wall time: **798.6s**

## Non-passing specs (2)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 133.8s | flaky | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins | Error: Timed out 120000ms waiting for expect(locator).toBeVisible() / Locator: getByTestId('import-progress-phase-done') / Expected: visible |
| 2.5s | flaky | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 133.8s | flaky | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 38.3s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 34.5s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 33.4s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 30.7s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 23.6s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.2s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 17.8s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 17.7s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 17.5s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 15.8s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 15.0s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 14.2s | expected | `importer/memberships-happy-path.spec.ts:32` — imports 5 memberships and auto-creates contacts |
| 11.4s | expected | `importer/invoice-details-happy-path.spec.ts:33` — imports 6 rows into 3 transactions with grouped line items |
| 10.2s | expected | `notes/notes-permissions.spec.ts:50` — toggle on — op_b cannot see Edit / Delete on op_a-authored note |


