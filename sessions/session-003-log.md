# Session 003 — First Public Page
**Date:** March 12, 2026
**Status:** Complete

---

## What Was Accomplished

### Task 1 — Pages Migration

Created `database/migrations/2026_03_12_230000_create_pages_table.php`:

- UUID primary key
- `title` string, required
- `slug` string, unique
- `content` longtext (nullable — will hold RichEditor / future TipTap output)
- `meta_title` string, nullable
- `meta_description` text, nullable
- `is_published` boolean, default false
- `published_at` timestamp, nullable
- Timestamps, soft deletes

### Task 2 — Page Model

Created `app/Models/Page.php`:

- `HasUuids`, `SoftDeletes`, `HasFactory`
- Spatie `HasSlug` — generates slug from title on create, does not regenerate on update
- `$fillable` for all above fields
- `$casts`: `is_published` as boolean, `published_at` as datetime
- `getRouteKeyName()` returns `'slug'`

### Task 3 — Filament PageResource

Created `app/Filament/Resources/PageResource.php` with three page classes:

**Form:**
- Title (required) — generates slug live via `afterStateUpdated` on create only
- Slug (editable, unique validation, `notIn` guard for reserved words: `admin`, `horizon`, `up`, `login`, `logout`, `register`)
- Content (Filament `RichEditor`, full width)
- Publication section: `is_published` toggle + `published_at` date-time picker (visible when published is true)
- SEO section (collapsible, collapsed by default): `meta_title`, `meta_description`

**Table columns:** title (searchable), slug, is_published (icon), published_at, updated_at (hidden by default)

**Filters:** `TernaryFilter` on `is_published`

Accessible at `/admin/pages`.

### Task 4 — Public Routes and Controller

Updated `routes/web.php`:
```php
Route::get('/', [PageController::class, 'home']);
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9\-]+');
```

The slug route uses a regex constraint (`[a-z0-9\-]+`) and is registered last, so Filament's routes (registered by its service provider) take precedence. No conflicts with `/admin` or other reserved paths.

Created `app/Http/Controllers/PageController.php`:
- `home()` — finds published `home` slug, 404 if absent or unpublished
- `show($slug)` — finds published page by slug, 404 if absent or unpublished

### Task 5 — Blade Templates

Created `resources/views/layouts/public.blade.php` — base public layout:
- Standard `<head>` with charset, viewport, title (uses `$title` variable)
- Meta description if provided
- Optional Pico CSS CDN include — controlled by `config('theme.pico')` / `THEME_PICO` env var (off by default)
- `@stack('styles')` — any view can push a custom stylesheet here
- `@yield('content')` body
- `@stack('scripts')` before `</body>`

Created `resources/views/pages/show.blade.php`:
- Extends `layouts.public` passing `meta_title ?? title` and `meta_description`
- Renders `$page->title` in `<h1>`
- Renders `{!! $page->content !!}` in a `<div class="page-content">`

Created `config/theme.php` with a single `pico` key bound to `env('THEME_PICO', false)`. This keeps the toggle out of `config/app.php` and makes it easy to add future theme settings.

Updated `.env.example` with:
- `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (used by seeder)
- `THEME_PICO=false`

### Task 6 — DatabaseSeeder

Replaced the Laravel scaffold seeder with a production-ready, env-driven seeder:

1. Creates all 6 Spatie roles if they don't exist: `super_admin`, `crm_manager`, `staff`, `finance_manager`, `events_manager`, `read_only`
2. Creates the initial admin user from `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` env vars and assigns `super_admin`. Skipped cleanly if those vars are not set.
3. Creates a `home` page with placeholder content (idempotent via `firstOrCreate`)

After `./dev fresh` (wipe + migrate + seed), `localhost/` works immediately.

### Task 7 — Tests

Created `tests/Feature/PageTest.php` — 6 tests, all passing:

- ✅ Published `home` slug serves at `/`
- ✅ Published page serves at `/{slug}`
- ✅ Unpublished page returns 404
- ✅ Non-existent slug returns 404
- ✅ Slug is auto-generated from title
- ✅ `/` returns 404 when no published home page exists

Deleted `tests/Feature/ExampleTest.php` — Laravel scaffold placeholder, superseded by the above.

---

## Final State

17 tests passing. Zero failures.

| Route | Resolves to |
|-------|------------|
| `localhost/` | `home` page (404 if unpublished or missing) |
| `localhost/admin` | Filament admin panel |
| `localhost/{slug}` | Any published page by slug |

---

## Key Decisions Made

| Decision | Choice | Reason |
|----------|--------|--------|
| CSS default | No framework | Start with less; Pico and custom CSS are opt-in hooks |
| Pico toggle | `THEME_PICO` env var → `config/theme.php` | Avoids `env()` calls outside config; clean on/off per installation |
| Pico delivery | CDN, not npm | No build pipeline; Pico is a quick-legibility layer, not a dependency |
| `@stack('styles')` | In `<head>`, after Pico | Custom sheet always wins; supports both add-on and full replace |
| Slug regex constraint | `[a-z0-9\-]+` | Locks the slug pattern at the router level, not just validation |
| Reserved slug validation | `notIn(['admin', 'horizon', 'up', 'login', 'logout', 'register'])` | Prevents slugs that would silently shadow other routes |
| Slug regeneration | `doNotGenerateSlugsOnUpdate()` | Prevents URLs from breaking when titles are edited |
| ExampleTest | Deleted | Replaced by PageTest which covers the same route with proper setup |

---

## Files Created / Modified This Session

**Created:**
- `database/migrations/2026_03_12_230000_create_pages_table.php`
- `app/Models/Page.php`
- `app/Filament/Resources/PageResource.php`
- `app/Filament/Resources/PageResource/Pages/ListPages.php`
- `app/Filament/Resources/PageResource/Pages/CreatePage.php`
- `app/Filament/Resources/PageResource/Pages/EditPage.php`
- `app/Http/Controllers/PageController.php`
- `resources/views/layouts/public.blade.php`
- `resources/views/pages/show.blade.php`
- `config/theme.php`
- `tests/Feature/PageTest.php`

**Modified:**
- `routes/web.php` — replaced welcome view route with PageController routes
- `database/seeders/DatabaseSeeder.php` — replaced scaffold with env-driven setup
- `.env.example` — added `ADMIN_*` and `THEME_PICO` vars
- `docs/ARCHITECTURE.md` — updated stack entry and marked Page as built

**Deleted:**
- `tests/Feature/ExampleTest.php` — scaffold placeholder

---

## Next Session

The public pipeline is working. Likely next candidates:

- **Navigation** — a `NavigationItem` model (label, url/slug, sort order, parent) managed in Filament, rendered in the public layout header
- **Post model** — similar to Page but with author, category, published_at prominently used
- **Media** — Spatie Media Library wired into Page/Post forms for featured images
