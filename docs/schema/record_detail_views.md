## record_detail_views

Per-record-type View definitions — a configured collection of widgets bound to a
specific record type (e.g. `App\Models\Contact`, `App\Models\PageTemplate`),
rendered inside Filament admin pages on the `record_detail_sidebar` slot.

The actual widgets live on `page_widgets` with
`owner_type = 'App\WidgetPrimitive\Views\RecordDetailView'`. No parallel
`record_detail_view_widgets` table — widget-shape storage is shared with pages,
templates, and dashboards per the widget-primitive track's polymorphic-owner
discipline.

Schema allows N Views per `record_type`. Most record types have one (the View's
widgets render on the host edit page's footer slot, no sub-nav). The exception
is template record types (e.g. `PageTemplate`) which have multiple Views (header,
footer, …) that surface as Filament record-sub-navigation entries via
`HasRecordDetailSubNavigation`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| handle | string | no | Per-record-type slug (e.g. `contact_overview`, `page_template_header`) |
| record_type | string | no | FQCN of the bound record model (e.g. `App\Models\Contact`) |
| label | string | no | Display label used by the sub-nav primitive |
| sort_order | integer | no | Default 0; ordering within a record type |
| layout_config | jsonb | yes | Mirrors `page_layouts.layout_config` shape; reserved for future View-level layout |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

**Indexes:** `handle`, `record_type`, and unique on (`record_type`, `handle`).

**Cascade:** none — `record_type` is a FQCN string, not a FK. Widgets owned by a
View are not removed by DB constraint when the View row is deleted (mirrors
`dashboard_configs`); orphan cleanup on `page_widgets` where
`owner_type = 'App\WidgetPrimitive\Views\RecordDetailView'` is the operational
answer if a View is ever deleted.

**Relation to `page_widgets`:** `RecordDetailView::pageWidgets()` is a `morphMany`
over `page_widgets` with `owner_type = 'App\WidgetPrimitive\Views\RecordDetailView'`.
The IsView contract method `widgets(): array` returns the active rows, eager-loaded
with `widgetType`, ordered by `sort_order`.

**Permissions:** none enforced at the table or registry layer in 5c — the host
Filament page's existing access gate governs reachability. Per-View permissions
land in Phase 5e (see widget-primitive track doc, "Pending paradigm questions").

**Authoring:** PHP-seeded only as of 5c (see `RecordDetailViewSeeder`). Admin UI
for per-record-type View authoring is deferred to a follow-up session.
