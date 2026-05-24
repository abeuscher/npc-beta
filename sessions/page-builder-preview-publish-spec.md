# Page Builder — Preview & Publish (Versioned Pages)

**Status:** Draft v1 — scoped, not yet executed
**Author:** scoped with Al (unnumbered session)
**Context:** Page-builder editorial workflow + demo-mode security hardening for NonprofitCRM
**Last updated:** 2026-05-24

---

## Problem

Two things this solves at once:

1. **No editorial preview.** The page builder autosaves edits straight to the live layout rows, and the public site renders from those same rows. There is no "make changes, look at them, then decide to publish" step. A CMS with no preview-then-publish is an unusual shape — useful for proving reactivity (and ours is very reactive as a result), but missing the editorial safety net real operators expect.

2. **Demo security hole.** Demo mode (`APP_ENV=demo`, `/demo/enter` → shared `demo@demo.local` user, `demo` role) auto-logs a visitor into the admin with limited rights. Because builder writes are live, a demo user editing an already-published page mutates the live public demo **instantly**. Critically, *adding a "publish" permission alone does not fix this* — the publish permission only governs the `pages.status` flip, not the layout writes, which are already live. Closing the hole requires real separation between "what the editor is working on" and "what the public sees."

## Current state (verified)

- **Save path:** `resources/js/page-builder-vue/stores/editor.ts` debounced autosave → `PageBuilderApiController` endpoints (`POST .../page-widgets`, `PATCH /page-widgets/{w}`, `PATCH /page-layouts/{l}`, reorder) → live `page_widgets` / `page_layouts` rows. No explicit save button; no draft layer.
- **Public render:** `PageController::show` / `::home` load the page by `status=published` and read the same `page_widgets` / `page_layouts` rows (`is_active=true`). `PostController` is parallel — `PostResource` is `Page` with `type=post`, same builder + same render model.
- **Visibility gate:** `pages.status` is `draft|published` (+ `published_at`). It gates whole-page visibility, **not** layout content. There is no schema-level draft/version of the layout.
- **JSON engine (the enabler):** `app/Services/ImportExport/ContentExporter.php` + `ContentImporter.php` (+ `Filament/Pages/SiteImportExportPage.php`) faithfully round-trip a page's full widget + layout + appearance tree **and media** as JSON — production-proven moving content between sites (sessions 303 theme/media portability, 319/320 media). Media is content-addressed by hash (session 320), so a snapshot's media references can't be clobbered by later edits — old versions stay renderable. Round-trip fidelity is therefore **empirically retired; no spike needed.**

## Architecture — Shape A (chosen)

**The inversion:** leave the reactive builder *completely untouched* — it keeps autosaving to the working rows, live-edit and all. Change one thing: **the public site stops reading the working rows and instead reads a published artifact.** "Publish" freezes the current working state into that artifact.

- **Working rows** (`page_widgets` / `page_layouts`) = the builder's editable copy. **Unchanged.** Keeps live autosave + reactivity.
- **`page_versions`** = JSON snapshots, the source of truth for the published version and for history. Each publish writes one snapshot via the existing `ContentExporter`.
- **Public render** reads the published version, not the working rows.
- **Demo** lacks the publish permission → can never write a snapshot → the public demo is frozen at the last published version. Edit + preview only. Hole closed by a real permission boundary, not a hack — and it's the *same mechanism* as the product feature, so there's no demo-only scaffolding.

This gives version history + querystring/`?version=N` preview + portability for free (the JSON snapshot *is* a portable export), while leaving the crown-jewel reactive autosave alone.

### One open implementation fork (decide in-code during the session)

How the **published** version renders. Both reuse the existing renderer; both are viable. Round-trip fidelity is already proven, so this is purely a renderer-integration choice:

