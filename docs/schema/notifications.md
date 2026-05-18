## notifications

Standard Laravel notifications table. Added at session 303 (migration `2026_05_18_130000_create_notifications_table.php`) to back Filament's persistent notification bell — the delivery surface for queued export/import results (media-portability draft decision #5). Nothing needed async notifications before this. The `data` column is **`json`** (not Laravel's stock `text`) so Filament's bell query — `data->>'format' = 'filament'` — works on PostgreSQL (the `->>` operator requires `json`/`jsonb`).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| type | string | no | Notification class name |
| notifiable_type | string | no | Morph type (e.g. App\Models\User) |
| notifiable_id | bigint | no | Morph id |
| data | json | no | Serialized notification payload (Filament reads `data->>'format'`) |
| read_at | timestamp | yes | Null until the recipient marks it read |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

**Deployment note:** if an environment already ran the original (stock `text`) form of this migration, roll it back one step and re-migrate so `data` is `json` — `php artisan migrate:rollback --step=1 && php artisan migrate` (the table is empty, so this is safe). Fresh `migrate` runs get `json` directly.
