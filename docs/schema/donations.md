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
| stripe_subscription_id | string | yes | Stripe subscription ID for recurring donations; null for one_off |
| stripe_customer_id | string | yes | Stripe customer ID; set for recurring donations |
| started_at | timestamp | yes | Set when status transitions to active |
| ended_at | timestamp | yes | Set when subscription is cancelled |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Index on `contact_id`, `fund_id`.
