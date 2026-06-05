# App Reference

Fast-orientation index for Claude. Read this at the start of any session before searching for files. It is not a replacement for `docs/schema/` — it is a map to where things live.

---

## Environments

| Name | Host | App path | Artisan |
|------|------|----------|---------|
| **Local (WSL2)** | `localhost` | `~/nonprofitcrm` (WSL2 Linux filesystem) | `docker compose exec app php artisan` |
| **Deploy server** | `root@167.172.141.225` (DigitalOcean, Ubuntu 22.04) | `/opt/nonprofitcrm` | `docker exec nonprofitcrm_app php artisan` |
| **Public demo** | `root@147.182.214.147` (DigitalOcean, Ubuntu 22.04) | `/opt/nonprofitcrm` | `docker exec nonprofitcrm_app php artisan` |

### Deploy server — Docker containers

| Container name | Role |
|----------------|------|
| `nonprofitcrm_app` | PHP-FPM (Laravel app) |
| `nonprofitcrm_nginx` | Nginx reverse proxy |
| `nonprofitcrm_worker` | Queue worker |
| `nonprofitcrm_postgres` | PostgreSQL 16 |
| `nonprofitcrm_redis` | Redis (cache + queue) |

Local container names are identical. Local compose file: `docker-compose.yml` in the project root. Deploy server compose file: `/opt/nonprofitcrm/docker-compose.prod.yml`.

Deploy server domain: `beuscher.net`. `.env` lives at `/opt/nonprofitcrm/.env` (not in source control).

### Public demo

The public-demo droplet at `147.182.214.147` runs the same image stack as the deploy server (identical compose file, identical container names — both droplets are isolated, one set of names per host). It exists to host a parked stable build at `rectanglemachine.com` that's deliberately frozen — updates flow only when commits land on the `release/public-demo` branch (not on push to `main`).

