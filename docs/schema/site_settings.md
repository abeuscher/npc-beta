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

**CMS keys:** `default_content_template_default` (type: string — UUID of the default content template for pages), `default_content_template_post` (type: string — UUID for posts), `default_content_template_event` (type: string — UUID for events). Empty string = no default. `noindex_global` (type: string — `'true'` or `'false'`; when `'true'` the public layout emits `<meta name="robots" content="noindex,nofollow">` site-wide and overrides the per-page `pages.noindex` flag).

**Design keys (group: `design`):** `button_styles` (type: json — site-wide button variant config managed by Theme → Buttons). `typography` (type: json — site-wide typography tree managed by Theme → Text Styles, consumed by `TypographyResolver` + `TypographyCompiler`; shape is `{ buckets: { heading_family, body_family, nav_family }, elements: { h1..h6, p, ul_li, ol_li }, sample_text }`).

**Onboarding key:** `installation_completed_at` (type: string — ISO-8601 timestamp written when the operator clicks "Mark setup complete" on the Setup Checklist dashboard widget; null when the install is in first-run mode). Read by `App\Services\Setup\SetupChecklist` and the `SetupChecklist` widget to switch between first-run and health-check render modes. Cleared by the widget's "Reset install state" action.
