## page_widgets

Widgets placed on a widget stack, ordered by sort_order. The stack is owned polymorphically — either a `Page` or a `Template`. Root widgets have `layout_id IS NULL`. Widgets inside a column layout reference the layout via `layout_id` and indicate their column slot via `column_index`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| owner_type | string | no | Morph type: `App\Models\Page` or `App\Models\Template` |
| owner_id | uuid | no | FK to the owning page or template (polymorphic, no DB constraint) |
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

**Indexes:** `(owner_type, owner_id)`.

**Cascade:** no DB-level FK from owner_id. Page and Template models handle deletion of owned rows via model observers/events.

### Appearance config shape

`appearance_config` is a nested jsonb bag that holds every per-widget appearance value the renderer cares about. Default `{}`. Keys are written sparsely — the renderer only emits inline style for keys that are present and non-empty.

```
{
  background: {
    color,
    gradient: {
      gradients: [
        { type, from, from_alpha, to, to_alpha, angle, css_override },
        ...
      ]
    },
    alignment,
    fit
  },
  text: {
    color,
    shadow
  },
  layout: {
    full_width,
    padding: { top, right, bottom, left },
    margin:  { top, right, bottom, left }
  }
}
```

Session 162 wrote the keys actually in use: `background.color`, `text.color`, `text.shadow`, `layout.full_width`, `layout.padding.{top,right,bottom,left}`, `layout.margin.{top,right,bottom,left}`. Session 168 added `text.shadow` (boolean, default absent/falsy; when true the renderer emits `text-shadow: 0 1px 3px rgba(0,0,0,0.6)`). Session 164 added `background.gradient` (with per-stop `from_alpha` / `to_alpha` integer fields, range `[0, 100]`, default `100`), `background.alignment` (9-point string: `top-left`, `top-center`, `top-right`, `middle-left`, `center`, `middle-right`, `bottom-left`, `bottom-center`, `bottom-right`), and `background.fit` (`cover` or `contain`).

**Background image:** The background image is **not** stored in the jsonb bag — there is no `background.image_id` key. Image presence is owned exclusively by the `appearance_background_image` Spatie media collection on `PageWidget` (single-file, Option B). The renderer detects an image via `$pw->getFirstMedia('appearance_background_image')`, and the Vue inspector detects it via the `appearance_image_url` field in the `formatWidget` API response.

**Overlay:** There is no `background.overlay` key. The overlay concept is implemented via gradients with per-stop alpha — a gradient with `from_alpha` / `to_alpha` < 100 paints a semi-transparent tint over the background image.

`layout.full_width` overrides the `widget_types.full_width` default per instance. Spacing values are integer pixel counts; the renderer casts to int and emits as `{prop}: {n}px`. Color values must match a hex pattern before being written into inline styles.
