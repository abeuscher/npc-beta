## page_layouts

Layout containers (column layouts) that hold widgets in a multi-column arrangement. Layouts participate in the page flow alongside root widgets, ordered by `sort_order`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| page_id | uuid | no | FK→pages, cascade |
| label | string | yes | User-visible name |
| display | string | no | default: 'grid'; values: flex, grid |
| columns | integer | no | default: 2; number of column slots |
| layout_config | jsonb | no | default: {}; CSS properties: grid_template_columns, gap, flex_wrap, justify_content, align_items, justify_items, grid_auto_rows, per-column flex-basis array, etc. |
| sort_order | integer | no | default: 0; position in the merged page flow |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
