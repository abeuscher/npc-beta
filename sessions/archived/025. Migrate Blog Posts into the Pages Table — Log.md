# Session 025 Log — Migrate Blog Posts into the Pages Table

## What was built

Eliminated the separate `Post` model and `posts` table. Blog posts are now `Page` records
with `type = 'post'`, consistent with how event landing pages use `type = 'event'`.

## Key decisions made

- **Slug storage**: Post slugs are stored WITH the blog prefix in the DB (e.g., `news/my-post`),
  matching the existing `events/my-event` pattern. This gives natural DB-level uniqueness.
- **Prefix change handling**: `CmsSettingsPage` now renames all `type = 'post'` slugs when
  the blog prefix is changed, using `old_prefix/` (with trailing slash) as the WHERE guard
  to avoid false positives. The blog index page slug is also renamed.
- **Blog index page**: The blog listing at `/news` is a real seeded `Page` record whose slug
  equals the blog prefix. `PostController::index()` loads it by slug and renders it through
  the standard CMS page view. A warning placeholder in `PostResource` alerts admins not to
  delete it.
- **NavigationItemResource**: Updated to resolve "Blog Posts" entries from
  `Page::where('type', 'post')` instead of the deleted `Post` model.

## Files changed

| File | Change |
|------|--------|
| `app/Models/Post.php` | Deleted |
| `app/Http/Controllers/PostController.php` | Queries `Page` model; `index()` loads blog index page by slug |
| `app/Filament/Resources/PostResource.php` | Targets `Page::class`, filters `type='post'`, includes PageBuilder, slug placeholder, blog-index warning |
| `app/Filament/Resources/PostResource/Pages/CreatePost.php` | Sets `type='post'`, prepends blog prefix to slug |
| `app/Observers/PageObserver.php` | Added `type='post'` handler mirroring the existing `type='event'` block |
| `app/Filament/Pages/Settings/CmsSettingsPage.php` | Renames post slugs and blog index slug on prefix change |
| `app/Services/WidgetDataResolver.php` | `blog_posts` case queries `Page::where('type','post')` |
| `app/Filament/Resources/NavigationItemResource.php` | Blog group resolves from `Page::where('type','post')` |
| `app/Filament/Resources/PageResource.php` | `getEloquentQuery()` excludes `type='post'` |
| `app/Models/NavigationItem.php` | Removed `post_id` from `$fillable` and `post()` relationship |
| `app/Providers/AuthServiceProvider.php` | Removed `Post` policy mapping |
| `database/seeders/DatabaseSeeder.php` | Seeds blog index page and sample posts as `Page` records |
| `database/seeders/BasePageSeeder.php` | Blog index slug uses `config('site.blog_prefix')` |
| `resources/views/posts/show.blade.php` | Renders via `<x-page-widgets>` block loop |
| `resources/views/posts/index.blade.php` | Renders the CMS blog index page |
| `resources/views/layouts/public.blade.php` | Removed `post` from eager load and `post_id` branches from nav href logic |
| `resources/views/widgets/blog-roll.blade.php` | Removed manual prefix prepend (slug already contains prefix) |
| `resources/views/widgets/blog-listing.blade.php` | Removed manual prefix prepend (slug already contains prefix) |
| `database/migrations/..._drop_post_id_from_navigation_items.php` | Drops `post_id` FK column |
| `tests/Feature/PostTest.php` | Rewritten to use `Page::where('type','post')` |
| `tests/Feature/BlogPrefixValidationTest.php` | Added 3 new tests for prefix rename behaviour |
| `tests/Feature/WidgetDataResolverTest.php` | `blog_posts` case now creates `Page` records |
| `tests/Feature/PermissionTest.php` | All `Post::` references replaced with `Page::` |
| `sessions/future-sessions.md` | "Post Block Editor" entry removed |

## Post-session fixes

Two bugs found during manual QA and fixed after the main implementation:

1. **`public.blade.php`** — still eagerly loaded the `post` relationship and had `post_id`
   branches in the nav href logic. Removed both.
2. **`blog-roll.blade.php` and `blog-listing.blade.php`** — were prepending the blog prefix
   manually to `$post['slug']`, doubling it since slugs now include the prefix. Fixed by
   removing the manual prepend.

## Test results

186 tests, 468 assertions — all passing.

## Roadmap impact

None. Session 025 was already the last queued session. The roadmap bible and session stubs
created at the end of this session (026–071) constitute the new forward plan.