- **Trigger:** push to `release/public-demo` runs `.github/workflows/deploy-demo.yml`. Manual `workflow_dispatch` is also supported from the Actions tab.
- **Image namespace:** the demo workflow tags images as `:demo-<sha>` and `:demo-latest` so the demo droplet never picks up `:latest` from prod's main-branch deploys.
- **Secrets used:** `DEMO_DEPLOY_HOST`, `DEMO_DEPLOY_USER`, `DEMO_DEPLOY_SSH_KEY` (separate from prod's `DEPLOY_*` triplet). `BUILD_SERVER_API_KEY` is shared with prod — both droplets can use the same build server cleanly because bundle filenames are content-hashed.
- **Updating the demo:** merge a fix into `main`, then merge or fast-forward `release/public-demo` to the desired commit and push. The branch IS the version pin.
- **`.env`:** lives at `/opt/nonprofitcrm/.env` on the demo droplet. Independent of the deploy-server `.env` — different `APP_URL` (`https://rectanglemachine.com`), different `APP_KEY`, different `DB_PASSWORD`, separate / sandbox integration keys, same `BUILD_SERVER_URL` + `BUILD_SERVER_API_KEY` if the demo should compile assets through the build server.
- **First-time data:** the workflow only runs `migrate --force` on each deploy (additive). Initial seed + scrub data load is a one-time manual step on the droplet (`docker exec nonprofitcrm_app php artisan migrate:fresh --seed --force`, then super-admin login + Random Data Generator widget).

**`.env` propagation note:** `docker-compose.prod.yml` wires the host `.env` via `env_file:` (read at container *start*, not mounted as a live file), so edits to `/opt/nonprofitcrm/.env` do not reach the running container until you restart it (`docker compose -f docker-compose.prod.yml up -d` or `restart app worker`). `php artisan config:clear` only flushes the cached config file — it does not refresh the process environment. After restarting, run `config:clear` to drop any stale `bootstrap/cache/config.php` (the `bootstrap_cache` named volume persists across restarts). The `worker` container needs the same restart for queue jobs to see new env values.

### Deploy pipeline + version recognition

Push to `main` triggers `.github/workflows/deploy.yml`:

1. **Resolve + enforce VERSION.** Reads the root `VERSION` file as the image tag and **fails if that tag is already published to GHCR** (immutable tags — "bump VERSION before merging to main"). So every deploy needs a fresh `VERSION`; bump it before merging, including a mid-session deploy-to-test (see `CLAUDE.md` Git Workflow). The demo-droplet workflow `deploy-demo.yml` is SHA-tagged (`demo-<sha>`) and is exempt.
2. **Build + push** `ghcr.io/.../nonprofitcrm-app:<VERSION>` (+ `:latest`) — the tag becomes usable as an FM upgrade target here, mid-Action.
3. **Deploy to the droplet** over SSH: `pull` → `up -d --wait` → `migrate --force` → `widgets:sync` → `view:clear` + `config:clear`. When the Action goes green, the deploy server is already serving the new image. (`widgets:sync` — session 341 — re-syncs widget definitions into `widget_types.config_schema` so pure-schema widget changes that ship in the image actually reach the running server; idempotent.)

**Where the running version surfaces (and the recognition lag).** A container reports its version from `/var/cache/app/VERSION` — baked from the `APP_VERSION` build arg at image build, exposed via `config('fleet.agent.app_version')`, returned by `GET /api/health` as `version`. It is correct the instant the container is up (no runtime cache). **Fleet Manager** shows the version from the latest `health_checks` row, written by its `poll-active-clients` scheduler task that polls every node **once a minute**. So after a deploy, FM keeps showing the old version for up to ~60s until its next poll — that gap is the lag, and it is FM-side, not the node. To check without waiting: curl the node's own `/api/health` (with the FM client cert) for the lag-free truth, or open the client's detail page in FM, which fires a synchronous poll on load and updates immediately.

### Wiping a node to a bare machine

The definitive teardown for a node installed via the deploy/FM path — removes every container and every named volume (database, media, caches), returning the box to a clean slate FM can re-provision onto. Run as root on the droplet. **Irreversible — destroys all data; restore from a backup blob if you need the data back.**

```bash
docker rm -f nonprofitcrm_nginx nonprofitcrm_app nonprofitcrm_worker nonprofitcrm_postgres nonprofitcrm_redis
docker volume rm nonprofitcrm_bootstrap_cache nonprofitcrm_libs_build nonprofitcrm_postgres_data nonprofitcrm_redis_data nonprofitcrm_storage_data nonprofitcrm_widget_build
docker system prune -a --volumes -f
```

The `prune` step also reclaims dangling images/networks so the next deploy pulls fresh. This leaves the host files (`/opt/nonprofitcrm/.env`, `nginx-certs/`) in place; delete `/opt/nonprofitcrm` too if you want FM to regenerate those.

---

## Admin UI — views and their files

The admin panel is built with Filament 3 and lives at `/admin`. Each resource has List, Create, and Edit pages under `app/Filament/Resources/{Name}Resource/Pages/`.

### CRM group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Contacts (list) | `ContactResource.php` |
| Edit Contact | `ContactResource/Pages/EditContact.php` |
| Members (list) | `MemberResource.php` |
| Memberships (list) | `MembershipResource.php` |
| Membership Tiers (list) | `MembershipTierResource.php` |
| Organizations (list) | `OrganizationResource.php` |
| Notes (list) | `NoteResource.php` |
| Custom Fields (list) | `CustomFieldDefResource.php` |

### CMS group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Pages (list) | `PageResource.php` |
| Edit Page (includes page builder) | `PageResource/Pages/EditPage.php` |
| Blog Posts (list) | `PostResource.php` |
| Edit Post (includes page builder) | `PostResource/Pages/EditPost.php` |
| Events (list) | `EventResource.php` |
| Edit Event | `EventResource/Pages/EditEvent.php` |
| Navigation (list) | `NavigationMenuResource.php` |
| Collections (list — custom collections) | `CollectionResource.php` |
| Collection Manager (list — content collections) | `ContentCollectionResource.php` |
| Forms (list) | `FormResource.php` |
| Templates (list) | `TemplateResource.php` |
| Edit Content Template | `TemplateResource/Pages/EditContentTemplate.php` |
| Edit Page Template | `TemplateResource/Pages/EditPageTemplate.php` |
| CMS Settings | `Filament/Pages/Settings/CmsSettingsPage.php` |
| Theme (Design System — text styles + buttons + colors) | `Filament/Pages/DesignSystemPage.php` (colour token contract: `docs/theme-color-tokens.md`) |

### Finance group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Donations (list) | `DonationResource.php` |
| Transactions (list) | `TransactionResource.php` |
| Giving Summary | `Filament/Pages/DonorsPage.php` |
| Products (list) | `ProductResource.php` |
| Funds & Grants (list) | `FundResource.php` |
| Campaigns (list) | `CampaignResource.php` |
| Finance Settings | `Filament/Pages/Settings/FinanceSettingsPage.php` |

### Tools group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| Importer | `Filament/Pages/ImporterPage.php` |
| Import Contacts | `Filament/Pages/ImportContactsPage.php` |
| Import Events | `Filament/Pages/ImportEventsPage.php` |
| Import Donations | `Filament/Pages/ImportDonationsPage.php` |
| Import Memberships | `Filament/Pages/ImportMembershipsPage.php` |
| Import Invoice Details | `Filament/Pages/ImportInvoiceDetailsPage.php` |
| Import Notes | `Filament/Pages/ImportNotesPage.php` |
| Import History | `Filament/Pages/ImportHistoryPage.php` |
| Import Progress (contacts) | `Filament/Pages/ImportProgressPage.php` |
| Import Progress (events) | `Filament/Pages/ImportEventsProgressPage.php` |
| Import Progress (donations) | `Filament/Pages/ImportDonationsProgressPage.php` |
| Import Progress (memberships) | `Filament/Pages/ImportMembershipsProgressPage.php` |
| Import Progress (invoice details) | `Filament/Pages/ImportInvoiceDetailsProgressPage.php` |
| Import Progress (notes) | `Filament/Pages/ImportNotesProgressPage.php` |
| Media Library | `Filament/Pages/MediaLibraryPage.php` |
| Mailing Lists (list) | `MailingListResource.php` |
| Tag Manager (list) | `TagResource.php` |
| Widget Manager (list) | `WidgetTypeResource.php` |

### Settings group

| View title (as seen in UI) | Resource / Page file |
|---------------------------|----------------------|
| General Settings | `Filament/Pages/Settings/GeneralSettingsPage.php` |
| Mail Settings | `Filament/Pages/Settings/MailSettingsPage.php` |
| Users (list) | `UserResource.php` |
| Roles (list) | `RoleResource.php` |
| System Emails (list) | `EmailTemplateResource.php` |

---

## Public-facing views and their files

| View / URL pattern | Controller / file |
|-------------------|-------------------|
| Home page (`/`) | `PageController::home` |
| Any published page (`/{slug}`) | `PageController::show` |
| Blog index (`/{blog_prefix}`) | `PostController::index` |
| Single blog post (`/{blog_prefix}/{slug}`) | `PostController::show` |
| Member portal login | `LoginController` |
| Member portal signup | `SignupController` |
| Member portal account dashboard | `AccountController` |
| Event registration (POST) | `EventController::register` |
| Product checkout (POST) | `ProductCheckoutController::store` |
| Donation checkout (POST) | `DonationCheckoutController::store` |
| Web form submission (POST) | `FormSubmissionController::store` |
| Event checkout (POST) | `EventCheckoutController::store` |
| Membership checkout (POST) | `MembershipCheckoutController::store` |
| Product waitlist (POST) | `ProductWaitlistController::store` |
| Events API (JSON) (`/api/events.json`) | `Api\EventsController::index` |
| Sitemap (`/sitemap.xml`) | `SitemapController::index` |
| Robots (`/robots.txt`) | `RobotsController::index` |
| Portal password reset | `Portal\PasswordResetController` |
| Portal email verification | `Portal\EmailVerificationController` |
| Portal account update (address, password, email) | `Portal\AccountController` |

All public controllers live in `app/Http/Controllers/`. Portal routes are prefixed by the `portal_prefix` site setting (default: `members`). Blog prefix is the `blog_prefix` site setting (default: `news`).

**Routing prefixes are namespaces, not route→page bindings (security boundary).** The Routing section of General Settings (`system_prefix`, `portal_prefix`, `blog_prefix`, `events_prefix`, `donations_prefix`) renames the first URL path segment for each content type — nothing more. The **authentication flow is deliberately not remappable**: sign-in / sign-up / password-reset / email-verify GET routes are served by fixed controllers (`Portal\LoginController` etc.) under `system_prefix`, and their POST endpoints sit at fixed root paths (`/login`, `/logout`, …) that ignore the prefix so a form action never depends on operator-editable config. The operator may rename the `system_prefix` segment, but cannot point the login route at an arbitrary CMS page — that would let the auth surface be remapped onto editable content. The content prefixes (blog/events/portal/donations) only rename where content lives and are fully operator-editable because doing so is cheap and safe. In short: prefixes are configurable; the binding of the auth flow to its controllers is not.

> **Member portal page topology (session 337).** Auth pages live under `system_prefix` (`/system/login`, `/system/signup`, `/system/forgot-password`). The logged-in portal — dashboard, account edit, event registrations — lives under `portal_prefix` (`/members`, `/members/account`, `/members/event-registrations`) as `type=member` pages served by the catch-all page route (auth + verified-email enforced in `PageController::show`). Post-login lands on the `/members` dashboard; the legacy `/system/account` path is a redirect alias to it. The `portal` navigation menu (`layouts.portal`) links the `/members` pages.

---

## Page builder — key components

| Your name | Class / file |
|-----------|-------------|
| Page builder bootstrap (Livewire shell for the Vue app) | `App\Livewire\PageBuilder` — `app/Livewire/PageBuilder.php` |
| Page builder bootstrap blade (mounts the Vue app) | `resources/views/livewire/page-builder.blade.php` |
| Vue page builder entry point | `resources/js/page-builder-vue/main.ts` |
| Vue page builder root component | `resources/js/page-builder-vue/App.vue` |
| Vue preview canvas / block cards | `resources/js/page-builder-vue/components/PreviewCanvas.vue`, `PreviewRegion.vue`, `LayoutRegion.vue` |
| Vue inspector panel | `resources/js/page-builder-vue/components/InspectorPanel.vue` |
| Vue Pinia editor store | `resources/js/page-builder-vue/stores/editor.ts` |
| Page builder JSON API (used by the Vue app) | `App\Http\Controllers\Admin\PageBuilderApiController` — `app/Http/Controllers/Admin/PageBuilderApiController.php` |
| Widget folders (definition + template + optional SCSS) | `app/Widgets/{PascalName}/` |
| Shared Blade fragments used by widgets | `resources/views/widget-shared/` |
| Page context service (data for widget templates) | `App\Services\PageContext` — `app/Services/PageContext.php` |
| Widget contract resolver | `App\WidgetPrimitive\ContractResolver` — `app/WidgetPrimitive/ContractResolver.php` |
| Widget registry (discovery of `app/Widgets/*`) | `App\Services\WidgetRegistry` — `app/Services/WidgetRegistry.php` |
| Widget config resolver | `App\Services\WidgetConfigResolver` — `app/Services/WidgetConfigResolver.php` |
| Appearance style composer | `App\Services\AppearanceStyleComposer` — `app/Services/AppearanceStyleComposer.php` |
| Demo data service (preview fallback data) | `App\Services\DemoDataService` — `app/Services/DemoDataService.php` |
| Widget renderer | `App\Services\WidgetRenderer` — `app/Services/WidgetRenderer.php` |

---

## Major dependencies

| Package | Used for |
|---------|----------|
| `filament/filament` v3 | Entire admin panel — resources, pages, forms, tables |
| `livewire/livewire` (via Filament) | Page builder UI, reactive admin components |
| Alpine.js (via Filament) | Front-end interactivity in admin and public pages |
| `laravel/cashier` | Stripe integration — subscriptions, charges |
| `spatie/laravel-permission` | Role and permission system |
| `spatie/laravel-activitylog` | Activity log on CRM records |
| `spatie/laravel-medialibrary` | File/image uploads |
| `spatie/laravel-sluggable` | Auto-slug generation on pages, events, etc. |
| `spatie/laravel-schemaless-attributes` | Flexible JSONB fields on models |
| `resend/resend-php` | Transactional email sending |
| `mailchimp/marketing` | MailChimp sync |
| `scssphp/scssphp` | Runtime SCSS compilation for template `custom_scss` (will move to build server post-beta) |
| `modern-normalize` | CSS reset for public pages (replaces Tailwind preflight) |
| `laravel/horizon` | Queue monitoring dashboard |
| `predis/predis` | Redis client (cache + queues) |
| `swiper` | Carousel/slider — Swiper.js (MIT license, copyright Vladimir Kharlampidi) |
| Pest v2 | Test runner |

---

## Build server — public asset pipeline

The public site's widget CSS and JS are compiled by an external build server. The admin panel CSS (Filament theme) stays on Vite.

### Connection

| Setting | Value |
|---------|-------|
| Config key | `services.build_server.url` / `services.build_server.api_key` |
| Env vars | `BUILD_SERVER_URL`, `BUILD_SERVER_API_KEY` |
| Local dev URL | `http://bundleserver:8080` |
| Auth | Bearer token (API key) |

### Triggering a build

```bash
# Inside the app container:
docker compose exec app php artisan build:public

# With detailed error output:
docker compose exec app php artisan build:public --debug
```

The command calls `App\Services\AssetBuildService::build()`, which:

1. Collects all SCSS partials from `resources/scss/` (in dependency order) and widget CSS/JS from `widget_types` records
2. Generates content-hashed filenames (`public-widgets-{hash}.css/js`)
3. POSTs the sources to the build server with Bearer auth
4. Writes the compiled bundles to `public/build/widgets/`
5. Writes `public/build/widgets/manifest.json` with the current filenames
6. Cleans up old bundles

### Where bundles live

```
public/build/widgets/
├── manifest.json
├── public-widgets-{hash}.css
└── public-widgets-{hash}.js
```

### How the layout loads bundles

The public layout (`resources/views/layouts/public.blade.php`) reads `public/build/widgets/manifest.json` and renders a `<link>` tag for the CSS bundle and a `<script>` tag for the JS bundle. If the manifest doesn't exist (build server hasn't run), no bundle tags are rendered and the site works normally via Vite.

