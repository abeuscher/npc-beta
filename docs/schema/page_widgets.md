## page_widgets

Widgets embedded on a page, ordered by sort_order. Root widgets have `layout_id IS NULL`. Widgets inside a column layout reference the layout via `layout_id` and indicate their column slot via `column_index`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| page_id | uuid | no | FK→pages, cascade |
| layout_id | uuid | yes | FK→page_layouts.id, cascade on delete; null for root widgets |
| column_index | smallint unsigned | yes | Slot index within layout; null for root widgets |
| widget_type_id | uuid | no | FK→widget_types, restrictOnDelete |
| label | string | yes | |
| config | jsonb | no | default: {} |
| query_config | jsonb | no | default: {} |
| appearance_config | jsonb | no | default: {}; nested per-instance appearance bag (background, text, layout) applied as inline styles by the renderer. See "Appearance config shape" below. |
| sort_order | integer | no | default: 0 |
| is_active | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |

### Appearance config shape

`appearance_config` is a nested jsonb bag that holds every per-widget appearance value the renderer cares about. Default `{}`. Keys are written sparsely — the renderer only emits inline style for keys that are present and non-empty.

```
{
  background: {
    color,
    gradient,
    image_id,
    alignment,
    fit,
    overlay: { enabled, color, opacity }
  },
  text: {
    color
  },
  layout: {
    full_width,
    padding: { top, right, bottom, left },
    margin:  { top, right, bottom, left }
  }
}
```

Session 162 wrote the keys actually in use today: `background.color`, `text.color`, `layout.full_width`, `layout.padding.{top,right,bottom,left}`, `layout.margin.{top,right,bottom,left}`. The remaining `background.gradient`, `background.image_id`, `background.alignment`, `background.fit`, and `background.overlay.*` keys are reserved for the universal Appearance panels delivered in session 163; they will be written when those panels ship.

`layout.full_width` overrides the `widget_types.full_width` default per instance. Spacing values are integer pixel counts; the renderer casts to int and emits as `{prop}: {n}px`. Color values must match a hex pattern before being written into inline styles.
