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
| match_key | string | no | default: 'email'. Contact field key (or custom field handle) used to match existing rows. |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set when the import was run against a saved source; enables per-source filtering on the history page. |
| column_preferences | jsonb | no | default: `{}`. Map of destination-field → preferred source-header for imports where the user resolved a multi-column mapping collision. Processor applies the preferred column's value when non-blank, falling back to other mapped columns otherwise. |
| relational_map | jsonb | no | default: `{}`. Keys = source headers mapped to a relational destination (`__org__`, `__note__`, `__tag__`). Values carry the per-column sub-configuration (org strategy, note delimiter + skip-blanks, tag delimiter). Empty when no relational columns were mapped. |
| custom_field_map | jsonb | yes | |
| custom_field_log | jsonb | yes | |
| status | string | no | default: 'pending' |
| started_at | timestamp | yes | |
| completed_at | timestamp | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
