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
| category | string | yes | crm, cms, finance, tools, settings, general |
| embedding | jsonb | yes | Reserved for future semantic search |
| search_weight | integer | no | default: 0; tiebreaker for HelpSearch ordering — higher wins when two articles tie on search-rank. Sourced from optional `search_weight:` frontmatter key in the article's markdown file |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
