# Events Index Page — Spec

## Purpose

Public-facing list of upcoming events for a single org. Primary browse surface. Optimized to look dignified with minimal art direction and to scan well at 5–30 events.

Not a discovery aggregator. Not a calendar grid. Not an archive. Those are different surfaces.

## Design anchor

Luma's calendar-page pattern (time-first vertical list, day-grouped, image-as-thumbnail) — chosen specifically because it **fails gracefully without good art**. A flat-color tile with the event name on it slots in without embarrassing the page. This is the floor; orgs with real design budget can still surface a hero, but no part of the layout *requires* one.

## Layout

Single column, centered, generous whitespace. No sidebar of filters. Optional right rail for mini-calendar (see below).

### Top of page

- Org name / logo (small, top-left, links home)
- Page title: "Events" (or org-configurable: "Programs," "What's Happening," etc.)
- Optional one-line description (org-configurable, plain text, no rich formatting)
- Filter row: 2 controls max — **Event Type** (dropdown, populated from event types in use), **Date Range** (dropdown: Next 30 days / Next 90 days / All upcoming). No search field on this page; search lives on the archive surface.

### Optional featured event (hero)

If the org marks one upcoming event as featured, render it above the list as a larger card:
- Wider thumbnail (full column width or 2/3)
- Larger title
- Short description excerpt (1–2 lines, truncated)
- "Featured" eyebrow label

Falls back to nothing if no event is flagged. Page does not reserve space for it.

### Event list

Grouped by date. Date headers are visible, sticky-on-scroll optional.

Group header format: `May 26 · Tuesday` (full day name spelled out; supports scanning weekday rhythm).

Within a day group, events sort by start time ascending.

#### Row anatomy (per event)

Left-to-right:

1. **Time** — small, monospace-feel, top-aligned (`6:30 PM`). Use start time only; range only if explicitly all-day or multi-day.
2. **Body block:**
   - Title (link to event LP)
   - Host attribution: "By [Committee Name]" or "By [Organizer Name]" — small, muted. Optional.
   - Location: pin icon + venue name or neighborhood. Click → opens map in modal or new tab.
   - Status badges (inline, small): `Members Only`, `Waitlist`, `Sold Out`, `Free`, `Registered` (auth state — see below). Max two badges visible; collapse rest.
3. **Thumbnail** — right-aligned, square, ~80–96px. See art-fallback rules below.

Entire row clickable, navigates to LP. Thumbnail does not need its own link.

### Mini-calendar (right rail, desktop only)

Optional component, off by default, opt-in per org.

- Current month grid, days with events get a subtle density indicator (dot or background fill — not event titles)
- Click a date → scroll to that day's section in the list (or, if no events that day, no-op with subtle feedback)
- Month-nav arrows; do not auto-load events from other months on this view (link to archive for past)
- Hides on mobile / narrow viewports

This is navigation, not filtering. The list does not collapse to show only the picked date.

## Art fallback (critical)

Every event has a thumbnail slot. It is never empty.

Precedence:
1. Event's uploaded image (cropped square, focal-point aware)
2. Event type's default image (org-configurable, one per type)
3. Generated tile: event title text on org's brand color, sans-serif, auto-sized to fit. Generated server-side at upload time or on demand, cached.

Generated tiles must look intentional, not broken. Test with long titles ("Community Tool Library Open Hours and Tool Donation Drop-off"), short titles ("Gala"), all-caps, mixed case. Center-align, max 3 lines, ellipsize if longer.

## Empty / sparse states

- **No upcoming events at all**: friendly message ("Nothing on the calendar right now — check back soon, or [subscribe to updates]"). Optional newsletter signup inline.
- **Fewer than 3 upcoming events**: render normally, no special treatment. Page is allowed to look short. Do not pad with past events.
- **All events clustered in one day/week**: render normally. The date grouping does the work.

## Auth states

Public/anonymous (default):
- Private/members-only events: hidden entirely from list
- Member pricing: not shown; LP handles tiered pricing display
- All other events: rendered as above

Authenticated member:
- Private/members-only events: appear in list with `Members Only` badge
- Events the member is registered for: show `Registered` badge (replaces other status badges if present)
- Member pricing: still not shown on index (keep index uncluttered); surfaced on LP

State changes (registration completed, etc.) come back from Stripe / form submission as a flash message on the destination page. Index does not need to refresh in-place.

## Schema / SEO

- Page-level: `ItemList` schema wrapping all upcoming events as `ListItem` → `Event` references
- Per-event: minimum `Event` schema with `name`, `startDate`, `location`, `url`. Richer markup lives on LPs.
- Canonical URL: `/events`
- OG image: featured event's image if set, else org's default OG image

## Out of scope (intentionally)

- Recurring events / series
- Calendar grid view as primary display
- Past / archived events
- Multi-org / aggregated views
- In-page search

## Open questions for implementation

- Date-range filter default: "Next 90 days" or "All upcoming"? Lean toward "All upcoming" for small orgs to avoid hiding events; revisit if orgs accumulate many.
- Mini-calendar: render server-side or client-side? Client-side gives smoother month nav; server-side is simpler and probably enough.
- Featured event: cap at one, or allow multiple? Recommend one; multiple featured events erodes the signal.
