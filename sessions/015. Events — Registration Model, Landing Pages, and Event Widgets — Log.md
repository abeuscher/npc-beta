# Session 015 Log — Events: Registration Model, Landing Pages, and Event Widgets

**Date:** 2026-03-15
**Branch:** ft-session-015
**Tests:** 101 passed, 0 failed

---

## What Was Built

Session 015 corrected two structural problems from Session 014 and added the landing page system with three event-aware widget types.

### Problems Corrected

**Problem 1 — Registration FK**: `event_registrations.event_date_id` was the wrong anchor. Registration now belongs to the *event* (series), not a specific occurrence. A recurring board meeting has one registrant list.

**Problem 2 — Public URL**: `/events/{slug}/{dateId}` exposed a UUID and treated a date as the primary public entity. The correct URL is `/events/{slug}` — one page per event.

---

## Changes Made

### Database (Migrations)

- `2026_03_15_100001_add_landing_page_id_to_events` — adds nullable `landing_page_id` FK to `pages` on the `events` table
- `2026_03_15_100002_alter_event_registrations_swap_fk` — drops `event_date_id` FK/column, adds `event_id` FK pointing directly to `events`

### Models

**`app/Models/Event.php`**
- Added `landing_page_id` to `$fillable`
- Changed `registrations()` from `hasManyThrough(EventRegistration, EventDate)` to `hasMany(EventRegistration::class)`
- Added `landingPage(): BelongsTo` relationship to `Page`
- Simplified `isAtCapacity()` — no longer needs table-qualified column name (no join ambiguity)

**`app/Models/EventRegistration.php`**
- Replaced `event_date_id` with `event_id` in `$fillable`
- Replaced `eventDate(): BelongsTo` with `event(): BelongsTo`

### Routes

Routes were already correct from Session 014 (no `/{dateId}` segment). No changes needed.

### Controllers

**`app/Http/Controllers/EventController.php`**
- `show(string $slug)` — removed `$dateId` param; loads event by slug, loads all upcoming dates as collection; passes `$event`, `$dates`, `$isCancelled`, `$isAtCapacity`, `$registrationOpen` to view; cancelled events render (no 404)
- `register(string $slug)` — removed `$dateId` param; guards check event status directly; creates `EventRegistration` with `event_id` instead of `event_date_id`

**`app/Http/Controllers/PageController.php`**
- Added event data injection: if widget config contains `event_id`, resolves the event and upcoming dates and merges them into the `Blade::render()` call as `$event` and `$dates`

### Blade Templates

**`resources/views/events/index.blade.php`**
- Updated both event links from `route('events.show', [$date->event->slug, $date->id])` to `route('events.show', $date->event->slug)`

**`resources/views/events/show.blade.php`**
- Complete rewrite to use `$event` and `$dates` (collection) instead of single `$date`
- Location fields now read directly from `$event` (not `$date->effectiveLocation()`)
- Dates list section shows all upcoming dates with location summary per date
- Registration form action updated to `route('events.register', $event->slug)`
- Meeting URL reads `$event->meeting_url` directly
- Cancellation notice driven by `$event->status === 'cancelled'`

### Seeders

**`database/seeders/WidgetTypeSeeder.php`**
- Added three new widget types: `event_description`, `event_dates`, `event_registration`

**`database/seeders/DatabaseSeeder.php`**
- Fixed stale `type` field in Contact `firstOrCreate` calls (column was removed in Session 013)

### Widget Partials (new files)

- `resources/views/widgets/event-description.blade.php` — renders `$event->description` if set
- `resources/views/widgets/event-dates.blade.php` — lists upcoming dates with location summary; shows "No upcoming dates scheduled" when empty
- `resources/views/widgets/event-registration.blade.php` — full registration form with honeypot, timing check, and state-based display (cancelled / at capacity / registration closed / open)

### Filament Admin

**`app/Filament/Resources/EventResource/Pages/EditEvent.php`**
- Replaced `viewNextDate` action with three new header actions:
  - `viewEventPage` — links to landing page URL if LP exists, otherwise event URL
  - `createLandingPage` — visible when `landing_page_id === null`; creates a draft Page with three pre-configured event widgets and sets `landing_page_id`; redirects to PageResource edit
  - `editLandingPage` — visible when `landing_page_id !== null`; links to PageResource edit

**`app/Filament\Resources\EventResource.php`**
- Replaced `Tabs` layout with `Split` layout (2-column left, 1-column right sidebar)
- Left: Description section (RichEditor) + Location section (all location fields)
- Right: Settings section (title, slug, status, is_free, capacity, registration_open) + Recurrence section
- Removed the Dates tab placeholder (relation manager handles dates below the form)

**`app/Filament/Resources/EventResource/RelationManagers/EventRegistrationsRelationManager.php`**
- Removed stale `eventDate.starts_at` column (relationship no longer exists)

### Factories & Tests

**`database/factories/EventRegistrationFactory.php`**
- Changed `event_date_id => EventDate::factory()` to `event_id => Event::factory()`

**`tests/Feature/EventTest.php`**
- Updated all route calls to remove `$date->id` argument
- Updated registration factory calls to use `event_id`
- Capacity test now creates registrations against `event_id`
- Added 5 new tests:
  - `creates a landing page with three widgets when action is triggered`
  - `view event page button uses landing page URL when landing_page_id is set`
  - `event_description widget renders event description on a page`
  - `event_dates widget renders upcoming dates on a page`
  - `event_registration widget renders the registration form on a page`

---

## Acceptance Criteria Status

- [x] `migrate:fresh` runs cleanly with all migrations
- [x] `event_registrations` has `event_id` FK, no `event_date_id`
- [x] `events` has `landing_page_id` nullable FK to pages
- [x] Registration creates a record with `event_id` set
- [x] Public URL is `/events/{slug}` — no date ID anywhere
- [x] Event show page lists all upcoming dates with location summary
- [x] Cancelled event renders with notice, not 404
- [x] Capacity enforced at event level
- [x] Three event widget types seeded in `widget_types`
- [x] Widget partials render correctly when event_id is in config
- [x] "Create basic landing page" action creates page + 3 widgets + sets landing_page_id
- [x] "Edit landing page" link appears after LP is created
- [x] EventResource form uses Split layout (no tabs)
- [x] All tests pass (101 passed)
