# Media Portability — Self-Contained Content Bundles + ID-Preserving Media Seed

Make page/template content bundles carry their media bytes (cross-system portable), and add a standalone media export/import that preserves original media identity so lightweight by-reference JSON page bundles resolve on the target. Dogfooded by lifting the public-demo site authoring out of the rest of the dev work via export/import.

---

## Stub reference

No `release-plan.md` entry. Emergent design session from the public-website dogfooding workflow: author pages (with images) and media in a working environment, move them into the demo site cleanly. **This is not a backup exercise** — full-system backup already exists (`spatie/laravel-backup`, FM `/api/backup/blob`). This is *page-scoped, operator-driven content portability*. Canonical design decisions are recorded inline below — the executing agent makes **no** architectural choices; all forks are resolved here.

---

## The established pattern this extends

- `App\Services\ImportExport\ContentExporter` (`FORMAT_VERSION = '1.1.0'`) → envelope `{format_version, exported_at, payload:{templates, pages}}`. Media is emitted as **descriptors** (`collection_name`, `file_name`, `disk`, `path = {media_id}/{file_name}`, `mime_type`, `size`) — **no bytes**.
- `App\Services\ImportExport\ContentImporter::import($bundle, ImportLog $log)` — validates envelope (major-version match), DB transaction, templates then pages, `rewireWidgetMedia()` / `rewirePageMedia()` resolve descriptors against the **local disk only** (`Storage::disk($disk)->exists($path)`). Cross-install ⇒ media silently dropped with a warning.
- Export call sites (all six keep their existing JSON action; each gains a sibling zip action):
  - `PageResource.php` bulk `exportSelected` (~line 230)
  - `PageResource/Pages/EditPageDetails.php` `exportPage` (~line 101)
  - `PostResource.php` (~line 157), `PostResource/Pages/EditPostDetails.php` (~line 86)
  - `TemplateResource/Pages/EditPageTemplate.php` (~line 58), `EditContentTemplate.php` (~line 48)
- Import: `App\Filament\Actions\ImportBundleAction` — single-file upload (50 MB cap), `ContentImporter::import()`, persistent notification with `ImportLog` warnings.
- `MediaLibraryPage` (Tools group, gated `view_any_page`) — read-only `media` table, per-row delete only. **No export/import.**
- Spatie: `media.id` is **bigint auto-increment**; `media.uuid` exists (nullable); `DefaultPathGenerator` ⇒ on-disk path `{media.id}/{file_name}`. Disks: `public` (local) / `spaces` (S3) — `MEDIA_DISK` env, default `public`. Page collections: `post_thumbnail`, `post_header`, `og_image` (all `singleFile`). PageWidget collections: `appearance_background_image` + per-image/video config field `config_{key}`.

---

## Resolved design decisions (canonical — do not re-derive)