### Template custom SCSS

Per-template `custom_scss` is still compiled at runtime by ScssPhp and injected as an inline `<style>` block. This is separate from the build server pipeline and will move to the build server post-beta. Note: ScssPhp does not support Sass `@use` modules, so template custom SCSS must use plain SCSS or CSS custom properties (`var(--color-primary)`) — not `$variables`.

### Fallback when the build server is unavailable

The existing bundles in `public/build/widgets/` persist on disk. If the build server is unreachable, the `build:public` command fails with an error, but the site continues serving the last successful build.

---

## Backups

CRM data is backed up nightly to a per-install DigitalOcean Spaces bucket. The pipeline is `spatie/laravel-backup` invoked by the Laravel scheduler.

### What gets backed up

- The `nonprofitcrm` Postgres database (via `pg_dump`).
- The Spatie media library tree under `storage/app/public`. As of session 320 this tree is content-addressed: files live at `cas/{hash[0:2]}/{hash}/...` rather than `{media.id}/...`. Backup and restore copy the tree by path, so the layout change is transparent to both — the procedures below are unchanged.

### Per-install bucket setup procedure

Each install gets its own DigitalOcean Spaces bucket and its own scoped access key. Setup steps for a new install:

1. **Create a Space** (DO console → Spaces → Create) with a name like `nonprofitcrm-{install-handle}-backups`. Region should match the droplet's region for lowest latency.
2. **Generate a bucket-scoped Spaces access key** (DO console → API → Spaces Keys → Generate New Key, with the bucket scoped to the new bucket and permission `Read/Write/Delete`).
3. **Set production env vars** in the install's `/opt/nonprofitcrm/.env`:
   - `SPACES_KEY=<scoped key>`
   - `SPACES_SECRET=<scoped secret>`
   - `SPACES_BUCKET=<bucket name from step 1>`
   - `SPACES_REGION=<region slug, e.g. nyc3>`
   - `SPACES_ENDPOINT=https://<region>.digitaloceanspaces.com`
   - `BACKUP_DISKS=spaces`
