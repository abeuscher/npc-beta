## custom_field_defs

Definitions for user-created custom fields on contacts and events.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| model_type | string | no | e.g. 'contact', 'event' |
| handle | string | no | unique per model_type |
| label | string | no | |
| field_type | string | no | default: 'text' |
| options | jsonb | yes | For select/checkbox field types |
| sort_order | unsignedInteger | no | default: 0 |
| is_filterable | boolean | no | default: false; marks field as available in mailing-list filter UI |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(model_type, handle)`.
