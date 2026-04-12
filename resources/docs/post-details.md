---
title: Post Details
description: How to manage blog post metadata including title, slug, status, images, tags, and SEO settings.
version: "0.25"
updated: 2026-04-12
tags: [cms, posts, blog, metadata, seo]
routes:
  - filament.admin.resources.posts.details
category: cms
---

# Post Details

The Post Details view is where you manage a post's metadata — everything about the post except its content blocks. You can reach it by clicking **Edit Post Details** in the header actions on the edit post view.

## Fields

- **Title** (required) — the post's headline.
- **Slug** — the URL path segment after the blog prefix (e.g., `/news/my-post`). Auto-generated from the title on creation but editable afterward.
- **Author** — the admin user credited as the post author.
- **Status** — **Draft** or **Published**. Draft posts are only visible to logged-in admins.
- **Publish Date** — appears when the status is set to Published. Can be set to a future date to schedule the post.
- **Tags** — categorize the post for filtered listings and related content.

## Tags

- **Selecting existing tags** — click the Tags field and type to search. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the x on any tag pill.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click **+**. The tag is created and applied immediately.

## Images

Expand the **Images** section to manage:

- **Thumbnail image** — used in blog listing widgets and social sharing cards.
- **Header image** — optional banner image displayed at the top of the post.
- **Open Graph image** — used for social sharing previews. Falls back to the thumbnail if not set.

## SEO

Expand the **SEO** section to manage:

- **Meta title** — overrides the post title in search engine results. Defaults to the post title if left blank.
- **Meta description** — a short summary shown in search engine results. Auto-extracted from post content if left blank.
- **Hide from search engines** — adds a `noindex` meta tag.

## Actions

- **Edit Post** — returns to the page builder view.
- **Save** — saves all metadata changes.
- **More actions** menu:
  - **Export Post** — downloads the post and its widget stack as a JSON file.
  - **Delete Post** — soft-deletes the post.
