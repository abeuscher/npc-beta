## donations

Stripe-backed donation records. One row per donation commitment (one-off or recurring). Completed payments are recorded as `transactions` with `subject_type = 'App\Models\Donation'`. Created by `DonationCheckoutController` on checkout initiation; updated by `StripeWebhookController` on payment completion.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id | uuid | yes | FK→contacts, nullOnDelete; set by webhook after Stripe confirms payment |
| fund_id | uuid | yes | FK→funds, nullOnDelete; null = unrestricted/general fund |
| type | string | no | Values: one_off, recurring |
| amount | decimal(10,2) | no | Amount in dollars; validated min $1 / max $10,000 |
| currency | string(3) | no | default: usd |
| frequency | string | yes | Values: monthly, annual; null for one_off |
| status | string | no | default: pending; values: pending, active, cancelled, past_due |
| source | string | no | default: stripe_webhook; values: import, stripe_webhook (per `Donation::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
| stripe_subscription_id | string | yes | Stripe subscription ID for recurring donations; null for one_off |
| stripe_customer_id | string | yes | Stripe customer ID; set for recurring donations |
| started_at | timestamp | yes | Set when status transitions to active |
| ended_at | timestamp | yes | Set when subscription is cancelled |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set for imported donations. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for imported donations so rollback can cascade. |
| external_id | string | yes | Source-system record ID for dedupe. |
| custom_fields | jsonb | yes | User-defined custom field values. Populated by the donations importer when columns are mapped as `__custom_donation__`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(contact_id)` — `donations_contact_id_index`.
- `(fund_id)` — `donations_fund_id_index`.
- `(import_source_id, external_id)` — `donations_import_external_idx`.
- `(source)` — `donations_source_index`.
