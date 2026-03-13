# Session 006 Prompt — Collections

## Context

Session 005 delivered site settings, the public frontend (Alpine, Pico, custom CSS/SCSS), and the blog. The two-tier data boundary was established: CRM data (Contacts, Memberships, Donations) is never surfaced publicly. Content data and **Collections** are the safe public data surface.

Session 006 builds Collections — the user-defined typed data buckets that the component/widget system (session 007) will query. This session is a standalone infrastructure build. The widget system is not built here; we are building the data layer it will consume.

---

## What Collections Are

A Collection is a user-defined content type. Examples: Board Members, Sponsors, Staff Profiles, FAQs, Testimonials, Program Descriptions. Each collection has a schema (a list of field definitions) and items conforming to that schema.

This is the same concept as:
- WordPress Custom Post Types + ACF field groups
- Craft CMS Sections + field layouts
- Contentful Content Types
- Statamic Collections

The key design constraints for this implementation:

1. **Schema is defined by the admin user in the Filament UI** — not in PHP code. No deployment required to add a new field to "Board Members."
2. **Item edit forms are generated dynamically from the schema at runtime** — a "Board Members" item form renders name/title/bio/photo fields because the schema defines them.
3. **Collections have an `is_public` flag** — only public collections can be queried by the component system. Private collections exist for internal admin use only.
4. **CRM data is never a Collection** — Contacts, Memberships, and Donations are architecturally excluded from this system and cannot be made public through it.

---

## Forward-Looking Note: Widget Types Files

When the widget/component system is built in session 007, components written against a collection will need to know the shape of the data they'll receive. This creates a contract problem: the developer writing the widget needs to know that `board_members` items have `name`, `title`, `bio`, and `photo` fields, and what types they are.

**This is worth designing in session 007, but the groundwork is here.** Consider during this session:
- Whether `Collection::getTypeDefinition()` should generate a JSON Schema object describing the collection's data shape
- Whether that schema should be exposed somewhere (an admin UI "copy schema" button, a generated file, a documented endpoint)
- Whether the widget system should validate component data against the schema at save time

No implementation of the types system is required in session 006. The decision to make here is whether the `fields` JSONB schema format is rich enough to drive type generation — it should be, as each field has `key`, `type`, and `required`. Flag this for session 007 to consume.

---

## Data Model

### `collections` table

Migration `create_collections_table`:
- `id` — UUID primary
- `name` — string, required (display name: "Board Members")
- `slug` — string, unique (machine name: "board_members" — used as the query key in session 007)
- `description` — text, nullable
- `fields` — JSONB — array of field definition objects (see schema below)
- `is_public` — boolean, default false — controls whether this collection is queryable from public components
- `is_active` — boolean, default true
- timestamps
- soft deletes

**Field definition schema** (stored in `fields` JSONB column). Each element is an object:
```json
{
  "key": "name",
  "label": "Full Name",
  "type": "text",
  "required": true,
  "helpText": "",
  "options": []
}
```

Supported field types in this session: `text`, `textarea`, `rich_text`, `number`, `date`, `toggle`, `image`, `url`, `email`, `select` (with an `options` array of `{"value": "...", "label": "..."}` objects for select type).

### `collection_items` table

Migration `create_collection_items_table`:
- `id` — UUID primary
- `collection_id` — UUID FK → collections, cascade delete
- `data` — JSONB — the item's field values, keyed by field `key`
- `sort_order` — integer, default 0
- `is_published` — boolean, default false
- timestamps
- soft deletes

---

## Models

**`app/Models/Collection.php`**: `HasUuids`, `SoftDeletes`, `HasFactory`, `HasSlug` (Spatie sluggable from `name`), `hasMany(CollectionItem::class)`.

Cast `fields` as `array`.

Add helper method `getFormSchema(): array` that converts the `fields` array into a Filament form schema array. This is used by `CollectionItemResource` to build the dynamic edit form. Each field type maps to a Filament component:

| Field type | Filament component |
|------------|-------------------|
| `text` | `TextInput` |
| `textarea` | `Textarea` |
| `rich_text` | `RichEditor` |
| `number` | `TextInput::make()->numeric()` |
| `date` | `DatePicker` |
| `toggle` | `Toggle` |
| `image` | `FileUpload` (images only) |
| `url` | `TextInput::make()->url()` |
| `email` | `TextInput::make()->email()` |
| `select` | `Select` with options from field definition |

