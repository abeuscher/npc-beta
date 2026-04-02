## navigation_items

Menu items within a navigation menu, supporting nested hierarchy.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| navigation_menu_id | uuid | no | FK→navigation_menus, cascade |
| label | string | no | |
| url | string | yes | |
| page_id | uuid | yes | FK→pages, nullOnDelete |
| parent_id | uuid | yes | FK→navigation_items (self), nullOnDelete |
| sort_order | integer | no | default: 0 |
| target | string | no | default: '_self' |
| is_visible | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
