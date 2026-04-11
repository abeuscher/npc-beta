# Session 016 Log — Page Type System & Unified Public Routing

**Date:** 2026-03-15
**Branch:** ft-session-016
**Tests:** 100 passed, 0 failed

---

## What Was Built

Session 016 unified all public routing through the page system. Previously, events had their
own `EventController::show()` and a parallel `/events/{slug}` route alongside the page-based
`/{slug}` route. This session removed that redundancy: every public URL is now a page, and
the catch-all slug route accepts forward-slash segments (`events/board-meeting`).

---

## Changes Made

### Database (Migrations)

- `2026_03_15_120000_add_type_to_pages_table` — adds `type` string column (default `default`)
  to the `pages` table. Current values: `default`, `event`, `post`.

### Models

**`app/Models/Page.php`**
- Added `type` to `$fillable`
- Added `$attributes = ['type' => 'default']` so new Page instances default to `default`
  without requiring a DB round-trip

### Routes (`routes/web.php`)

- Removed `GET /events` → `EventController@index`
- Removed `GET /events/{slug}` → `EventController@show`
- Kept `POST /events/{slug}/register` → `EventController@register` (throttled)
- Updated the catch-all slug route regex from `[a-z0-9\-]+` to `.*`, allowing forward-slash
  segments such as `events/board-meeting` and `blog/my-post`

### Controllers

**`app/Http/Controllers/EventController.php`**
- Removed `index()` method — replaced by a page with an `events_listing` widget
- Removed `show()` method — replaced by PageController serving pages of type `event`
- Updated `register()`:
  - Event is loaded at the top of the method (before honeypot checks) so the landing page
    URL is available throughout
  - All redirects now go to `url('/' . $event->landingPage->slug)` if a landing page exists,
    or `url('/events')` as a fallback — `route('events.show', ...)` no longer exists

### Filament Admin

**`app/Filament/Resources/EventResource/Pages/EditEvent.php`**
- "Create basic landing page" action now:
  - Creates the Page without an explicit slug (lets Spatie generate from title), then calls
    `$page->update(['slug' => 'events/' . $event->slug])` — safe because `doNotGenerateSlugsOnUpdate()` is set
  - Sets `type => 'event'` on the page
- "View event page" fallback URL updated from `route('events.show', ...)` to
  `url('/' . $eventsPrefix . '/' . $record->slug)`

**`app/Filament/Resources/PageResource.php`**
- Slug field: validation rule changed from `alpha_dash` to `regex:/^[a-z0-9\-\/]+$/` to allow
  forward-slash path segments
- Added `type` Select field (Default / Event / Post) to the basic info section, defaulting to Default
- Added `type` badge column to the pages table with colour coding (gray / warning / info)

### Services

**`app/Services/WidgetDataResolver.php`**
- `resolveEvents()`: removed `route('events.show', ...)` reference (route no longer exists)
- Eager-loads `event.landingPage` on the EventDate query
- URL now resolves to `url('/' . $date->event->landingPage->slug)` when a landing page exists,
  or `url('/events')` as a fallback

### Seeders

**`database/seeders/WidgetTypeSeeder.php`**
- Added `events_listing` widget type: server-rendered, uses `events` collection, config schema
  has optional `heading` field, template is `@include('widgets.events-listing')`
- Added `blog_listing` widget type: server-rendered, uses `blog_posts` collection, config schema
  has optional `heading` field, template is `@include('widgets.blog-listing')`

**`database/seeders/BasePageSeeder.php`** (new file)
- Creates 5 base pages on fresh install (idempotent via `firstOrCreate`):
  - Home (`slug: home`, type: default, published)
  - About (`slug: about`, type: default, draft)
  - Contact (`slug: contact`, type: default, draft)
  - Events (`slug: events`, type: default, published) — seeded with `events_listing` widget
  - Blog (`slug: blog`, type: default, published) — seeded with `blog_listing` widget
- Seeds 5 navigation items (Home, About, Contact, Events, Blog) pointing to the above pages
- Calls `WidgetTypeSeeder` as a dependency before creating pages

**`database/seeders/DatabaseSeeder.php`**
- Added `$this->call(WidgetTypeSeeder::class)` — widget types are now seeded in all environments
- Replaced inline home page creation with `$this->call(BasePageSeeder::class)` — all base
  pages and navigation are now managed by `BasePageSeeder`

### Blade Templates (new files)

- `resources/views/widgets/events-listing.blade.php` — renders upcoming event dates using the
  `$events` collection variable; shows title, formatted datetime, free badge, and link
- `resources/views/widgets/blog-listing.blade.php` — renders recent posts using the
  `$blog_posts` collection variable; shows title, optional excerpt, and date

### Tests

**`tests/Feature/EventTest.php`**
- Removed tests for `GET /events` (EventController::index) and `events.show` route — those
  routes no longer exist
- Updated all registration redirect assertions from `route('events.show', ...)` to
  `assertRedirect()` or specific landing page URLs
- Added `it registration redirects to the landing page when one exists` — verifies the new
  redirect target
- Updated landing page creation test to assert `slug = 'events/test-event'` and `type = 'event'`
- Added `it events_listing widget renders upcoming events on a page` — end-to-end test of the
  new widget type

**`tests/Feature/PageTest.php`**
- Removed `content` field from `Page::create()` calls (column was dropped in Session 015)
- Added `it serves a published page with a nested slug` — verifies `.*` catch-all route works
  for slugs like `events/board-meeting`
- Added `it page type defaults to default` — verifies model-level default

### Planning

- `sessions/session-021-outline.md` — outline for a future installer session: browser wizard
  + `php artisan install` CLI, toggleable content packs, `InstallationState` service
- `sessions/future-sessions.md` — updated index to reference session 021

---

## Acceptance Criteria Status

- [x] `pages` table has `type` column (default `default`)
- [x] New pages default to type `default` without an explicit value
- [x] `GET /events` and `GET /events/{slug}` routes removed
- [x] `POST /events/{slug}/register` route retained and working
- [x] Catch-all `/{slug}` route accepts forward-slash segments
- [x] `/events/board-meeting` served by PageController (not EventController)
- [x] "Create landing page" action produces slug `events/{event-slug}` and type `event`
- [x] `events_listing` widget type seeded; renders upcoming dates on any page
- [x] `blog_listing` widget type seeded; renders recent posts on any page
- [x] BasePageSeeder creates 5 base pages + 5 nav items on fresh install
- [x] WidgetTypeSeeder runs in all environments via DatabaseSeeder
- [x] PageResource slug field accepts forward slashes; type field present
- [x] All tests pass (100 passed)
