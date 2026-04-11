# Session 002 — Admin Route Conflict, Contact Model

## Context

This is a Laravel 11 application running in Docker Compose (app, nginx, postgres, redis). The stack uses Filament 3 as the CRM admin panel and Twill 3 as the CMS. All packages are installed, all migrations are current, and the app is serving at http://localhost.

Read the following before starting:
- `docs/PRINCIPLES.md` — development principles and the standard all code is held to
- `docs/ARCHITECTURE.md` — full technical overview and design decisions
- `sessions/001.md` — summary of what was built last session
- `composer.json` — installed packages
- `.env` — current environment configuration

---

## Task 1 — Resolve /admin Route Conflict (do this first)

Filament and Twill are both mounted at `/admin`. This causes a `RouteNotFoundException: Route [twill.dashboard] not defined` error when logging into Twill, because Filament has taken over the route namespace.

**Fix:** Move Twill to `/cms` by configuring `config/twill.php`. Twill's path is controlled by the `admin_app_path` key. Filament stays at `/admin`.

After the fix:
- `http://localhost/admin` — Filament CRM panel (Laravel admin users)
- `http://localhost/cms` — Twill CMS panel (content editors)

Verify both panels are accessible and login works on each before proceeding.

---

## Task 2 — Contact Migration

Create the migration for the `contacts` table. This is the central entity — everything else relates to it.

Fields required:
- `id` — UUID primary key
- `type` — enum: `individual`, `organization` (default: `individual`)
- `prefix` — nullable string (Mr, Ms, Dr, etc.)
- `first_name` — nullable string
- `last_name` — nullable string
- `organization_name` — nullable string (used when type = organization)
- `preferred_name` — nullable string
- `email` — nullable string, indexed
- `email_secondary` — nullable string
- `phone` — nullable string
- `phone_secondary` — nullable string
- `address_line_1` — nullable string
- `address_line_2` — nullable string
- `city` — nullable string
- `state` — nullable string (2-char for US, flexible for international)
- `postal_code` — nullable string
- `country` — nullable string, default 'US'
- `notes` — nullable text
- `custom_data` — JSONB, nullable (Spatie schemaless attributes)
- `is_deceased` — boolean, default false
- `do_not_contact` — boolean, default false
- `source` — nullable string (how they were added: import, manual, form, etc.)
- `timestamps` — created_at, updated_at
- `softDeletes` — deleted_at

---

## Task 3 — Contact Model

Create `app/Models/Contact.php` with:
- `HasUuids`
- `SoftDeletes`
- Spatie `HasSchemalessAttributes` on the `custom_data` column
- Correct `$fillable` array
- `$casts` for the enum type field and booleans
- A `getDisplayNameAttribute` accessor that returns `organization_name` for orgs, and `first_name . ' ' . last_name` for individuals, falling back gracefully if fields are empty

---

## Task 4 — Filament Contact Resource

Create a Filament resource for Contact with:

**List view columns:** display name, type (badged), email, phone, city/state, created_at

**Create/Edit form:**
- Type selector (individual / organization) that shows/hides relevant fields
- All name fields
- Contact info section (email x2, phone x2)
- Address section
- Flags section (is_deceased, do_not_contact)
- Notes textarea
- Source field

**Filters:** by type, by do_not_contact flag, by is_deceased flag

---

## Task 5 — Factory and Pest Feature Test

Create `database/factories/ContactFactory.php` using Faker. Generate realistic data for both individual and organization types.

Create a Pest feature test at `tests/Feature/ContactTest.php` that covers:
- A contact can be created with valid data
- Display name accessor returns correct value for individuals
- Display name accessor returns correct value for organizations
- Soft delete works (record is not destroyed, just hidden)

Run the tests inside Docker (`./dev artisan test`) and confirm they pass before closing the session.

---

## End of Session

When all tasks are complete:
1. Update `docs/ARCHITECTURE.md` — mark Contact entity as built
2. Update `sessions/001.md` if any corrections are needed
3. Write `sessions/002.md` with a summary of what was built
4. List any superfluous files or new decisions that emerged
