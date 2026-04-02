## job_batches

Laravel job batch tracking.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | string | no | PK |
| name | string | no | |
| total_jobs | integer | no | |
| pending_jobs | integer | no | |
| failed_jobs | integer | no | |
| failed_job_ids | longText | no | |
| options | mediumText | yes | |
| cancelled_at | integer | yes | |
| created_at | integer | no | |
| finished_at | integer | yes | |
