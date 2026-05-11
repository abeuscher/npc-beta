---
title: Event Registration Form Widget
description: Configuring the sign-up form for a single event, including the event selection, capacity handling, and paid-event payment flow.
tags: [widget, page-builder, event-registration, events, forms, cms]
category: cms
standalone: true
---

# Event Registration Form Widget

The Event Registration Form widget renders a registration form for a single specific event. Visitors fill in their name, email, and any required attendee details, then either submit (for free events) or proceed to Stripe Checkout (for paid events). Successful registrations land as `Registration` records in the CRM, attached to the chosen event and to a created-or-matched Contact.

This widget always renders one event's registration form. To list multiple upcoming events with a per-event registration link, use the Events Listing widget instead — the listing's "Register" buttons jump to a single-event registration page where this widget is the natural fit.

## When to use this widget

Use Event Registration Form on a dedicated event-detail page when you want the registration form visible directly on the page (rather than behind a "Register" button). Common pattern: the system-generated event page for each event already has a registration affordance, but operators creating a custom landing page for a high-profile event can drop this widget onto that page for richer surrounding content.

## Inspector — Content tab

- **Event** — pick the event to register against. The dropdown lists every event in the CRM, regardless of status. Required — until an event is picked, the widget shows a setup notice in the editor.

Once an event is picked, every behaviour of the form (free vs. paid, capacity, attendee fields, external-registration redirect) is driven by that event's configuration. The widget has no event-specific overrides.

## Inspector — Appearance tab

No widget-specific appearance fields beyond the standard widget controls (background, padding, full-width).

## How the form behaves per-event

The event's configuration drives the form's shape:

- **Status.** Only events with status `published` show a registration form. Drafts and scheduled events render a "registration not yet open" notice.
- **Registration mode.** Events can be configured for *internal* registration (this form is the registration surface) or *external* registration (the form redirects to a configured external URL like Eventbrite or a Google Form). When external, the widget renders a "Register on (external site)" button instead of a form.
- **Free vs. paid.** Free events skip Stripe Checkout — submission lands a Registration record and shows an in-line confirmation. Paid events redirect to Stripe Checkout for payment; the Registration is finalised on Stripe webhook receipt.
- **Capacity.** Events with a capacity that's been reached render a "registration full" notice in place of the form. Visitors cannot submit even if they bypass the UI.
- **Mailing-list opt-in.** Events configured with `mailing_list_opt_in_enabled` add a checkbox to the form for the attendee to subscribe to the chosen mailing list.

## Common patterns

- **Featured-event landing page.** Build a custom page about your gala — hero image, sponsor logos, schedule — and drop the registration form near the bottom. Set the event-detail page's template to a minimal one so the custom landing page is the primary registration surface.
- **Single annual-event microsite.** A page like `/annual-conference` with the registration form prominently placed at the top of the page, beneath a hero.
- **Free vs. paid in the same place.** Two separate pages, two widget instances, two events. The widget itself doesn't change behaviour — the event's configuration does.

## Gotchas

- **Capacity is enforced at registration time, not at page-load time.** A visitor who loads the page before the event hits capacity and submits after may still see the "registration full" notice on submit. The form re-checks capacity server-side.
- **Paid events require Stripe configured.** Same as Donation Form — Stripe keys must be set in Finance Settings before paid-event registration will complete.
- **Re-pointing the widget to a different event.** Changing the **Event** dropdown swaps the form's target event but does not retroactively touch existing registrations from prior submissions — those are tied to the *event*, not the *widget instance*.
- **Cancelled or deleted events.** If the event is cancelled, the widget renders a "registration closed" notice. If the event is deleted, the widget shows the setup notice (event no longer exists).
- **Ticket tiers are not on this widget today.** All registrations for a given paid event are a single price. Multi-tier ticketing (general / VIP / member-discount) is on the roadmap; see the Event Ticket Tiers release entry. When that lands, the widget will surface tier selection — current widget instances will continue to work with a single tier.
