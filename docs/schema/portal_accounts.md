## portal_accounts

Portal login credentials for contacts. One record per contact with member/volunteer portal access.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| contact_id | uuid | yes | FK → contacts.id, nullOnDelete |
| email | string(255) | no | unique; login identifier |
| password | string | no | bcrypt hash |
| email_verified_at | timestamp | yes | null = unverified |
| is_active | boolean | no | default: true; false = access suspended |
| remember_token | string(100) | yes | |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |
