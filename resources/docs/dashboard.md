---
title: Dashboard
description: The main landing page of the admin panel, showing a welcome message, quick actions, integration status, and a help placeholder.
version: "0.39"
updated: 2026-03-19
tags: [dashboard, overview]
routes:
  - filament.admin.pages.dashboard
---

# Dashboard

The Dashboard is the first screen you see when you log into the admin panel. It is arranged as a two-column grid with four sections.

## Welcome

The top-left panel displays the welcome message configured in **Settings → General → Admin Panel → Dashboard welcome message**. If no message has been set, a placeholder prompt is shown instead. This is a good place to post a short note for your team — upcoming maintenance, a staff reminder, or a seasonal greeting.

## Help

The top-right panel is reserved for a help article search. Search functionality is coming soon.

## Quick Actions

The bottom-left panel provides one-click shortcuts to the most common content creation tasks:

- **New Blog Post** — opens the create screen for a new post.
- **New Event** — opens the create screen for a new event.

## Integration Status

The bottom-right panel shows which third-party integrations are currently configured. An integration appears here when its API key has been entered in **Settings → General → Integrations**. If no keys are present, a prompt is shown to direct you to settings.

Currently tracked integrations: MailChimp, Resend, Stripe, QuickBooks.

## Getting Around

Use the left-hand navigation to move between sections of the admin panel. Navigation is grouped into **CRM**, **CMS**, **Finance**, **Tools**, and **Settings**. Groups can be collapsed and expanded.
