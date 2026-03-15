# ADR 014 — Event Data Model

**Date:** 2026-03-14
**Status:** Accepted

---

## Context

Session 014 builds the Events system end-to-end. Several architectural decisions were made before implementation began.

---

## Decisions

### 1. `event_dates` is a separate table

**Decision:** Event metadata lives on `events`; each occurrence lives on `event_dates`.

**Rationale:** A single-date gala and a weekly board meeting have the same structure but different cardinality. Collapsing dates into the `events` table would require either a single `starts_at`/`ends_at` pair (breaking recurring support) or a JSON array of dates (breaking queryability). A separate `event_dates` table makes all occurrence-level queries — "what is happening this week?", "how many people registered for the March 5th meeting?" — straightforward indexed queries.

---

### 2. Occurrence changes do not cascade to siblings

**Decision:** Editing an `event_date` record affects only that occurrence. There is no cascade to other occurrences or back to the parent `event`.

**Rationale:** The practical case that drove this: the weekly board meeting moves to a different room on March 12th. Staff needs to notify March 12th attendees without touching the April or May meetings. If edits cascaded, a location change on one occurrence would silently affect all future meetings. The `event` record functions as a template that occurrences inherit from via `effectiveLocation()` and `effectiveMeetingUrl()`, but inheritance flows one direction only (parent → child, never child → parent or child → siblings).

---

### 3. `contact_id` is nullable, present now

**Decision:** `event_registrations.contact_id` is a nullable FK to `contacts`.

**Rationale:** Member pre-fill (auto-populating the registration form if a logged-in member is detected) is not built in this session. However, the FK exists so that future work — either matching an anonymous registration to an existing contact post-hoc, or pre-filling for authenticated members — requires zero schema migration. A `NULL` contact_id means "anonymous registrant"; a non-null value means "linked to a known contact."

---

### 4. `stripe_payment_intent_id` is on `event_registrations` now

**Decision:** A nullable `stripe_payment_intent_id` string column is on `event_registrations` from day one.

**Rationale:** The ticketing session (future) will attach a Stripe PaymentIntent to a registration. Adding the column now avoids a migration mid-feature and makes the intended payment shape explicit in the schema from the start. No payment logic exists yet; the column is always NULL for free events.

---

### 5. Honeypot + rate limiting instead of CAPTCHA

**Decision:** Public registration form uses a honeypot hidden field, a submission timing check (< 3 seconds = bot), and Laravel's throttle middleware (10 requests per minute per IP). No third-party CAPTCHA.

**Rationale:** The project has a strong privacy-first philosophy. Google reCAPTCHA v3 sends behavioural data about every visitor to Google. hCaptcha is similarly enterprise/data-driven. For free event registration — where no money is at risk — a honeypot is a proportionate first defence with zero external dependencies and no privacy compromise. When payment goes live (ticketing session), the threat model changes meaningfully: fraud liability and chargebacks become real. At that point, Google reCAPTCHA v3 (invisible, no puzzle, liability transfer to Google) is the pragmatic upgrade path.

---

### 6. Location fields on `events`, not a separate `locations` table

**Decision:** Physical location is stored as flat fields (`address_line_1`, `city`, `state`, etc.) directly on the `events` table. A `location_override` JSON column on `event_dates` allows per-occurrence overrides.

**Rationale:** A reusable `locations` table (e.g. "City Hall Conference Room B") is useful when the same venue recurs across many events. For the MVP, that complexity is premature — we don't yet know how many events will share a venue, and a field set is simpler to build, search, and display. The `location_override` pattern on `event_dates` already handles the "one occurrence has a different venue" case without requiring a locations admin. A `locations` table can be introduced in a later session if reuse patterns emerge.
