# Session 014 Log — Events: Foundation

**Date:** 2026-03-14
**Branch:** ft-session-014
**Status:** Complete

---

## What Was Built

### Data Model

Three new migrations, all applied cleanly:

- `create_events_table` — canonical event record: title, slug, description, status, physical location fields (address, city, state, zip, map_url, map_label), virtual flags (is_virtual, meeting_url), registration settings (is_free, capacity, registration_open), and recurrence support (is_recurring, recurrence_type, recurrence_rule JSON).
- `create_event_dates_table` — individual occurrences: event_id FK, starts_at, ends_at, status (inherited/draft/published/cancelled), location_override JSON, meeting_url_override, notes.
- `create_event_registrations_table` — registrant records: event_date_id FK, contact_id (nullable FK for future member pre-fill), name, email, phone, company, address fields, status, registered_at, stripe_payment_intent_id (nullable, reserved for ticketing session), notes.

### Models

- `app/Models/Event.php` — fillable, casts, `eventDates()` / `registrations()` (hasManyThrough) relationships, `published` / `upcoming` / `openForRegistration` scopes, `nextDate()`, `isAtCapacity()`, `generateDatesFromRule()`.
- `app/Models/EventDate.php` — fillable, casts, `event()` / `registrations()` relationships, `upcoming` / `published` scopes, `effectiveStatus()`, `effectiveLocation()`, `effectiveMeetingUrl()`, `registrationCount()`, `isAtCapacity()`.
- `app/Models/EventRegistration.php` — fillable, casts, `eventDate()` / `contact()` relationships.

### Public Routing and Controller

- `config/site.php` — added `events_prefix` key (`EVENTS_PREFIX` env, default `'events'`).
- `routes/web.php` — three event routes: index, show (slug + dateId), and register (POST, throttled at 10/min).
- `app/Http/Controllers/EventController.php` — `index()`, `show()`, `register()`. Registration includes honeypot check (`_hp_name`), timing check (`_form_start`, < 3s = silent discard), validation, capacity + cancellation + payment guards, and record creation.

### Blade Templates

- `resources/views/events/index.blade.php` — upcoming event dates list with location summary, free/sold-out/registration-closed badges, pagination.
- `resources/views/events/show.blade.php` — full event detail page: cancellation notice, date/time, physical address with map link, virtual meeting URL (revealed post-registration), description, registration form with honeypot fields, and success/capacity/closed states.

Registration form field policy (privacy-first):
- Required: name, email
- Optional: phone, organization
- Address fields: shown only for in-person events, all optional

### Filament Admin UI

- `app/Filament/Resources/EventResource.php` — Content group, sort 5, tabbed form (Details / Location / Dates), table with status badge, date count, free icon.
- `Pages/ListEvents`, `Pages/CreateEvent`, `Pages/EditEvent` — standard pages.
- `RelationManagers/EventDatesRelationManager.php` — create/edit/delete individual dates, collapsible location override section, "Generate from Rule" action (modal form → calls `generateDatesFromRule()` → bulk inserts dates, shows count notification). Visible only when event is recurring with rule type.
- `RelationManagers/EventRegistrationsRelationManager.php` — read-only registrant view: name, email, date, status badge, registered_at.

### WidgetDataResolver

- `app/Services/WidgetDataResolver.php` — `resolveEvents()` method wired to `source_type = 'events'`. Returns upcoming published event dates with title, slug, starts_at, ends_at, is_virtual, is_free, and public URL.

### Factories

- `EventFactory` — states: `draft()`, `cancelled()`, `virtual()`, `withCapacity(int)`.
- `EventDateFactory` — states: `upcoming()`, `past()`, `cancelled()`.
- `EventRegistrationFactory` — default status `registered`, nullable contact.

### ADR

- `docs/adr/014-event-data-model.md` — documents six architectural decisions: separate event_dates table, no cascade on occurrence edits, nullable contact_id, stripe_payment_intent_id placeholder, honeypot over CAPTCHA, and flat location fields over a locations table.

---

## Bug Fixed During Session

**`Event::isAtCapacity()` — ambiguous column reference**

The `registrations()` relationship is `hasManyThrough(EventRegistration::class, EventDate::class)`. Both `event_registrations` and `event_dates` have a `status` column. The `whereIn('status', [...])` clause was ambiguous to PostgreSQL.

Fix: qualified the column as `whereIn('event_registrations.status', [...])` in [app/Models/Event.php](../app/Models/Event.php).

---

## Tests

16 Pest feature tests, all passing. Full suite: 95 tests / 217 assertions, no failures.

Coverage:
- Published/upcoming scopes
- Public event index (published visible, draft/past hidden)
- Public show page (renders, cancellation notice)
- Registration (creates record, capacity block, cancellation block)
- Honeypot (silent discard when filled)
- Timing check (silent discard under 3 seconds)
- `isAtCapacity()` (null capacity = false; full capacity = true)
- `nextDate()` (returns next future date)

---

## Design Notes

### Recurring Events

The recurrence system works as follows:
- `is_recurring = true` + `recurrence_type = 'rule'` → staff uses "Generate from Rule" action in the Dates relation manager to create occurrences in bulk from a pattern (daily, business days, weekly, monthly-by-weekday, monthly-by-date).
- `is_recurring = true` + `recurrence_type = 'manual'` → staff adds dates individually; no generation action.
- Each generated `event_date` inherits the event's status and location but can be overridden independently.

### Public Form Security Posture (This Session)

- CSRF: Laravel default
- Honeypot: hidden `_hp_name` field + `_form_start` timestamp (< 3s = bot)
- Rate limiting: `throttle:10,1` (10 per IP per minute)
- No external CAPTCHA: Google reCAPTCHA deferred to ticketing session when liability transfer justifies the privacy trade-off

---

## What This Unlocks

- Ticketing/payment session can attach a Stripe PaymentIntent to `event_registrations.stripe_payment_intent_id`
- Member pre-fill session can populate `contact_id` on registration and pre-fill the form for authenticated users
- Email notifications session has a clean `event_registrations` table to query for reminders and confirmations
- The events system collection (`source_type = 'events'`) now returns real data through `WidgetDataResolver`
