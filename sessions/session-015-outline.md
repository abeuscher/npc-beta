# Session 015 Outline — Events: Registration Model, Landing Pages, and Event Widgets

> **Session Preparation**: Design decisions are fully locked from the session 014 design
> conversation. No design discussion needed — begin coding immediately. Read the session 014
> log and ADR 014 for context on what was built and why.

---

## Context

Session 014 built the Events foundation with `event_registrations` linked to `event_date_id`.
During post-build review this was identified as the wrong anchor: registration belongs to the
event (the series), not a specific occurrence. A recurring board meeting has one registrant
list. Dates are variable operational details. This session corrects the model, simplifies the
public URL, and builds the landing page creation system with three event-aware widget types.

---

## Key Decisions (locked)

- Registration belongs to the **event**, not the date. `event_registrations.event_date_id` → `event_id`.
- Public URL is `/events/{slug}` only. No date ID in any public URL ever.
- Event landing pages are regular **Pages** (page builder), not a separate model.
- `events.landing_page_id` — nullable FK to `pages`. Auto-generated event page is the fallback.
- Three new widget types: `event_description`, `event_dates`, `event_registration`.
- "Create basic landing page" action auto-builds a Page with all three widgets pre-wired.
- Wipe all event data: run `migrate:fresh` before applying new migrations.
- EventResource form: replace Tabs with Split layout (content left, settings sidebar right).

---

## Scope

**In:**
- New migrations: `add_landing_page_id_to_events`, `alter_event_registrations_swap_fk`
- `migrate:fresh` to wipe and rebuild
- Updated `Event` and `EventRegistration` models
- Simplified routes and controller (`/events/{slug}`, no date ID)
- Updated Blade templates (date list + single registration form per event)
- Three new event widget types + render logic
- "Create basic landing page" action on EditEvent
- EventResource form UI: Split layout, renamed sections
- Updated "View event page" button (prefers LP URL when set)
- Updated tests and factories

**Out:**
- Ticketing / Stripe (session 016)
- Member pre-fill on registration
- Per-date attendance tracking
- Email notifications

---

## Rough Build List

1. Migrations: `add_landing_page_id_to_events`, `alter_event_registrations_swap_fk`
2. `php artisan migrate:fresh` — wipes all data, re-runs all migrations clean
3. Update `EventRegistration`: `event_id` FK, `event()` relationship
4. Update `Event`: `landing_page_id` fillable, `landingPage()` relationship, `registrations()` now `hasMany` (not hasManyThrough)
5. Update routes: drop `/{dateId}` segments from show and register routes
6. Update `EventController`: `show($slug)` loads all upcoming dates; `register($slug)` anchors to event
7. Update Blade templates: event show page renders date list + single form; index links updated
8. Seed or register three new widget types in `widget_types` table
9. Add render logic for the three widget types (follow existing widget render pattern)
10. "Create basic landing page" action on `EditEvent` page
11. EventResource form: Split layout — main area (description, location) + sidebar (status, registration, recurrence, LP link)
12. Update `EditEvent` header: "View event page" button (LP URL if set, else `/events/{slug}`)
13. Update tests: routes, registration model, event-level capacity
14. Update `EventRegistrationFactory`: `event_id` not `event_date_id`

---

## Widget Specs

### `event_description`
Config: `{ "event_id": "uuid" }`
Output: renders `event.description` (rich text)

### `event_dates`
Config: `{ "event_id": "uuid" }`
Output: all upcoming `event_dates` ordered by `starts_at`, one per line:
```
May 5, 2026 — Meadowlands, NJ
June 10, 2026 — Boston, MA
July 8, 2026 — Online
```
Location summary rule: city + state if in-person; "Online" if virtual only; "In-person + Online" if hybrid.

### `event_registration`
Config: `{ "event_id": "uuid" }`
Output: full registration form (honeypot, capacity check, cancellation check).
POSTs to `/events/{slug}/register`. Shows success / sold-out / closed as appropriate.

---

## "Create Basic Landing Page" Action

Appears on EditEvent header when `landing_page_id` is null.
When already set, shows "Edit landing page" link instead.

Steps:
1. Create `Page` (title = event title, slug = event slug, status = draft)
2. Create three `PageWidget` records (sort 1–3): EventDescription, EventDates, EventRegistration,
   each with config `{ "event_id": "<uuid>" }`
3. Set `event.landing_page_id` = new page ID, save
4. Redirect to Filament page editor for the new page

---

## Updated Public Routes

```php
Route::get("/{$prefix}",                   [EventController::class, 'index'])->name('events.index');
Route::get("/{$prefix}/{slug}",             [EventController::class, 'show'])->name('events.show');
Route::post("/{$prefix}/{slug}/register",   [EventController::class, 'register'])
    ->name('events.register')
    ->middleware('throttle:10,1');
```

---

## What This Unlocks

- Session 016 (Ticketing): Stripe attaches to `event_registrations` which is cleanly event-scoped
- Future: member pre-fill looks up `contact_id` on an event registration
- Future: exporters work against a clean event-level registrant list
- Future: email notifications query `event_registrations` by `event_id`
