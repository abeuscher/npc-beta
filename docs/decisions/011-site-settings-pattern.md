# Decision 011 — Site Settings Pattern: DB-Driven Config

**Date:** March 2026
**Status:** Decided

---

## Decision

Store site-wide configuration in a `site_settings` database table as key/value pairs. On application boot, merge these values into Laravel's `config('site.*')` namespace. Views and controllers read from `config('site.*')` with hard-coded fallbacks.

---

## Context

The application needs runtime-configurable settings — site name, blog URL prefix, Pico CSS toggle, custom stylesheet path — that:

1. Can be changed by an admin without a server restart or code deployment
2. Are readable from controllers, views, and Blade templates without querying the DB on every request
3. Survive fresh installs before the database is migrated (no bootstrap crash)

---

## Approach

**`site_settings` table** — `key`, `value` (text, nullable), `group` (for UI grouping), `type` (for casting: string/boolean/integer/json).

**`AppServiceProvider::boot()`** — reads all settings at boot time with `SiteSetting::all()->keyBy('key')`, calls `config([...])` to merge values into `config('site.*')`. Wrapped in `try/catch (\Throwable)` so a fresh install before migrations runs cleanly.

**`SiteSetting::get()` / `SiteSetting::set()`** — static helpers. `get()` reads from Redis cache (TTL 60 min) and falls through to DB on miss. `set()` writes to DB and calls `Cache::forget()`.

**Filament CMS Settings page** — saves each field via `SiteSetting::set()`, then calls `Artisan::call('config:clear')` to ensure the next request re-reads from DB.

---

## Why Not `.env`

`.env` requires a server-side file edit and process restart. It is not appropriate for end-user configuration. The goal is for an organization's admin to change "Site Name" or toggle Pico CSS through a UI without infrastructure access.

---

## Config:Clear Requirement

Because the boot-time merge runs once per process lifecycle, changing a setting via the admin does **not** immediately affect an already-running PHP-FPM worker pool. Calling `Artisan::call('config:clear')` on save clears the compiled config, ensuring the next request re-bootstraps from the updated DB values.

For FPM deployments with OPCache, a worker restart may also be needed. The settings page documents this as helper text on the blog_prefix field.

---

## Blog Prefix Routing

The blog prefix (`config('site.blog_prefix', 'news')`) is registered in `routes/web.php` at boot time. URLs: `/{prefix}` (index), `/{prefix}/{slug}` (show). Changing the prefix via the admin requires `php artisan config:clear` — documented in the UI.

Validation prevents setting the prefix to: any existing `pages.slug` value, or the reserved words `admin`, `horizon`, `up`, `login`, `logout`, `register`.

---

## SCSS Compilation

Custom stylesheets are uploaded via the CMS Settings page. If the file extension is `.scss`, it is compiled to CSS using `scssphp/scssphp` (pure PHP, no Node dependency) before storage. Compiled output is stored to `storage/app/public/site/custom.css`. Compilation errors are surfaced as Filament validation notifications — the upload is rejected and the previous stylesheet is retained.
