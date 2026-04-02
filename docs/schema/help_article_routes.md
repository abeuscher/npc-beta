## help_article_routes

Maps Filament route names to help articles for contextual help lookup.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigInteger | no | PK |
| help_article_id | bigInteger | no | FK→help_articles, cascade |
| route_name | string | no | indexed |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(help_article_id, route_name)`.
