# Session 014 Outline — Events: Ticketing & Payment

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the Event and EventRegistration models (session 012),
> the EventDate model (session 013), the Transaction and Donation models (sessions 003-004),
> and ADR 005 (Stripe as financial source of truth). Payment integration decisions made earlier
> in the project govern what is built here.

---

## Goal

Add paid ticketing to events. Free events continue to work as built in session 012. Paid events create Transaction records and interact with Stripe. The CRM gets a complete registrant record regardless of whether the event is paid or free.

---

## Key Decisions to Make at Session Start

- **Ticket model**: Does a ticket belong to an EventRegistration (one ticket per registrant), or can a registrant purchase multiple tickets (for guests)? The latter requires a quantity field.
- **Ticket tiers**: Does this session support multiple ticket types (e.g. "General Admission $25 / VIP $75"), or just a single price per event? Tiers add a `ticket_types` table.
- **Stripe integration**: Stripe Checkout (redirect to Stripe-hosted page) vs Stripe Elements (embedded card form on our page)? Checkout is far simpler and more secure. Recommend Checkout for v1.
- **Webhook handling**: Stripe webhooks confirm payment and trigger CRM record creation. Is there a queue worker running on staging (session 009) to handle async webhooks?
- **Refunds**: In scope for this session or not?
- **Capacity enforcement**: If the event has a capacity set, what happens when it fills? Waitlist (session 012 has a `waitlisted` status)? Hard stop?

---

## Scope (draft — refine at session start)

**In:**
- `ticket_types` table (if multi-tier chosen): event_id, name, price_cents, capacity (nullable), sort_order
- Stripe Checkout session creation for paid event registration
- Stripe webhook handler: `checkout.session.completed` → create/update EventRegistration, create Transaction record
- Transaction record created for paid registration (links to existing Transaction model)
- Admin UI: ticket type management, registration list shows payment status
- Public: "Register" / "Buy Tickets" CTA on event landing page; redirect to Stripe Checkout
- Free event registration path unchanged from session 012

**Out:**
- Refund processing (future)
- Discount codes / promo pricing
- Stripe Elements embedded form (use Checkout for now)
- Ticket PDF / email confirmation (session 015)

---

## Rough Build List

- Migrations: ticket_types (if needed), add payment fields to event_registrations
- Stripe Checkout session creation service
- Webhook controller and handler (queue-aware)
- EventRegistration: add payment_status, stripe_session_id, ticket_type_id
- Transaction: link registration to transaction via existing model
- Filament: ticket type management on EventResource, payment status in registrations list
- Public: CTA on event landing page, success/cancel redirect pages
- Tests: webhook handler, free event bypass, capacity enforcement
- ADR: Stripe Checkout decision, ticket model choices

---

## Open Questions at Planning Time

- Are Stripe credentials available for a test environment by this session?
- Does the existing Transaction model need modification to link to EventRegistrations?
- Is capacity enforcement blocking for launch or can it be a later addition?

---

## What This Unlocks

- Session 015 can send receipt emails from completed transactions
- Finance reporting can include event revenue
- Mailchimp sync (session 015) can segment by payment status