4. **Restart the worker container** so the scheduler picks up the new env. The first scheduled `backup:run` (next 01:30 UTC) writes the first blob to the bucket and updates `storage/app/private/fleet/last-backup-at`.
5. **Verify** by hitting `/api/health` with a curl that presents the FM-supplied client cert after the first backup runs — `last_backup_at.status` should be `green` with a recent ISO timestamp. Auth is mTLS as of v2.0.0; see `docs/runbooks/fleet-manager-cert-paste.md` for the verification command.

### Local dev posture

Local dev defaults to `BACKUP_DISKS=local`, which writes backups to `storage/app/private/Laravel/`. No Spaces credentials are required to run `php artisan backup:run` locally for testing. Each manual run also writes `storage/app/private/fleet/last-backup-at`, so local `/api/health` responses reflect a green `last_backup_at` once a manual run has happened.

### Orphan media sweep

`spatie/laravel-medialibrary` ships `media-library:clean`, which removes orphan `media` rows (where the polymorphic owner is gone) and orphan conversion files (where the parent `media` row is gone but the conversion file lingers). Session 247 registered it in `bootstrap/app.php`'s `withSchedule()` block on a daily cadence (`->onOneServer()->withoutOverlapping()`). It does NOT walk the whole `storage/app/public/` tree looking for files with no `media` row — that wholesale-wipe gap remains open as a stub in `session-outlines.md` (the original 247 `app:reset` fix was reverted at session 252; see the stub for the open design question). Subject to the same scheduler-runner gap below.

