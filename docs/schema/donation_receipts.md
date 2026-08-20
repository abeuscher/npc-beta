## donation_receipts

**Owned by the Donations plugin (`nonprofitcrm/donations`, extracted to `crm-plugin--donations` session 391)** — created by the plugin's `database/migrations/`, not core's schema dump.

One row per tax receipt send. Used to prevent duplicate sends and to maintain an audit trail. Re-sends create additional rows rather than overwriting.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| contact_id | uuid | no | FK→contacts, restrictOnDelete |
| tax_year | integer | no | Calendar year covered by the receipt |
| sent_at | timestamp | no | When the receipt email was sent |
| total_amount | decimal(10,2) | no | Sum of all active donations in the tax year |
| breakdown | json | no | Array of `{fund_label, restriction_type, amount}` |
| created_at | timestamp | no | |

Index on `(contact_id, tax_year)`.
