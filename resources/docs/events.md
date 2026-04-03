---
title: Events
description: How to create and manage events, including date and location details, registration, and the event's public page.
version: "0.24"
updated: 2026-03-16
tags: [cms, events, registration]
routes:
  - filament.admin.resources.events.index
  - filament.admin.resources.events.create
  - filament.admin.resources.events.edit
category: cms
---

# Events

Events represent public-facing happenings — fundraising galas, volunteer days, workshops, and similar gatherings. Each event has its own page on the public website.

## Event List

The Events index shows all upcoming and past events. You can filter by date or status.

## Creating an Event

- **Title** (required) — the name of the event.
- **Slug** — URL path for the event page. Auto-generated from the title.
- **Description** — the full event description displayed on the public event page.
- **Start Date / Time** and **End Date / Time** — when the event begins and ends.
- **Location** — venue name and address.
- **Capacity** — optional maximum number of registrants.
- **Published** — toggle to make the event page visible on the public site.

## Event Page

Every event automatically gets a public page at its slug. This page shows the event details and, if registration is enabled, a registration form.

## Content Blocks

Like standard pages, events support content blocks below the main description. Use these to add speaker bios, schedules, maps, or sponsor logos.

## Tags

You can apply event tags to categorize events for filtering and public listings. The Tags field appears in the event edit form.

- **Selecting existing tags** — click the Tags field and type to search. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the × on any tag pill.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click **+**. The tag is created and applied immediately.

## Cancelling an Event

To cancel an event and notify registered attendees, open the event record and click **Cancel Event** in the page header. A three-step preview wizard opens:

1. **Confirm** — shows how many registered attendees with email addresses will be notified.
2. **Preview** — renders the cancellation email as it will appear for the first recipient.
3. **Send** — click **Cancel Event** to set the event status to *Cancelled* and dispatch the emails.

Only attendees with a *registered* status and a valid email address receive the email. The event status is updated at the same time the emails are sent.

## Past Events

Events with a past end date remain in the system and on the public site unless you unpublish them. You may want to keep past events published for archival purposes or remove them to keep the site tidy.
