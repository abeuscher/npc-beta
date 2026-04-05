## products

Named finite-inventory entitlements offered by the organisation (plots, slots, packages, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| slug | string | no | unique; used as handle in widgets |
| description | text | yes | |
| capacity | integer | no | Total units available |
| status | string | no | default: 'draft'; values: draft, published |
| sort_order | integer | no | default: 0 |
| is_archived | boolean | no | default: false |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

### Media collections

| Collection | Type | Notes |
|---|---|---|
| product_image | single file | Product photo. Conversions: webp, responsive breakpoints via `ImageSizeProfile::photo()`. |
