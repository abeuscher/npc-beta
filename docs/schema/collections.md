## collections

Named collections used as data sources for page widgets (custom items, events, posts).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | uuid | no | PK |
| name | string | no | |
| handle | string | no | unique; renamed from `slug` in session 013 |
| description | text | yes | |
| fields | jsonb | no | default: [] |
| accepted_sources | jsonb | no | default: `["human"]`; set of `App\WidgetPrimitive\Source::*` values that may write into this collection (gated at `DataSink::write` via `CollectionItem::acceptsSource`). Added in session 213. |
| source_type | string | no | default: 'custom' |
| is_public | boolean | no | default: false |
| is_active | boolean | no | default: true |
| created_at | timestamp | no | |
| updated_at | timestamp | no | |
| deleted_at | timestamp | yes | Soft delete |
