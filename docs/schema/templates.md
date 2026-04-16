## templates

Page templates (header, footer, colors, fonts, SCSS) and content templates (widget stack presets). One `page`-type template is marked `is_default`; non-default templates inherit from the default for any null field.

Content templates own their widget stack as polymorphic `page_widgets` + `page_layouts` rows (`owner_type = 'App\Models\Template'`). The `definition` JSONB column was removed in session 187.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | e.g. "Default", "Landing Page", "Minimal" |
| type | string | no | `page` or `content` |
| description | text | yes | |
| is_default | boolean | no | default: false; exactly one `page`-type template may be default |
| primary_color | string | yes | null = inherit from default |
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
