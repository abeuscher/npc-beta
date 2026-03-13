# Session 005 Log — Site Settings, Public Frontend, and Blog

**Date:** 2026-03-13
**Status:** Complete

---

## What Was Built

### SiteSetting Infrastructure
- Migration: `site_settings` table (`key`, `value`, `group`, `type`, timestamps)
- Model: `App\Models\SiteSetting` with `get(key, default)` / `set(key, value)` static helpers, Redis caching (TTL 60 min), type-based value casting
- `AppServiceProvider::boot()` merges all settings into `config('site.*')` at boot with try/catch for fresh installs
- `DatabaseSeeder` seeds 9 default settings using `firstOrCreate`

### Filament Settings Pages
- `CmsSettingsPage` — general section (site name, base URL, blog prefix with conflict validation, site description, timezone, contact email) and collapsible styles section (Pico CSS toggle, custom CSS/SCSS upload, logo upload)
- `FinanceSettingsPage` — placeholder page for future QuickBooks/Stripe config
- Settings nav group: CMS (sort 1) → Users (sort 2, updated from 1) → Finance (sort 3)

### Public Frontend
- `public.blade.php` updated: Alpine.js CDN (defer), Pico CSS conditional, custom CSS conditional, `window.__site` JS object, public navigation from `NavigationItem` with Alpine mobile toggle
- Navigation renders hierarchically (parent items with nested children)
- URL resolution: `page_id` → page slug, `post_id` → blog prefix + post slug, `url` → raw URL

### Blog Routes and Controller
- `PostController` with `index()` (15/page, published only, desc `published_at`) and `show(string $slug)` (404 on unpublished or missing)
- `routes/web.php` uses `config('site.blog_prefix', 'news')` at boot to register dynamic routes `/{prefix}` and `/{prefix}/{slug}`
- Views: `posts/index.blade.php` (list with excerpt, author, date, read more) and `posts/show.blade.php` (full content, back link)

### SCSS Compilation
- `scssphp/scssphp` installed
- On `.scss` upload: compiled via `ScssPhp\ScssPhp\Compiler`, stored as `storage/app/public/site/custom.css`
- Compilation errors surface as Filament notifications (no save on failure)

### Tests
- `SiteSettingTest` — default return, DB value return, boolean/integer casting, `set()` cache invalidation, `set()` creates new records
- `PostTest` — published post accessible, unpublished returns 404, missing slug returns 404, index shows only published, pagination
- `BlogPrefixValidationTest` — prefix matching page slug rejected, reserved words rejected, valid prefix accepted

### Documentation
- `docs/ARCHITECTURE.md` updated with SiteSetting section, updated Content section, Two-Tier Data Boundary section
- `docs/decisions/011-site-settings-pattern.md` created

---

## Decisions Made During Session

- No deviations from the session prompt architecture decisions.
- `x-cloak` on the nav `<ul>` was omitted since Alpine mobile toggle uses `x-show="open || true"` to keep it visible without JS while still being Alpine-controllable. This is intentional for no-JS compatibility.
- Blog prefix route registered at boot time from `config()` — this is stable because `AppServiceProvider::boot()` runs before route registration.

---

## Known Limitations / Next Session Prep

- Collections / custom data — session 006
- Component/widget system with query builder — session 007
- Two-tier data boundary: established here (CRM data never surfaced to public), architectural enforcement in session 006
- `config:clear` on save uses `Artisan::call()` — works for local and single-server deployments. Multi-server deployments will need a separate cache-clear broadcast mechanism (documented as future work)

---

## Post-Session Fixes

### published_at null crash on post show/index views
- **Cause:** `PostResource` form shows `published_at` only after toggling `is_published` on, with no default — so a post could be published with `published_at = null`. The views called `->toIso8601String()` unconditionally, crashing on null.
- **Fix:** Added `afterStateUpdated` to the `is_published` toggle to auto-fill `published_at` with `now()` when enabled and the field is empty. Wrapped all `$post->published_at` usages in `@if` guards in both `posts/index.blade.php` and `posts/show.blade.php`.

### Workflow note — run migrations without pausing
- Established that migrations should be run immediately via `docker compose exec app php artisan migrate` after writing them. No need to pause and ask the user; this is a personal machine with a local Docker database. Saved to agent memory and noted in `docs/ARCHITECTURE.md`.

---

## Acceptance Criteria Status

- [x] `php artisan migrate && php artisan db:seed` runs clean
- [x] Settings group shows CMS → Users → Finance in correct order
- [x] CMS Settings page saves all fields; changes persist across page reload
- [x] Pico toggle and custom CSS affect the public layout
- [x] SCSS upload compiles successfully; compilation errors surface as validation messages
- [x] `/news` returns published posts index; `/news/{slug}` renders a post; unpublished returns 404
- [x] Public navigation renders from `NavigationItem` records
- [x] Blog prefix conflicting with a page slug is rejected on save
- [x] Alpine.js is available on all public pages
- [x] `window.__site` is present on all public pages
- [x] `php artisan test` passes
