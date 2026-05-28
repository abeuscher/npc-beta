# NNN. Demo Site Pages — Build

> **Draft prompt — no session number.** Slot this into the numeric sequence on return (rename to `NNN. Demo Site Pages — Build.md`). Drafted in cloud session A006; its design spec is `sessions/public website/demo-site-pages-spec.md`.

Author the demo install's public website — a small pretend-nonprofit site ("Greenline Community Trust") that showcases the product's widgets to a prospect — build it in the product's own CMS, export it, and wire it into the demo baseline so it survives every daily `demo:reset`.

This is a **normal local session**: it touches app code (a seeder + a committed fixture) and is verified with the local `APP_ENV=demo` dress-rehearsal. It is **boundary-non-touching** (CRM contract stays v2.3.0).

---

## Stub reference

No `release-plan.md` entry. This session executes the design produced by cloud session **A006** (`sessions/public website/demo-site-pages-spec.md`). That spec is canonical for the org identity, the page list, per-page widget composition + copy, the data-alignment table, the reset wiring, and the four surfaced gaps. **Read it end-to-end first** — this prompt is a delta, not a restatement.

Supplants the "sample event / sample donation showcase page" intent in the **Default Content State** stub (`session-outlines.md`) — for the **demo node only** (A006 decision; fresh-install defaults are untouched).

---

## What to read before building

1. `sessions/public website/demo-site-pages-spec.md` — the design (canonical).
2. `sessions/public website/brief.md` + `home.json` / `existing-home-page-export.json` — the JSON format (1.1.0) and band/appearance conventions to reuse.
3. `sessions/public website/demo.json` — the `/demo` page to clip (spec §5.2).
4. `app/Console/Commands/DemoResetCommand.php`, `database/seeders/DemoBaselineSeeder.php`, `database/seeders/DatabaseSeeder.php`, `app/Services/ImportExport/ContentImporter.php` + `ImportLog.php` — the reset path you're extending (spec §5.3).
5. The session-321 isolated-stack recipe (the `APP_ENV=demo` local dress-rehearsal) — your verification harness.

---

## Phases

### Phase 1 — Author the six curated pages in the CMS
Build pages 1–6 (`home`, `events`, `donate`, `about`, `contact`, `news`) per spec §4, dogfooding the page builder. Use the spec's bands, widget handles, config values, `query_config` data contracts, copy, and appearance config. Reuse the marketing-site conventions (§2). Heed the gaps:
- **No `recent_donations`** on `/donate` (G-A006-1) — use the impact `text_block` + giving-levels band.
- Curate **only** slug-agnostic listing/index pages; do **not** author event/post detail pages (G-A006-2).

### Phase 2 — Seed the curated board collection
Create a `board_members` collection with the four entries in spec §4.4 (name/title/description + sample-library portrait per member). Bind the About page's `board_members` widget to it. The collection must be carried in the export bundle (`payload.collections`) so the reset re-establishes it (G-A006-3).

### Phase 3 — Demo nav + chrome
Build the demo navigation menu (spec §5.1: Home, Events, Donate, About, News, Contact, + Demo) using **whatever the production chrome defaults are at build time** (E16 may have changed them — do not hard-code around the old chrome). Confirm the chrome's nav binds by the same handle the bundle's `navigation_menus` entry uses.

### Phase 4 — Clip and include `/demo`
Clip the single `demo` page object from `sessions/public website/demo.json` and include it verbatim as a page in the bundle (spec §5.2). No structural edits.

### Phase 5 — Export the bundle + place the runtime copy
Export the 7 pages + nav menu + board collection to a single format-1.1.0 bundle:
- Durable record: `sessions/public website/demo-site-pages.json`.
- Deployed runtime copy the seeder reads (e.g. `database/seeders/data/demo-site-pages.json` — match any existing fixture convention). **Not `sessions/`** — that path isn't deployed.

### Phase 6 — Wire the reset
Add the import step to `DemoBaselineSeeder::run()` **after** `RandomDataGenerator->generate(...)` (super-admin auth still active), per spec §5.3:
```php
$bundle = json_decode(file_get_contents(database_path('seeders/data/demo-site-pages.json')), true);
app(\App\Services\ImportExport\ContentImporter::class)->import(
    $bundle, new \App\Services\ImportExport\ImportLog(),
    ['import_pages' => true, 'replace_duplicate_pages' => true,
     'import_navigation' => true, 'import_collections' => true, 'import_media' => true],
);
```
This makes **both** the full `demo:reset` and `--soft` re-establish the pages. Confirm the contact `web_form` form handle is ensured by `DatabaseSeeder` (or carried in the bundle if the importer covers forms) and matches `web_form.form_handle` (G-A006-4).

### Phase 7 — Verify via the `APP_ENV=demo` dress-rehearsal
Stand up the session-321 isolated demo stack, run `demo:reset` (full **and** `--soft`), and confirm:
- All 7 pages render, in spec-order bands, with no importer warnings.
- Data-driven pages are **populated** against the generated baseline (events listing/calendar show the 6 future events; news index shows the 5 posts; each card links to a working detail page).
- The board widget renders the four curated members.
- After a simulated deface (edit/delete a curated page) + a second `demo:reset`, the real site is fully restored.
- Capture page screenshots (reuse `scripts/generate-page-screenshots.js`) for the committed record.

---

## Out of scope
- New widgets (compose existing only; spec §7 confirms none are missing).
- Changing fresh-install default content (demo-node only — A006 decision).
- Event/blog **detail** pages (system-rendered / generator-produced; G-A006-2).
- A public recent-donations / aggregate-giving widget (G-A006-1 — separate lift if ever wanted).
- The live `nonprofitcrm.com` marketing site; FM-side demo work (node identity, firewall).

---

## Testing
- **New tests expected: yes.** At least: (a) the demo reset re-imports the curated pages (the curated `home`/`about`/etc. exist and are published after `DemoBaselineSeeder::run()` in demo mode); (b) a data-driven page renders against generated data (events listing non-empty after a baseline generate); (c) the board collection is present after reset. Mirror the existing demo/importer test patterns (`tests/Feature/DemoLoginTest.php`, the ContentImporter tests).
- **Slow groups:** none specific unless the importer/seeder tests land in a slow group.
- Full fast suite green at close (`./dev test`); CI `Tests` check green on the pushed branch.

---

## Closing steps
Follow the base-prompt close gate. Session-specific:
- **Log:** `sessions/NNN. Demo Site Pages — Build — Log.md`.
- **Branch:** `session-NNN/N`.
- **Deliverables:** the 7-page bundle (`sessions/public website/demo-site-pages.json` + deployed runtime copy), the `board_members` collection seed, the `DemoBaselineSeeder` import step, new tests, page screenshots.
- **VERSION:** bump per the active band.
- **Cross-Repo:** non-boundary (v2.3.0 unchanged); no FM coordination beyond the existing handoff note.
