## portal_password_reset_tokens

**Owned by the Member Portal plugin (`nonprofitcrm/member-portal`, extracted to `crm-plugin--member-portal` session 395)** — created by the plugin's `database/migrations/`, not core's schema dump.

Password reset tokens for the member portal. Entirely separate from `password_reset_tokens` (admin users).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| email | string | no | PK |
| token | string | no | bcrypt hash of the reset token |
| created_at | timestamp | yes | |