1. **Container, not a format bump.** The bundle becomes a **zip** whose root is `bundle.json` (byte-identical to today's envelope — same shape, same descriptors) plus a `media/` tree:
   ```
   bundle.zip
   ├── bundle.json                       # unchanged envelope
   └── media/{media_id}/{file_name}      # one entry per descriptor, mirrors the on-disk path
   ```
   `ContentExporter::FORMAT_VERSION` **stays `1.1.0`**. `validateEnvelope()` is **untouched**. The zip is detected by file type, not a version field. JSON-only bundles and zip bundles both flow through `ContentImporter::import()`. Add one additive, ignorable envelope key `media_transport` (`"reference"` for legacy/JSON, `"embedded"` for zip) for explicitness only — never load-bearing for validation.

2. **Importer resolution order: archive first, local disk fallback.** When importing a zip, extract to a temp dir, parse `bundle.json`, and resolve each media descriptor from the extracted `media/{path}` **first**; fall back to the existing `Storage::disk($disk)->exists($path)` behavior if absent. JSON bundles keep today's behavior exactly (fallback path only). This preserves same-install round-trips unchanged.

3. **Posture B — ID-preserving standalone media seed.** Standalone media export/import preserves the **original bigint `media.id`** so `DefaultPathGenerator` lands files at the same `{id}/{file_name}` path, making the cheap by-reference JSON page bundles resolve on the target after a one-time media push. Standalone import does a **raw explicit-id insert** of the `media` row (bypassing autoincrement), places the file at `{id}/{file_name}` on the target's configured media disk, then **resets the Postgres `media_id_seq`** to `MAX(id)+1`. Also preserve `uuid`, `name`, `collection_name`, `model_type`, `model_id`, `mime_type`, `size`, `custom_properties`, `manipulations`, `responsive_images`, `order_column`.
   - **Collision policy (canonical):** for an incoming `media.id` that already exists on the target — if the existing row's `uuid` **and** `file_name` match the incoming descriptor, treat as **already-seeded, skip** (idempotent re-push). If the id exists with a **different** `uuid`/`file_name`, **do not silently remap** — record an `ImportLog` warning naming the id and **skip that media row** (the operator resolves by exporting from a clean source). No clobber, no silent divergence.
   - **Orphan-owner policy:** standalone media rows carry their original `model_type`/`model_id`. On import, if that owner row does not exist on the target, still create the media row + file (so by-reference page bundles imported later resolve), and log an info entry. The media is "parked" against its original (possibly absent) owner reference — acceptable because resolution is path-based, not relational.

4. **Conversions are regenerated, not shipped.** The zip carries **originals only** (`media/{id}/{file_name}`), never conversion subdirectories. After attach/seed, dispatch conversion regeneration on the queue (per-media, mirroring Spatie's regenerate path). Keeps the zip lean and avoids stale-conversion drift.

5. **Queued, both directions.** Export and import are queued jobs (Horizon/Redis already wired). Consequence — **no `streamDownload` for export**: the job builds the zip to `local` disk at `storage/app/exports/bundles/{uuid}.zip` (private), then sends the operator a **persistent database notification** (`Notification::make()->...->sendToDatabase($user)`) carrying a download action. A gated route/Filament action (`update_page`) serves it via `Storage::disk('local')->download(...)`. Import: upload zip to `local`, dispatch job, job extracts + runs `ContentImporter`, sends a persistent database notification with the `ImportLog` summary (mirror the current `ImportBundleAction` notification body, async). Disk-leak hygiene: temp extraction dirs and the upload file are removed in a `finally`; export artifacts get a documented TTL sweep entry (out-of-scope to *implement* the sweeper — note it in the housekeeping inbox).

6. **Standalone media envelope shape.** Same envelope, `payload.pages` / `payload.templates` empty, new additive `payload.media` array of full descriptors (the posture-B columns from #3). `ContentImporter` learns a `payload.media` pass that runs **before** pages (so a combined bundle seeds media, then pages resolve by reference). `ContentExporter` gains `exportMedia(array $mediaIds)` and `exportAllMedia()`.

7. **Security.** Zip-slip guard on extraction: every entry path normalized, must stay within the temp dir, no `..`, no absolute, no symlink entries. Extend the existing descriptor path-traversal defence-in-depth (already in `rewire*Media`) to archive entries. Enforce a per-entry and total uncompressed-size ceiling (zip-bomb guard) before extraction. Permission gates unchanged: content bundle export/import `update_page`; standalone media export/import gated `update_page` (the Media Library page's own `canAccess` stays `view_any_page`, the new actions require `update_page`).

8. **Size ceiling.** Raise the `ImportBundleAction` upload cap (and the new media-import upload) to a documented ceiling (propose 512 MB) — `maxSize` + an explicit modal note. Synchronous fallback is removed for these; everything goes through the queue.

---

## Phases (one session; explicit internal cut-line)

### Phase 1 — Self-contained content bundle (page/template + media bytes)

The robust, fully-portable path. Foundation for Phase 2 (same container + zip primitive).

- New `App\Services\ImportExport\BundleArchive` service: builds a zip from a `ContentExporter` envelope + collected media files (reads bytes via `Storage::disk($media->disk)->get()`, disk-agnostic); and the inverse — extracts a zip to a temp dir with the zip-slip + zip-bomb guards, returns `(envelope, mediaRootPath)`.
- `ContentImporter`: detect zip vs JSON in the entry path; archive-first media resolution (decision #2). JSON path byte-unchanged.
- Queued export job + stored-artifact + database-notification + gated download route (decision #5). New "Export bundle (with media)" sibling action at the page/post export call sites (keep the existing JSON action verbatim).
- `ImportBundleAction`: accept `.zip` (sniff magic/extension), raised cap (decision #8), dispatch the queued import job.
- **Cut-line:** Phase 1 alone is a coherent, shippable milestone — cross-system page portability with media. If the session overflows, stop here cleanly; Phase 2 becomes a documented continuation.

### Phase 2 — Standalone ID-preserving media seed + Media Library UI

- `ContentExporter::exportMedia()` / `exportAllMedia()` → `payload.media` standalone envelope (decision #6).
- `ContentImporter` `payload.media` pass (runs before pages): raw explicit-id insert, file placement at `{id}/{file_name}` on target media disk, `media_id_seq` reset, collision + orphan-owner policies, queued conversion regeneration (decisions #3, #4).
- `MediaLibraryPage`: bulk "Export selected media" + "Export all media" actions (queued, same artifact/notification pattern), and an "Import media bundle" action (gated `update_page`).
- Round-trip proof: export media from a source, wipe the media disk + `media` rows on a simulated target, import the standalone bundle, then import a **JSON-only** (by-reference) page bundle and confirm images resolve.

---

## Out of scope

- **UUID-based path generator migration.** Switching `DefaultPathGenerator` → a UUID path generator (`{uuid}/{file_name}`) is the structurally cleaner long-term posture-B shape but is a global change touching every existing media file on disk + a one-time relocation migration — explicitly **not** this session. Posture B here is delivered via explicit-id preservation (decision #3). Record a one-line stub in `session-outlines.md` flagging the UUID-path-generator shape as the eventual robust form.
- Export-artifact TTL sweeper implementation (housekeeping-inbox note only).
- CRM list-data exports (`ListExportService`) — unrelated surface, untouched.
- Conversion-file shipping (decision #4 — regenerate, never ship).
- Member-portal-side media export.

---

## Open questions to resolve at session start

- Confirm the 512 MB upload ceiling and `storage/app/exports/bundles/` artifact location are acceptable on the target environment's disk budget.
- Confirm the database-notification + download-action UX (vs. an email link) — recommendation: in-app persistent database notification, no email.

---

## Testing

- **New tests expected:** yes.
  - `BundleArchive` zip build/extract round-trip; zip-slip rejection; zip-bomb ceiling rejection.
  - Content bundle with media: export → wipe local disk → import zip → media resolves (cross-system simulation).
  - JSON-only bundle still round-trips on same install (regression — byte-unchanged path).
  - Posture B: standalone media export → wipe `media` rows + disk → import → ids/uuids/paths preserved, `media_id_seq` correct; collision policy (skip-identical, warn-and-skip-divergent); orphan-owner parking.
  - End-to-end: standalone media seed, then by-reference JSON page bundle resolves on the seeded target.
  - Permission gates on every new action (`update_page`).
  - Queued-job behavior asserted via `Queue::fake()` / job dispatch + the database notification payload.
- **Slow groups:** none expected; flag if zip round-trips with real media push individual tests >5s (move to `->group('slow')`).
- Front-end: no Vue/SCSS source touched (Filament actions only) — no build expected; confirm at close.

---

## Closing steps

Per the base-prompt close gate. Stub-driven session — on close, add the UUID-path-generator out-of-scope stub to `session-outlines.md`, the export-artifact TTL note to `housekeeping-inbox.md`, and (since this introduces an operator-facing surface) a help-doc stub per the style rules.