### Scheduler runner — known gap

The `worker` service (both `docker-compose.yml` and `docker-compose.prod.yml`) runs `php artisan queue:work` only — no `schedule:work` and no cron-driven `schedule:run`. The 242 wiring registers `backup:clean` and `backup:run` in `bootstrap/app.php`'s `withSchedule()` block (visible via `php artisan schedule:list`), and 247 added `media-library:clean`, but none of them fire automatically until a scheduler runner is added (a separate worker variant, sidecar, or host-level cron). Manual `php artisan backup:run` works today; production deployment requires the runner to be in place before the daily cadence takes effect. Listed as a carry-forward in `sessions/tracks/fleet-manager-agent.md`.

### Restoration (manual)

`spatie/laravel-backup` does not ship restore tooling — restoration is a manual operator operation. The procedure was verified end-to-end against a local backup during session 242 manual testing (twice — once against the seeded baseline, once against a 99 MB backup with 48 admin-created contacts + 95 user-uploaded media; both round-tripped cleanly).

1. **Locate the backup blob.** On Spaces, download the most-recent zip from the install's bucket. Locally, zips live at `storage/app/private/{APP_NAME}/<timestamp>.zip`. Each zip contains `db-dumps/postgresql-{db_name}.sql.gz` (gzip-compressed plain SQL — `database_dump_compressor` is set to `Spatie\DbDumper\Compressors\GzipCompressor::class`) plus the included media tree under `storage/app/public/...` (paths are relative to `base_path()`).
2. **Wipe the target before restoring.** `docker compose exec app php artisan db:wipe --force` drops all tables, types, and views — cleaner than `migrate:fresh` since the dump's `CREATE TABLE` statements would otherwise conflict with the freshly-migrated empty tables. Also wipe the media tree for a clean restore: `rm -rf storage/app/public/*`.
3. **Extract the archive.** `unzip <backup>.zip -d /tmp/restore` (or any work directory).
4. **Restore the database.** `gunzip -c /tmp/restore/db-dumps/postgresql-<db_name>.sql.gz | PGPASSWORD=<DB_PASSWORD> psql -h <host> -U <user> -d <db_name>`. The dump is gzip-compressed plain SQL — pipe through `gunzip -c` and stream into `psql`; `pg_restore` is for `pg_dump --format=custom` output, which we do not produce. The container's postgres requires a real password (configured in `docker-compose.yml` from `DB_PASSWORD`); set `PGPASSWORD` inline so the command does not stall on a silent password prompt.
5. **Restore media.** `cp -r /tmp/restore/storage/app/public/* /var/www/html/storage/app/public/`. The zip stores media at paths relative to `base_path()` (i.e. `storage/app/public/...`) because `relative_path` is set to `base_path()` in the spatie config.

