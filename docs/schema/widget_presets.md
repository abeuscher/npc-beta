## widget_presets

User-authored draft presets created from inside the page builder inspector. Drafts are a designer scratch pool per widget type — they are *input to code*, not a parallel runtime source. A designer saves the current live appearance of a widget instance as a draft, iterates, then exports the draft as a PHP array literal to paste into the widget's `presets()` method on its definition class.

Drafts are global per widget type — no per-site, per-template, or per-user scoping. The draft pool is small by design (10s of entries, not 1000s).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| widget_type_id | uuid | no | FK → widget_types.id, cascade on delete |
| handle | string | no | slug; unique per widget_type_id (composite unique index on (widget_type_id, handle)). Auto-generated on save as `draft-N` |
| label | string | no | auto-generated as `Draft N` on save; user-renamable |
| description | text | yes | nullable; user-populated |
| config | jsonb | no | default: {}; keys MUST correspond to `config_schema` fields whose `group` is `appearance` on the associated widget type. Non-appearance keys are rejected with 422 on save. See widget_types.config_schema |
| appearance_config | jsonb | no | default: {}; mirrors page_widgets.appearance_config shape |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

### Indexes

- Primary key on `id`.
- Composite unique index on `(widget_type_id, handle)`.
- Foreign key `widget_type_id` → `widget_types.id` with `cascadeOnDelete`.

### Appearance-group rule

Every row's `config` is constrained to keys that resolve to a `config_schema` field with `group: 'appearance'` on the parent widget type. Server-side validation enforces this on create and rename endpoints, and `WidgetManifestTest` re-asserts it across both code-declared and DB-authored presets.
