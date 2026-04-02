## import_sessions

Import sessions for the current batch-import workflow with review and approval.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| session_label | string | yes | User-provided display name for the session |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete |
| model_type | string | no | |
| status | string | no | default: 'pending'; values: pending, reviewing, approved, rolled_back |
| filename | string | yes | |
| row_count | integer | yes | |
| tag_ids | jsonb | yes | UUIDs of tags to apply to imported contacts |
| imported_by | bigInteger | yes | FK→users, nullOnDelete |
| approved_by | bigInteger | yes | FK→users, nullOnDelete |
| approved_at | timestamp | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
