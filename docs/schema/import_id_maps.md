## import_id_maps

Maps external source IDs to internal model UUIDs for re-import deduplication.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| import_source_id | uuid | no | FK→import_sources, cascade |
| model_type | string | no | |
| source_id | string | no | The external system's ID for this record |
| model_uuid | uuid | no | The internal UUID of the matched/created model |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(import_source_id, model_type, source_id)`.
