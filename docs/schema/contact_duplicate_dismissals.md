## contact_duplicate_dismissals

Pairs of contacts that an admin has confirmed are not duplicates. Excluded from future duplicate detection results.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id_a | uuid | no | FK→contacts, cascade; lower of the two IDs |
| contact_id_b | uuid | no | FK→contacts, cascade; higher of the two IDs |
| dismissed_by | bigint | yes | FK→users, nullOnDelete |
| dismissed_at | timestamp | no | default: now() |

Unique constraint on `(contact_id_a, contact_id_b)`.
