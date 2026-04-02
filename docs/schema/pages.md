## pages

Static pages and landing pages. Posts are stored here with `type = 'post'`; member portal pages with `type = 'member'`; protected infrastructure pages with `type = 'system'`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| title | string | no | |
| slug | string | no | unique |
| type | string | no | default: 'default'; values: default, post, event, member, system — enforced by DB check constraint |
| template_id | uuid | yes | FK → templates, nullOnDelete; null = use default template |
| author_id | bigint unsigned | no | FK → users.id, restrictOnDelete |
| status | string | no | default: 'draft'; values: draft, published |
| meta_title | string | yes | |
| meta_description | text | yes | |
| og_image_path | string | yes | URL or path for Open Graph image |
| noindex | boolean | no | default: false; adds noindex meta tag when true |
| head_snippet | text | yes | Per-page code injected before </head> |
| body_snippet | text | yes | Per-page code injected before </body> |
| custom_fields | jsonb | yes | |
| published_at | timestamp | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
