## templates

Page templates (header, footer, colors, fonts, SCSS) and content templates (widget stack presets). One `page`-type template is marked `is_default`; non-default templates inherit from the default for any null field.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | e.g. "Default", "Landing Page", "Minimal" |
| type | string | no | `page` or `content` |
| description | text | yes | |
| is_default | boolean | no | default: false; exactly one `page`-type template may be default |
| definition | jsonb | no | default: {}; for `content` templates: array of widget specs `[{handle, config, sort_order}]`. For `page` templates: unused |
| primary_color | string | yes | null = inherit from default |
| heading_font | string | yes | null = inherit |
| body_font | string | yes | null = inherit |
| header_bg_color | string | yes | null = inherit |
| footer_bg_color | string | yes | null = inherit |
| nav_link_color | string | yes | null = inherit |
| nav_hover_color | string | yes | null = inherit |
| nav_active_color | string | yes | null = inherit |
| custom_scss | text | yes | null = inherit |
| header_page_id | uuid | yes | FK → pages; null = inherit default's header |
| footer_page_id | uuid | yes | FK → pages; null = inherit default's footer |
| created_by | bigint unsigned | yes | FK → users |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
