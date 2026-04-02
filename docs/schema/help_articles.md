## help_articles

Seeded help documentation articles shown in the admin sidebar.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| slug | string | no | unique |
| title | string | no | |
| description | text | no | |
| content | longText | no | |
| tags | json | yes | |
| app_version | string | yes | |
| last_updated | date | yes | |
| embedding | jsonb | yes | Reserved for future semantic search |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
