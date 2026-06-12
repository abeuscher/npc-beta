## users

Admin user accounts.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| name | string | no | |
| email | string | no | unique |
| email_verified_at | timestamp | yes | |
| password | string | no | |
| remember_token | string | yes | |
| is_active | boolean | no | default: true |
| two_factor_secret | text | yes | encrypted TOTP secret (Fortify); null until enrollment |
| two_factor_recovery_codes | text | yes | encrypted JSON array of recovery codes (Fortify) |
| two_factor_confirmed_at | timestamp | yes | set when enrollment is confirmed with a live code |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Two-factor authentication (session 359, A5): admin login requires a TOTP second factor. The `two_factor_*` columns follow Laravel Fortify's canonical shape; secret and recovery codes are encrypted via `Crypt`. A user is "enrolled" only once `two_factor_confirmed_at` is set (confirmed with a live code). The demo user (`APP_ENV=demo`) is exempt and never acquires 2FA state.
