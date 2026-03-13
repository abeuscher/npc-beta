# Session 005 Prompt — Site Settings, Public Frontend, and Blog

## Context

Session 004 built the complete admin information architecture: three domain groups (CRM, Content, Finance), all models and Filament resources, and a populated demo seeder. The admin panel is fully navigable.

Session 005 does three things in parallel: establishes site-wide settings infrastructure, wires up the public frontend (Alpine, Pico, custom CSS/SCSS), and delivers the first real public content surfaces (blog index, blog post, public navigation).

---

## Architecture Decisions Established in Pre-Session Discussion

### SiteSetting — database-driven config

A `site_settings` table stores key/value pairs. An `AppServiceProvider` reads from this table at boot and merges values into `config('site.*')` (with a try/catch so fresh installs before migration don't crash). Routes and views use `config('site.*')` with hard-coded defaults as fallbacks.

When a setting is saved in Filament, the application config cache is cleared so the next request re-reads from the database. This is the correct pattern — it does not require a build step or server restart.

### Blog prefix routing

The blog prefix (`config('site.blog_prefix', 'news')`) is registered in `web.php` at boot time using the config value. The URL pattern is `/{blog_prefix}` (index) and `/{blog_prefix}/{slug}` (show). Because the config is seeded and cached, the route is always consistent with the stored setting. Changing the prefix via the admin requires a `php artisan config:clear` — document this in the settings UI as helper text.

### CSS/SCSS upload

`composer require scssphp/scssphp` (pure PHP SCSS compiler, no Node). The upload field accepts `.css` or `.scss`. On upload, if the file is `.scss` it is compiled to CSS via `scssphp\scssphp\Compiler` and the compiled output is stored (not the source). The compiled CSS is served from storage. If compilation fails, return a Filament validation error with the compiler's error message.

Logo is handled the same way — file upload stored via Spatie Media Library, attached to the SiteSetting model (or stored as a path value in the settings table).

### Two-tier data boundary (established for session 006+)

Content data and custom Collections are surfaceable to the public frontend. CRM data (Contacts, Memberships, Donations) is never surfaced by the component/widget system. This is enforced architecturally in session 006.

---

## Tasks

### 1. SiteSetting Infrastructure

**Migration** `create_site_settings_table`:
- `id` — bigint (standard, not UUID — this is config data, not a domain entity)
- `key` — string, unique
- `value` — text, nullable
- `group` — string (for grouping in the admin UI: `general`, `styles`, `finance`)
- `type` — string (for casting: `string`, `boolean`, `integer`, `json`) — default `string`
- timestamps

**Model** `app/Models/SiteSetting.php`:
- Standard Eloquent model (no UUID, no soft deletes)
- Static helper: `SiteSetting::get(string $key, mixed $default = null)` — reads from DB with Redis cache (TTL: 60 minutes)
- Static helper: `SiteSetting::set(string $key, mixed $value)` — writes to DB and flushes the cache key
- Cast `value` based on `type` column when reading

**AppServiceProvider boot**:
```php
try {
    $settings = SiteSetting::all()->keyBy('key');
    config([
        'site.name'          => $settings->get('site_name')?->value    ?? config('app.name'),
        'site.base_url'      => $settings->get('base_url')?->value      ?? 'http://localhost',
        'site.blog_prefix'   => $settings->get('blog_prefix')?->value   ?? 'news',
        'site.description'   => $settings->get('site_description')?->value ?? '',
        'site.timezone'      => $settings->get('timezone')?->value      ?? 'America/Chicago',
        'site.contact_email' => $settings->get('contact_email')?->value ?? '',
        'site.use_pico'      => (bool) ($settings->get('use_pico')?->value ?? false),
        'site.custom_css'    => $settings->get('custom_css_path')?->value ?? null,
        'site.logo'          => $settings->get('logo_path')?->value     ?? null,
    ]);
} catch (\Throwable $e) {
    // DB not ready (fresh install before migrations) — fall through to defaults
}
```

**Seeder** — add to `DatabaseSeeder` (always runs, not local-only — these are installation defaults):

Use `SiteSetting::firstOrCreate(['key' => ...], [...])` for each:

| key | value | group | type |
|-----|-------|-------|------|
| site_name | My Organization | general | string |
| base_url | http://localhost | general | string |
| blog_prefix | news | general | string |
| site_description | (empty) | general | string |
| timezone | America/Chicago | general | string |
| contact_email | (empty) | general | string |
| use_pico | false | styles | boolean |
| custom_css_path | null | styles | string |
| logo_path | null | styles | string |

---

### 2. Filament Settings Pages

These are Filament `Page` classes (not Resources). They render a form backed by `SiteSetting` records.

#### CmsSettingsPage

`app/Filament/Pages/Settings/CmsSettingsPage.php`

- Navigation group: `Settings`
- Navigation label: `CMS`
- Navigation icon: `heroicon-o-cog-6-tooth`
- Navigation sort: 1

**Form — General section:**
- Site Name (`site_name`) — TextInput, required
- Base URL (`base_url`) — TextInput, helperText: "Used for generating absolute links. Example: https://yourorg.org"
- Blog Prefix (`blog_prefix`) — TextInput, alpha_dash, required, helperText: "The URL segment for your blog. Example: 'news' → /news/post-slug. Changes require a cache clear to take effect.", validation: must not match any existing `pages.slug` value (custom rule — see task 6), must not be a reserved word (admin, horizon, up, login, logout, register)
- Site Description (`site_description`) — Textarea, nullable
- Timezone (`timezone`) — Select with common US timezones + UTC
- Contact Email (`contact_email`) — TextInput, email, nullable

**Form — Styles section** (collapsible, collapsed by default):
- Use Pico CSS (`use_pico`) — Toggle, helperText: "Enables Pico CSS, a lightweight classless stylesheet. Good baseline for unstyled installations."
- Custom Stylesheet (`custom_css_path`) — FileUpload, accepts `.css` and `.scss`. On upload: detect extension, if `.scss` compile via `scssphp\scssphp\Compiler`, store compiled output to `storage/app/public/site/custom.css`, save path to setting. If compilation fails, throw a Filament validation error with the compiler's error message. HelperText: "Upload a .css or .scss file. SCSS is compiled on upload."
- Logo (`logo_path`) — FileUpload, accepts `.png`, `.jpg`, `.svg`, stores to `storage/app/public/site/logo.*`, saves path to setting.

**Save behavior**: On form save, call `SiteSetting::set()` for each field, then `Artisan::call('config:clear')`.

#### FinanceSettingsPage

`app/Filament/Pages/Settings/FinanceSettingsPage.php`

- Navigation group: `Settings`
- Navigation label: `Finance`
- Navigation icon: `heroicon-o-banknotes`
- Navigation sort: 3
- Form: empty Section with placeholder text: "Finance settings will be configured here. QuickBooks and Stripe configuration coming soon."

---

### 3. Settings Navigation Restructure

Current state: `UserResource` shows as "Users" under Settings (sort 1).

Target state: Settings group contains three items in this order:
1. **CMS** — new page, sort 1
2. **Users** — existing UserResource, update sort to 2, keep label and icon unchanged
3. **Finance** — new page, sort 3

**Note on "CRM" sub-label**: The desired long-term state is a visual "CRM" section header above "Users" within the Settings group, as the Settings section will eventually hold CRM-specific configuration alongside user management. Filament v3 does not support non-clickable section headers within a nav group natively. This is a documented future enhancement. For now, the flat ordering (CMS → Users → Finance) achieves the correct grouping intent without the sub-label.

Update `UserResource`: `$navigationSort = 2`.

---

### 4. Public Layout — Alpine, Pico, Custom CSS, Site Data

Update `resources/views/layouts/public.blade.php`:

**In `<head>`:**
- `<title>` uses `config('site.name')` as default
- Alpine.js from CDN (stable 3.x, defer): `<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>`
- Pico CSS: `@if(config('site.use_pico'))` — load from CDN (not `.env`)
- Custom stylesheet: `@if(config('site.custom_css'))` → `<link rel="stylesheet" href="{{ asset(config('site.custom_css')) }}">`
- `@stack('styles')` — unchanged, still present after the above

**Before `</body>`:**
```blade
<script>
window.__site = {
    name: @json(config('site.name')),
    blogPrefix: @json(config('site.blog_prefix')),
    contactEmail: @json(config('site.contact_email')),
};
</script>
@stack('scripts')
```

**Public navigation** — add a `<nav>` in the layout body:
- Query: `NavigationItem::where('is_visible', true)->whereNull('parent_id')->orderBy('sort_order')->with(['children', 'page', 'post'])->get()`
- Render as a `<ul>` with nested `<ul>` for children
- URL resolution per item: if `page_id` → `url("/{$item->page->slug}")`, if `post_id` → `url(config('site.blog_prefix') . "/{$item->post->slug}")`, if `url` → `$item->url`
- Simple Alpine mobile toggle: `x-data="{ open: false }"` on the nav element with a hamburger button

---

### 5. Blog Routes, Controller, and Views

**`web.php`** — add after the existing page routes:
```php
$blogPrefix = config('site.blog_prefix', 'news');
Route::get("/{$blogPrefix}", [PostController::class, 'index'])->name('posts.index');
Route::get("/{$blogPrefix}/{slug}", [PostController::class, 'show'])->name('posts.show');
```

**`PostController`** (`app/Http/Controllers/PostController.php`):
- `index()`: paginate published posts (15/page), ordered by `published_at` desc. Pass to `posts/index` view.
- `show(string $slug)`: find published post by slug, abort(404) if not found or `is_published = false`. Pass to `posts/show` view.
- Both actions pass `$title` and `$description` for the layout.

**Views**:
- `resources/views/posts/index.blade.php` — extends `layouts.public`, lists posts with title, excerpt, author name, published date, and "Read more" link
- `resources/views/posts/show.blade.php` — extends `layouts.public`, renders full post content, author, published date, back link to index

---

### 6. Blog Prefix Conflict Validation

Custom validation rule in `CmsSettingsPage` when saving `blog_prefix`:
- Check `Page::where('slug', $value)->exists()` — if true, reject with: "This prefix conflicts with an existing page slug '/{value}'. Choose a different prefix or rename the page."
- Check against reserved words: `admin`, `horizon`, `up`, `login`, `logout`, `register`

---

### 7. composer require

```bash
composer require scssphp/scssphp
```

Run before writing the upload handler. Compiler class: `ScssPhp\ScssPhp\Compiler`.

---

### 8. Tests

- `SiteSettingTest`: `get()` returns default when key missing; returns cast value when present; `set()` invalidates cache
- `PostTest`: published post accessible at `/{blog_prefix}/{slug}`; unpublished returns 404; missing slug returns 404; index returns only published posts
- `BlogPrefixValidationTest`: blog_prefix matching an existing page slug fails validation; reserved word fails validation

---

### 9. Documentation

- Update `docs/ARCHITECTURE.md`: note SiteSetting infrastructure, config-driven routing, two-tier data boundary (established here, enforced in session 006)
- `docs/decisions/011-site-settings-pattern.md`: DB-driven config approach, boot-time merge into `config('site.*')`, config:clear requirement on changes, why not `.env`
- `sessions/session-005-log.md`: written at session end

---

## What This Session Does Not Cover

- Collections / custom data (session 006)
- Component/widget system with query builder (session 007)
- Member portal / gated content
- Events
- Finance integrations

---

## Acceptance Criteria

- [ ] `php artisan migrate && php artisan db:seed` runs clean
- [ ] Settings group shows CMS → Users → Finance in correct order
- [ ] CMS Settings page saves all fields; changes persist across page reload
- [ ] Pico toggle and custom CSS affect the public layout
- [ ] SCSS upload compiles successfully; compilation errors surface as validation messages
- [ ] `/news` returns published posts index; `/news/{slug}` renders a post; unpublished returns 404
- [ ] Public navigation renders from `NavigationItem` records
- [ ] Blog prefix conflicting with a page slug is rejected on save
- [ ] Alpine.js is available on all public pages
- [ ] `window.__site` is present on all public pages
- [ ] `php artisan test` passes
