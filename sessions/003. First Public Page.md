# Session 003 Prompt — First Public Page

## Context

Session 002 removed Twill and confirmed the admin stack: **Filament + Blade + Livewire**. Filament is running cleanly at `/admin`. The public website at `localhost/` currently serves Laravel's default welcome view.

Session 003 builds the first piece of the public-facing web layer: a **Page model** managed in Filament, rendered to the browser at a public URL.

## Frontend Tooling — Decided

**JavaScript:** Alpine.js only. Already in the stack. No additional JS libraries to start.

**CSS:** No framework required. The public Blade layout is built from day one with two hooks:
1. An optional Pico CSS include — off by default, enabled via a config flag (`THEME_PICO=true` or similar)
2. A `@stack('styles')` slot for a completely separate stylesheet, overriding or replacing Pico

This means any installation can: run with no CSS at all (bare HTML, useful for testing), drop in Pico for quick legibility, or supply a fully custom stylesheet with no framework in the way. No component library selected — defer until there is something concrete to style.

**No component library discussion this session.** That decision is deferred. Start with less.

---

## Primary Goal

A page record created in Filament should be reachable in the browser. The homepage (`/`) should render a page with slug `home`. Other pages should be reachable at `/{slug}`. This pipeline — Filament manages the record, Laravel routes it, Blade renders it — is the foundation everything else builds on.

---

## Tasks

### 1. Page Model and Migration

Create `database/migrations/[timestamp]_create_pages_table.php`:

- `id` — UUID primary key
- `title` — string, required
- `slug` — string, unique, URL-safe
- `content` — longtext (will hold TipTap HTML output)
- `meta_title` — string, nullable (SEO)
- `meta_description` — text, nullable (SEO)
- `is_published` — boolean, default false
- `published_at` — timestamp, nullable
- Timestamps, soft deletes

Create `app/Models/Page.php`:
- `HasUuids`, `SoftDeletes`, `HasFactory`
- `$fillable` for all above fields
- `$casts` for `is_published` (boolean), `published_at` (datetime)
- Spatie `sluggable` for auto-generating slugs from title (package already installed)

### 2. Filament Page Resource

Create `app/Filament/Resources/PageResource.php`:

**Form fields:**
- Title (text input, required) — generates slug live
- Slug (text input, editable, unique validation)
- Content (RichEditor — use Filament's built-in `RichEditor` for now; TipTap plugin can be added in a later session)
- Meta Title, Meta Description (collapsible SEO section)
- Is Published (toggle)
- Published At (date-time picker, shown when is_published is true)

**List columns:** title, slug, is_published (badge), published_at, updated_at

**Filters:** is_published

Pages: `ListPages`, `CreatePage`, `EditPage`

### 3. Public Routes and Controller

Update `routes/web.php`:

```php
Route::get('/', [PageController::class, 'home']);
Route::get('/{slug}', [PageController::class, 'show']);
```

Create `app/Http/Controllers/PageController.php`:
- `home()` — finds the published page with slug `home`, returns 404 if not found or not published
- `show($slug)` — finds a published page by slug, returns 404 if not found or not published

### 4. Blade Templates

Create a minimal but real layout. No heavy design — clean, functional, and ready to be styled later.

`resources/views/layouts/public.blade.php` — base layout:
- `<head>` with meta charset, viewport, title (uses `$page->meta_title ?? $page->title`)
- Meta description if set
- CSS link — whichever approach was chosen in the session 003 tooling discussion
- `<body>` with `@yield('content')` or `$slot`
- No nav yet — that comes when navigation is built

`resources/views/pages/show.blade.php` — page view:
- Renders `{!! $page->content !!}` inside the layout
- Uses the page title as the `<h1>`

### 5. Database Seeder

Create `database/seeders/DatabaseSeeder.php` that:
- Creates the initial admin `User` from env vars (`ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`)
- Assigns the `super_admin` role
- Creates all 6 Spatie roles if they don't exist: `super_admin`, `crm_manager`, `staff`, `finance_manager`, `events_manager`, `read_only`
- Creates a `home` page with placeholder content so `localhost/` works immediately after `php artisan db:seed`

This replaces the manual `php artisan make:filament-user` step with a repeatable, env-driven setup command.

### 6. Tests

`tests/Feature/PageTest.php`:
- Published page with slug `home` is served at `/`
- Published page is served at `/{slug}`
- Unpublished page returns 404
- Non-existent slug returns 404
- Slug is auto-generated from title

### 7. Documentation

After all tasks are complete, write `sessions/003.md` capturing what was built, any decisions made, and the current state.

---

## Routing Note

`localhost/` maps to the home page. `localhost/admin` maps to Filament. `localhost/{slug}` maps to any other page. These do not conflict. The slug route is registered last in `web.php` so Filament's routes (registered by its service provider) take priority.

If a slug ever collides with a reserved path (e.g., `admin`, `horizon`), the slug route will simply never be reached for that value. A validation rule on the slug field should prevent `admin`, `horizon`, `up`, and other reserved words from being used as slugs.

---

## Suggestions for Discussion

The following are not required for session 003 but are worth raising:

**Navigation model.** A simple `NavigationItem` model (label, url/slug, sort order, parent) managed in Filament and rendered in the public layout header. Could be included in session 003 or deferred.

**HTMX.** If HTMX is chosen in the tooling discussion, evaluate whether any session 003 interactions (e.g., the home page contact form placeholder) are worth building with it immediately to validate the pattern before more complex features depend on it.

**`robots.txt` and `sitemap.xml`.** Not urgent for local dev. Worth adding before any public deployment.
