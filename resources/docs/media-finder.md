---
title: Media Finder
description: Scan the media library for duplicate files, unused media the site no longer references, and records whose file is missing from disk, then triage them with per-row confirmed deletes.
version: "0.1"
updated: 2026-05-22
tags: [admin, tools, media, cleanup]
routes:
  - filament.admin.pages.media-finder-page
category: tools
---

# Media Finder

> Stub — operator-facing tool introduced in session 318. See `docs/runbooks/media-finder.md` for the full safety model.

Media Finder (under **Tools**) runs two on-demand scans over the media library and lists the results for triage. Both scans are read-only; the only change it makes is deleting a file you confirm.

## Unused scan

Lists media nothing on the site points at any more. A file is **referenced** when its owning record still reads the collection it lives in, or when its `/storage/…` URL is embedded in rich-text content. Candidates are tagged:

- **Dead collection** — the owner record exists but no longer reads this file (a widget image field was removed or swapped). Nothing else cleans these up.
- **Orphan owner** — the owning record is gone. Also handled by the nightly `media-library:clean` sweeper; shown here only for visibility.

## Duplicate scan

Clusters records that look like copies — identical file contents (byte hash) or the same filename and size. Each member shows whether it is still **referenced**, so you keep the one in use.

## Missing-file scan

Lists media records whose file is gone from disk (a 404 if something loads it). **Broken (referenced)** rows are rendering a broken image on a live surface and need attention; **dead record** rows are stale and safe to delete.

## Deleting

Each row has a confirmed **Delete** action. Deletes remove the file and its conversions from disk and cannot be undone, so they happen one at a time after confirmation — there is no bulk delete.
