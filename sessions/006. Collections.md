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
5. **`source_type` distinguishes generic from reserved collections** — see below.

---

## Source Types and Reserved Collections

The `collections` table has a `source_type` enum column. This is the mechanism by which the widget system (session 007) knows where to actually fetch data for a given collection reference.

| source_type  | Data backend                                   | Editable by user? |
|--------------|------------------------------------------------|-------------------|
| `custom`     | `collection_items` JSONB (the generic case)    | Yes               |
| `blog_posts` | `Post` model (session 005)                     | No — system only  |
| `events`     | `Event` model (future session)                 | No — system only  |
| `donations`  | Donation form/CRM records (future session)     | No — system only  |

**System collections** (`blog_posts`, `events`, `donations`) are seeded automatically and are not user-creatable. They appear in the Collections list so the admin can see all data sources in one place, and so they can be toggled public/private via `is_public`. Their field schemas describe the shape of data the widget system will receive from the real underlying model — they do not use `collection_items` at all.

**Validation rule**: A user creating a `custom` collection must not be able to use a reserved slug. Add validation in `CollectionResource` that rejects `slug` values of: `blog_posts`, `events`, `donations`, `pages`. Error message: "This slug is reserved for a system data source."

**Public URL prefixes for system collections**: Events and blog posts have user-configurable public URL prefixes stored in site settings. `blog_prefix` already exists (default: `news`). Add `events_prefix` (default: `events`) to the site settings seed alongside it. These are placeholders; the settings UI will be fully designed in a later session.

---

## Forward-Looking Note: Widget Queries and Type Contracts

### Query filtering (session 007 concern, documented here)

Every widget that consumes a collection — generic or reserved — must be able to express a filtered query: "give me items from source X where field Y = Z, limit N, ordered by W." Examples:
- A blog roll widget requesting the latest 3 published posts
- A careers widget requesting only collection items where `job_open = true`

The JSONB structure in `collection_items.data` is deliberately designed to support field-level filtering via PostgreSQL JSONB operators. The `scopePublic()` scope on `Collection` is the foundation; session 007 will extend it to accept filter parameters. System source types (`blog_posts`, `events`, `donations`) will expose Eloquent scopes that accept the same filter shape so the widget system has a uniform interface regardless of source.

No implementation of query filtering is required in session 006. The decision to flag here: the `fields` JSONB schema (each field has `key`, `type`, `required`) is rich enough to drive type generation and query validation in session 007.

### Widget type contracts (session 007 concern)

When the widget system is built, components written against a collection need to know the shape of the data they will receive. `Collection::getTypeDefinition()` should generate a JSON Schema object from the `fields` array. This is flagged for session 007 to implement; the field schema format built today is sufficient to drive it.

---

## Money Siloing Principle — Documented in Code and ADR

Events and Donations each span all three products (CRM, CMS, payments). The architectural rule governing their intersection:

**Payment data is strictly one-directional and never surfaces in the CMS.**

```
CMS → Payment system   (sends intent: "process this transaction")
Payment system → CRM   (confirms result: "transaction recorded")
CRM = record of truth  (all financial and registrant history lives here)
CMS never queries financial records
```

CMS-facing exposure for reserved types:
- **Events**: display data only — title, date, location, description, registration URL. Registrant records, ticket data, and revenue stay in CRM/Payment and are never queryable from public components.
- **Donations**: form configuration only. Transaction records, donor history, and amounts stay in CRM/Payment and are never queryable from public components.

Add this principle as a comment block in `Collection.php` alongside the existing CRM exclusion comment.

---

## Data Model

### `collections` table

Migration `create_collections_table`:
- `id` — UUID primary
- `name` — string, required (display name: "Board Members")
- `slug` — string, unique (machine name: "board_members" — used as the query key in session 007)
- `description` — text, nullable
- `fields` — JSONB — array of field definition objects (see schema below)
- `source_type` — enum: `custom`, `blog_posts`, `events`, `donations` — default `custom`
- `is_public` — boolean, default false
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

Supported field types: `text`, `textarea`, `rich_text`, `number`, `date`, `toggle`, `image`, `url`, `email`, `select` (with an `options` array of `{"value": "...", "label": "..."}` objects for select type).

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

Cast `fields` as `array`. Cast `source_type` as a string (or enum cast if using a PHP enum — a string cast is sufficient).

Add helper method `getFormSchema(): array` that converts the `fields` array into a Filament form schema array. This is used by `CollectionItemResource` to build the dynamic edit form. Each field type maps to a Filament component:

| Field type   | Filament component                       |
|--------------|------------------------------------------|
| `text`       | `TextInput`                              |
| `textarea`   | `Textarea`                               |
| `rich_text`  | `RichEditor`                             |
| `number`     | `TextInput::make()->numeric()`           |
| `date`       | `DatePicker`                             |
| `toggle`     | `Toggle`                                 |
| `image`      | `FileUpload` (images only)               |
| `url`        | `TextInput::make()->url()`               |
| `email`      | `TextInput::make()->email()`             |
| `select`     | `Select` with options from field def     |

Each generated component should set `->label($field['label'])`, `->required($field['required'])`, `->helperText($field['helpText'] ?? '')`, and be keyed under `data.{field['key']}`.

Add `scopePublic(Builder $query)`: returns collections where `is_public = true` AND `is_active = true`. Session 007 will extend this scope to accept filter parameters.

Add `isSystemCollection(): bool` helper that returns `true` when `source_type !== 'custom'`.

Add the following comment block:

```
Collections marked is_public = true will be queryable from public-facing page components
in session 007+. Never mark a collection as public if it contains personal, financial,
or membership-related data. CRM entities (Contacts, Memberships, Donations, Organizations)
are architecturally excluded from the collection system and cannot be surfaced publicly
through this mechanism regardless of settings.

Payment data is strictly one-directional: CMS → Payment → CRM. Financial transaction
records, registrant data, and donor history must never be queried from or displayed by
the CMS layer under any circumstances. System collections (blog_posts, events, donations)
expose display-only data shapes; their underlying financial and relational records remain
exclusively in the CRM and payment systems.
```

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
                Validate: slug not in reserved list [blog_posts, events, donations, pages]
                Error: "This slug is reserved for a system data source."
                Hidden / read-only when source_type !== 'custom'
  - source_type Select, read-only (displayed for context, not editable by user)
                Hidden on create (always 'custom' for user-created collections)
  - description Textarea, nullable
  - is_public   Toggle
                helperText: "Only public collections can be queried from public-facing components.
                             Do not enable for collections containing personal, financial, or membership data."
  - is_active   Toggle, default true

Section: Fields
  Visible only when source_type = 'custom'
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
- source_type — badge: "Custom" (gray) / "System" (info) based on value
- fields_count — computed from count of `fields` JSON array elements; shows "—" for system collections
- is_public — badge: "Public" (success) / "Private" (gray)
- is_active — boolean icon
- items_count — `withCount('collectionItems')` — shows "—" for system collections
- updated_at — toggleable, hidden by default

**Actions:**
- `EditAction` — hidden for system collections (`isSystemCollection()`)
- `DeleteAction` — hidden for system collections

**Relation managers**: `CollectionItemsRelationManager` — hidden for system collections

Also add the security comment block from Collection.php to CollectionResource.php.

### CollectionItemResource

`app/Filament/Resources/CollectionItemResource.php`

Navigation: hidden from sidebar — `protected static bool $shouldRegisterNavigation = false`. Items are managed exclusively through the relation manager on CollectionResource.

**Form**: Built dynamically. On the edit page, call `$this->record->collection->getFormSchema()` to generate the data fields. On the create page, resolve the collection from context (`ownerRecord` on the relation manager). Wrap the dynamic schema in a `Section::make('Content')`. Add fixed fields above it:
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

## Site Settings — events_prefix

Add `events_prefix` to the site settings seed in `DatabaseSeeder` alongside the existing `blog_prefix`:

```php
['key' => 'events_prefix', 'value' => 'events', 'group' => 'general', 'type' => 'string'],
```

This is a placeholder for the full settings UI redesign in a later session. The value controls the public URL prefix for event pages (e.g., `/events/annual-gala`).

---

## Seeder Demo Data

Add to `DatabaseSeeder` demo section (local env only):

**System collections** (seeded in all environments, not local-only):

