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
- **Ticket tiers.** The widget reads the event's ticket tiers and renders the form accordingly:
  - **No tiers** — the event is free and uncapped; the form shows just the attendee fields and a "Register for this event" button.
  - **One tier** — the tier is auto-selected via a hidden input. If the tier has a price > 0, the button reads "Register & pay" and the price displays above the form; otherwise it reads "Register for this event".
  - **Two or more tiers** — a radio picker appears above the attendee fields. Each option shows the tier name, price, and "(sold out)" when that tier is at capacity. The first non-sold-out tier is pre-selected. The submit button reads "Register" and the eventual flow (free or paid) is decided server-side from the chosen tier's price.
- **Free vs. paid.** A registration whose chosen tier has price 0 lands directly and shows an in-line confirmation. A registration whose chosen tier has price > 0 redirects to Stripe Checkout for payment; the Registration is finalised on Stripe webhook receipt.
- **Capacity.** Capacity is per-tier. A tier with `capacity` reached renders as "(sold out)" in the picker (disabled). When every tier on the event is at capacity, the form is replaced with a "registration full" notice. Capacity is re-checked server-side on submit, so a visitor who loaded the page before the tier filled gets the "(sold out)" notice on submit.
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
- **Mixed-price tier sets.** Events with a mix of free and paid tiers are supported — the same form submits to a single endpoint, and the server decides whether to land the registration directly or redirect to Stripe based on the chosen tier's price. Operators configuring such events should know that the submit-button label stays neutral ("Register") because the action depends on which tier the visitor picks.
