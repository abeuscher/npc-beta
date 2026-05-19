# Playwright diagnostic digest

- total specs run: **66**
- passed: **51**  ·  failed: **5**  ·  flaky: **3**  ·  skipped: **7**
- wall time: **849.9s**

## Non-passing specs (8)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 22.6s | unexpected | `template-scheme.spec.ts:108` — PREVIEW: page-builder preview content scope follows the SAME scheme, faithful to | Error: Timed out 20000ms waiting for expect(locator).toHaveCSS(expected) / Locator: locator('.widget-preview-scope.np-site').first() / Expected string: "rgb(17, 24, 39)" |
| 22.1s | flaky | `event-registration/ticket-tier-picker.spec.ts:24` — free event with no tiers renders no quantities UI | Error: Timed out 20000ms waiting for expect(locator).toBeVisible() / Locator: getByRole('heading', { name: 'Register', exact: true }) / Expected: visible |
| 16.8s | unexpected | `event-registration/ticket-tier-picker.spec.ts:71` — multi-tier event renders one spinner per tier and sums the subtotal across them | Error: Timed out 15000ms waiting for expect(locator).toHaveCount(expected) / Locator: locator('input[name="quantities[ed62b97d-d771-4105-ac66-fdd75e489d1c]"]') / Expected: 1 |
| 16.8s | flaky | `event-registration/ticket-tier-picker.spec.ts:42` — single-tier paid event renders one quantity spinner with default 1 and a live su | Error: Timed out 15000ms waiting for expect(locator).toHaveCount(expected) / Locator: locator('input[name="quantities[ee8289af-6a1e-4452-9591-95dc2fb61c3d]"]') / Expected: 1 |
| 16.5s | unexpected | `widget-color-tokens.spec.ts:37` — PUBLIC: migrated widget colour resolves to the --np-color-* token | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.widget-blog-listing__empty').first() / Expected: visible |
| 4.2s | unexpected | `page-builder/inline-editing-phase2.spec.ts:98` — round-trips a nested plaintext path (columns.0.title) to raw config | Error: expect(received).toBe(expected) // Object.is equality / Expected: "Starter Plan" / Received: "Basic" |
| 1.5s | flaky | `page-builder/full-width-matrix.spec.ts:349` — editor (bg:false, content:false): outer container --contained iff !bg, inner gri | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |
| 0.0s | unexpected | `page-builder/full-width-matrix.spec.ts:402` — every widget handle renders without console errors on the public site | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 33.0s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 28.7s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 28.5s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 27.3s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 24.3s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 22.6s | unexpected | `template-scheme.spec.ts:108` — PREVIEW: page-builder preview content scope follows the SAME scheme, faithful to |
| 22.1s | flaky | `event-registration/ticket-tier-picker.spec.ts:24` — free event with no tiers renders no quantities UI |
| 20.7s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 19.9s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 16.8s | unexpected | `event-registration/ticket-tier-picker.spec.ts:71` — multi-tier event renders one spinner per tier and sums the subtotal across them |
| 16.8s | flaky | `event-registration/ticket-tier-picker.spec.ts:42` — single-tier paid event renders one quantity spinner with default 1 and a live su |
| 16.5s | unexpected | `widget-color-tokens.spec.ts:37` — PUBLIC: migrated widget colour resolves to the --np-color-* token |
| 16.5s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 15.3s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 15.3s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |


