---
title: General Settings
description: Site URL, admin branding, routing prefixes, and integration keys.
version: "0.58"
updated: 2026-03-22
tags: [settings, admin, routing]
routes:
  - filament.admin.pages.general-settings-page
---

# General Settings

Stub — body copy to be written in a future session.

## Routing Prefixes

The **Routing** section controls the URL prefix for blog posts, events, and the member portal. Users with the **Manage Routing Prefixes** permission can edit these values without full super-admin access.

Changing a prefix will update all affected page slugs automatically, but any external links or bookmarks pointing to the old URLs will return 404. Redirect handling is a future concern.
