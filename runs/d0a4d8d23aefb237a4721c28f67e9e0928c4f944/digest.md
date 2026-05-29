# Playwright diagnostic digest (A007)

- total specs run: **46**
- passed: **46**  ·  failed: **0**  ·  flaky: **0**  ·  skipped: **0**
- wall time: **405.4s**

## Non-passing specs (0)

_none — suite green_

## 15 slowest specs (uniform-timeout signature check)

| dur | status | spec |
|----:|:------|:-----|
| 43.0s | expected | `importer/contacts-update-strategy.spec.ts:28` — re-import with update strategy stages 5 changes and approve applies them |
| 26.7s | expected | `importer/contacts-duplicate-header.spec.ts:30` — duplicate Email header surfaces in review step and import proceeds with default  |
| 20.5s | expected | `importer/contacts-pii-rejection.spec.ts:25` — contacts CSV with SSN-format values in an extra column is rejected |
| 19.0s | expected | `importer/contacts-happy-path.spec.ts:27` — imports 5 contacts end-to-end and lands in pending-review |
| 19.0s | expected | `importer/donations-error-report.spec.ts:43` — donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloa |
| 12.3s | expected | `notes/notes-permissions.spec.ts:50` — toggle on — op_b cannot see Edit / Delete on op_a-authored note |
| 8.5s | expected | `importer/donations-mapping-indicator.spec.ts:24` — mapping select exposes a search input when opened |
| 6.8s | expected | `importer/site-import-export.spec.ts:30` — Import Site analyzes the bundle on upload and reveals gated toggles |
| 6.5s | expected | `page-builder/inline-editing-foundation.spec.ts:142` — the top-left grip reorders the widget and the new order persists |
| 5.7s | expected | `notes/notes-permissions.spec.ts:42` — toggle off baseline — op_b sees Edit and Delete on op_a-authored note |
| 5.4s | expected | `notes/notes-permissions.spec.ts:66` — toggle on + edit_others_note granted — op_b can edit again |
| 4.9s | expected | `page-builder/inline-editing-phase2.spec.ts:95` — round-trips a nested plaintext path (columns.0.title) to raw config |
| 4.8s | expected | `page-builder/inline-editing-phase2.spec.ts:126` — round-trips a nested richtext path (columns.0.attribute_rows.0.value) |
| 4.5s | expected | `memos/memos-trix-to-quill.spec.ts:41` — Memos item save round-trip persists Quill-authored content |
| 4.4s | expected | `page-builder/inline-editing-foundation.spec.ts:126` — the overlay body is no longer a drag handle (whole-panel reorder gone) |


