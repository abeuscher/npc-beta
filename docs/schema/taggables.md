## taggables

Polymorphic pivot table linking tags to any taggable model.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| tag_id | uuid | no | FK→tags, cascade |
| taggable_id | uuid | no | Polymorphic FK |
| taggable_type | string | no | |

Composite PK on `(tag_id, taggable_id, taggable_type)`.
