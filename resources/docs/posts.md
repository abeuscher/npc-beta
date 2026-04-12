---
title: Blog Posts
description: How to write, publish, and manage blog posts that appear on the public website.
version: "0.25"
updated: 2026-04-12
tags: [cms, posts, blog, content]
routes:
  - filament.admin.resources.posts.index
  - filament.admin.resources.posts.create
  - filament.admin.resources.posts.edit
category: cms
---

# Blog Posts

Blog posts are articles that appear on the public-facing blog section of your website. They share the same content system as Pages but are displayed in reverse-chronological order and may include author and date metadata.

## Post List

The Posts index shows all posts with their title, publication date, and status. Drafts are visible only to admins.

## Writing a Post

When you create a new post, you set the title. After creation, you are taken to the page builder to add content using the same block editor as Pages.

The builder header shows the post title, author, status, and public URL. Use **+ Widget** and **+ Columns** below the preview to add content blocks. Click any block to edit it in the inspector panel on the right. Changes are saved automatically.

## Post Details

To edit post metadata (title, slug, status, images, tags, SEO), click **Edit Post Details** in the page header. See the **Post Details** help article for more information.

## Save as Template

When a post has at least one block, the **Save as Template** button appears at the bottom of the builder. This saves the current block arrangement as a reusable content template.

## Editing Published Posts

You can edit a published post at any time. Changes take effect immediately. If you need to make substantial changes to a live post, consider setting it back to draft via Post Details until edits are complete.

## Deleted Records and Trash

When you delete a post, the record is soft-deleted — it is hidden from normal views and the public blog but kept in the database so it can be restored if needed.

### Viewing trashed records

Use the **Trashed** filter above the table to control which records appear:

- **Without trashed** (default) — only active records are shown.
- **With trashed** — active and deleted records are shown together. Deleted records can be identified by the Restore action in their row.
- **Only trashed** — only deleted records are shown.

### Restoring a deleted record

Find the record using the Trashed filter set to **With trashed** or **Only trashed**, then click **Restore** in the row actions. The record is immediately returned to active status.

### Permanently deleting (purge)

Force-delete permanently removes a record from the database. This action is restricted to super-admin users and cannot be undone. Force-delete appears as an action on trashed records only when you are logged in as a super-admin.
