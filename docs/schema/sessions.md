## sessions

Laravel session storage.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | string | no | PK |
| user_id | bigInteger | yes | FK→users, indexed |
| ip_address | string(45) | yes | |
| user_agent | text | yes | |
| payload | longText | no | |
| last_activity | integer | no | indexed |
