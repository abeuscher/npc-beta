## jobs

Laravel job queue.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| queue | string | no | indexed |
| payload | longText | no | |
| attempts | unsignedTinyInteger | no | |
| reserved_at | unsignedInteger | yes | |
| available_at | unsignedInteger | no | |
| created_at | unsignedInteger | no | |
