## campaigns

Fundraising campaigns that donations can be attributed to.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| description | text | yes | |
| goal_amount | decimal(10,2) | yes | |
| starts_on | date | yes | |
| ends_on | date | yes | |
| is_active | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
