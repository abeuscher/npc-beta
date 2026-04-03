---
title: Pages
description: How to create and manage public website pages using the built-in page builder, including content blocks, slugs, and publishing.
version: "0.25"
updated: 2026-04-01
tags: [cms, pages, page-builder, content]
routes:
  - filament.admin.resources.pages.index
  - filament.admin.resources.pages.create
  - filament.admin.resources.pages.edit
category: cms
---

# Pages

Pages are the public-facing content pages of your website. Each page has a URL slug, a title, and a body made up of content blocks assembled with the page builder.

## Page List

The Pages index shows all pages with their slug, title, and published status. Use the **New Page** button to create a page.

## Creating a Page

- **Title** (required) — the page's display title. Also used to generate the slug.
- **Slug** — the URL path for this page (e.g., `/about-us`). Auto-generated from the title but editable.
- **Content blocks** — build the page body using the block editor. Add text, images, headings, buttons, and other content elements.
- **Published** — toggle to control whether the page is visible on the public site.

## The Page Builder

The page builder uses a block-based editor. Each block type has its own fields. Blocks can be reordered by dragging or using the up/down controls in the block menu. Click the ellipsis (⋯) on any block to duplicate, move, or delete it.

## Slugs

Slugs are generated from the page title when you first create a page. You can edit the slug at any time. Changing a slug will break any existing links to the old URL — update links accordingly.

## Tags

You can apply page tags to categorize content and build filtered listings. The Tags field appears in the page edit form.

- **Selecting existing tags** — click the Tags field and type to search. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the × on any tag pill.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click **+**. The tag is created and applied immediately.

## Saving and Publishing

Use the **Save** button to save a draft. Toggle the **Published** switch to make the page live on the public site. Pages that are not published are only visible to logged-in admins.

## Deleted Records and Trash

When you delete a page, the record is soft-deleted — it is hidden from normal views but kept in the database so it can be restored if needed. Deleted pages return a 404 on the public site.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin. System pages cannot be deleted or force-deleted.
