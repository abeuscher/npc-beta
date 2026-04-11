# Session 008 Outline — Blog & Content Publishing Polish

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline, review the current state of the codebase
> (Post model, Page model, public routes, existing tests), and expand it into a full
> implementation prompt before coding begins. Update any decisions below that have been
> resolved by prior session work.

---

## Goal

Replace the blunt `is_published` boolean on Post (and Page) with a proper publishing workflow. Add a status model, timezone-aware publish date gating, a last-updated timestamp, and a preview mode that lets admins view content before it goes live.

---

## Key Decisions to Make at Session Start

- **Preview URL scheme**: Token-based (unauthenticated, shareable with stakeholders) or requires admin login? Token-based is more useful but adds a signed URL / token storage concern. Decide before building.
- **Pages**: Do Pages get the same status treatment as Posts, or just Posts for now?
- **`updated_at` vs explicit `last_updated_at`**: Laravel's `updated_at` exists on both models already. Is a separate user-facing "last updated" display field needed, or is surfacing `updated_at` in the public template sufficient?

---

## Scope

**In:**
- Remove `is_published` boolean from Post; replace with `status` enum: `draft`, `published`, `preview`
- `published` status: if no `published_at` date is set, auto-assign current datetime; post is live only if `published_at` ≤ now (site timezone)
- `unpublished` / `draft` status: post is never live regardless of date
- `preview` status: post is accessible only at `/{blog_prefix}/{slug}?preview=true` (for now, unauthenticated but obscure — make more sophisticated later)
- Last updated display: surface `updated_at` in the public post view
- Blog index: must exclude posts that are not `published` or whose `published_at` is in the future
- Timezone: use the `timezone` site setting for all date comparisons
- Filament form update: replace Published toggle + DateTimePicker with the new status Select + conditional DateTimePicker
- Update existing tests; add new tests for status gating

**Out:**
- Full token-signed preview URLs (noted for future)
- Page status treatment (noted, deferred)
- Email notifications on publish
- Scheduled publish via queue/job (publish date gating is query-time, not event-driven)

---

## Rough Build List

- Migration: change `is_published` → `status` enum on `posts` table; keep `published_at`
- Post model: update casts, add `scopePublished()` that checks status + date + timezone
- Public blog controller/routes: swap `is_published` filter for `scopePublished()`
- Preview route: if `?preview=true` and status = `preview`, allow access
- Filament PostResource: new status Select field with conditional date picker behaviour
- Update all tests that reference `is_published`
- New tests: preview mode, future-dated post gating, timezone gating

---

## Open Questions at Planning Time

- Should `preview` mode require any form of authentication, or is "obscurity" (the query param) sufficient for v1?
- Do we want a `scheduled` status (distinct from `published` with a future date), or is `published` + future `published_at` sufficient?

---

## What This Unlocks

- Correct content publishing workflow for all blog content going forward
- Preview mode foundation that Pages and other content types can adopt later
- Timezone-aware date handling pattern that Events will also need
