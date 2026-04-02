## import_staged_updates

Staged field changes for existing contacts matched during an import. Applied (or discarded) when the import session is approved or rolled back.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| import_session_id | uuid | no | FK→import_sessions, cascadeOnDelete |
| contact_id | uuid | no | FK→contacts, cascadeOnDelete |
| attributes | jsonb | yes | Proposed field changes to apply on approval |
| tag_ids | jsonb | yes | Tag UUIDs to syncWithoutDetaching on approval |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
