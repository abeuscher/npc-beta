## donations

Stripe-backed donation records. One row per donation commitment (one-off or recurring). Completed payments are recorded as `transactions` with `subject_type = 'App\Models\Donation'`. Created by `DonationCheckoutController` on checkout initiation; updated by `StripeWebhookController` on payment completion.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| contact_id | uuid | yes | FK→contacts, nullOnDelete; set by webhook after Stripe confirms payment |
| organization_id | uuid | yes | FK→organizations, nullOnDelete; set when the donation is corporate (Org-as-source). Coexists non-exclusively with `contact_id`. |
| fund_id | uuid | yes | FK→funds, nullOnDelete; null = unrestricted/general fund |
| type | string | no | Values: one_off, recurring |
| amount | decimal(10,2) | no | Amount in dollars; validated min $1 / max $10,000 |
| currency | string(3) | no | default: usd |
| frequency | string | yes | Values: monthly, annual; null for one_off |
| status | string | no | default: pending; values: pending, active, cancelled, past_due |
| source | string | no | default: stripe_webhook; values: import, stripe_webhook, scrub_data (per `Donation::ACCEPTED_SOURCES`). Origin discriminator — orthogonal to `status`. |
| stripe_subscription_id | string | yes | Stripe subscription ID for recurring donations; null for one_off |
| stripe_customer_id | string | yes | Stripe customer ID; set for recurring donations |
| started_at | timestamp | yes | Set when status transitions to active |
| ended_at | timestamp | yes | Set when subscription is cancelled |
| acknowledged_at | timestamp | yes | Set when the automatic per-gift tax-acknowledgment email is queued (session 373 / C3b). Null = not yet acknowledged. Per-gift idempotency marker so a webhook replay never double-emails the donor; distinct from the annual `donation_receipts` table keyed `(contact_id, tax_year)`. Marks the initial gift only — recurring renewals are a per-charge concern (fast-follow). |
| import_source_id | uuid | yes | FK→import_sources, nullOnDelete. Set for imported donations. |
| import_session_id | uuid | yes | FK→import_sessions, nullOnDelete. Set for imported donations so rollback can cascade. |
| external_id | string | yes | Source-system record ID for dedupe. |
| custom_fields | jsonb | yes | User-defined custom field values. Populated by the donations importer when columns are mapped as `__custom_donation__`. |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Indexes:
- `(contact_id)` — `donations_contact_id_index`.
- `(organization_id)` — `donations_organization_id_index`.
- `(fund_id)` — `donations_fund_id_index`.
- `(import_source_id, external_id)` — `donations_import_external_idx`.
- `(source)` — `donations_source_index`.

Related tables:
- [donation_credits](donation_credits.md) — polymorphic soft-credit attribution layer (recipient is Contact or Organization). Cascade-deletes when the parent donation is force-deleted. See `Donation->softCredits` relation.
