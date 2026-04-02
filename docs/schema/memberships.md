## memberships

Membership records for contacts, including tier, status, and dates. Multiple records per contact are normal (history preserved).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id | uuid | no | FK→contacts, restrictOnDelete |
| tier_id | uuid | yes | FK→membership_tiers, set null on delete |
| status | string | no | default: 'pending'; values: pending, active, expired, cancelled |
| starts_on | date | yes | |
| expires_on | date | yes | null for lifetime tiers |
| amount_paid | decimal(10,2) | yes | |
| stripe_session_id | string | yes | Stripe Checkout session ID for paid memberships |
| stripe_subscription_id | string | yes | Stripe subscription ID for recurring memberships |
| notes | text | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
