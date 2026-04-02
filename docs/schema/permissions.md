## permissions

Spatie Laravel Permission — permission definitions.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| name | string | no | |
| guard_name | string | no | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(name, guard_name)`.
