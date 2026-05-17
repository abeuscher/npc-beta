## templates

Page templates (header, footer, SCSS) and content templates (widget stack presets). One `page`-type template is marked `is_default`; non-default templates inherit from the default for any null field. Colour was relocated off this table to the site-wide Theme palette (`site_settings` key `theme_colors`, group `design`) at session 297 — the `primary_color` / `header_bg_color` / `footer_bg_color` / `nav_link_color` / `nav_hover_color` / `nav_active_color` columns were dropped.

Content templates own their widget stack as polymorphic `page_widgets` + `page_layouts` rows (`owner_type = 'App\Models\Template'`). The `definition` JSONB column was removed in session 187.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | e.g. "Default", "Landing Page", "Minimal" |
| type | string | no | `page` or `content` |
| description | text | yes | |
| is_default | boolean | no | default: false; exactly one `page`-type template may be default |
| custom_scss | text | yes | null = inherit |
| header_page_id | uuid | yes | FK → pages; null = inherit default's header |
| footer_page_id | uuid | yes | FK → pages; null = inherit default's footer |
| created_by | bigint unsigned | yes | FK → users |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
