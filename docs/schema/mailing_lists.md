## mailing_lists

Dynamic mailing lists with filter criteria that resolve to a set of contacts.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| description | text | yes | |
| conjunction | string | no | default: 'and'; values: and, or |
| raw_where | text | yes | Optional raw SQL WHERE clause override |
| is_active | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
