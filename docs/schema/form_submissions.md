## form_submissions

Immutable records of individual web form submissions.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | no | PK |
| form_id | bigint | no | FK→forms, cascadeOnDelete; indexed |
| contact_id | uuid | yes | FK→contacts, nullOnDelete; indexed; set when form_type=contact and contact is created/updated |
| data | json | no | key/value map of field handle → submitted value |
| ip_address | string | yes | |
| created_at | timestamp | no | no updated_at — submissions are immutable |
| deleted_at | timestamp | yes | Soft delete |
