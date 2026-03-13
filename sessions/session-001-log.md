# Session 001 — Environment Bootstrap
**Date:** March 12, 2026
**Status:** Complete

---

## What Was Accomplished

### Environment Setup
- Identified PHP 7.3 / Composer 1.9 as a blocker — incompatible with Laravel 11
- Installed Laravel Herd for Windows to get PHP 8.4 and Composer 2.9
- Resolved PATH conflict between old PHP and Herd PHP by removing old binaries
- Cleaned up `C:\Users\Al\bin` (removed php, php.cmd, php73, php73.cmd)
- Uninstalled Composer 1.9 via Windows Apps

### Laravel Skeleton Bootstrap
- Created project skeleton via `composer create-project laravel/laravel` into temp, merged into project directory
- Confirmed all key files in place: `artisan`, `bootstrap/app.php`, `config/`, `public/index.php`

### Database
- Installed PostgreSQL 18 natively on Windows
- Created `nonprofitcrm` database
- Ran initial migrations — 28 tables created including Laravel core, Twill, and Spatie activity log tables

### Filament (CRM Admin)
- Installed Filament 3 via `php artisan filament:install --panels`
- `AdminPanelProvider` registered in `bootstrap/providers.php`
- Admin panel assets published to `public/`

### Twill (CMS)
- Ran `php artisan twill:install` — config and assets published
- Twill migrations confirmed already included in initial migration run
- Superadmin account created manually via `php artisan twill:superadmin`

### Spatie Packages
- Published migrations and config for: `laravel-permission`, `laravel-medialibrary`, `laravel-activitylog`
- All Spatie migrations run clean

### Architecture Decision — Full Docker
- Mid-session decision to move to fully containerized local dev (Docker Compose)
- Rationale: open source project needs one-command setup for contributors; no redundant tooling
- Discussion confirmed: dev = Docker Compose; production = Docker container + managed PostgreSQL/Redis
- Native PostgreSQL and Herd become unused (not removed, just irrelevant to project)

### Docker Setup
- Wrote `Dockerfile` — PHP 8.4-FPM, all required extensions compiled in
- Wrote `docker-compose.yml` — four services: app, nginx, postgres, redis
- Wrote `docker/nginx/default.conf` — Laravel-optimized Nginx config
- Wrote `docker/php/local.ini` — upload limits, memory, execution time
- Wrote `.dockerignore` — excludes vendor, node_modules, .env
- Wrote `dev` shell script — shorthand wrapper for all common container commands
- Updated `.env` and `.env.example` — DB_HOST=postgres, REDIS_HOST=redis
- Built image successfully (exit code 0)
- All four containers running: app, nginx, postgres, redis
- Re-ran all 28 migrations inside Docker — all clean
- Redis connectivity confirmed via `php artisan cache:clear`
- Routes verified: Filament and Twill both registered at `/admin`

---

## Current State

The application is running at `http://localhost` via Docker Compose. All services healthy. All migrations current. Admin panels accessible.

**Remaining before first feature (Contact model):**
- Create Twill superadmin inside Docker: `docker compose exec app php artisan twill:superadmin`

---

## Key Decisions Made

| Decision | Choice | Reason |
|----------|--------|--------|
| Local dev environment | Docker Compose | Single-command setup for open source contributors |
| PHP in production | Container (Dockerfile) | Consistent with dev; portable across hosting targets |
| PostgreSQL in production | Managed service (not containerized) | Data safety; no self-managed backups |
| Redis in production | Managed service (not containerized) | Same reason |
| Deployment target | TBD — Fly.io, Render, or VPS | Decision deferred; Dockerfile makes it flexible |

---

## Files Created This Session

- `Dockerfile`
- `docker-compose.yml`
- `docker/nginx/default.conf`
- `docker/php/local.ini`
- `.dockerignore`
- `dev` (shell script)
- `php-install.md` (now superseded — see superfluous files note)
- `sessions/001.md` (this file)

---

## Superfluous Files (safe to delete)

- `php-install.md` — written for manual PHP install on Windows; Docker makes it irrelevant
- `nonprofit-platform-architecture.md` — original pre-scaffold architecture document; superseded by `docs/ARCHITECTURE.md`

---

## Next Session

Begin Contact model: migration, model class, Filament resource, factory, and Pest feature tests.
