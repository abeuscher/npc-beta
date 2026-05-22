# Runbook — Media Finder

Operator guide for the **Tools → Media Finder** page (`MediaFinderPage`). The tool finds duplicate and unused media for triage. Both scans are read-only; the only state change is deleting a file you confirm.

## What "referenced" means in this app

Media is **not** referenced by an id stored in a config column. Every `media` row is attached to an owning record (a widget, page, event, product, email template, collection item) through a Spatie *collection* — `model_type` + `model_id` + `collection_name`. A file is referenced when **either**:

- **(A)** its owning record exists *and* still reads the collection the file lives in — e.g. a `Page`'s `post_header`, a `PageWidget`'s `appearance_background_image`, or `config_{field}` for an image/video field still present in that widget type's schema; **or**
- **(B)** its `/storage/{id}/…` URL is embedded in rich-text content. The scan reads `page_widgets.config`, `collection_items.data`, `events.description`, `events.meeting_details`, and `email_templates.body`, and resolves the `{id}` in any `/storage/{id}/…` token (covering `<img src>`, `<a href>`, `srcset`, and conversion paths). Inline images uploaded inside the rich-text editor are kept alive by this rule.

Anything matching neither rule is an unused candidate.

## Unused scan

Lists unused candidates, each tagged:

- **Dead collection** — owner record exists but no longer reads this file (a widget image field was removed or swapped). Nothing else cleans these up; this is the case the tool exists for.
- **Orphan owner** — the owning record is gone. Also handled by the nightly `media-library:clean` sweeper; surfaced here only for visibility. Deleting these here is harmless but optional.

**Out of scope (never flagged):** seeder / sample-library images; reference history (what used a file before it was changed). Orphan-owner *deletion* is intentionally left to the nightly sweeper.

## Duplicate scan

Clusters records that look like copies:

- **Identical contents** — same SHA-256 over the stored bytes. The reliable "same file uploaded twice" signal. The hash is read from the `media.content_hash` column (populated at upload, backfilled for older rows), so this pass is instant — it no longer re-reads every file.
- **Same name & size** — a cheaper secondary signal for rows the hash pass did not already cluster.

Each member shows whether it is still **referenced**, so you keep the in-use record and delete the stray copies. Note that in this app duplicate files are usually each owned by a *different* record (the same logo uploaded to two widgets), so each owner still needs its own copy — deleting a referenced member breaks that owner.

> The duplicate scan is a **visibility** tool: it does not reclaim disk on its own, because each duplicate is a distinct file owned by a distinct record. Stopping re-uploads at the source is now handled by upload-time dedup (below); reclaiming disk (storing identical bytes once) is the remaining follow-up — content-addressed storage with refcounted deletion.

## Missing-file scan

Lists media **records whose file is gone from disk** — a 404 if anything tries to load it. Each row is tagged:

- **Broken (referenced)** — a live surface still points at this file, so it is rendering a broken image right now. Highest priority: re-upload the asset or remove the reference.
- **Dead record** — nothing references it; the row is just stale. Safe to delete.

The per-row action here is **Delete record** — it removes the dangling `media` row (and any leftover conversion files). It does not bring the file back.

## Safety model

- Scans are read-only and run synchronously on demand. They make no changes.
- **Delete** is per-row and requires confirmation. It removes the file and its conversions from disk and **cannot be undone**. There is no bulk delete and no automatic deletion.
- The page is gated behind `manage_cms_settings`.

## Upload-time dedup (prevention)

The finder cleans up duplicates after the fact; upload-time dedup stops most of them from being created. Every media file carries a `content_hash` (SHA-256 of the stored bytes), set when it lands. When you upload an image in the **page builder** (widget image fields and appearance backgrounds) or the **rich-text editor** (inline images), the editor hashes the file first and checks it against the library:

- **Exact match (same bytes)** — a prompt offers to **reuse** the existing asset instead of storing another copy. In the page builder you can also **upload as new**; in the rich-text editor, accepting reuse inserts the existing image's URL with no new upload at all.
- **Same filename, different bytes** (an edited graphic) — surfaced on surfaces that keep the original filename, so you can pick the existing version to replace or keep the new one. Page-builder uploads use randomised filenames, so they match on content hash only.

The reuse list shows a thumbnail, size, whether the asset is **in use on your site**, and how many copies exist; identical copies are collapsed to one entry, sorted in-use-first.

> **Honest limit (until content-addressed storage lands):** choosing *reuse* for a widget field still writes a physical copy of the bytes for the new owner — the win this session is behavioural (a coherent, findable library and far fewer fresh re-uploads), not yet disk savings. Inline-image reuse is the exception: it inserts the existing URL and writes nothing new.

**Surfaces not yet covered:** the Filament resource upload fields (post/event/product/widget-type thumbnails and headers) do not yet run the dedup prompt — that is scheduled post-Beta. The dashboard and record-detail builders' appearance-background uploads fall through to a normal upload (the check degrades silently where the route is absent).

## When to use it

Run the unused scan after a round of content editing that removed or swapped widget images, to catch dead-collection files the nightly sweeper won't. Run the duplicate scan periodically to find re-uploads of the same asset.
