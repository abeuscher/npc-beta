## roles

Spatie Laravel Permission — role definitions with optional display label.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| name | string | no | Machine name |
| label | string | yes | Display label; falls back to name if null |
| guard_name | string | no | Always 'web' |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(name, guard_name)`.
