## role_has_permissions

Spatie Laravel Permission — role-to-permission assignments.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| permission_id | bigInteger | no | FK→permissions, cascade |
| role_id | bigInteger | no | FK→roles, cascade |

Composite PK on `(permission_id, role_id)`.
