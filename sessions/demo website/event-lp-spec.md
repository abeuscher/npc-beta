# Event Landing Page — Spec

## Purpose

Dedicated page for a single event. The destination, not the index. Does the heavy lifting that the list view deliberately doesn't: long-form description, registration / ticketing, donation tiers, sponsor recognition, volunteer signup, member pricing, photo galleries from past instances, schema markup, social sharing.

Real URL. Real meta tags. Real `Event` schema. Shareable on Facebook, printable for the board member who insists on paper.

## Design philosophy

Two-tier ambition:

- **Baseline**: clean, dignified, works with a phone snapshot and a logo. The food-pantry case.
- **Ceiling**: SFMOMA-grade when the org invests in art direction. The gala case.

Same layout, same components. The difference is the inputs (image quality, description length, sponsor logos present, gallery present). The layout does not collapse when sections are empty; it just gets shorter.

## URL

`/events/{slug}` — slug derived from title, editable in admin. Stable; never recycled.

## Layout

Single column, centered, max-width ~720–800px for reading comfort. Sections stack vertically. Right rail optional for desktop (sticky registration card — see below).

### 1. Header

- Hero image, full-width within column, ~16:9 or 4:3 (configurable per org default)
- Fallback to generated tile (same rules as index page) at larger size — still doesn't look broken when no real image exists
- Event type eyebrow label above title (small, colored): `WORKSHOP`, `FUNDRAISER`, `VOLUNTEER`, etc.
- Title (large, primary)
- Date / time (formatted: `Saturday, June 14, 2026 · 6:00–9:00 PM`)
- Location: venue name, address, link to map. Embedded map optional, off by default (loads JS, hurts perf — opt in per event).

### 2. Registration card

Sticky on desktop right rail; inline below header on mobile. Always above the fold on initial load.

Contents depend on event configuration:

**Free, no registration required:**
- Just shows "Free · No registration needed" and an "Add to calendar" affordance

