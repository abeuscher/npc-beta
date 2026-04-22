## page_layouts

Layout containers (column layouts) that hold widgets in a multi-column arrangement. Layouts participate in the owner's widget flow alongside root widgets, ordered by `sort_order`. Owned polymorphically by a `Page` or `Template`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| owner_type | string | no | Morph type: `App\Models\Page` or `App\Models\Template` |
| owner_id | uuid | no | FK to the owning page or template (polymorphic, no DB constraint) |
| label | string | yes | User-visible name |
| display | string | no | default: 'grid'; values: flex, grid |
| columns | integer | no | default: 2; number of column slots |
| layout_config | jsonb | no | default: {}; layout-behavior keys — see "Key split" below |
| appearance_config | jsonb | no | default: {}; per-layout appearance bag (background, layout spacing) applied as inline styles by the renderer. Shape mirrors `page_widgets.appearance_config` minus the `text` subtree. |
| sort_order | integer | no | default: 0; position in the merged page flow |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

**Indexes:** `(owner_type, owner_id)`.

**Cascade:** no DB-level FK from owner_id. Page and Template models handle deletion of owned rows via model observers/events.

### Key split between `layout_config` and `appearance_config`

Session 207 split the layout's configuration into two columns with separate contracts:

- `layout_config` holds layout-behavior keys only: `full_width`, `display` (mirrored here for historical reasons; the column is authoritative), `columns` (same), `gap`, `grid_template_columns`, `grid_auto_rows`, `align_items`, `justify_items`, `justify_content`, `flex_wrap`, `flex_basis[]`.
- `appearance_config` holds per-layout appearance values that the renderer emits as inline styles. Whitelist is `['background', 'layout']` — no `text` subtree (layouts have no text controls). The shape matches `page_widgets.appearance_config`:
  ```
  {
    background: {
      color,
      gradient: { gradients: [...] },
      alignment,
      fit
    },
    layout: {
      padding: { top, right, bottom, left },
      margin:  { top, right, bottom, left }
    }
  }
  ```
  `full_width` intentionally stays on `layout_config`, not on `appearance_config.layout`, because it is a layout-behavior toggle rather than an appearance override.

The 207 data migration moved the pre-existing `layout_config.background_color`, `padding_{top,right,bottom,left}`, and `margin_{top,right,bottom,left}` keys into `appearance_config` under the shape above, then removed them from `layout_config`.
