---
title: General Settings
description: Site URL, admin branding, routing prefixes, and integration keys.
version: "0.68"
updated: 2026-03-23
tags: [settings, admin, routing]
routes:
  - filament.admin.pages.general-settings-page
---

# General Settings

General Settings is the main configuration page for the admin panel. Most sections are super-admin only. The **Routing** section is also accessible to users with the `manage_routing_prefixes` permission.

## Site

- **Site URL** — the public URL of this installation, used for generating absolute links in emails and public pages. Example: `https://yourorg.org`. Include the protocol (`https://`) and omit any trailing slash.

## Admin Panel

These settings control the appearance of the admin panel itself — they have no effect on the public site.

- **Company Name** — text displayed in the admin header beside your logo.
- **Primary colour** — the accent colour used throughout the admin panel. Accepts any hex value. Default is `#f59e0b` (amber).
- **Dashboard welcome message** — rich text displayed at the top of the admin dashboard. Leave blank to hide the welcome panel entirely.
- **Logo** — the current logo is previewed here. Use the **Upload new logo** field to replace it. Accepted formats: PNG, JPEG, SVG. The logo is stored in public storage and served at its original dimensions — resize before uploading.

## Routing

The Routing section controls the URL prefix for blog posts, events, the member portal, and system pages. These prefixes determine the first path segment in the URL for each content type.

- **Blog prefix** — URL segment for blog posts. Example: `news` → `/news/post-slug`. Reserved words (`admin`, `horizon`, `login`, etc.) are rejected. Cannot conflict with an existing page slug.
- **Events prefix** — URL segment for event pages. Example: `events` → `/events/event-slug`.
- **Member portal prefix** — URL prefix for all member portal routes. Example: `members` → `/members/login`.
- **System pages prefix** — optional prefix for system-generated pages (password reset, email verification). Leave blank for root-level paths (`/login`), or set to e.g. `system` for `/system/login`.

**Warning:** changing any prefix rewrites all affected slugs automatically, but any external links or bookmarks pointing to the old URLs will immediately return 404. Redirect handling is not currently built.

## System Page Content

These rich-text fields control the content displayed on system pages that cannot use the standard CMS page builder.

- **Reset password page** — content rendered above the reset-password form.
- **Email verification page** — content rendered above the email address and logout button on the verification notice.

## Integrations

API keys for external payment and accounting integrations. These fields are placeholders for future finance sessions — the Stripe and QuickBooks integrations are not yet active.

- **Stripe API Key** — stored for future use.
- **QuickBooks API Key** — stored for future use.
