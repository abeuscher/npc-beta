## portal_password_reset_tokens

Password reset tokens for the member portal. Entirely separate from `password_reset_tokens` (admin users).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| email | string | no | PK |
| token | string | no | bcrypt hash of the reset token |
| created_at | timestamp | yes | |
