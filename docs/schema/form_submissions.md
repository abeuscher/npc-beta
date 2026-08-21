## form_submissions

**Owned by the Forms plugin (`nonprofitcrm/forms`, extracted to `crm-plugin--forms` session 397)** — created by the plugin's `database/migrations/`, not core's schema dump.

Immutable records of individual web form submissions. The `FormSubmission` model stays core (plan § 6.7).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| form_id | bigint | no | FK→forms, cascadeOnDelete; indexed |
| contact_id | uuid | yes | FK→contacts, nullOnDelete; indexed; set when form_type=contact and contact is created/updated |
| data | json | no | key/value map of field handle → submitted value |
| ip_address | string | yes | |
| created_at | timestamp | no | no updated_at — submissions are immutable |
| deleted_at | timestamp | yes | Soft delete |
