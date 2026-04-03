---
title: Portal Pages
description: How to create pages visible only to authenticated, verified member portal users.
version: "0.68"
updated: 2026-03-23
tags: [cms, pages, portal, members]
routes:
  - filament.admin.resources.pages.index
  - filament.admin.resources.pages.create
  - filament.admin.resources.pages.edit
category: cms
---

# Portal Pages

Portal pages are CMS pages with their **Page Type** set to **Portal**. They are only accessible to logged-in, email-verified members via the member portal — anonymous visitors are redirected to the portal login page.

Portal pages are created and managed through the same **CMS → Pages** interface as standard pages. The page type determines where the page appears and who can see it.

## Creating a Portal Page

1. Go to **CMS → Pages** and click **New Page**.
2. Set the **Page Type** to **Portal**.
3. Give the page a title and slug. The slug should not include the portal prefix — that is added automatically based on the **Member portal prefix** in General Settings.
4. Build the page content using the page builder as normal.
5. Set the page to **Published** when it is ready for members to see.

Unpublished portal pages are not accessible even to authenticated members.

## Navigation

Portal pages do not appear in the public site navigation. To link to a portal page from within the portal (e.g. from the portal navigation menu), create a navigation menu item pointing to the page's slug with the portal prefix applied.

## Constraints

- Portal pages cannot be converted to a different page type after creation — the page type is locked once set.
- There is no per-page access control beyond the portal authentication check. All verified, active members can access all published portal pages.
- The portal prefix is configured under **Settings → General → Routing**. Changing it updates all portal page slugs automatically.

## Related

- See the [CMS Pages](cms-pages) help article for general page builder documentation.
- See the [Member Portal](memberships) section for context on portal membership and access.
