## transactions

Financial transaction ledger entries. Subject is polymorphic — one table covers donations and future payment types. `contact_id` is denormalized for efficient filtering; populated from the subject's contact where known, null for manual (off-system) entries.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| subject_type | string | yes | Polymorphic model class, e.g. App\Models\Donation |
| subject_id | string | yes | Polymorphic FK (UUID string) |
| contact_id | uuid | yes | FK → contacts.id; denormalized for filtering; null on manual entries |
| type | string | no | default: 'payment' |
| amount | decimal(10,2) | no | |
| direction | string | no | default: 'in'; values: in, out |
| status | string | no | default: 'pending'; values: pending, completed, failed |
| stripe_id | string | yes | |
| quickbooks_id | string | yes | |
| qb_sync_error | text | yes | Last sync error message; cleared on success |
| qb_synced_at | timestamp | yes | Set when QB sync succeeds |
| occurred_at | timestamp | no | default: current |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
