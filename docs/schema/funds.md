## funds

Named funds that donations can be allocated to.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| code | string | no | unique |
| description | text | yes | |
| is_active | boolean | no | default: true |
| restriction_type | string | no | default: unrestricted; values: unrestricted, temporarily_restricted, permanently_restricted |
| is_archived | boolean | no | default: false |
| quickbooks_account_id | string | yes | Per-fund QB deposit account override |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
