# Playwright diagnostic digest

- total specs run: **63**
- passed: **55**  ·  failed: **3**  ·  flaky: **3**  ·  skipped: **2**
- wall time: **968.3s**

## Non-passing specs (6)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 22.8s | unexpected | `widget-color-tokens.spec.ts:50` — PREVIEW: page-builder preview resolves the token faithfully | Error: Timed out 20000ms waiting for expect(locator).toBeVisible() / Locator: locator('.np-site .widget-blog-listing__empty').first() / Expected: visible |
| 17.9s | flaky | `page-builder/inline-editing-phase2.spec.ts:98` — round-trips a nested plaintext path (columns.0.title) to raw config | Error: expect(received).toBe(expected) // Object.is equality / Expected: "Starter Plan" / Received: "BasicStarter Plan" |
| 17.2s | flaky | `event-registration/ticket-tier-picker.spec.ts:71` — multi-tier event renders one spinner per tier and sums the subtotal across them | Error: Timed out 15000ms waiting for expect(locator).toHaveCount(expected) / Locator: locator('input[name="quantities[a1f549f2-ef72-45b7-8118-0575f8785b1f]"]') / Expected: 1 |
| 17.2s | unexpected | `event-registration/ticket-tier-picker.spec.ts:106` — sold-out tier disables the spinner at max=0 | Error: Timed out 15000ms waiting for expect(locator).toBeEnabled() / Locator: locator('input[name="quantities[5d88fd53-aebb-4418-aabe-7143529d7b34]"]') / Expected: enabled |
| 8.3s | unexpected | `page-builder/inline-editing-phase2.spec.ts:125` — round-trips a nested richtext path (columns.0.attribute_rows.0.value) | Error: Timed out 5000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="109a44de-db36-4a03-8cee-9a8ded64588b"]').locator('[data-config-key="columns.0.attribute_rows.0.value"]').locator('.ql-editor') / Expected: visible |
| 2.0s | flaky | `page-builder/full-width-matrix.spec.ts:337` — editor (bg:false, content:false): outer container --contained iff !bg, inner gri | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 41.8s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 35.7s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 35.2s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 33.8s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 31.9s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 26.4s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.8s | unexpected | `widget-color-tokens.spec.ts:50` — PREVIEW: page-builder preview resolves the token faithfully |
| 22.5s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 19.3s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 19.1s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 18.0s | expected | `importer/invoice-details-grouping.spec.ts:66` — conflicting parent-invoice fields resolve via first-row-wins |
| 17.9s | flaky | `page-builder/inline-editing-phase2.spec.ts:98` — round-trips a nested plaintext path (columns.0.title) to raw config |
| 17.4s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 17.3s | expected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group |
| 17.2s | flaky | `event-registration/ticket-tier-picker.spec.ts:71` — multi-tier event renders one spinner per tier and sums the subtotal across them |


