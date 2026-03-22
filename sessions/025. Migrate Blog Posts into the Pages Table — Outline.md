# Session 025 Outline — Migrate Blog Posts into the Pages Table

> **Supersedes:** The "Post Block Editor" entry in `future-sessions.md` (queued CMS).
> This is a larger migration than just adding a block editor to posts — it merges the
> Post model entirely into the Page model as `type = 'post'`, giving all content types
> the same page builder infrastructure.
>
> **Data wipe OK:** All existing data is test data. Drop and reseed freely.

---

## Goal

Eliminate the separate `Post` model and `posts` table. Blog posts become `Page` records
with `type = 'post'`, consistent with how event landing pages are `type = 'event'`.
All three content types — pages, posts, event landing pages — then use identical
page builder infrastructure, publication model, and SEO fields.

---

## Architecture

### Model layer

- Delete `app/Models/Post.php`
- Add a scope to `Page` if convenient: `scopePosts($query)` → `where('type', 'post')`
- No new migration needed — `pages.type` already exists
- Drop the `posts` table (or just leave the migration in place; the table will be empty)

### Routing and controller

`PostController` currently resolves slugs against the `posts` table.

- Change queries to `Page::where('type', 'post')->...`
- The configurable `blog_prefix` setting already prefixes blog routes — keep it
- Blog post slugs in the `pages` table should NOT include the prefix (the prefix is
  a routing concern, not a storage concern). This matches how `page_type = 'event'`
  uses `events/` as a DB slug prefix — **confirm** whether to follow that same pattern
  or keep the prefix in the route only. Recommendation: store slugs WITHOUT prefix for
  posts, since the prefix is configurable and could change.

### Views

- `resources/views/posts/show.blade.php` currently renders `{{ $post->content }}` (raw HTML from RichEditor).
  Replace with the page builder widget render loop (same as `resources/views/cms/page.blade.php`).
- `resources/views/posts/index.blade.php` — swap `Post::` query for `Page::where('type','post')`.

### Filament resource

Replace the current `PostResource` (which targets `Post::class`) with a new version:

- Model: `Page::class`
- `getEloquentQuery()`: filters to `where('type', 'post')`
- `mutateFormDataBeforeCreate()` or `creating()`: sets `type = 'post'` automatically
- Form: same as `PageResource` but with "Blog Post" labels and no `type` select
- Includes the `PageBuilder` Livewire component section (same as PageResource)
- Navigation: CMS group, "Blog Posts" label, keep the same navigation sort as today

### WidgetDataResolver

The `blog_posts` case currently queries `Post::published()`. Change to:

```php
Page::where('type', 'post')
    ->where('is_published', true)
    ->orderBy('published_at', 'desc')
```

### Seeder

Update `DatabaseSeeder` to create sample blog posts as `Page` records with
`type = 'post'` and a populated page builder block instead of a `content` string.

---

## Files Expected to Change

| File | Action |
|------|--------|
| `app/Models/Post.php` | Delete |
| `app/Http/Controllers/PostController.php` | Update — query `Page` model |
| `resources/views/posts/show.blade.php` | Update — widget render loop |
| `resources/views/posts/index.blade.php` | Update — `Page` query |
| `app/Filament/Resources/PostResource.php` | Replace — new version targeting `Page` |
| `app/Services/WidgetDataResolver.php` | Update `blog_posts` case |
| `database/seeders/DatabaseSeeder.php` | Update — seed posts as Pages |
| `sessions/future-sessions.md` | Remove "Post Block Editor" from queue |

---

## Tests to Update / Write

- `PostTest` — all assertions rewritten to use `Page::where('type','post')`
- `BlogPrefixValidationTest` — may need updating if slug storage convention changes
- `WidgetDataResolverTest` — `blog_posts` case now returns `Page` records
- Permission tests — `cms editor can manage posts` should still pass via new PostResource

---

## Open Questions (resolve at session start)

1. **Slug storage convention for posts** — store with or without blog prefix in the DB?
   Recommendation: WITHOUT prefix (prefix is a routing concern only). This means the
   `BlogPrefixValidationTest` must check that the prefix doesn't collide with any
   `pages.slug` value (already true) but NOT that post slugs avoid the prefix in storage.

2. **Post index page** — does it exist as a page in the CMS (a `Page` record with a
   special slug matching the blog prefix), or is it purely controller-rendered with no
   CMS page backing it? Current behavior: controller-rendered. Decide if this changes.

3. **Navigation items** — NavigationItemResource already has a separate "Blog Posts"
   group pointing at `Post::` records. Update it to point at `Page::where('type','post')`.
