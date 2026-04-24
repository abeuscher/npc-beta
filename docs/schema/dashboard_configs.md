## dashboard_configs

Per-role admin dashboard arrangement. Each row is a cheap polymorphic owner for
widgets placed on the `dashboard_grid` slot — the actual widgets live on
`page_widgets` with `owner_type = 'App\Models\DashboardConfig'`. No parallel
`dashboard_widgets` table; the widget-shape storage is shared with pages and
templates per the widget-primitive migration doc's "Polymorphic owner discipline".

One row per Spatie `Role`. When a user has multiple roles, the dashboard is
resolved from their first role by ascending `roles.id` (documented rule — see
`DashboardConfig::forUser()`).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| role_id | unsignedBigInteger | no | FK→roles.id, cascade on delete, unique |
| label | string | yes | Optional per-role display label; leave null at seed time |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

**Indexes:** unique on `role_id`.

**Cascade:** deleting a role cascades the owning `DashboardConfig` row. Widgets
owned by that config are not removed by DB constraint — `page_widgets` has no
DB-level FK on `owner_id`. If a role is ever deleted, an orphan-cleanup pass
on `page_widgets` where `owner_type = 'App\Models\DashboardConfig'` and no
matching owner exists is the operational answer; no such pass exists today.

**Relation to `page_widgets`:** `DashboardConfig::widgets()` is a `morphMany`
over `page_widgets` with `owner_type = 'App\Models\DashboardConfig'`. Dashboard
widgets are always root (`layout_id` NULL, `column_index` NULL) — the slot's
`layoutConstraints()['column_stackable']` is `false`, and no nesting UI exists.
