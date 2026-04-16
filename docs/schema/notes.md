## notes

Notes attached to any model via polymorphic relationship (contacts, organizations, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| notable_id | uuid | no | Polymorphic FK |
| notable_type | string | no | |
| author_id | bigInteger | yes | FK→users, nullOnDelete |
| body | text | no | |
| occurred_at | timestamp | no | default: current |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set on notes created by the contact importer so the timeline can deep-link to the source. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
