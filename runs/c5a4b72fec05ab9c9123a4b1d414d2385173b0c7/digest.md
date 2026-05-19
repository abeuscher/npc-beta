# Playwright diagnostic digest

- total specs run: **66**
- passed: **38**  ·  failed: **8**  ·  flaky: **2**  ·  skipped: **18**
- wall time: **1226.4s**

## Non-passing specs (10)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 59.7s | flaky | `importer/donations-mapping-indicator.spec.ts:80` — mapping select exposes a search input when opened | Error: expect(received).toBeGreaterThan(expected) / Expected: > 0 / Received:   0 |
| 22.6s | unexpected | `template-scheme.spec.ts:75` — PUBLIC inverse scheme: content region follows, chrome holds (compose-not-bleed) | Error: Timed out 20000ms waiting for expect(locator).toHaveCSS(expected) / Locator: locator('main') / Expected string: "rgb(17, 24, 39)" |
| 18.3s | unexpected | `page-builder/inline-editing-phase2.spec.ts:74` — unselected preview is pixel-clean; selecting arms the prose nodes | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="af8ee507-6648-40a8-a0d3-f33174b08e18"]') / Expected: visible |
| 18.2s | unexpected | `page-builder/full-width-matrix.spec.ts:273` — editor renders solid bg color set via DB on a column | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[data-layout-id="a3036db9-3b33-4992-bb81-78633a46000e"]') / Expected: visible |
| 18.2s | unexpected | `dashboard-settings/dashboard-settings.spec.ts:26` — loads, adds a widget, and the DB reflects the new arrangement | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: getByText('Dashboard arrangement') / Expected: visible |
| 18.2s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and persists background + padding | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('[data-layout-id="f7deba51-9aa2-40cf-9a27-a0802ec55c02"]') / Expected: visible |
| 18.2s | unexpected | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.preview-region[data-widget-id="2e5c1c1f-4084-4ee2-a12c-782858fcd630"]') / Expected: visible |
| 17.9s | unexpected | `memos/memos-trix-to-quill.spec.ts:28` — Memos item create modal mounts QuillEditor (not Trix) | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.ql-toolbar').first() / Expected: visible |
| 17.6s | unexpected | `widget-color-tokens.spec.ts:37` — PUBLIC: migrated widget colour resolves to the --np-color-* token | Error: Timed out 15000ms waiting for expect(locator).toBeVisible() / Locator: locator('.widget-blog-listing__empty').first() / Expected: visible |
| 17.3s | flaky | `event-registration/ticket-tier-picker.spec.ts:106` — sold-out tier disables the spinner at max=0 | Error: Timed out 15000ms waiting for expect(locator).toBeEnabled() / Locator: locator('input[name="quantities[c91aa8cc-d9ff-4b74-a1d1-b23353b64b06]"]') / Expected: enabled |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 59.7s | flaky | `importer/donations-mapping-indicator.spec.ts:80` — mapping select exposes a search input when opened |
| 39.2s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 36.6s | expected | `importer/events-update-strategy.spec.ts:36` — re-import with update strategy stages title changes; approval applies them |
| 34.3s | expected | `importer/memberships-update-strategy.spec.ts:36` — re-import with update strategy stages notes changes; approval applies them |
| 34.2s | expected | `importer/donations-update-strategy.spec.ts:36` — re-import with update strategy stages amount changes; approval applies them |
| 32.0s | expected | `importer/invoice-details-update-strategy.spec.ts:33` — re-import with update strategy stages line-item changes; approval applies them |
| 24.4s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.6s | unexpected | `template-scheme.spec.ts:75` — PUBLIC inverse scheme: content region follows, chrome holds (compose-not-bleed) |
| 22.3s | expected | `importer/notes-happy-path.spec.ts:36` — imports every note from the generated notes.csv and attaches each to its contact |
| 18.4s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 18.3s | unexpected | `page-builder/inline-editing-phase2.spec.ts:74` — unselected preview is pixel-clean; selecting arms the prose nodes |
| 18.2s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 18.2s | unexpected | `page-builder/full-width-matrix.spec.ts:273` — editor renders solid bg color set via DB on a column |
| 18.2s | unexpected | `dashboard-settings/dashboard-settings.spec.ts:26` — loads, adds a widget, and the DB reflects the new arrangement |
| 18.2s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and persists background + padding |


