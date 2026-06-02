---
title: General Settings
description: Site URL, admin branding, routing prefixes, and integration keys.
version: "0.68"
updated: 2026-03-23
tags: [settings, admin, routing]
routes:
  - filament.admin.pages.general-settings-page
category: settings
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

### What you can and cannot route

These settings rename the **URL namespace** for each content type — the first path segment only. They do **not** let you choose which page or controller answers a given route.

That distinction is deliberate, and it is a security boundary:

- **The login and authentication flow is locked to fixed system controllers.** Sign-in, sign-up, password reset, and email verification are served by dedicated controllers (not by editable CMS pages), and their form-submission endpoints sit at fixed root paths (`/login`, `/logout`, …) that ignore the prefix entirely. You can rename the **System pages prefix** (e.g. `/system/login` → `/auth/login`), but you cannot repoint the login route at an arbitrary page. Allowing that would let the authentication surface be remapped onto operator- or attacker-editable content — exactly the kind of footgun we keep out of reach.
- **The content namespaces are yours to set.** Blog, events, member portal, and donations prefixes only rename where content lives. Renaming a namespace is cheap and safe, so it's left fully editable.

In short: the *prefixes* are configurable; the *binding of the auth flow to its controllers* is not.

## System Page Content

These rich-text fields control the content displayed on system pages that cannot use the standard CMS page builder.

- **Reset password page** — content rendered above the reset-password form.
- **Email verification page** — content rendered above the email address and logout button on the verification notice.

## Notes

Tenant-wide settings for the Notes (Timeline) surface — the structured-interaction stream attached to every contact and organization.

- **Restrict note edits to author** — when on, users can only edit or delete notes they authored themselves; non-author users see no Edit / Delete affordances on the Timeline. Default off (preserves the pre-restriction behaviour where any user with the `update_note` / `delete_note` capability can edit any note). Users granted the **Edit any note** permission (`edit_others_note`) bypass the restriction. Useful for tenants who want each operator's interaction history to be tamper-evident — managers retain edit reach via the override permission, but everyone else can only revise what they wrote.

## Integrations

API keys for external payment and accounting integrations. These fields are placeholders for future finance sessions — the Stripe and QuickBooks integrations are not yet active.

- **Stripe API Key** — stored for future use.
- **QuickBooks API Key** — stored for future use.
