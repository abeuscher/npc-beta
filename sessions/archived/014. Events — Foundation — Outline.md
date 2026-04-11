# Session 012 Outline — Events: Foundation

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline alongside the completed CRM taxonomy (session 010),
> user roles (session 011), and the Events system collection seeded in session 006. There is a
> substantial design conversation needed at the start of this session. Reserve time for it.
> Do not begin coding until the data model is agreed.

---

## Goal

Build the Event model, its admin UI, its public landing page, and its connection to CRM registration records. This session covers free events only — ticketing and payment are session 014. The Events system collection (source_type = events) seeded in session 006 should be resolved against this real Event model by the end of this session.

---

## Key Decisions to Make at Session Start

- **Event date model**: Single date only (this session) or design for multiple dates from the start? A recurring workshop and a one-off gala have very different needs. Getting this wrong is expensive to fix later.
- **Location model**: Is location a set of fields on Event, or a separate reusable `Location` model? Multiple locations (or virtual + physical hybrid) argue for a separate model.
- **Speakers/Presenters/Hosts**: Are these linked to Contact records, a standalone `Person` model, or just a text name field? Linking to Contacts is powerful but adds complexity. Decide the MVP approach.
- **Virtual events**: Meeting links, platform (Zoom, Teams, etc.). Is this in scope for this session or session 013?
- **Public landing page**: Does an Event get its own Page record (created automatically), or does the public URL resolve from an `events_prefix` site setting + event slug directly (like blog posts)?
- **Registration**: In this session, does registration just create a Contact + EventRegistration record, or is there a public-facing registration form? If a form, that intersects with the form builder (deferred). Decide the MVP.
- **Mailchimp**: Is the registration list ↔ Mailchimp sync in scope for this session, session 015, or later?
- **Grant activity**: Confirmed deferred. Note in the model but do not build.

---

## Scope (draft — refine at session start)

**In:**
- `events` table: title, slug, description, starts_at, ends_at, location fields, is_virtual, meeting_url, status (draft/published/cancelled), capacity (nullable), is_free, registration_open
- `event_registrations` table: event_id, contact_id (nullable for anonymous), name, email, status (registered/waitlisted/cancelled/attended), registered_at
- Event model with scopes: upcoming, published, open for registration
- Public event URL routing via `events_prefix` site setting
- Public event landing page (Blade template)
- EventRegistration admin view (read-only in first pass — write/form in later session)
- Filament EventResource: full admin UI for creating/editing events
- Wire Events system collection (`source_type = events`) to resolve from the real Event model in `WidgetDataResolver`
- Event list widget or confirm CollectionListWidget handles it via the system collection

**Out:**
- Ticketing and payment (session 014)
- Multiple dates / locations (session 013)
- Speakers/presenters model (session 013, unless simple text field is sufficient here)
- Email notifications/reminders (session 015)
- Public registration form (intersects with form builder — scope carefully)

---

## Rough Build List

- Migrations: events, event_registrations
- Models: Event, EventRegistration
- Scopes: upcoming, published, openForRegistration
- Public routes + controller + Blade template for event landing page and event index
- Filament EventResource (Content group, sort 5)
- EventRegistrationsRelationManager on EventResource
- Update WidgetDataResolver: events handle → Event model query
- Update session 006 Events system collection field schema if needed
- Tests: event scopes, public routing, registration creation
- ADR: Event data model decisions

---

## Open Questions at Planning Time

- Should the event registration form be a public-facing Blade form in this session, or is that deferred to the form builder session?
- Is capacity enforcement (waitlist logic) needed in this session or later?
- How does a cancelled event behave publicly — 404, or a "this event is cancelled" page?

---

## What This Unlocks

- Session 013 can extend dates, locations, speakers without a data model rewrite
- Session 014 has a clean registration record to attach payment to
- Session 015 can send emails to `event_registrations` records
- Import/Export session has a well-defined registrant data shape