The success-record file at `storage/app/private/fleet/last-backup-at` is **not** included in the backup zip — `source.files.include` is `storage/app/public` only. That is intentional: the success-record is per-install metadata about *this* install's backup history, not data to replicate onto another install. After a cross-install restore, the recipient's `last_backup_at` continues to reflect *their* most-recent local `backup:run`, which is the right answer.

Backup restoration is intentionally manual at v1 — high-stakes operations benefit from the operator pausing to verify each step rather than a click-button shortcut.

### Demo-node restore (`demo:restore`)

The one exception to manual-only restore is the **demo node**, which resets on a schedule by restoring a curated baseline blob (session 336; see `docs/fleet-manager-agent-contract.md` § Demo-node reset coordination). `php artisan demo:restore [blob]` automates steps 2–5 above against a **local** blob FM has pushed onto the node — it never fetches the blob itself, so the demo node needs no outbound egress. It is **hard-gated to `isDemoMode()`** (`APP_ENV=demo`) at the top of the command and refuses to run anywhere else, so the destructive wipe-and-replace can never reach a real customer node. The blob path defaults to `storage/app/backups/demo-baseline.zip` (a directory `config/backup.php` already excludes from `backup:run`); pass an explicit path to override. After restoring the DB (`db:wipe --drop-views --drop-types` → `gunzip` the dump → `psql -f`) and the media tree (`storage/app/public`), it overwrites the `base_url` `SiteSetting` from this node's own `APP_URL` so the authoring environment's value never leaks onto the node. `demo:reset` (the code-reseed path) is retained for local/dev convenience; the demo node's reset cron points at `demo:restore`.

