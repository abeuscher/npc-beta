# Session 025 — Migrate Blog Posts into the Pages Table

## Context

`Post` is being eliminated as a separate model. Blog posts become `Page` records with `type = 'post'`. This matches the existing `type = 'event'` pattern. All three content types — pages, posts, event landing pages — then share the same page builder infrastructure, publication model, and SEO fields.

**Data wipe is OK.** All existing data is test data. Drop and reseed freely.

---

## Resolved design decisions

**Slug storage:** Post page slugs are stored WITH the blog prefix in the DB, exactly like event landing pages store `events/my-slug`. Example: a post with slug `my-article` and blog prefix `news` is stored as `news/my-article`. The prefix is stripped by the router before the controller receives the slug parameter — no change needed there.

**Prefix change handler:** When the user saves a new `blog_prefix` in `CmsSettingsPage`, run a find-and-replace on the `pages` table for all `type = 'post'` records. Use `old_prefix/` (with the trailing slash) in the WHERE clause to avoid false positives. Performance is not a concern — this is a rare admin action.

**Blog index page:** The blog index (`/news` or whatever the prefix resolves to) is a real `Page` record whose `slug` equals the blog prefix (e.g., `news`). It contains a `blog_post_list` widget. `PostController::index()` loads this page by slug and renders it through the standard CMS page view. Seed this page in `DatabaseSeeder`. Add a warning in the Filament admin (e.g., a `Placeholder` or notification) that this page should not be deleted, as it serves as the blog index.

**NavigationItemResource:** Update it to resolve "Blog Posts" entries against `Page::where('type', 'post')` instead of `Post::`.

---

## Files to change

| File | Action |
|------|--------|
| `app/Models/Post.php` | Delete |
| `app/Http/Controllers/PostController.php` | Update — query `Page` model; `index()` loads blog index page by slug |
| `resources/views/posts/show.blade.php` | Update — replace `{{ $post->content }}` with page builder widget render loop |
| `resources/views/posts/index.blade.php` | Update — render the CMS blog index page (same as `cms/page.blade.php`) |
| `app/Filament/Resources/PostResource.php` | Replace — target `Page::class`, filter `type = 'post'`, set type on create |
| `app/Filament/Pages/CmsSettingsPage.php` | Add prefix-change slug-rename handler for `type = 'post'` pages |
| `app/Services/WidgetDataResolver.php` | Update `blog_posts` case — query `Page::where('type','post')` |
| `app/Observers/PageObserver.php` | Add handler: when a page's `type` is changed to `'post'`, prepend blog prefix to slug (mirrors the existing `type = 'event'` logic) |
| `database/seeders/DatabaseSeeder.php` | Seed blog index page (`slug = blog_prefix`, `blog_post_list` widget) and sample posts as `type = 'post'` Pages |
| `app/Filament/Resources/NavigationItemResource.php` | Update Blog Posts group to resolve against `Page::where('type','post')` |
| `sessions/future-sessions.md` | Remove "Post Block Editor" entry |

---

## Detailed implementation notes

### PostResource (replacement)
- `protected static string $model = Page::class;`
- `getEloquentQuery()`: `parent::getEloquentQuery()->where('type', 'post')`
- `mutateFormDataBeforeCreate()` or a `creating` observer hook: set `type = 'post'` and prepend `blog_prefix . '/'` to the slug
- Form mirrors `PageResource` with "Blog Post" labels, no `type` selector, includes `PageBuilder` Livewire component section
- Keep same navigation group (CMS) and sort order as current `PostResource`
- Add a `Placeholder` component at the top of the form warning that the slug is prefixed automatically and that changing the blog prefix in settings will update all post slugs

### Blog index page warning
In `PostResource` or a dedicated `Page` edit context, detect when the record being edited has `slug = config('site.blog_prefix')` and show a `Placeholder` warning: "This is the blog index page. Deleting it will break the blog listing at /[prefix]."

### CmsSettingsPage prefix-change handler
When `blog_prefix` is saved and the new value differs from the old:
```php
Page::where('type', 'post')
    ->where('slug', 'like', $oldPrefix . '/%')
    ->each(function (Page $page) use ($oldPrefix, $newPrefix) {
        $page->updateQuietly([
            'slug' => $newPrefix . '/' . substr($page->slug, strlen($oldPrefix) + 1),
        ]);
    });
```
Also update the blog index page slug itself: find `Page` with `slug = $oldPrefix` (no type filter, since it's a plain page) and update it to `$newPrefix`.

### PostController
- `index()`: load `Page::where('slug', config('site.blog_prefix'))->firstOrFail()`, render via `cms.page` view (same as normal pages)
- `show(string $slug)`: query `Page::where('type','post')->where('slug', config('site.blog_prefix') . '/' . $slug)->where('is_published', true)->firstOrFail()`

### PageObserver addition
Mirror the existing `type = 'event'` block — if `type` is changed to `'post'` and slug does not already start with `blog_prefix . '/'`, prepend it via `updateQuietly`.

### Seeder
- Create the blog index `Page`: `slug = config('site.blog_prefix', 'news')`, `title = 'News'`, `type = null` (it's a normal page), `is_published = true`, one block of type `blog_post_list`
- Create 2–3 sample posts as `Page` records: `type = 'post'`, `slug = blog_prefix . '/sample-post-title'`, `is_published = true`, one rich text block

---

## Tests to update / write

- `PostTest` — all assertions rewritten to use `Page::where('type','post')`; verify slug is stored with prefix
- `BlogPrefixValidationTest` — add a test that changing the prefix renames post slugs; verify the trailing-slash query avoids false positives
- `WidgetDataResolverTest` — `blog_posts` case now returns `Page` records
- Permission tests — `cms editor can manage posts` passes via new `PostResource`
- `PostController` test — `index()` renders the blog index page; `show()` resolves full prefixed slug
