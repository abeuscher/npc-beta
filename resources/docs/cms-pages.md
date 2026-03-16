---
title: Pages
description: How to create and manage public website pages using the built-in page builder, including content blocks, slugs, and publishing.
version: "0.24"
updated: 2026-03-16
tags: [cms, pages, page-builder, content]
routes:
  - filament.admin.resources.pages.index
  - filament.admin.resources.pages.create
  - filament.admin.resources.pages.edit
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

## Saving and Publishing

Use the **Save** button to save a draft. Toggle the **Published** switch to make the page live on the public site. Pages that are not published are only visible to logged-in admins.
