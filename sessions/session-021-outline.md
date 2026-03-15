# Session 021 Outline — Installer: Browser & CLI Setup Wizard

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, review the current `DatabaseSeeder`, `BasePageSeeder`,
> `WidgetTypeSeeder`, and `PermissionSeeder` to understand the full seeding surface area.
> Review Laravel's built-in Prompts package (available since Laravel 10) and the storage
> driver configuration, as the installation state flag depends on both.

---

## Goal

Replace the manual post-clone setup process (copy `.env`, run migrations, run seeders, create
admin user) with a guided installer that works both in the browser and on the command line.
The installer should be idempotent, guard against re-running on an already-installed instance,
and give the operator control over which content is seeded.

---

## Key Decisions to Make at Session Start

- **Installation state flag**: A `storage/app/.installed` file is simple and has no
  chicken-and-egg problem (unlike a DB record). A settings row in the DB is more portable
  across deploys. A hybrid (file flag + `is_installed` setting) is the most robust. Decide
  before building the guard middleware.
- **`.env` writing**: Should the installer write `.env` directly (risky in some hosting
  environments), or assume `.env` already exists and only configure what's inside the app
  (DB credentials, app key, mail settings)? The latter is safer and more common in shared
  hosting contexts.
- **Web installer routing**: Should the installer live at `/install` (a public Laravel route
  before Filament boots) or be a standalone PHP script outside the framework? The Laravel
  route approach is simpler but requires the framework to boot, which requires a working DB.
  Consider a two-phase approach: Phase 1 (env/DB config) runs as a standalone script;
  Phase 2 (seeding/admin creation) runs inside Laravel.
- **Installer library vs. roll-your-own**: Packages like `rashidlaracasts/installer` exist
  but may not match the project's stack. Rolling our own with Alpine.js + Filament-style
  styling is likely cleaner given what's already in the project.
- **CLI installer**: Use Laravel's Prompts package (`laravel/prompts`) for a polished
  interactive experience. Decide whether `php artisan install` is a thin wrapper around the
  same service class the web installer uses, or a separate flow.
- **Content pack granularity**: Should packs be coarse (base/demo toggle) or fine-grained
  (base pages, sample events, sample contacts, sample donations, demo navigation)? Finer
  control is more useful to evaluators but more complex to build.
- **Re-run protection**: Should there be an admin-only "re-seed content" action after initial
  install, or is seeding strictly a one-time install concern?

---

## Scope (draft — refine at session start)

**In:**
- `InstallationState` service: reads/writes the installed flag, exposes `isInstalled()`
- `EnsureInstalled` middleware: redirects to `/install` if not installed, protecting all other routes
- Web installer: multi-step Alpine.js wizard at `/install` (bypasses auth, bypasses `EnsureInstalled`)
  - Step 1: Requirements check (PHP version, extensions, writable paths)
  - Step 2: Database connection test (reads from `.env`, tests connection)
  - Step 3: Run migrations (`php artisan migrate`)
  - Step 4: Site settings (site name, URL, timezone, contact email)
  - Step 5: Admin account (name, email, password)
  - Step 6: Content packs (checkbox list — see Content Packs below)
  - Step 7: Complete (links to admin, links to public site)
- CLI installer: `php artisan install` — interactive prompts, same steps, same service class
- Content pack system: named packs with descriptions, each backed by a seeder or seeder group
- `BasePageSeeder` refactored to be the "Base Pages" pack (currently it always seeds everything)
- Guard: installed check runs on every web and API request via middleware

**Out:**
- Writing or modifying `.env` from within the installer (assume `.env` is already configured
  before the installer runs — deployment tooling handles this)
- Multi-tenancy or per-organisation installer runs
- Upgrade/migration runner for existing installs (future — separate session)
- Automated backups before re-seeding

---

## Content Packs (proposed)

| Pack handle         | Label                  | Contents                                                      | Default |
|---------------------|------------------------|---------------------------------------------------------------|---------|
| `base_pages`        | Base Pages & Nav       | Home, About, Contact, Events, Blog pages + navigation         | ✓ on    |
| `widget_types`      | Built-in Widget Types  | All WidgetType records (text_block, event_*, events_listing…) | ✓ on    |
| `system_collections`| System Collections     | blog_posts and events Collection records                      | ✓ on    |
| `demo_contacts`     | Sample Contacts & Orgs | 10 demo contacts, 3 orgs, tags                                | off     |
| `demo_finance`      | Sample Finance Data    | Campaign, funds, 3 donations                                  | off     |
| `demo_content`      | Sample CMS Content     | 1 post, board members collection + items                      | off     |

The `DatabaseSeeder` becomes an orchestrator that calls `InstallationPackRunner::run($packs)`
rather than inlining seeder calls directly. In local development, all packs including demo
packs are still seeded automatically.

---

## Rough Build List

- `app/Services/InstallationState.php` — state flag read/write
- `app/Services/InstallationPackRunner.php` — runs a list of pack handles
- `app/Http/Middleware/EnsureInstalled.php` — redirect guard
- `app/Http/Controllers/InstallerController.php` — web installer steps (or a Livewire component)
- `resources/views/installer/` — multi-step wizard views (Alpine.js, no auth layout)
- `app/Console/Commands/InstallCommand.php` — `php artisan install`
- Content pack definitions (array config or small class per pack in `app/Installer/Packs/`)
- Refactor `DatabaseSeeder` to delegate to `InstallationPackRunner`
- Refactor `BasePageSeeder`, `WidgetTypeSeeder`, `PermissionSeeder` to be pack-aware
- Tests: installer state flag, pack runner, middleware redirect, CLI command (mocked prompts)
- ADR: installer architecture decisions (state flag choice, web vs CLI parity)

---

## Open Questions at Planning Time

- Does the target hosting environment support running artisan commands post-deploy (shared
  hosting, cPanel), or is the web installer the primary path for non-technical operators?
- Should the installer support upgrading an existing install (running new migrations + new
  seeders only), or is that a separate `php artisan upgrade` command?
- Is there value in a "reset to demo" action in the admin panel for evaluators, distinct from
  a fresh install?

---

## What This Unlocks

- Non-technical operators can install the CRM without touching a terminal
- The project is distributable as a product, not just a bespoke codebase
- Content packs lay the groundwork for optional feature modules (e.g. a "Grants module pack"
  that seeds its own widget types, sample data, and permissions in one step)
- A clean install story is a prerequisite for any public release or client handoff
