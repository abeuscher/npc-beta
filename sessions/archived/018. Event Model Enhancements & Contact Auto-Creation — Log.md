# Session 018 Log — Event Model Enhancements & Contact Auto-Creation

## Summary

Extended the Event model with a registration mode system, external registration support,
contact auto-creation, and mailing list opt-in. Redesigned the EventResource admin form
around a cleaner three-section layout. Auto-created landing pages for new events.
Simplified the Navigation admin to a grouped page/post picker. Fixed a routing bug where
pages with slug-based route keys broke Filament admin URLs for slugs containing slashes.

---

## What Was Built

### Registration Mode System
- Replaced `registration_open` boolean with `registration_mode` enum: `open`, `closed`,
  `none`, `external`
- Added `external_registration_url` field — visible only when mode is `external`
- Public registration widget shows mode-specific messaging and an external link button
  when applicable
- `EventController::register()` blocks submission for all non-open modes with
  mode-appropriate messages

### Contact Auto-Creation
- Added `auto_create_contacts` toggle to Event (default true)
- `EventRegistrationObserver::created()` calls `Contact::firstOrCreate` on email after
  sending confirmation, mapping all address fields (`zip` → `postal_code`)
- Sets `contact_id` on the registration record after creation/match
- Skips entirely when email is blank or `auto_create_contacts` is false

### Mailing List Opt-In
- Added `mailing_list_opt_in_enabled` toggle to Event (default false)
- Added `mailing_list_opt_in` boolean to `EventRegistration`
- Opt-in checkbox appears on public registration form only when enabled

### Event Landing Page Auto-Creation
- `EventResource::createLandingPageForEvent()` static method creates a draft Page of
  type `event` at `events/{slug}` with three pre-configured widgets:
  `event_description`, `event_dates`, `event_registration`
- Sets `landing_page_id` on the event; is a no-op if already set
- Called automatically in `CreateEvent::afterCreate()` — no manual button needed on create
- EditEvent retains a "Create basic landing page" toolbar button for events that somehow
  lack one

### EventResource Form Redesign
- Right column sections: Settings (title, slug, status, read-only landing page status),
  Dates (compact repeater with icon-only delete), Registration Details (mode select,
  external URL, capacity, price, toggles)
- `landing_page_status` placeholder shows edit-in-CMS and view-public icon buttons via
  `->hintActions()`; hidden on create

### NavigationItemResource Simplification
- Grouped internal page picker: Pages / Events / Blog (alphabetical within each group)
- Event landing pages appear in the Events group (`Page::where('type', 'event')`)
- Removed blog/events URL-prefix index entries — only real pages and posts
- Virtual `internal_page` field with `page:uuid` / `post:uuid` prefixed keys

### PageObserver
- New observer on `Page` model
- When `type` changes to `event` and slug doesn't already start with `events/`, silently
  prefixes the slug via `updateQuietly`

### Routing Fix: Page Route Key
- Changed `Page::getRouteKeyName()` from `'slug'` to `'id'`
- Fixes Filament admin 404s for pages whose slugs contain forward slashes (e.g.
  `events/als-cool-party` was producing `/admin/pages/events/als-cool-party/edit`)
- Public routing unaffected — `PageController` queries by slug directly

---

## Migrations

- `2026_03_15_180001_update_events_registration_fields` — adds `external_registration_url`,
  `registration_mode` (default `open`), `auto_create_contacts` (default true),
  `mailing_list_opt_in_enabled` (default false); drops `registration_open`
- `2026_03_15_180002_add_mailing_list_opt_in_to_event_registrations` — adds
  `mailing_list_opt_in` boolean (default false)

---

## Tests Added

- `EventRegistrationModeTest` — open/closed/none/external modes, scope, widget messaging
- `ContactAutoCreationTest` — creates contact, respects flag, no duplicate on match,
  populates contact_id, skips on blank email
- `MailingListOptInTest` — checkbox render, stored correctly on submit
- `PageObserverTest` — prefixes slug on type→event, no double prefix
- `EventLandingPageTest` — creates page at correct slug, creates 3 widgets, sets
  landing_page_id, no-op when already set

---

## Files Changed

**Modified:**
- `app/Models/Event.php`
- `app/Models/EventRegistration.php`
- `app/Models/Page.php`
- `app/Observers/EventRegistrationObserver.php`
- `app/Filament/Resources/EventResource.php`
- `app/Filament/Resources/EventResource/Pages/CreateEvent.php`
- `app/Filament/Resources/EventResource/Pages/EditEvent.php`
- `app/Filament/Resources/NavigationItemResource.php`
- `app/Http/Controllers/EventController.php`
- `database/factories/EventFactory.php`
- `resources/views/widgets/event-registration.blade.php`
- `tests/Feature/EventTest.php`

**New:**
- `app/Observers/PageObserver.php`
- `database/migrations/2026_03_15_180001_update_events_registration_fields.php`
- `database/migrations/2026_03_15_180002_add_mailing_list_opt_in_to_event_registrations.php`
- `tests/Feature/ContactAutoCreationTest.php`
- `tests/Feature/EventLandingPageTest.php`
- `tests/Feature/EventRegistrationModeTest.php`
- `tests/Feature/MailingListOptInTest.php`
- `tests/Feature/PageObserverTest.php`
- `sessions/session-018-log.md`
- `sessions/session-019-prompt.md`

---

## Deferred / Notes

- Mailing list opt-in will need a fuller flow in a future session (e.g. integration with
  a mailing list provider or internal list management)
- Event landing pages are created as drafts; a future setting could allow auto-publishing
- No CDN or image storage configured yet — currently local disk via Spatie Media Library;
  to be addressed in an infrastructure session
- Role deletion safety (users holding a deleted role silently lose access) remains a
  known open issue from earlier sessions
