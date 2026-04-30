## contacts

Individual people in the CRM — donors, volunteers, members, etc.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| organization_id | uuid | yes | FK→organizations, nullOnDelete |
| household_id | uuid | yes | FK→contacts (self), nullOnDelete; equals `id` when contact is solo/head |
| prefix | string | yes | |
| first_name | string | yes | |
| last_name | string | yes | |
| email | string | yes | indexed |
| phone | string | yes | |
| address_line_1 | string | yes | |
| address_line_2 | string | yes | |
| city | string | yes | |
| state | string | yes | |
| postal_code | string | yes | |
| date_of_birth | date | yes | |
| country | string | yes | default: 'US' |
| do_not_contact | boolean | no | default: false |
| mailing_list_opt_in | boolean | no | default: false |
| source | string | no | default: 'manual'; values: manual, import, web_form, api, scrub_data |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete |
| custom_data | jsonb | yes | SchemalessAttributes; written by importer |
| custom_fields | jsonb | yes | User-defined custom field values |
| quickbooks_customer_id | string | yes | QB Customer ID, cached after first match/create |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
