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
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
