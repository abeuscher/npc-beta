## purchases

**Owned by the Products plugin (`nonprofitcrm/products`, extracted to `crm-plugin--products` session 398)** — created by the plugin's `database/migrations/`, not core's schema dump.

Completed product purchases. Created only on `checkout.session.completed` webhook; never pre-created.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| product_id | uuid | no | FK→products, restrictOnDelete |
| product_price_id | uuid | no | FK→product_prices, restrictOnDelete |
| contact_id | uuid | yes | FK→contacts, nullOnDelete; set from Stripe `customer_details.email` |
| stripe_session_id | string | yes | Stripe Checkout session ID |
| amount_paid | decimal(10,2) | no | From Stripe `amount_total / 100` |
| status | string | no | default: 'active'; values: active, cancelled |
| occurred_at | timestamp | no | Set to now() on creation |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
