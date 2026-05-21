# Playwright diagnostic digest (A003)

- total specs run: **67**
- passed: **56**  ·  failed: **2**  ·  flaky: **0**  ·  skipped: **9**
- wall time: **949.2s**

## Non-passing specs (2)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 0.0s | unexpected | `importer/donations-happy-path.spec.ts:34` — imports 5 donations with transactions and auto-creates contacts | TimeoutError: page.goto: Timeout 45000ms exceeded. / Call log: /   - navigating to "http://localhost:8090/admin/login", waiting until "load" |
| 0.0s | unexpected | `importer/donations-mapping-indicator.spec.ts:24` — rows render incomplete by default and flip to complete when mapped | TimeoutError: page.goto: Timeout 45000ms exceeded. / Call log: /   - navigating to "http://localhost:8090/admin/login", waiting until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 42.7s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 39.0s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 35.7s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 35.2s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 31.5s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 26.8s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 23.5s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 20.5s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 20.0s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 18.8s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 18.6s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 17.1s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 16.6s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 16.3s | expected | `importer/memberships-happy-path.spec.ts:32` — imports 5 memberships and auto-creates contacts |
| 12.7s | expected | `importer/invoice-details-happy-path.spec.ts:33` — imports 6 rows into 3 transactions with grouped line items |


