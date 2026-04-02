## import_logs

Legacy import log records (pre-session-038 importer). Superseded by import_sessions but retained for audit history.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| user_id | bigInteger | yes | FK→users, nullOnDelete |
| model_type | string | no | |
| filename | string | no | |
| storage_path | string | yes | |
| column_map | jsonb | yes | |
| row_count | integer | no | default: 0 |
| imported_count | integer | no | default: 0 |
| updated_count | integer | no | default: 0 |
| skipped_count | integer | no | default: 0 |
| error_count | integer | no | default: 0 |
| errors | jsonb | yes | |
| duplicate_strategy | string | no | default: 'skip' |
| custom_field_map | jsonb | yes | |
| custom_field_log | jsonb | yes | |
| status | string | no | default: 'pending' |
| started_at | timestamp | yes | |
| completed_at | timestamp | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
