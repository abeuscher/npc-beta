## collection_items

Individual items within a collection.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| collection_id | uuid | no | FK→collections, cascade |
| data | jsonb | no | default: {} |
| sort_order | integer | no | default: 0 |
| is_published | boolean | no | default: false |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
