## dashboard_views

Per-role admin dashboard arrangement. Each row is a cheap polymorphic owner for
widgets placed on the `dashboard_grid` slot — the actual widgets live on
`page_widgets` with `owner_type = 'App\WidgetPrimitive\Views\DashboardView'`.
No parallel `dashboard_widgets` table; the widget-shape storage is shared with
pages, templates, and record-detail Views per the widget-primitive track's
polymorphic-owner discipline.

`DashboardView` implements `App\WidgetPrimitive\IsView` (the shared
widget-composition primitive). It speaks the View vocabulary natively alongside
`RecordDetailView`. Per-role keying is the View's natural index, so this table
keeps `role_id` as the unique key rather than the `(record_type, handle)` shape
used by `record_detail_views`. The `IsView::handle()` accessor returns the
role-name slug derived from the linked `Role`.

One row per Spatie `Role`. When a user has multiple roles, the dashboard is
resolved from their first role by ascending `roles.id` (documented rule — see
`DashboardView::forUser()`).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| role_id | unsignedBigInteger | no | FK→roles.id, cascade on delete, unique |
| label | string | yes | Optional per-role display label; leave null at seed time |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

**Indexes:** unique on `role_id`.

**Cascade:** deleting a role cascades the owning `DashboardView` row. Widgets
owned by that view are not removed by DB constraint — `page_widgets` has no
DB-level FK on `owner_id`. If a role is ever deleted, an orphan-cleanup pass
on `page_widgets` where `owner_type = 'App\WidgetPrimitive\Views\DashboardView'`
and no matching owner exists is the operational answer; no such pass exists today.

**Relation to `page_widgets`:** `DashboardView::pageWidgets()` is a `morphMany`
over `page_widgets` with `owner_type = 'App\WidgetPrimitive\Views\DashboardView'`.
The IsView contract method `widgets(): array` returns the active rows,
eager-loaded with `widgetType`, ordered by `sort_order`. Dashboard widgets are
always root (`layout_id` NULL, `column_index` NULL) — the slot's
`layoutConstraints()['column_stackable']` is `false`, and no nesting UI exists.

**History:** the table was renamed from `dashboard_configs` in session 236 when
the model was lifted from `App\Models\DashboardConfig` into the View vocabulary
(`App\WidgetPrimitive\Views\DashboardView` implementing `IsView`). The original
table landed in session 215.
