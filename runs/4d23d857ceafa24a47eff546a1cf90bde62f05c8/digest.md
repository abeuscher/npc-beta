# Playwright diagnostic digest

- total specs run: **66**
- passed: **30**  ·  failed: **14**  ·  flaky: **0**  ·  skipped: **22**
- wall time: **953.5s**

## Non-passing specs (14)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 22.3s | unexpected | `event-registration/ticket-tier-picker.spec.ts:24` — free event with no tiers renders no quantities UI | Error: Timed out 20000ms waiting for expect(locator).toBeVisible() / Locator: getByRole('heading', { name: 'Register', exact: true }) / Expected: visible |
| 22.1s | unexpected | `template-scheme.spec.ts:75` — PUBLIC inverse scheme: content region follows, chrome holds (compose-not-bleed) | Error: Timed out 20000ms waiting for expect(locator).toHaveCSS(expected) / Locator: locator('main') / Expected string: "rgb(17, 24, 39)" |
| 17.5s | unexpected | `page-builder/inline-editing-phase2.spec.ts:74` — unselected preview is pixel-clean; selecting arms the prose nodes | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="9a0af333-e665-4709-b45c-f9acb1270bb3"]') / Expected: visible |
| 17.5s | unexpected | `page-builder/full-width-matrix.spec.ts:273` — editor renders solid bg color set via DB on a column | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[data-layout-id="c33dadd0-dcd7-4656-8d9e-3d27db3d5323"]') / Expected: visible |
| 17.5s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and persists background + padding | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[data-layout-id="01dfddcb-072f-45f7-bbeb-3ac5ae5bacee"]') / Expected: visible |
| 17.5s | unexpected | `dashboard-settings/dashboard-settings.spec.ts:26` — loads, adds a widget, and the DB reflects the new arrangement | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: getByText('Dashboard arrangement') / Expected: visible |
| 17.5s | unexpected | `memos/memos-trix-to-quill.spec.ts:28` — Memos item create modal mounts QuillEditor (not Trix) | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.ql-toolbar').first() / Expected: visible |
| 17.5s | unexpected | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="f4b336db-9801-4613-ba74-3e6d7e2896f0"]') / Expected: visible |
| 16.9s | unexpected | `widget-color-tokens.spec.ts:37` — PUBLIC: migrated widget colour resolves to the --np-color-* token | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.widget-blog-listing__empty').first() / Expected: visible |
| 0.0s | unexpected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  | Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan csv:generate-fake-imports --seed=4202 |
| 0.0s | unexpected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected | Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan csv:generate-fake-imports --seed=4201 |
| 0.0s | unexpected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa | Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan csv:generate-fake-imports --seed=4203 |
| 0.0s | unexpected | `importer/invoice-details-grouping.spec.ts:26` — line-item ordering is preserved for a 3+ line-item invoice group | Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan csv:generate-fake-imports --seed=4204 |
| 0.0s | unexpected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact | Error: Command failed: docker compose -p nonprofitcrm_e2e -f docker-compose.e2e.yml exec -T app php artisan csv:generate-fake-imports --seed=4206 |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 32.0s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 30.4s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 28.5s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 28.4s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 22.6s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 22.3s | unexpected | `event-registration/ticket-tier-picker.spec.ts:24` — free event with no tiers renders no quantities UI |
| 22.1s | unexpected | `template-scheme.spec.ts:75` — PUBLIC inverse scheme: content region follows, chrome holds (compose-not-bleed) |
| 17.5s | unexpected | `page-builder/inline-editing-phase2.spec.ts:74` — unselected preview is pixel-clean; selecting arms the prose nodes |
| 17.5s | unexpected | `page-builder/full-width-matrix.spec.ts:273` — editor renders solid bg color set via DB on a column |
| 17.5s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and persists background + padding |
| 17.5s | unexpected | `dashboard-settings/dashboard-settings.spec.ts:26` — loads, adds a widget, and the DB reflects the new arrangement |
| 17.5s | unexpected | `memos/memos-trix-to-quill.spec.ts:28` — Memos item create modal mounts QuillEditor (not Trix) |
| 17.5s | unexpected | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) |
| 16.9s | unexpected | `widget-color-tokens.spec.ts:37` — PUBLIC: migrated widget colour resolves to the --np-color-* token |
| 14.1s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |


