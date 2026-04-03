---
title: CMS Settings
description: Site name, description, timezone, contact email, and event publishing defaults.
version: "0.68"
updated: 2026-03-23
tags: [settings, admin, cms]
routes:
  - filament.admin.pages.cms-settings-page
category: settings
---

# CMS Settings

CMS Settings controls the basic identity and behaviour of the public-facing site. Access is restricted to super-admin users.

## General

- **Site Name** (required) — the name of the organisation or site. Used in page titles, email footers, and anywhere the system needs to refer to the site by name.
- **Site Description** — a short description of the site. Used as the default meta description on pages that do not have one set explicitly.
- **Timezone** — the timezone used for displaying and storing dates. Defaults to Central Time. Affects event times, note timestamps, and any other date-based output. Choose the timezone where your organisation is based.
- **Contact Email** — a general contact address displayed on the public site or used in system-generated communications where a reply address is needed. Optional.
- **Auto-publish new events** — when enabled, the landing page for a newly created event is set to **Published** automatically. When disabled, it defaults to **Draft** and must be published manually. This setting has no effect on events created via import.
