# Playwright diagnostic digest (A007)

- total specs run: **46**
- passed: **44**  ·  failed: **1**  ·  flaky: **1**  ·  skipped: **0**
- wall time: **414.7s**

## Non-passing specs (2)

| dur | status | spec | error (first lines) |
|----:|:------|:-----|:--------------------|
| 22.7s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and writes background + padding to the lay | Error: Timed out 20000ms waiting for expect(locator).toBeVisible() / Locator: locator('.layout-inspector').locator('.inspector-tabs').getByRole('button', { name: 'Column Settings' }) / Expected: visible |
| 2.3s | flaky | `page-builder/inline-editing-foundation.spec.ts:79` — body-click selects the widget (no whole-panel button needed) | TimeoutError: page.waitForURL: Timeout 20000ms exceeded. / =========================== logs =========================== / waiting for navigation until "load" |

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 33.6s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 22.8s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 22.7s | unexpected | `page-builder/layout-inspector.spec.ts:26` — drives the three-tab layout inspector and writes background + padding to the lay |
| 17.2s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 15.3s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 15.2s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 8.5s | expected | `notes/notes-permissions.spec.ts:50` — toggle on — op_b cannot see Edit / Delete on op_a-authored note |
| 7.4s | expected | `importer/donations-mapping-indicator.spec.ts:24` — mapping select exposes a search input when opened |
| 5.3s | expected | `page-builder/inline-editing-foundation.spec.ts:142` — the top-left grip reorders the widget and the new order persists |
| 5.3s | expected | `importer/site-import-export.spec.ts:30` — Import Site analyzes the bundle on upload and reveals gated toggles |
| 4.4s | expected | `notes/notes-permissions.spec.ts:66` — toggle on + edit_others_note granted — op_b can edit again |
| 4.1s | expected | `page-builder/inline-editing-phase2.spec.ts:126` — round-trips a nested richtext path (columns.0.attribute_rows.0.value) |
| 3.9s | expected | `notes/notes-permissions.spec.ts:42` — toggle off baseline — op_b sees Edit and Delete on op_a-authored note |
| 3.8s | expected | `page-builder/inline-editing-foundation.spec.ts:126` — the overlay body is no longer a drag handle (whole-panel reorder gone) |
| 3.3s | expected | `memos/memos-trix-to-quill.spec.ts:41` — Memos item save round-trip persists Quill-authored content |


