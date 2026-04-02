## page_widgets

Widgets embedded on a page, ordered by sort_order. Supports unlimited nesting via `parent_widget_id` (used by column layout widgets).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| page_id | uuid | no | FK→pages, cascade |
| parent_widget_id | uuid | yes | FK→page_widgets.id, set null on parent delete |
| column_index | smallint unsigned | yes | Slot index within parent column widget; null for root widgets |
| widget_type_id | uuid | no | FK→widget_types, restrictOnDelete |
| label | string | yes | |
| config | jsonb | no | default: {} |
| query_config | jsonb | no | default: {} |
| style_config | jsonb | no | default: {}; per-instance padding/margin applied as inline styles by the renderer |
| sort_order | integer | no | default: 0 |
| is_active | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
