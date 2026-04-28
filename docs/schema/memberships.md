## memberships

Membership records for contacts, including tier, status, and dates. Multiple records per contact are normal (history preserved).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id | uuid | no | FK→contacts, restrictOnDelete |
| tier_id | uuid | yes | FK→membership_tiers, set null on delete |
| status | string | no | default: 'pending'; values: pending, active, expired, cancelled |
| source | string | no | default: human; values: human, import, stripe_webhook (per `Membership::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
| starts_on | date | yes | |
| expires_on | date | yes | null for lifetime tiers |
| amount_paid | decimal(10,2) | yes | |
| stripe_session_id | string | yes | Stripe Checkout session ID for paid memberships |
| stripe_subscription_id | string | yes | Stripe subscription ID for recurring memberships |
| notes | text | yes | |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set for imported memberships. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for imported memberships so rollback can cascade. |
| external_id | string | yes | Source-system record ID for dedupe. |
| custom_fields | jsonb | yes | User-defined custom field values. Populated by the memberships importer when columns are mapped as `__custom_membership__`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |

Indexes:
- `(import_source_id, external_id)` — `memberships_import_external_idx`.
- `(source)` — `memberships_source_index`.