```php
// System collection: Blog Posts
Collection::firstOrCreate(
    ['slug' => 'blog_posts'],
    [
        'name'        => 'Blog Posts',
        'description' => 'System collection — backed by the Post model. Not editable.',
        'source_type' => 'blog_posts',
        'fields'      => [
            ['key' => 'title',        'label' => 'Title',          'type' => 'text',     'required' => true,  'helpText' => ''],
            ['key' => 'excerpt',      'label' => 'Excerpt',        'type' => 'textarea', 'required' => false, 'helpText' => ''],
            ['key' => 'published_at', 'label' => 'Published Date', 'type' => 'date',     'required' => false, 'helpText' => ''],
            ['key' => 'slug',         'label' => 'Slug',           'type' => 'text',     'required' => true,  'helpText' => ''],
        ],
        'is_public'   => true,
        'is_active'   => true,
    ]
);

// System collection: Events
Collection::firstOrCreate(
    ['slug' => 'events'],
    [
        'name'        => 'Events',
        'description' => 'System collection — will be backed by the Event model in a future session.',
        'source_type' => 'events',
        'fields'      => [
            ['key' => 'title',            'label' => 'Event Title',        'type' => 'text',     'required' => true,  'helpText' => ''],
            ['key' => 'starts_at',        'label' => 'Start Date & Time',  'type' => 'date',     'required' => true,  'helpText' => ''],
            ['key' => 'ends_at',          'label' => 'End Date & Time',    'type' => 'date',     'required' => false, 'helpText' => ''],
            ['key' => 'location',         'label' => 'Location',           'type' => 'text',     'required' => false, 'helpText' => ''],
            ['key' => 'description',      'label' => 'Description',        'type' => 'textarea', 'required' => false, 'helpText' => ''],
            ['key' => 'registration_url', 'label' => 'Registration URL',   'type' => 'url',      'required' => false, 'helpText' => 'External ticketing or registration link.'],
        ],
        'is_public'   => false,
        'is_active'   => true,
    ]
);

// System collection: Donations
Collection::firstOrCreate(
    ['slug' => 'donations'],
    [
        'name'        => 'Donations',
        'description' => 'System collection — CMS-facing display config only. All financial records remain in the CRM.',
        'source_type' => 'donations',
        'fields'      => [
            ['key' => 'campaign_name', 'label' => 'Campaign Name',    'type' => 'text',    'required' => true,  'helpText' => ''],
            ['key' => 'fund_name',     'label' => 'Fund Name',        'type' => 'text',    'required' => false, 'helpText' => ''],
            ['key' => 'goal_amount',   'label' => 'Goal Amount',      'type' => 'number',  'required' => false, 'helpText' => 'Used for progress display only.'],
            ['key' => 'is_active',     'label' => 'Campaign Active',  'type' => 'toggle',  'required' => false, 'helpText' => ''],
        ],
        'is_public'   => false,
        'is_active'   => true,
    ]
);
```

**Demo collection: Board Members** (local env only):
- slug: `board_members`
- source_type: `custom`
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
  - `isSystemCollection()` returns true for source_type !== 'custom'
  - `isSystemCollection()` returns false for source_type = 'custom'
- `CollectionItemTest`:
  - An item stores arbitrary JSONB data correctly
  - `data` is cast as array
  - Soft-deleted items are excluded from default queries
- `CollectionPublicFlagTest`:
  - A collection with `is_public = false` is not returned by `scopePublic()`
  - A collection with `is_public = true` and `is_active = true` is returned by `scopePublic()`
  - A collection with `is_public = true` but `is_active = false` is not returned by `scopePublic()`

---

## Documentation

- Update `docs/information-architecture.md`: add Collections to the Content domain table, mark as ✅ Built
- `docs/decisions/012-collections-as-public-data-surface.md`: documents —
  - The two-tier boundary and why CRM entities are excluded
  - The `source_type` system and the three reserved source types (`blog_posts`, `events`, `donations`)
  - Why Events and Blog Posts are not generic collections: they are structured, relationally-backed entities with behavior JSONB cannot model
  - The money siloing principle: payment data is one-directional, never queryable from CMS
  - How `is_public` is the security gate for generic collections
  - Query filtering as a forward-looking contract: widgets will filter by field values, limit, and order; the JSONB structure supports this; session 007 will implement the query interface
  - The types contract question (widget type definitions from field schema) deferred to session 007
- `sessions/session-006-log.md`: written at session end

---

## What This Session Does Not Cover

- The component/widget system that queries collections (session 007)
- Public API endpoint or resolver for collection data (session 007)
- Query filtering interface (session 007)
- Widget types files / JSON Schema generation from collection fields (session 007)
- The Event model and its CRM/payment integrations (future session)
- The Donations form and its CRM/payment integrations (future session)
- Custom field types beyond the base ten defined above
- Collection-level permissions (which roles can manage which collections)
- Full settings UI for `events_prefix` and `blog_prefix` (later session)

---

## Acceptance Criteria

- [ ] A Collection can be created with an arbitrary field schema via the Filament UI
- [ ] A CollectionItem edit form renders the correct dynamic fields from the collection's schema
- [ ] Required fields in the schema are enforced as required in the dynamic form
- [ ] Items can be reordered by drag-and-drop in the relation manager
- [ ] `is_public` toggle displays clear security guidance text
- [ ] `Collection::scopePublic()` exists and returns only public, active collections
- [ ] `Collection::isSystemCollection()` correctly identifies reserved source types
- [ ] System collections (`blog_posts`, `events`, `donations`) are seeded and visible in the Collections list
- [ ] System collections cannot be edited or deleted via the Filament UI
- [ ] Users cannot create a custom collection with a reserved slug
- [ ] `events_prefix` site setting is seeded
- [ ] Demo "Board Members" collection and 3 items seed correctly
- [ ] `php artisan test` passes