- **A1 — render published from JSON (hydrate transient).** Public render reads `published_version.snapshot_json`, hydrates **unsaved** model objects (reuse `ContentImporter`'s field mapping without persisting), feeds the existing renderer. One row set per page (the working set); no twin. Leanest storage. Same single path also serves any `?version=N` preview. *New element:* rendering from unsaved models — low risk given the importer proves the JSON is complete, but it's the one untrodden seam.
- **A2 — materialize published JSON into a twin row set.** On publish, import the snapshot into a hidden "published twin" row set; public resolves to the twin; editor edits the working set. Reuses the renderer 100% (real rows, zero new render seam) at the cost of twin rows + a slug→which-row-set indirection. Old versions live as JSON only, re-materialized on demand.

**Recommendation:** start at **A1** — it's the unified mechanism (one render-from-snapshot path serves both the live published page and arbitrary `?version=N` previews, so you need it anyway), and it avoids twin-row bookkeeping. Fall back to **A2** only if non-persisting hydration fights the renderer. Either way, dynamic widgets (those with `query_config`) still re-query live data at render time — the snapshot stores *config*, not data, which is the correct behavior.

## Data model

- **`page_versions`** (new): `id`, `page_id` (FK → `pages`, indexed), `version_no` (int, per-page incrementing) and/or `label`, `snapshot_json` (jsonb — a `ContentExporter` page payload), `published_at`, `published_by_user_id` (FK → `users`), `created_at`.
- **`pages`**: + `published_version_id` (nullable FK → `page_versions`). A page with `published_version_id = null` has never been published. + a "has unpublished changes" signal — a dirty flag set on any working-row write and cleared on publish (cheapest), or computed by comparing working state to the published snapshot.
- **`pages.status` stays orthogonal.** Keep `status`/`published_at` as the existing whole-page visibility gate; layer versioning on top via `published_version_id`. Do **not** fold them together — visibility ("is this page live at all") and content versioning ("which layout is live") are different axes.
- **Backfill migration:** for every currently-`published` page, snapshot its current working rows as `version_no = 1` and set `published_version_id`. Existing pages come out of the migration already published-at-current-state, no visible change.

## Flows

- **Edit** — unchanged. Builder autosaves to working rows; any write sets the page's dirty flag.
- **Preview** — auth-gated. `GET /{slug}?preview=working` (or a signed preview route) renders the **working rows** (literally today's render path). `?version=N` renders snapshot N. Default (no querystring) = the published version.
- **Publish** *(permission `publish_page`)* — serialize working rows → new `page_versions` row (`version_no++`) → set `pages.published_version_id` → clear dirty flag. (A2 also materializes the twin.) Does **not** trigger `build:public` — that's the asset bundle, separate from content.
- **Revert / discard working changes** — deserialize the published snapshot back over the working rows via `ContentImporter` (into the working set), clearing the dirty flag. Reuses the proven import path.
- **Unpublish** — decide whether this is the existing `status=draft` flip, clearing `published_version_id`, or both. Recommend: `status` remains the visibility switch; `published_version_id` persists so re-publishing restores the last live version.

## Demo wiring

- New permission **`publish_page`** (and likely `unpublish_page`). Register in `database/seeders/PermissionSeeder.php`. Grant to every role that currently holds `update_page` **except `demo`**; `super_admin` gets it via the `Gate::before` bypass.
- `PagePolicy::publish` (+ `unpublish`); gate the Filament publish action and the builder's publish control on it.
- Net: demo edits the working copy and previews freely but can never publish → public demo shows the frozen published version. Same for posts.

## Render branch points

- `app/Http/Controllers/PageController.php` `show` / `home` — read the published version (A1 hydrate-from-JSON, or A2 twin rows) instead of the live working rows. Add the auth-gated `?preview=working` / `?version=N` branch.
- `app/Http/Controllers/PostController.php` — parallel change (shared model).
- Builder UI — a publish control + an "unpublished changes" indicator driven by the dirty flag; a "preview" link opening the working-rows preview.

## Testing

- **Pest:** publish creates a version row + advances `published_version_id`; public render shows the published snapshot, not subsequent working edits; `demo` role cannot publish (policy + permission); revert restores working to published; version history accumulates; backfill migration snapshots existing published pages as v1. Posts mirror.
- **Playwright (standing spec):** edit a page → preview shows the change → public URL still shows the old version → publish → public URL now shows it; demo user has no publish affordance.

## Lift

**~1.5–2.5 sessions.** Migration + `page_versions` model + publish/preview/revert flows + the render branch + the `publish_page` permission/policy + the dirty indicator + Pest/Playwright. Posts are covered by the same model. **No de-risking spike** — JSON round-trip fidelity is production-proven, which was the only risk in Shape A.

## Open questions

- A1 vs A2 render mechanism (recommend A1; settle with code in front of you).
- `version_no` vs `label` vs both; retention/pruning policy for old versions (unbounded history vs keep-last-N).
- Does "unpublish" differ from "never published"? (See Flows recommendation.)
- Templates also own `page_widgets`/`page_layouts` — **out of scope**; versioning is pages + posts only unless a forcing function appears.
- Boundary-touching check: this is CRM-internal, no Fleet Manager surface — CRM contract stays v2.3.0.
