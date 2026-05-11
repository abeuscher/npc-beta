---
title: Event Registration Form Widget
description: Configuring the sign-up form for a single event, including the event selection, capacity handling, and paid-event payment flow.
tags: [widget, page-builder, event-registration, events, forms, cms]
category: cms
standalone: true
parent: widgets
---

# Event Registration Form Widget

The Event Registration Form widget renders a registration form for a single specific event. Visitors fill in their name, email, and any required attendee details, then either submit (for free events) or proceed to Stripe Checkout (for paid events). Successful registrations land as `Registration` records in the CRM, attached to the chosen event and to a created-or-matched Contact.

This widget always renders one event's registration form. To list multiple upcoming events with a per-event registration link, use the Events Listing widget instead — the listing's "Register" buttons jump to a single-event registration page where this widget is the natural fit.

## When to use this widget

Use Event Registration Form on a dedicated event-detail page when you want the registration form visible directly on the page (rather than behind a "Register" button). Common pattern: the system-generated event page for each event already has a registration affordance, but operators creating a custom landing page for a high-profile event can drop this widget onto that page for richer surrounding content.

## Inspector — Content tab

- **Event** — pick the event to register against. The dropdown lists every event in the CRM, regardless of status. Required — until an event is picked, the widget shows a setup notice in the editor.

Once an event is picked, every behaviour of the form (free vs. paid, ticket tiers, capacity, attendee fields, external-registration redirect) is driven by that event's configuration. The widget has no event-specific overrides.

## Inspector — Appearance tab

No widget-specific appearance fields beyond the standard widget controls (background, padding, full-width).

## How the form behaves per-event

The event's configuration drives the form's shape:

- **Status.** Only events with status `published` show a registration form. Drafts and scheduled events render a "registration not yet open" notice.
- **Registration mode.** Events can be configured for *internal* registration (this form is the registration surface) or *external* registration (the form redirects to a configured external URL like Eventbrite or a Google Form). When external, the widget renders a "Register on (external site)" button instead of a form.
- **Ticket tiers + quantity spinners.** The widget reads the event's ticket tiers and renders one quantity spinner per tier:
  - **No tiers** — the event is free and uncapped; the form shows just the attendee fields and a "Register for this event" button. No quantity UI.
  - **One tier** — a single quantity spinner labeled with the tier name and price, defaulted to 1 (so a buyer can click submit immediately). The submit button reads "Register & pay" for priced tiers, "Register for this event" for free ones.
  - **Two or more tiers** — one spinner per tier, each defaulted to 0. The buyer fills in how many of each tier they want; the form submits as a single transaction. A live subtotal updates beneath the spinners as the buyer adjusts quantities. The submit button reads "Register"; whether the flow is free or paid is decided server-side from the resulting order total.
- **Multi-quantity purchase.** A buyer can purchase N of one tier (e.g. 3 General) or any mix across tiers (e.g. 2 General + 1 VIP). Each line lands as its own registration row in the CRM, all sharing buyer details and — for paid orders — a single Stripe Checkout session id. Mixed free + paid purchases route to Stripe (the free tier appears alongside the paid tier as a $0 line item); orders where every chosen tier is free skip Stripe and land directly.
- **Free vs. paid.** Decided by the order total, not the tier set. If every chosen tier is free, the flow lands the registrations directly and shows an in-line confirmation. If any chosen tier is paid, the flow redirects to Stripe Checkout for payment; the registrations are finalised on Stripe webhook receipt.
- **Capacity.** Capacity is per-tier and aggregates by ticket count (sum of `quantity` across active registrations). A tier with capacity remaining ≤ 0 renders as "(sold out)" with its spinner disabled at max=0. A tier with finite remaining capacity caps the spinner's max at the remaining count; an unlimited tier caps at 99. Server-side validation re-checks against actual current registrations on submit, so a buyer who loaded the page before a tier filled will get a per-tier remaining-capacity error rather than a partial order.
- **Form-level minimum.** The buyer must select at least one ticket across all spinners — submit with every spinner at 0 returns a "please choose at least one ticket" error.
- **Mailing-list opt-in.** Events configured with `mailing_list_opt_in_enabled` add a checkbox to the form for the attendee to subscribe to the chosen mailing list.

## Common patterns

- **Featured-event landing page.** Build a custom page about your gala — hero image, sponsor logos, schedule — and drop the registration form near the bottom. Set the event-detail page's template to a minimal one so the custom landing page is the primary registration surface.
- **Single annual-event microsite.** A page like `/annual-conference` with the registration form prominently placed at the top of the page, beneath a hero.
- **Free vs. paid in the same place.** Two separate pages, two widget instances, two events. The widget itself doesn't change behaviour — the event's configuration does.

## Gotchas

- **Capacity is enforced at registration time, not at page-load time.** A visitor who loads the page before a tier hits capacity and submits after may still see the "this ticket tier is at capacity" error on submit. The form re-checks capacity server-side per-tier.
- **Paid events require Stripe configured.** Same as Donation Form — Stripe keys must be set in Finance Settings before paid-event registration will complete.
- **Re-pointing the widget to a different event.** Changing the **Event** dropdown swaps the form's target event but does not retroactively touch existing registrations from prior submissions — those are tied to the *event*, not the *widget instance*.
- **Cancelled or deleted events.** If the event is cancelled, the widget renders a "registration closed" notice. If the event is deleted, the widget shows the setup notice (event no longer exists).
- **Mixed-price tier sets.** Events with a mix of free and paid tiers are supported — the same form submits to a single endpoint, and the server decides whether to land the registration directly or redirect to Stripe based on the order total (any positive total → Stripe; zero total → free path). Operators configuring such events should know the submit-button label stays neutral ("Register") because the action depends on which tiers the visitor picks.
- **Admin "View Registrants" surfaces quantity per row.** A buyer who purchased "2 General + 1 VIP" appears as two rows in the registrants table — one row with tickets=2 on General, another with tickets=1 on VIP. They are linked operationally by buyer name/email (and, for paid orders, by Stripe session id) but not by a separate "order" record. Operators wanting per-attendee data (names of all 3 ticket holders, etc.) should use the public form's Notes field as the interim workaround; structured per-attendee sub-rows are a post-1.0 feature.
