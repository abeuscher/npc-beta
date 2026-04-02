## model_has_permissions

Spatie Laravel Permission — direct model-to-permission assignments.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| permission_id | bigInteger | no | FK→permissions, cascade |
| model_type | string | no | |
| model_id | bigInteger | no | |

Composite PK on `(permission_id, model_id, model_type)`. Index on `(model_id, model_type)`.
