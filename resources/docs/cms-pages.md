---
title: Pages
description: How to create and manage public website pages using the built-in page builder, including content blocks and visual editing.
version: "0.25"
updated: 2026-04-12
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

When you create a new page, you set the title, page type, template, and optionally select a content template to prepopulate blocks. After creation, you are taken to the page builder to add content.

## The Page Builder

The page builder is the primary editing view when you open a page. The header shows the page title, author, status, and public URL. The main area is a live preview of your page content.

### Adding blocks

Use the **+ Widget** button below the preview to open the widget picker and add a content block. Use the **+ Columns** button to add a multi-column layout (2, 3, or 4 columns).

### Editing blocks

Click any block in the preview to select it. The inspector panel on the right shows the block's settings. Changes are saved automatically as you edit — there is no save button needed.

The inspector has two tabs:
- **Content** — the block's content fields (text, images, links, etc.).
- **Appearance** — background color/image, text color, spacing, and layout options.

### Reordering blocks

Drag blocks in the preview to reorder them. You can also drag blocks into and out of column layouts.

### Block actions

Click the ellipsis menu on any block to access additional actions: duplicate, delete, or toggle visibility.

### Viewport preview

Use the device icons above the preview to switch between desktop, tablet, and mobile viewport widths. The preview scales to show how the page will look at each size.

## Page Details

To edit page metadata (title, slug, status, template, images, tags, SEO), click **Edit Page Details** in the page header. See the **Page Details** help article for more information.

## Save as Template

When a page has at least one block, the **Save as Template** button appears at the bottom of the builder. This saves the current block arrangement as a reusable content template that can be applied when creating new pages.

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
