## organizations

Organizations that contacts can be affiliated with (companies, foundations, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| type | string | yes | values: nonprofit, for_profit, government, other |
| website | string | yes | |
| phone | string | yes | |
| email | string | yes | |
| address_line_1 | string | yes | |
| address_line_2 | string | yes | |
| city | string | yes | |
| state | string | yes | |
| postal_code | string | yes | |
| country | string | yes | default: 'US' |
| source | string | no | default: `'human'`. `Organization::ACCEPTED_SOURCES` = `[HUMAN, IMPORT, SCRUB_DATA]`. Indexed. |
| custom_fields | jsonb | yes | Free-form key/value bag for user-defined custom fields landed via the Org importer's `__custom_organization__` sentinel. Read/write via the `'array'` cast. |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set on rows created by the Organizations importer. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set on rows created by the Organizations importer. |
| external_id | string | yes | Source-system identifier. Set by the Organizations importer for dedupe on re-import. Null for admin-authored orgs and for stubs auto-created by Contact / Donation / Membership / Event / Invoice imports via the `__org_*__` sentinels. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |

Indexes:

- `organizations_source_index` on `(source)` — source-scoped wipe + filter.
- `organizations_import_external_idx` on `(import_source_id, external_id)` — fast upsert lookup for the Organizations importer on re-import.
