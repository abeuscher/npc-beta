## site_settings

Key-value store for site-wide configuration managed via Settings pages. Rows with `type = 'encrypted'` store their value encrypted via `Crypt::encryptString()` and are decrypted transparently by `SiteSetting::get()`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| key | string | no | unique |
| value | text | yes | |
| group | string | no | default: 'general' |
| type | string | no | default: 'string'; values: string, boolean, integer, json, encrypted |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

**Finance keys:** `stripe_publishable_key` (type: string), `stripe_secret_key` (type: encrypted), `stripe_webhook_secret` (type: encrypted), `stripe_payment_method_types` (type: json — array of enabled Stripe payment method type strings), `quickbooks_api_key` (type: encrypted), `qb_client_id` (type: encrypted), `qb_client_secret` (type: encrypted), `qb_access_token` (type: encrypted), `qb_refresh_token` (type: encrypted), `qb_realm_id` (type: encrypted), `qb_token_expires_at` (type: encrypted).
