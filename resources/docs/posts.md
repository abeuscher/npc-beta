---
title: Blog Posts
description: How to write, publish, and manage blog posts that appear on the public website.
version: "0.25"
updated: 2026-04-01
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

- **Title** (required) — the post's headline.
- **Slug** — auto-generated from the title. Appears in the post's URL.
- **Content** — the body of the post, written in the rich text editor.
- **Excerpt** — a short summary used in post listings and social previews. If left blank, the system uses the first paragraph.
- **Featured image** — an image displayed at the top of the post and in listing cards.
- **Published** — toggle to publish. Draft posts are not visible to the public.
- **Published at** — optionally set a future date to schedule the post.

## Tags

Posts can be tagged to help readers find related content. Tags appear on the public post page and can be used to build filtered listing pages.

- **Selecting existing tags** — click the Tags field and type to search. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the × on any tag pill.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click **+**. The tag is created and applied immediately.

## Editing Published Posts

You can edit a published post at any time. Changes take effect immediately on save. If you need to make substantial changes to a live post, consider setting it back to draft until edits are complete.

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
