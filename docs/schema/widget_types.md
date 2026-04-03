## widget_types

Definitions of available widget types for pages (server-rendered or client-rendered).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| handle | string | no | unique |
| label | string | no | |
| description | text | yes | Short description shown in widget picker |
| category | jsonb | no | default: ["content"]; array of category slugs: content, layout, media, blog, events, forms, portal, giving_and_sales |
| allowed_page_types | jsonb | yes | Array of page type strings (default, post, member, system), or null for "all" |
| render_mode | enum | no | default: 'server'; values: server, client |
| collections | jsonb | no | default: [] |
| assets | jsonb | no | default: {}; keys: css (string[]), js (string[]), scss (string[]) — external file paths loaded by the layout |
| default_open | boolean | no | default: false |
| full_width | boolean | no | default: false; when true, widget renders without the page content container |
| config_schema | jsonb | no | default: [] |
| template | text | yes | |
| css | text | yes | |
| js | text | yes | |
| variable_name | string | yes | |
| code | text | yes | |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
