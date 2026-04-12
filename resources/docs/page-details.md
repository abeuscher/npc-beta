---
title: Page Details
description: How to manage page metadata including title, slug, status, template, images, tags, SEO settings, and code snippets.
version: "0.25"
updated: 2026-04-12
tags: [cms, pages, metadata, seo]
routes:
  - filament.admin.resources.pages.details
category: cms
---

# Page Details

The Page Details view is where you manage a page's metadata — everything about the page except its content blocks. You can reach it by clicking **Edit Page Details** in the header actions on the edit page, or via the **More actions** menu on the details page itself.

## Fields

- **Title** (required) — the page's display title. Also used to auto-generate the slug on creation.
- **Slug** — the URL path for this page (e.g., `/about-us`). Auto-generated from the title on creation but editable afterward. Changing a slug will break any existing links to the old URL.
- **Author** — the admin user credited as the page author.
- **Status** — **Draft** or **Published**. Draft pages are only visible to logged-in admins. Published pages are live on the public site.
- **Publish Date** — appears when the status is set to Published. Defaults to the current date and time.
- **Page Template** — controls the header, footer, and styling for this page. Defaults to the site's default template.
- **Tags** — categorize the page for filtered listings.

## Tags

- **Selecting existing tags** — click the Tags field and type to search. Click a tag to apply it. Applied tags appear as pills.
- **Removing a tag** — click the x on any tag pill.
- **Creating a new tag** — type the label in the **Create tag** field below the selector and click **+**. The tag is created and applied immediately.

## Images

Expand the **Images** section to manage:

- **Thumbnail image** — used in listing widgets and social sharing cards.
- **Open Graph image** — used for social sharing previews (Facebook, Twitter, etc.). Falls back to the thumbnail if not set.

## SEO

Expand the **SEO** section to manage:

- **Meta title** — overrides the page title in search engine results. Defaults to the page title if left blank.
- **Meta description** — a short summary shown in search engine results. Auto-extracted from page content if left blank.
- **Hide from search engines** — adds a `noindex` meta tag. Use for thank-you pages, confirmation pages, and similar pages that should not appear in search results.

## Header & Footer Snippets

Available from the **More actions** menu. Lets you inject custom code per page:

- **Head snippet** — code inserted before `</head>` (e.g., tracking pixels, custom styles).
- **Body snippet** — code inserted before `</body>` (e.g., chat widgets, analytics scripts).

For site-wide scripts, use the CMS Settings page instead.

## Actions

- **Edit Page** — returns to the page builder view.
- **Save** — saves all metadata changes.
- **More actions** menu:
  - **Export Page** — downloads the page and its widget stack as a JSON file.
  - **Save Block Layout as Template** — saves the current widget arrangement as a reusable content template.
  - **Edit Header & Footer Snippets** — manage per-page code injection.
  - **Delete Page** — soft-deletes the page (system pages cannot be deleted).
