## model_has_roles

Spatie Laravel Permission — model-to-role assignments.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| role_id | bigInteger | no | FK→roles, cascade |
| model_type | string | no | |
| model_id | bigInteger | no | |

Composite PK on `(role_id, model_id, model_type)`. Index on `(model_id, model_type)`.
