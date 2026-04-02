## waitlist_entries

Contacts waiting for a sold-out product. Processing is manual; no automated slot-holding.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| product_id | uuid | no | FK→products, cascadeOnDelete |
| contact_id | uuid | yes | FK→contacts, nullOnDelete |
| status | string | no | default: 'waiting'; values: waiting, notified, converted, cancelled |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint enforced in application: one entry per contact/product combination.
