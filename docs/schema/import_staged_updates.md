## import_staged_updates

Staged field changes for existing records matched during an import. Polymorphic — one table covers contacts, events, donations, memberships, and transactions. Applied (or discarded) when the import session is approved or rolled back.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| import_session_id | uuid | no | FK→import_sessions, cascadeOnDelete |
| subject_type | string | no | Polymorphic model class, e.g. `App\Models\Contact`, `App\Models\Event`, `App\Models\Donation`, `App\Models\Membership`, `App\Models\Transaction` |
| subject_id | uuid | no | Polymorphic FK (UUID) — the existing record being updated |
| attributes | jsonb | yes | Proposed field changes to apply on approval |
| tag_ids | jsonb | yes | Tag UUIDs to syncWithoutDetaching on approval; contacts only |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(subject_type, subject_id)` — `import_staged_updates_subject_type_subject_id_index`.
- `(import_session_id)` — `import_staged_updates_import_session_id_index`.