Each generated component should set `->label($field['label'])`, `->required($field['required'])`, `->helperText($field['helpText'] ?? '')`, and be keyed under `data.{field['key']}` (nested inside the `data` JSONB column using Filament's dot-notation path for nested fields).

**`app/Models/CollectionItem.php`**: `HasUuids`, `SoftDeletes`, `HasFactory`, `belongsTo(Collection::class)`.

Cast `data` as `array`.

---

## Filament Resources

### CollectionResource

`app/Filament/Resources/CollectionResource.php`

- Navigation group: `Content`
- Navigation label: `Collections`
- Navigation icon: `heroicon-o-circle-stack`
- Navigation sort: 4 (after Navigation at sort 3)

**Form:**

```
Section: Collection Details
  - name        TextInput, required, live(onBlur) → auto-sets slug on create
  - slug        TextInput, unique, alpha_dash, helperText: "Machine name used as the data key in components."
  - description Textarea, nullable
  - is_public   Toggle
                helperText: "Only public collections can be queried from public-facing components.
                             Do not enable for collections containing personal, financial, or membership data."
  - is_active   Toggle, default true

Section: Fields
  - fields  Repeater
    Each item:
      - key      TextInput, required, alpha_dash, helperText: "Lowercase with underscores. Used in templates as item.key"
      - label    TextInput, required
      - type     Select: text / textarea / rich_text / number / date / toggle / image / url / email / select
      - required Toggle, default false
      - helpText TextInput, nullable
      - options  Repeater (visible only when type = select)
                   Each item: value (TextInput), label (TextInput)
```

**Table:**
- name — searchable, sortable
- slug
- fields_count — computed from count of `fields` JSON array elements (not a DB aggregate — use `getStateUsing`)
- is_public — badge: "Public" (success) / "Private" (gray)
- is_active — boolean icon
- items_count — `withCount('collectionItems')` or `counts('collectionItems')`
- updated_at — toggleable, hidden by default

**Relation managers**: `CollectionItemsRelationManager`

### CollectionItemResource

`app/Filament/Resources/CollectionItemResource.php`

Navigation: hidden from sidebar — `protected static bool $shouldRegisterNavigation = false`. Items are managed exclusively through the relation manager on CollectionResource. The resource must still exist for relation manager pages to function.

**Form**: Built dynamically. On the edit page, call `$this->record->collection->getFormSchema()` to generate the data fields. On the create page, resolve the collection from context (URL parameter or `ownerRecord` on the relation manager). Wrap the dynamic schema in a `Section::make('Content')`. Add fixed fields above it:
- `sort_order` — TextInput, numeric, default 0
- `is_published` — Toggle, default false

**Table** (used in CollectionItemsRelationManager):
- is_published — boolean icon, label "Published"
- sort_order — sortable
- A summary column: value of the first `text` or `textarea` type field in the collection schema, labeled with that field's label. If no such field exists, show the item's UUID truncated to 8 characters.
- updated_at — sortable

### CollectionItemsRelationManager

`app/Filament/Resources/CollectionResource/RelationManagers/CollectionItemsRelationManager.php`

- Relationship: `collectionItems`
- Table: as described above
- `reorderable('sort_order')` — enable drag-to-reorder
- Actions: `CreateAction`, `EditAction`, `DeleteAction`
- Bulk actions: `DeleteBulkAction`

---

## Security — Documented in Code

Add the following comment block in both `CollectionResource.php` and `Collection.php`:

```
Collections marked is_public = true will be queryable from public-facing page components
in session 007+. Never mark a collection as public if it contains personal, financial,
or membership-related data. CRM entities (Contacts, Memberships, Donations, Organizations)
are architecturally excluded from the collection system and cannot be surfaced publicly
through this mechanism regardless of settings.
```

---

## Seeder Demo Data

Add to `DatabaseSeeder` demo section (local env only):

**Collection: Board Members**
- slug: `board_members`
- is_public: true
- is_active: true
- fields:
  - `name` (text, required, label: "Full Name")
  - `title` (text, label: "Title or Role")
  - `bio` (textarea, label: "Biography")
  - `is_active` (toggle, label: "Currently Active")

**3 items** (is_published: true):
1. name: "Margaret Osei", title: "Board Chair", bio: "Margaret has served on the board since 2019.", is_active: true
2. name: "David Reyes", title: "Treasurer", bio: "David brings 20 years of nonprofit finance experience.", is_active: true
3. name: "Yuki Tanaka", title: "Secretary", bio: "Yuki joined the board in 2022.", is_active: true

---

## Tests

- `CollectionTest`:
  - A collection can be created with a valid field schema
  - Slug is auto-generated from name via Spatie sluggable
  - `getFormSchema()` returns a `TextInput` for a `text` field, a `Select` for a `select` field, etc.
  - `getFormSchema()` marks required fields as required
- `CollectionItemTest`:
  - An item stores arbitrary JSONB data correctly
  - `data` is cast as array
  - Soft-deleted items are excluded from default queries
- `CollectionPublicFlagTest`:
  - A collection with `is_public = false` is not returned by a `scopePublic()` scope (define this scope on the model for session 007 to use)
  - A collection with `is_public = true` is returned

---

## Documentation

- Update `docs/information-architecture.md`: add Collections to the Content domain table, mark as ✅ Built
- `docs/decisions/012-collections-as-public-data-surface.md`: documents the two-tier boundary, why CRM entities are excluded, how `is_public` is the security gate, the types contract question deferred to session 007
- `sessions/session-006-log.md`: written at session end

---

## What This Session Does Not Cover

- The component/widget system that queries collections (session 007)
- Public API endpoint or resolver for collection data (session 007)
- Widget types files / JSON Schema generation from collection fields (session 007 — see forward-looking note above)
- Custom field types beyond the base ten defined above
- Collection-level permissions (which roles can manage which collections)

---

## Acceptance Criteria

- [ ] A Collection can be created with an arbitrary field schema via the Filament UI
- [ ] A CollectionItem edit form renders the correct dynamic fields from the collection's schema
- [ ] Required fields in the schema are enforced as required in the dynamic form
- [ ] Items can be reordered by drag-and-drop in the relation manager
- [ ] `is_public` toggle displays clear security guidance text
- [ ] `Collection::scopePublic()` exists and returns only public, active collections
- [ ] Demo "Board Members" collection and 3 items seed correctly
- [ ] `php artisan test` passes