---

## Fleet Manager mTLS — cert paths

The Fleet Manager agent contract authenticates via mTLS at the TLS layer as of v2.0.0 (session 248). Nginx terminates the handshake; the application has no auth code path on `/api/health`. Two cert plumbing details matter operationally:

- **Local dev:** run `bin/dev-certs.sh` once per fresh checkout to generate `nginx-certs/localhost.{crt,key}` (the server-side TLS cert for `localhost`). Both files are gitignored. Without them, `docker compose up` fails because nginx cannot load the SSL config. The placeholder client-trust cert at `nginx-certs/fm-client.crt` is committed and untouched by the script.
- **Production:** the operator pastes the FM-supplied client cert into `/opt/nonprofitcrm/nginx-certs/fm-client.crt` (bind-mounted into the nginx container at `/etc/nginx/certs/fm-client.crt`) before first nginx start, and re-pastes plus restarts on rotation. Full procedure at `docs/runbooks/fleet-manager-cert-paste.md`.

The mTLS gate is `/api/health`-scoped only — public routes, admin, portal stay reachable without a client cert.

## Dev tooling — widget thumbnails

| Item | Path / command |
|------|----------------|
| Dev demo route (non-production only) | `GET /dev/widgets/{handle}` — `App\Http\Controllers\Dev\WidgetDemoController@show` |
| Dev preset variant route (non-production only) | `GET /dev/widgets/{handle}/presets/{presetHandle}` — `WidgetDemoController@showPreset` |
| Gate | `routes/dev.php` is conditionally required by `routes/web.php`; `App\Http\Middleware\DevRoutesMiddleware` enforces 404 in production |
| Widget manifest JSON command | `docker compose exec app php artisan widgets:manifest-json` |
| Thumbnail capture script (host-side) | `scripts/generate-thumbnails.js` — standalone Node, runs on the WSL2 host (not inside Docker). Supports `--widget=` and `--preset=` filters. |
| Host-level Playwright install | `npm install --global playwright && npx playwright install chromium` (not in `package.json`) |
| Thumbnail output | `app/Widgets/{PascalName}/thumbnails/static.png` and `preset-{handle}.png`, committed to the repo |
| Public thumbnail serving route | `GET /widget-thumbnails/{handle}/{file}` — `App\Http\Controllers\WidgetThumbnailController`. Strict filename regex (`static.png` / `preset-*.png`). |

## Dev tooling — importer test fixtures

| Item | Path / command |
|------|----------------|
| Generate adversarial CSV fixtures | `docker compose exec app php artisan import-fixtures:generate --importer=<name> --shape=<clean\|messy\|corrupt\|pii\|stress> --seed=<n>` |
| Output directory | `storage/app/import-test-fixtures/` (gitignored) |
| Pest runner | `tests/Feature/Generated/ImportFixtureRunnerTest.php` (fast suite + `--group=slow` for stress) |
| Documentation | [`docs/runbooks/import-fixture-generator.md`](runbooks/import-fixture-generator.md) |
| Builder classes | `app/Services/Import/Fixtures/Importers/<Importer>FixtureBuilder.php` |
| Transform classes | `app/Services/Import/Fixtures/Transforms/{Messy,Corrupt,Pii,Stress}Transform.php` |
| Generator service | `App\Services\Import\Fixtures\FixtureGenerator` |
| Runner service | `App\Services\Import\FixtureRunner` (consumes a CSV + manifest, replays `processOneRow` per row off-Livewire) |
