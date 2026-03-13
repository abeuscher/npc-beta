# Session 002 — Contact Model, Unified Auth, Twill Removal
**Date:** March 12, 2026
**Status:** Complete

---

## What Was Accomplished

### Task 1 — /admin Route Conflict Resolved

Filament and Twill were both mounted at `/admin`, causing a `RouteNotFoundException` when logging into Twill.

**Fix:** Set `admin_app_path` to `cms` in `config/twill.php`. Routes confirmed clean:
- `http://localhost/admin` — Filament CRM panel
- `http://localhost/cms` — Twill CMS panel

### Task 2 — Contacts Migration

Created `database/migrations/2026_03_12_194756_create_contacts_table.php`.

- UUID primary key
- `type` enum: `individual` / `organization`
- Full name fields (prefix, first, last, org, preferred)
- Contact info (email ×2, phone ×2)
- Full address (line 1/2, city, state, postal, country — defaults to `US`)
- `notes` text, `custom_data` JSONB
- Boolean flags: `is_deceased`, `do_not_contact`
- `source` string
- Timestamps + soft deletes

### Task 3 — Contact Model

Created `app/Models/Contact.php`:
- `HasUuids`, `SoftDeletes`, `HasFactory`
- Spatie `SchemalessAttributes` cast on `custom_data`
- Full `$fillable` array
- `$casts` for booleans
- `getDisplayNameAttribute` — returns `organization_name` for orgs, `first_name last_name` for individuals

### Task 4 — Filament Contact Resource

Created `app/Filament/Resources/ContactResource.php` with full form, list, and filter configuration.

**List columns:** display name (searchable), type (badged), email (copyable), phone, city/state, created at

**Form sections:** Type selector (live, shows/hides relevant fields), Name, Contact Information, Address, Flags, Additional

**Filters:** type, do_not_contact, is_deceased

Routes verified at `/admin/contacts`.

### Task 5 — Factory and Tests

Created `database/factories/ContactFactory.php` — default `individual` state, `organization()`, `doNotContact()`, `deceased()` states.

Created `tests/Feature/ContactTest.php` — 4 tests all passing:
- ✅ Contact can be created with valid data
- ✅ Display name returns full name for individual
- ✅ Display name returns organization name for organization
- ✅ Soft delete does not destroy the record

---

## Post-Session Browser Fixes

Several errors emerged after the initial work was confirmed in the browser.

**Livewire JS 404:** Nginx returned 404 for `.js` files not on disk instead of passing to PHP. Fixed by adding `try_files $uri /index.php?$query_string` to `docker/nginx/default.conf`'s static assets location block.

**Missing Filament user:** No user existed in the fresh Docker database. Fixed by running `php artisan make:filament-user`. `FILAMENT_ADMIN_EMAIL/PASSWORD` and `TWILL_ADMIN_EMAIL/PASSWORD` kept as separate env vars for production flexibility.

**Twill login redirect loop:** `twill:superadmin` had never been run inside Docker. Fixed by running it in the container. This also surfaced the need to create all 6 Spatie roles before assigning `super_admin`.

---

## Unified Authentication — Option C

The user requested a single login for the entire product. After discussing three options the user chose **Option C: Filament as the sole login, Twill trusts the web guard session via middleware bridging.**

### What was built

- `app/Http/Middleware/BridgeFilamentToTwill.php` — extends Twill's `Authenticate`, checks for a `web` guard session, promotes it to the `twill_users` guard, then delegates to Twill's auth logic. Unauthenticated users redirect to Filament's login.
- `app/Http/Middleware/RedirectTwillLogin.php` — intercepts `/cms/login` before route matching and 301-redirects to `/admin/login`.
- `app/Providers/AppServiceProvider.php` — overrides the `twill_auth` route middleware alias after all providers boot.
- `config/twill.php` — configured `models.user` to `App\Models\User`.
- `App\Models\User` — added `is_active`, Twill-compatibility accessors, `HasRoles`.
- `tests/Feature/UnifiedAuthTest.php` — 8 tests covering the bridge, redirects, role values, and guard state. All passing.

The bridge worked: one login at `/admin`, both panels accessible.

---

## TwillModelContract Errors and the Decision to Remove Twill

When clicking the Twill user profile page, a series of `BadMethodCallException` errors emerged. Twill's internal code expected its own model shape — an informal dependency far larger than the formal `TwillModelContract` interface.

### The pattern

Twill assumes `A17\Twill\Models\User` will always be the user model. That class uses `IsTranslatable`, `HasMedias`, `HasPresenter`, and other traits adding ~20 methods Twill's UI code calls at runtime. Pointing `twill.models.user` to `App\Models\User` caused repeated hits as each screen was visited. Stubbing them one by one was reactive and fragile.

### The architectural discussion

This triggered a broader discussion about the stack. Key conclusions:

**The two-runtime problem:** Filament runs on Livewire (server-rendered). Twill is a Vue SPA. They can be styled similarly but will never feel like one application — navigation between them always causes a full page reload and a runtime teardown/bootstrap. A shared header or unified shell is not achievable without rebuilding both.

**The must-have features are all data problems:** Events and registration, member portal, newsletter signup, donation forms, QuickBooks sync — none require a block editor. They are forms that create records, pipelines that sync data, and dashboards that display records. Filament's native territory.

**Filament is sufficient for CMS needs at this scale:** A `Page` model with TipTap rich text and Filament's Builder field covers staff updating content a few times a month. Twill earns its complexity for a dedicated content team publishing rich editorial daily. That is not this product's use case.

### The decision: remove Twill entirely

New stack: **Filament + Blade + Livewire + Alpine + Tailwind**. No Twill. No bridge. No two-runtime problem. No model contract shims.

---

## Twill Removal

`composer remove area17/twill` pulled out 39 packages — Twill plus all transitive dependencies (AWS SDK, Google Analytics API, Azure Blob Storage, 2FA libs, imgix, League Glide, Socialite, Laravel UI, and more).

**Deleted:**
- `config/twill.php`, `config/translatable.php`, `routes/twill.php`
- `app/Http/Middleware/RedirectTwillLogin.php`
- `app/Http/Middleware/BridgeFilamentToTwill.php`
- `resources/views/twill/`, `resources/views/site/layouts/block.blade.php`
- `public/assets/twill/` (compiled Vue SPA — CSS, JS, fonts, manifest)
- `tests/Feature/UnifiedAuthTest.php`

**Simplified:**
- `App\Models\User` — stripped to 35 lines. No interface, no stubs, no Twill imports. `isSuperAdmin()` retained.
- `AppServiceProvider` — empty provider.
- `bootstrap/app.php` — `withMiddleware` block retained (empty, but required — DebugBar calls `appendMiddlewareToGroup('web', ...)` during boot and throws if the middleware manager is not initialized).
- `database/factories/UserFactory` — added `is_active: true` as default.

**New:**
- `tests/Feature/UserTest.php` — 6 tests covering the clean User model.
- `database/migrations/2026_03_12_225649_rename_cms_editor_role_to_staff.php` — renames `cms_editor` (a Twill concept) to `staff`.

---

## Final State

12 tests passing. Application running at `http://localhost`. Filament at `/admin`. Single auth. Clean codebase with no Twill artifacts.

---

## Key Decisions Made

| Decision | Choice | Reason |
|----------|--------|--------|
| Test database | PostgreSQL `nonprofitcrm_test` | SQLite doesn't support JSONB |
| Unified auth | Filament as sole login | Single product, one login experience |
| CMS tool | Removed Twill, use Filament | Two-runtime problem; must-have features are data problems not editorial |
| Frontend stack | Blade + Livewire + Alpine + Tailwind | Already in stack; lowest complexity for expected traffic and team size |
| `cms_editor` role | Renamed to `staff` | Twill-specific name; concept survives, name does not |
| `withMiddleware` block | Retained empty | DebugBar requires it to initialize middleware manager in tests |

---

## Files Created / Modified This Session

**Created:**
- `database/migrations/2026_03_12_194756_create_contacts_table.php`
- `database/migrations/2026_03_12_214529_add_is_active_and_published_to_users_table.php`
- `database/migrations/2026_03_12_225649_rename_cms_editor_role_to_staff.php`
- `app/Models/Contact.php`
- `app/Filament/Resources/ContactResource.php` (+ Pages/)
- `database/factories/ContactFactory.php`
- `tests/Feature/ContactTest.php`
- `tests/Feature/UserTest.php`
- `phpunit.xml` — switched to PostgreSQL

**Modified:**
- `app/Models/User.php` — added `is_active`, Twill compat, then stripped all Twill code
- `app/Providers/AppServiceProvider.php` — added bridge alias, then emptied
- `bootstrap/app.php` — added/removed RedirectTwillLogin, left withMiddleware stub
- `docker/nginx/default.conf` — added `try_files` to static assets block
- `database/factories/UserFactory.php` — added `is_active: true` default

**Deleted:** all Twill files listed above

---

## Next Session

Build the public-facing web layer. Goal: a `Page` model managed in Filament, published to `localhost/`. See `sessions/003-prompt.md`.
