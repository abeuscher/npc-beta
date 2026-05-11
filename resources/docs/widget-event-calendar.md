---
title: Event Calendar Widget
description: Configuring the interactive calendar view of published events — heading, default view, and event-filter behaviour.
tags: [widget, page-builder, event-calendar, events, cms]
category: cms
standalone: true
---

# Event Calendar Widget

The Event Calendar widget renders a month or week calendar view of your published events. Visitors can navigate between months or weeks, click an event to see its detail page, and view multiple events on the same day. The calendar is powered by the [jcalendar](https://github.com/jspreadsheet/calendar) library and renders interactively in the browser.

## When to use this widget

Use Event Calendar when you have several events in a span of time and want visitors to scan them by date — a recurring class schedule, a season of performances, a multi-day conference. For a small number of upcoming events without dates clustered together, the Events Listing widget is usually a better fit.

## Inspector — Content tab

- **Heading** — text rendered above the calendar. Optional; leave blank for no heading.

That's the only content-tab field. The widget has no per-event configuration — every published event in the CRM automatically appears on the calendar. The calendar is driven by the events list, not by per-widget event selection.

## Inspector — Appearance tab

- **Default view** — the calendar's initial view when the page loads: **Month** (a traditional month grid) or **Week** (a horizontal seven-day strip). Visitors can switch between views once the calendar has loaded.

Standard widget appearance fields (background, padding, full-width) apply as usual.

## What appears on the calendar

The calendar shows every event whose **status** is `published` and whose **start date** falls within the visible range. Drafts, scheduled events (status `scheduled` until their publish-at fires), and unpublished events do not appear.

Each event entry on the calendar is the event's title, hyperlinked to the event's public detail page. Events that span multiple days appear on each day they span.

## Common patterns

- **Class schedule on the homepage.** Drop the calendar at the bottom of the homepage with the default Month view. Visitors see this month's classes at a glance and can click forward to the next month.
- **Performance season on a dedicated page.** Create a page called "Season" and put the calendar at the top. Set the default view to Week if performances cluster tightly.
- **Two calendars on different pages.** No special configuration — the calendar always shows all published events, so two instances of the widget on different pages show the same data styled per-page.

## Gotchas

- **There is no per-widget filter today.** The calendar shows every published event. If you need a filtered view (only events of a certain type, only events tagged `family-friendly`, etc.), that filter doesn't exist in the widget yet — the Events Listing widget supports filtering and is the right answer for filtered views.
- **Past events still appear when navigating backward.** The calendar is not "upcoming only" — backward navigation shows historic events. That's usually the right behaviour for archives, but if you want only-upcoming the calendar isn't the widget for that.
- **All-day vs. timed events.** Events with a start time render at that time within the day. Events without a start time render as all-day entries at the top of the day's cell.
- **Time zones.** Event times are stored in UTC and rendered in the visitor's local time zone via the browser. Operators in the admin see times in the configured site time zone; visitors see times in their own.