**Free with RSVP:**
- Email field + Register button
- Capacity indicator if set ("12 of 50 spots filled" — only if org opts in; many won't want this visible)
- Waitlist toggle when full

**Paid tickets (single tier):**
- Price displayed
- Quantity selector (default 1, max configurable)
- "Get tickets" button → Stripe checkout

**Paid tickets (tiered):**
- Tier list with name, price, brief description, quantity selector each
- Member pricing surfaces here when authenticated (e.g., "$50 · Member price $35")
- Sold-out tiers render disabled with `Sold Out` badge
- "Get tickets" button → Stripe checkout with selected tiers

**Donation event:**
- Suggested giving levels with names ("Friend $50 / Supporter $250 / Sponsor $1,000")
- Custom amount field
- Recurring toggle if org accepts recurring
- "Donate" button → Stripe checkout

**Member-only event (authenticated members):**
- Shows registration card as normal
- Anonymous visitors see "This event is for members only" with sign-in / membership info link instead of registration form

**Closed registration:**
- "Registration closed" message, no form. Event still visible.

### Post-action state

Stripe / form returns to the LP with `?registered=1` (or similar) → success flash above registration card: "You're registered. Confirmation sent to [email]." Registration card replaced with confirmation summary: ticket count, calendar links (Google / iCal / Outlook), "Manage registration" link if applicable.

For authenticated members already registered: registration card replaced on initial load by the same confirmation summary.

### 3. About this event

Long-form rich text. Markdown or block editor. Supports headings (H2/H3 only — H1 reserved for page title), paragraphs, lists, links, inline images, blockquotes. No embeds beyond images in v1 (no video, no iframes — keeps surface area small).

Empty state: section omitted entirely. Don't render a heading with no content.

### 4. Hosts / organizers

Optional section. Renders org sub-units (committees, chapters) or named individuals:

- Small portrait or logo
- Name
- Role / affiliation (one line)
- Optional bio (short)

Multiple hosts render as a horizontal list or grid. Single host renders inline.

Empty state: section omitted.

### 5. Sponsors

Optional section. Tiered display:

- **Lead sponsors**: larger logos, more prominent, often with a brief mention
- **Supporting sponsors**: smaller logo grid
- **In-kind / community partners**: smallest, simple text list acceptable

Org configures tiers and which sponsors fall where. All logos link out to sponsor's URL if provided.

Empty state: section omitted. (Most events won't have sponsors. Don't render a "Sponsors" heading with nothing under it.)

### 6. Volunteer signup

Optional section, separate from attendance. Some events need volunteers in addition to (or instead of) attendees:

- Brief description of what's needed
- Role options if multiple (setup, cleanup, registration table, etc.)
- Time slots if shift-based
- Signup form → email + name + role/slot selection

Same post-action treatment as registration (flash + confirmation summary).

Empty state: section omitted.

### 7. From past years (gallery)

Optional section. Photos from previous instances of recurring annual events. Helps build the case for the gala / annual fundraiser / community day:

- 3–8 thumbnail grid
- Click → lightbox
- Optional caption per photo

Empty state: section omitted. (No recurring-event handling yet — this is just a manual photo upload section per event.)

### 8. Practical info

Optional section for the unglamorous logistics:

- Parking
- Accessibility (wheelchair access, ASL interpretation, sensory considerations)
- What to bring
- Weather contingency
- Age restrictions

Rendered as a simple definition list or labeled paragraphs. Org-configurable which fields show.

Empty state: section omitted.

### 9. Share

Bottom of page:

- Copy link button
- Share to Facebook, X, LinkedIn, email
- Add to calendar (Google / iCal / Outlook) — also reachable from registration confirmation

### 10. Related / next events

Bottom of page, small. Up to 3 upcoming events from the same org, sorted by date ascending.

Empty state: section omitted (if this is the only upcoming event).

## Art fallback

Hero image precedence:
1. Event's uploaded hero image (landscape preferred; cropped if portrait)
2. Event type's default hero image (org-configurable)
3. Generated tile (same rules as index thumbnails, scaled up to hero size)

Generated hero must look intentional. Bigger size = more room for typographic treatment. Suggest: org's brand color background, event title centered, event type label small above. Reserves room to look credible without a photo.

## Auth states

**Anonymous:**
- Member-only events: header visible, registration card replaced with "Members only — sign in or learn about membership" block
- Member pricing not shown
- "Registered" state not shown

**Authenticated non-member:**
- Same as anonymous for member-only events
- Member pricing not shown
- Registered state shown if applicable

**Authenticated member:**
- All events accessible
- Member pricing shown in tiered ticket displays
- Registered state shown if applicable (registration card → confirmation summary on load)

**Authenticated admin / org staff:**
- "Edit event" link visible (top of page or floating)
- Out of scope for this spec — admin views handled separately

## Schema / SEO

- `Event` schema with full fields: `name`, `description`, `startDate`, `endDate`, `location` (with `Place` sub-schema), `image`, `offers` (per ticket tier), `organizer`, `eventStatus`, `eventAttendanceMode`
- Meta tags: title, description, OG image (hero), OG type `article` or `event`
- Twitter card: summary_large_image
- Canonical URL: `/events/{slug}`
- robots: indexable (default); option to mark private events `noindex`

## Performance

- Hero image: responsive `srcset`, lazy below-fold images
- Embedded map: deferred / opt-in per event (don't load Google Maps JS unless event has explicit map embed enabled)
- Stripe.js: load only when registration card interaction begins, not on initial page load (defer the connect)

## Print

Print stylesheet should produce a one-page-ish flyer:
- Title, date, time, location, description
- QR code linking to the LP URL (auto-generated, top-right)
- Hide: registration card (replaced by "Register at [URL]" + QR), share buttons, related events, past gallery

This is the "the board secretary needs to print 50 copies for the church bulletin board" case. Worth getting right because the alternative is they make their own flyer in Word and bypass the system.

## Out of scope (intentionally)

- Recurring events / series detail handling
- Multi-day event UX (treat as single event with date range for now)
- Live-stream / hybrid event embedding
- Comments / discussion
- Email-the-organizer form (use Hosts section + their org email)
- Editing from public page (admin handles)

## Open questions

- Sticky registration card on desktop: how aggressive? Sticks through entire scroll or releases near footer? Lean toward releasing near footer so it doesn't compete with related events / share section.
- Capacity indicator default: on or off? Recommend off — most small orgs find "12 of 50 filled" demoralizing when filling slowly. Opt-in per event.
- Past gallery section title: "From past years"? "Previous events"? Lean toward "From past years" — frames it as legacy/tradition rather than archive.
- Member pricing display: inline within tier ("$50 · Member $35") or separate member tier row? Inline is more compact; separate row is more explicit. Recommend inline.
