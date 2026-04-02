## invitation_tokens

Secure tokens used to invite new admin users via email. One pending token per user at a time.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| user_id | bigInteger | no | FK→users, cascade |
| token | string | no | SHA-256 hash of the plain token; unique |
| expires_at | timestamp | no | 48 hours from creation |
| accepted_at | timestamp | yes | Set on use; null means token has not been used |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
