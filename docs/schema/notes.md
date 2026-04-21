## notes

User-authored interactions attached to any model via polymorphic relationship (contacts, organizations, etc.). Covers calls, meetings, emails, tasks, letters, SMS, and plain notes — distinguished by the `type` column. Coexists with `activity_logs` (automatic CUD audit stream) on the Contact / Organization Timeline.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| notable_id | uuid | no | Polymorphic FK |
| notable_type | string | no | |
| author_id | bigInteger | yes | FK→users, nullOnDelete |
| type | string | no | default: `'note'`. Canonical values (known-with-icons in UI): `call`, `meeting`, `email`, `note`, `task`, `letter`, `sms`. Free-text tolerated so importers preserve source vocabulary. |
| subject | string | yes | Optional short title / headline. |
| status | string | no | default: `'completed'`. Canonical values: `completed`, `scheduled`, `cancelled`, `left_message`, `no_show`. Free-text tolerated. |
| body | text | no | Rich HTML (Quill-authored). Rendered with `{!! !!}` on the Timeline. |
| occurred_at | timestamp | no | default: current |
| follow_up_at | timestamp | yes | "Next action" date. Combined with `type='task'` + `status='scheduled'` to represent an open task. |
| outcome | text | yes | Short summary of result — typically paired with call/meeting types. |
| duration_minutes | integer | yes | Interaction length in minutes. |
| meta | jsonb | yes | Source-specific fields not captured by the ten structured columns (priority, location, multiple participants, custom CSV columns, etc.). Write-once from the importer; read-displayed on the Timeline. Not user-authorable via the admin forms. |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set on notes created by the contact importer so the timeline can deep-link to the source. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set on notes created by the standalone notes importer so the review/approve/rollback flow can scope to the session. |
| external_id | string | yes | Source-system identifier. Set by the standalone notes importer for dedupe on re-import. Null for admin-authored notes and for notes created by the contacts-importer `__note_contact__` appendage. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |

Indexes:

- `notes_notable_type_notable_id_index` on `(notable_type, notable_id)` — polymorphic lookup.
- `notes_notable_occurred_at_index` on `(notable_type, notable_id, occurred_at DESC)` — Timeline queries.
- `notes_type_index` on `(type)` — Timeline type-filter queries.
- `notes_follow_up_at_index` on `(follow_up_at) WHERE follow_up_at IS NOT NULL` — partial index for future follow-up dashboard.
- `notes_import_external_idx` on `(import_source_id, external_id)` — dedupe lookup for the notes importer on re-import.
