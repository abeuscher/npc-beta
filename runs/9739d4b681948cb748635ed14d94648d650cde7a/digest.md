# Playwright diagnostic digest (A003)

- total specs run: **67**
- passed: **58**  ·  failed: **0**  ·  flaky: **1**  ·  skipped: **8**
- wall time: **864.7s**

## Non-passing specs (1)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 64.1s | flaky | `importer/content-bundle-two-step.spec.ts:18` — analyzes the bundle on upload and reveals gated theme + page toggles | Error: expect(received).toBeGreaterThan(expected) / Expected: > 0 / Received:   0 |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 64.1s | flaky | `importer/content-bundle-two-step.spec.ts:18` — analyzes the bundle on upload and reveals gated theme + page toggles |
| 42.2s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 38.4s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 36.6s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 36.1s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 32.7s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 27.7s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 23.1s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 21.4s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 19.6s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 19.4s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 19.3s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 18.3s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 16.3s | expected | `importer/events-happy-path.spec.ts:38` — imports 3 events with registrations and transactions |
| 15.1s | expected | `importer/memberships-happy-path.spec.ts:32` — imports 5 memberships and auto-creates contacts |


