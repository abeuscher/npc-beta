# Session 013 Outline — Events: Extended (Dates, Locations, Speakers, Virtual)

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the Event model as built in session 012. Several decisions
> deferred from session 012 resolve here. Read the session 012 log carefully before expanding
> this outline into a full prompt.

---

## Goal

Extend the Event model to support multiple dates, multiple locations, virtual events, and speaker/presenter/host attribution. These are the structural features that distinguish a real event platform from a simple calendar entry.

---

## Key Decisions to Make at Session Start

- **Multiple dates**: Are these truly separate event instances (each with their own registration list and capacity), or are they "occurrences" of a single event that share one registration list? A workshop that runs Monday and Wednesday is different from a conference that runs three days. Decide the model.
- **`EventDate` model**: If multiple dates, does a separate `event_dates` table make sense, or is a JSONB dates array on the Event sufficient? Relational is better for querying and registration-per-date scenarios.
- **Location model**: Standalone reusable `Location` model (address, map link, virtual flag, meeting URL) vs. fields directly on Event or EventDate? Reusable locations are better for organisations with a regular venue.
- **Speakers/Presenters/Hosts — the People question**: Three options: (a) link to Contact records, (b) a separate lightweight `Person` model (name, bio, photo, title — not a CRM record), (c) a JSONB array on Event. Option (b) is probably right for v1 — speakers are often external and shouldn't pollute the donor CRM.
- **Virtual events**: Is `is_virtual` + `meeting_url` on Event/EventDate sufficient, or do we need platform metadata (Zoom meeting ID, etc.)?

---

## Scope (draft — refine at session start)

**In:**
- `event_dates` table (if multiple date model chosen): event_id, starts_at, ends_at, location_id (nullable)
- `locations` table: name, address fields, is_virtual, meeting_url, map_link, notes
- `people` table (lightweight speaker model): name, title, bio, photo, website, is_public
- `event_speakers` pivot: event_id, person_id, role (speaker/presenter/host/panelist)
- Admin UI for all of the above (Filament relation managers)
- Public event template updated to show dates, location(s), speakers
- Widget data resolver updated to return richer event data shape
- Tests for new relationships and public template rendering

**Out:**
- Registration-per-date (complex, assess at session start whether session 012's registration model handles it)
- Speaker public profile pages (could be a future collection or standalone feature)

---

## Rough Build List

- Migrations: event_dates, locations, people, event_speakers pivot
- Models: EventDate, Location, Person; update Event relationships
- Filament: EventDatesRelationManager, relation manager or inline for locations and speakers
- Public event template: multi-date display, location display (physical vs virtual), speaker list
- WidgetDataResolver: return richer event data array
- Tests

---

## Open Questions at Planning Time

- Does the `Person` model overlap with any Contact subtype defined in session 010? If speakers can also be donors or members, the two should link. Assess after session 010 completes.
- Should locations be manageable as a standalone admin resource, or only editable in the context of an event?

---

## What This Unlocks

- Session 014 (ticketing) has the correct date/occurrence model to attach tickets to
- Session 015 (notifications) can send per-date reminders
- People model is available for other uses (staff profiles, board members — could replace some generic collections)
