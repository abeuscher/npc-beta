---
title: Events Mini-Calendar Widget
description: Configuring the server-rendered month mini-calendar for an events-index right rail — heading, event-density dots, month navigation, and click-to-scroll.
tags: [widget, page-builder, event-mini-calendar, events, cms]
category: cms
standalone: true
parent: widgets
---

# Events Mini-Calendar Widget

The Events Mini-Calendar is a compact month grid built for the right rail of an events index. It renders the current month with small dots marking the days that have published events, and lets visitors flip to the previous or next month and click a day to jump to that day's section in the events list. It is **server-rendered** — every date is computed on the server, so there is no heavy calendar library and the widget works without JavaScript for everything except the month-flip and click-to-scroll conveniences.

## When to use this widget

Use the Mini-Calendar alongside an Events Listing in a two-column layout — the list on the left, the calendar in a narrow right rail. It gives visitors a month-at-a-glance sense of how busy a period is and a quick way to scan by date. For the events themselves, the Events Listing widget is what shows titles, images, and details; the Mini-Calendar is a navigation companion, not a replacement.

## Inspector — Content tab

- **Heading** — optional text rendered above the calendar (e.g. "This Month"). Leave blank for no heading.

There is no per-event configuration. Every published event automatically contributes to the density dots — the calendar is driven by the events list, not by per-widget event selection.

## What the dots mean

Each day with one or more published events shows a dot. The dot grows with the number of events that day — a single small dot for one event, larger for two, largest for three or more. Days with no events show just the date number.

## Month navigation

The calendar pre-renders the previous, current, and next month and starts on the current month. The ‹ and › arrows flip between those three months entirely in the browser — no page reload, and no other months are loaded. This is navigation, not filtering: flipping the month does **not** change which events the list shows.

## Click-to-scroll

Clicking a day that has events scrolls the events list to that day's section. This relies on the Events Listing rendering day anchors; if the list on the page isn't grouped by day, clicking a day does nothing rather than erroring.

## Gotchas

- **Desktop-only.** The Mini-Calendar is sized for a right rail and hides itself on narrow (mobile/tablet) viewports, where the rail collapses. The events list remains fully usable on its own.
- **Window is three months.** Only the previous, current, and next month are available from the arrows. Deep navigation to arbitrary months is intentionally not offered — the calendar is a scanning aid for the near term.
- **Published events only.** Drafts and not-yet-published events do not produce dots.
