## product_prices

Price tiers for a product. Multiple tiers share the same inventory pool. Zero-amount tiers follow the same Stripe Checkout flow.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| product_id | uuid | no | FK→products, cascadeOnDelete |
| label | string | no | Display name for this tier |
| amount | decimal(10,2) | no | 0.00 for free tiers |
| stripe_price_id | string | yes | Populated by ProductPriceObserver on save when amount > 0 |
| sort_order | integer | no | default: 0 |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
