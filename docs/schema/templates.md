## templates

Page templates (header, footer, SCSS) and content templates (widget stack presets). One `page`-type template is marked `is_default`; non-default templates inherit from the default for any null field. Colour was relocated off this table to the site-wide Theme palette (`site_settings` key `theme_colors`, group `design`) at session 297 — the `primary_color` / `header_bg_color` / `footer_bg_color` / `nav_link_color` / `nav_hover_color` / `nav_active_color` columns were dropped.

Per-template *structural* deviation returned at session 301 (Theme/Template Re-Taxonomy arc): a page template **selects** a vetted content `scheme` (it never edits colour tokens) and may independently suppress the header/footer. `scheme` / `no_header` / `no_footer` carry concrete non-null defaults (concrete-values rule) — they are NOT inherited from the default template, and `scheme='default'` is the empty delta (byte-identical to the 297 token defaults). A null `header_page_id`/`footer_page_id` still means "inherit the theme header/footer"; `no_header`/`no_footer` is the distinct "suppress entirely" state and wins even when a chrome page is set.

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
| scheme | string | no | default: `'default'`; selected content-region colour scheme (`default` \| `inverse`); concrete, not inherited; selects among the fixed `--np-color-*` tokens |
| no_header | boolean | no | default: false; suppress the header entirely (wins even if `header_page_id` set); concrete, not inherited |
| no_footer | boolean | no | default: false; suppress the footer entirely (wins even if `footer_page_id` set); concrete, not inherited |
| created_by | bigint unsigned | yes | FK → users |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
