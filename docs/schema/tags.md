## tags

Tags for categorising contacts, pages, posts, events, and collection items.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| slug | string | no | unique |
| type | string | no | default: 'contact'; values: contact, page, post, event, collection |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

Unique constraint on `(name, type)`.
