---
title: Content, Theme & Media Portability
description: Export pages, templates, the theme, and media as portable bundles and import them into another install — how the queued export/import works and where the result shows up.
version: "0.1"
updated: 2026-05-18
tags: [cms, export, import, portability, media, theme, bundles]
category: cms
---

# Content, Theme & Media Portability

> Stub — operator-facing surface introduced in session 303. Expand into a full walkthrough when this gets a documentation pass.

You can move content between installs (for example, author on a working site and push it to the demo site) without copying the database. Three things are portable:

- **Pages & posts & templates** — on a page/post Details screen or the list-view bulk menu, "Export with media (zip)" produces a self-contained `.zip` carrying the content **and the image bytes**. The existing JSON "Export" still works for lightweight, by-reference bundles.
- **Theme** — on **CMS → Theme**, "Export Theme" / "Import Theme" moves the colour palette, typography, and button styles. Import merges over the defaults and never wipes settings the file doesn't mention; the public CSS rebuilds automatically.
- **Media** — on **CMS → Media Library**, "Export selected media" / "Export all media" / "Import media bundle" moves media while preserving each file's identity, so lightweight by-reference page bundles resolve after a one-time media push.

## How it behaves

- **Everything runs in the background.** After you trigger an export or upload an import, you get a "queued" toast immediately. When the job finishes, the **notification bell** (top bar) shows the result — an export gives you a download link; an import reports what was imported plus any warnings.
- **Exports are private.** The download link is gated to operators who can edit pages; artifacts are not publicly reachable.
- **Imports overwrite by slug.** A page whose slug matches an existing page is replaced in place.
- **Re-importing media is safe.** Already-present media is skipped; a genuine id conflict with different content is reported and skipped rather than overwritten.

## Requirements

The background worker must be running for exports/imports to complete (the toast only confirms the job was queued; the bell confirms it finished).
