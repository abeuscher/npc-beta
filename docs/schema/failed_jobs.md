## failed_jobs

Laravel failed job records.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| uuid | string | no | unique |
| connection | text | no | |
| queue | text | no | |
| payload | longText | no | |
| exception | longText | no | |
| failed_at | timestamp | no | default: current |
