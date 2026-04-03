---
title: Media Library
description: Browse, inspect, and delete all media files stored in the system via Spatie Media Library.
version: "0.92"
updated: 2026-03-29
tags: [admin, cms, media]
routes:
  - filament.admin.pages.media-library-page
category: cms
---

# Media Library

The Media Library page provides a central view of every file uploaded through the CMS — widget images, collection item media, and email template assets. It is a browsing and housekeeping tool, not an upload destination.

## Browsing

The table lists every media file in the system with a thumbnail preview (for images), file name, type, size, and upload date.

- **Owner** shows what the file belongs to — a page widget, collection item, or email template — along with its name or label so you can find the source.
- **Collection** shows the Spatie media collection the file was added to (e.g. "images", "header").
- **Conversions** shows whether the system successfully generated optimised versions (WebP, responsive sizes). Green means all conversions completed; yellow means some are missing; gray means the file type has no conversions (e.g. SVGs).

Use the search bar to find files by name. The filters narrow results by owner type (Page Widget, Collection Item, Email Template) or file type (Images, Documents, Other).

## Deleting Files

Click the delete action on any row to remove a media file. The confirmation modal shows the file name before you proceed. Deletion removes both the database record and the physical file from disk — this cannot be undone.

**Important:** deleting a media file does not delete the item it belongs to. If you delete an image that belongs to a collection item or a page widget, that item will still exist but its image will be missing. The public page will render a broken or blank image where the file used to appear. If you need to replace an image, do so from the widget or collection item editor rather than deleting it here first.

## Uploading

This page does not support uploading. Files are uploaded through their respective editors — the page builder (for widget images), the collection item editor (for collection media), and the email template editor (for email header images).
