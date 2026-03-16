# Session 024 Log — Help System

**Date:** 2026-03-16
**Branch:** ft-session-024
**Status:** Complete

---

## Summary

Built a context-sensitive help system for the Filament admin panel. Help content lives in Markdown files under `resources/docs/`, is synced to the database via an Artisan command, and is displayed via a `?` icon in the page header that opens a slide-over panel.

---

## Pre-Session Discussion

Before writing any code, a planning discussion covered:

- **Storage:** Markdown files as source of truth, synced into the DB via `php artisan help:sync`. DB enables future full-text search; files keep content in version control.
- **Frontmatter spec:** `title`, `description`, `version`, `updated`, `tags`, `routes` — routes is a list of Filament route names the article covers (one-to-many relationship).
- **UX:** `?` icon in the Filament page header opens a slide-over panel. No modal, no accordion.
- **LLM/AI optimization:** Discussed `llms.txt` / `llms-full.txt` standard and the value of the `description` field for retrieval. Deferred public hosting and SEO optimization — not yet building a marketing site.
- **Search:** Full-text search via PostgreSQL deferred. `embedding` (jsonb) column added as a placeholder for future vector search. pgvector not in the current Docker image (`postgres:16-alpine`), so jsonb is the safe placeholder.
- **Admin UI:** No Filament editor for help content. The system reads and indexes only. `help:sync` is the maintenance interface.
- **Folder:** `resources/docs/` (not `resources/help/` — `docs` is the more widely used convention).
- **Open source alternatives:** Evaluated Shepherd.js, Docsearch, Stonly, Pendo, WalkMe. None fit the use case (context-sensitive, embedded in Filament, Markdown-sourced, self-hosted). Custom build is the right call.

---

## Files Created

### Database
- `database/migrations/2026_03_16_000001_create_help_articles_table.php` — creates `help_articles` (slug, title, description, content, tags, app_version, last_updated, embedding) and `help_article_routes` (help_article_id, route_name) tables.

### Models
- `app/Models/HelpArticle.php` — casts tags/embedding as array, last_updated as date.
- `app/Models/HelpArticleRoute.php` — no timestamps, belongs to HelpArticle.

### Service
- `app/Services/HelpArticleService.php` — `forRoute(string $routeName): ?HelpArticle`. Returns null gracefully if no article exists for the current route.

### Artisan Command
- `app/Console/Commands/HelpSync.php` — `php artisan help:sync`
  - Globs all `*.md` files in `resources/docs/`
  - Parses YAML frontmatter using `symfony/yaml` (added as a dependency)
  - Upserts `help_articles` by slug
  - Deletes and re-inserts `help_article_routes` rows
  - Removes DB articles whose source file no longer exists
  - Reports `✓ slug (N routes)` per file and a summary line
  - Validates required frontmatter keys; skips with warning if missing
  - Idempotent — safe to run multiple times

### Blade Component
- `resources/views/components/help-slide-over.blade.php` — renders nothing if `$article` is null. When an article is present, renders a `?` icon button (Heroicon `question-mark-circle`) that triggers an Alpine.js slide-over panel. Panel shows the article title, rendered Markdown body, and a footer with last-updated date and app version. Dismissible via `×` button, backdrop click, or Escape key.

### Filament Integration
- `app/Providers/Filament/AdminPanelProvider.php` — added `renderHook` at `PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE`. Resolves the current Filament route name and renders the help component. The `?` button only appears when an article exists for the current route.

### Markdown Documentation (resources/docs/)
20 files created covering all major admin sections:

| File | Covers |
|---|---|
| `dashboard.md` | Dashboard (1 route) |
| `contacts.md` | Contacts index, create, edit |
| `households.md` | Households index, create, edit |
| `organizations.md` | Organizations index, create, edit |
| `memberships.md` | Memberships index, create, edit |
| `notes.md` | Notes index, create, edit |
| `tags-crm.md` | CRM Tags index, create, edit |
| `import-contacts.md` | Import Contacts, Import History, Import Progress |
| `cms-pages.md` | Pages index, create, edit |
| `posts.md` | Posts index, create, edit |
| `events.md` | Events index, create, edit |
| `navigation.md` | Navigation Items index, create, edit |
| `content-collections.md` | Content Collections index, create, edit |
| `donations.md` | Donations index, create, edit |
| `transactions.md` | Transactions index, create, edit |
| `funds.md` | Funds index, create, edit |
| `campaigns.md` | Campaigns index, create, edit |
| `custom-fields.md` | Custom Field Defs index, create, edit |
| `users.md` | Users index, create, edit |
| `roles.md` | Roles index, create, edit |

Each file has complete frontmatter and 2–4 paragraphs of real, useful placeholder content.

### Tests
- `tests/Feature/HelpArticleServiceTest.php` — 3 tests: article found for known route, null for unknown route, null when no articles exist.
- `tests/Feature/HelpSyncCommandTest.php` — 4 tests: sync succeeds, route mappings created, at least 15 articles synced, idempotent.

---

## Dependency Added
- `symfony/yaml ^8.0` — used by `help:sync` to parse frontmatter. Installed via `composer require symfony/yaml`.

---

## Maintenance

To update help content:
1. Edit or add `.md` files in `resources/docs/`
2. Run `php artisan help:sync`

No deployment or code change is required for content updates — only for structural changes (new fields, new UI behavior).

---

## Deferred / Future

- **Search UI** — PostgreSQL full-text search on `help_articles.content` is possible now that the data is in the DB. Frontend search UI deferred.
- **`llms.txt` / `llms-full.txt` generation** — planned to add to `help:sync` as a side effect when public hosting is ready.
- **Public knowledge base** — deferred until marketing site is built.
- **Vector/embedding search** — `embedding` jsonb column is a placeholder. Activate with pgvector image swap + a separate embedding command when ready.

---

## Test Results

```
Tests: 183 passed (455 assertions)
Duration: 107.54s
```

All pre-existing tests continue to pass. 7 new tests added.
