# Session 012 Prompt — User Roles & Permissions

## Context

This session implements a fine-grained permission system across all 18 Filament resources using Laravel Policies + Spatie Laravel Permission (^6.7). Six roles already exist in the seeder: `super_admin`, `crm_manager`, `staff`, `finance_manager`, `events_manager`, `read_only`. The `HasRoles` trait is already on the `User` model. No policies exist yet (`app/Policies/` is empty).

---

## Decisions Made

### Permission granularity
Use per-resource CRUD permissions seeded via Spatie: `view_any_{resource}`, `view_{resource}`, `create_{resource}`, `update_{resource}`, `delete_{resource}`. This provides maximum flexibility for future role changes.

### Policy approach
Laravel Policies per model, registered via an `AuthServiceProvider`. Filament v3 automatically respects registered policies for `canAccess()`, `canCreate()`, `canEdit()`, `canDelete()`, and `canView()`.

### `super_admin` bypass
Register a `Gate::before()` callback in `AuthServiceProvider` so `super_admin` users bypass all policy checks. Do **not** grant them explicit permissions — the gate bypass is sufficient.

### Role management UI
Out of scope for this session. Roles and permissions are entirely code-managed (seeded). No Filament UI for role/permission editing.

### `events_manager` scope
For this session, `events_manager` gets CRM **view** access only. Full Events resource permissions will be added when the Events module is built. The role slot is reserved and functional.

---

## Permission Map

| Resource | crm_manager | staff | finance_manager | events_manager | read_only |
|----------|:-----------:|:-----:|:---------------:|:--------------:|:---------:|
| **CRM** |
| Contact | **full** | — | view | view | view |
| Household | **full** | — | view | view | view |
| Organization | **full** | — | view | view | view |
| Membership | **full** | — | view | view | view |
| Note | **full** | — | — | — | view |
| Tag | **full** | **full** | — | — | view |
| **Finance** |
| Donation | view | — | **full** | — | view |
| Transaction | view | — | **full** | — | view |
| Fund | view | — | **full** | — | view |
| Campaign | view | — | **full** | — | view |
| **CMS** |
| Post | — | **full** | — | — | view |
| Page | — | **full** | — | — | view |
| Collection | — | **full** | — | — | view |
| CollectionItem | — | **full** | — | — | view |
| CmsTag | — | **full** | — | — | view |
| NavigationItem | — | **full** | — | — | view |
| **Admin** |
| User | — | — | — | — | — |
| WidgetType | — | — | — | — | — |

**Legend:** `full` = viewAny + view + create + update + delete. `view` = viewAny + view only. `—` = no access. `super_admin` bypasses all checks via `Gate::before`.

---

## Build Steps

### Step 1 — Create `AuthServiceProvider`

Create `app/Providers/AuthServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Contact::class        => \App\Policies\ContactPolicy::class,
        \App\Models\Household::class      => \App\Policies\HouseholdPolicy::class,
        \App\Models\Organization::class   => \App\Policies\OrganizationPolicy::class,
        \App\Models\Membership::class     => \App\Policies\MembershipPolicy::class,
        \App\Models\Note::class           => \App\Policies\NotePolicy::class,
        \App\Models\Tag::class            => \App\Policies\TagPolicy::class,
        \App\Models\Donation::class       => \App\Policies\DonationPolicy::class,
        \App\Models\Transaction::class    => \App\Policies\TransactionPolicy::class,
        \App\Models\Fund::class           => \App\Policies\FundPolicy::class,
        \App\Models\Campaign::class       => \App\Policies\CampaignPolicy::class,
        \App\Models\Post::class           => \App\Policies\PostPolicy::class,
        \App\Models\Page::class           => \App\Policies\PagePolicy::class,
        \App\Models\Collection::class     => \App\Policies\CollectionPolicy::class,
        \App\Models\CollectionItem::class => \App\Policies\CollectionItemPolicy::class,
        \App\Models\CmsTag::class         => \App\Policies\CmsTagPolicy::class,
        \App\Models\NavigationItem::class => \App\Policies\NavigationItemPolicy::class,
        \App\Models\User::class           => \App\Policies\UserPolicy::class,
        \App\Models\WidgetType::class     => \App\Policies\WidgetTypePolicy::class,
    ];

    public function boot(): void
    {
        // super_admin bypasses all policy checks
        Gate::before(function (\App\Models\User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });
    }
}
```

Register it in `bootstrap/app.php` (Laravel 11 style) by adding it to the `withProviders()` call, or in `config/app.php` providers array if that file exists.

**Important**: Check how providers are registered in this project (Laravel 11 uses `bootstrap/app.php`). Add `AuthServiceProvider` correctly.

---

### Step 2 — Create Policy Classes

Create 18 policy files in `app/Policies/`. All policies follow the same pattern — they check Spatie permissions. Here is the template:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\{ModelName};

class {ModelName}Policy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_{resource}');
    }

    public function view(User $user, {ModelName} $model): bool
    {
        return $user->can('view_{resource}');
    }

    public function create(User $user): bool
    {
        return $user->can('create_{resource}');
    }

    public function update(User $user, {ModelName} $model): bool
    {
        return $user->can('update_{resource}');
    }

    public function delete(User $user, {ModelName} $model): bool
    {
        return $user->can('delete_{resource}');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_{resource}');
    }
}
```

Use this resource-name mapping:

| Model | Permission slug |
|-------|----------------|
| Contact | `contact` |
| Household | `household` |
| Organization | `organization` |
| Membership | `membership` |
| Note | `note` |
| Tag | `tag` |
| Donation | `donation` |
| Transaction | `transaction` |
| Fund | `fund` |
| Campaign | `campaign` |
| Post | `post` |
| Page | `page` |
| Collection | `collection` |
| CollectionItem | `collection_item` |
| CmsTag | `cms_tag` |
| NavigationItem | `navigation_item` |
| User | `user` |
| WidgetType | `widget_type` |

**UserPolicy** and **WidgetTypePolicy** should return `false` for all methods (only `super_admin` can manage these, and they are covered by the Gate::before bypass).

---

### Step 3 — Create Permission Seeder

Create `database/seeders/PermissionSeeder.php`. This seeder:

1. Creates all permissions using `Permission::firstOrCreate(['name' => ...])` (idempotent)
2. Assigns permissions to roles
3. Clears Spatie permission cache after seeding (`app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions()`)

Full permission list to create (90 permissions total: 5 actions × 18 resources):

```
view_any_contact, view_contact, create_contact, update_contact, delete_contact
view_any_household, view_household, create_household, update_household, delete_household
view_any_organization, view_organization, create_organization, update_organization, delete_organization
view_any_membership, view_membership, create_membership, update_membership, delete_membership
view_any_note, view_note, create_note, update_note, delete_note
view_any_tag, view_tag, create_tag, update_tag, delete_tag
view_any_donation, view_donation, create_donation, update_donation, delete_donation
view_any_transaction, view_transaction, create_transaction, update_transaction, delete_transaction
view_any_fund, view_fund, create_fund, update_fund, delete_fund
view_any_campaign, view_campaign, create_campaign, update_campaign, delete_campaign
view_any_post, view_post, create_post, update_post, delete_post
view_any_page, view_page, create_page, update_page, delete_page
view_any_collection, view_collection, create_collection, update_collection, delete_collection
view_any_collection_item, view_collection_item, create_collection_item, update_collection_item, delete_collection_item
view_any_cms_tag, view_cms_tag, create_cms_tag, update_cms_tag, delete_cms_tag
view_any_navigation_item, view_navigation_item, create_navigation_item, update_navigation_item, delete_navigation_item
view_any_user, view_user, create_user, update_user, delete_user
view_any_widget_type, view_widget_type, create_widget_type, update_widget_type, delete_widget_type
```

Role permission assignments (from permission map above):

- **crm_manager**: all 5 actions on contact, household, organization, membership, note, tag; view_any + view on donation, transaction, fund, campaign
- **staff**: all 5 actions on post, page, collection, collection_item, cms_tag, navigation_item, tag
- **finance_manager**: all 5 actions on donation, transaction, fund, campaign; view_any + view on contact, household, organization, membership
- **events_manager**: view_any + view on contact, household, organization, membership
- **read_only**: view_any + view on contact, household, organization, membership, note, tag, donation, transaction, fund, campaign, post, page, collection, collection_item, cms_tag, navigation_item

Call this seeder from `DatabaseSeeder.php` (add `$this->call(PermissionSeeder::class)` before role creation or just after — but ensure roles exist first).

---

### Step 4 — Update `DatabaseSeeder.php`

Modify `DatabaseSeeder.php` to call `PermissionSeeder` after roles are created. The role creation code already exists; add the seeder call immediately after roles are seeded:

```php
$this->call(PermissionSeeder::class);
```

---

### Step 5 — Register Provider in `bootstrap/app.php`

Laravel 11 registers extra providers in `bootstrap/app.php`. Add:

```php
->withProviders([
    App\Providers\AuthServiceProvider::class,
])
```

Check the existing `bootstrap/app.php` for the correct location (may need to chain onto existing `withProviders` or add a new one).

---

### Step 6 — Run Migrations and Seeders

```bash
docker compose exec app php artisan db:seed --class=PermissionSeeder
```

(Run from inside the project's Docker environment. Roles already exist from the main seeder; `PermissionSeeder` is idempotent and can safely run against an existing database.)

---

### Step 7 — Write Tests

Create `tests/Feature/PermissionTest.php`. Test the following scenarios:

1. **super_admin can do everything**: Can viewAny, create, update, delete Contact
2. **crm_manager can manage CRM**: Can create/update/delete Contact, Organization, Household, Membership
3. **crm_manager cannot manage Finance**: Cannot create/update/delete Donation, Transaction, Fund
4. **crm_manager can view Finance**: Can viewAny/view Donation
5. **staff can manage CMS**: Can create/update/delete Post, Page, Collection
6. **staff cannot access CRM**: Cannot viewAny Contact, Organization
7. **finance_manager can manage Finance**: Can create/update/delete Donation, Transaction, Fund
8. **finance_manager can view CRM**: Can viewAny/view Contact
9. **finance_manager cannot manage CRM**: Cannot create/update/delete Contact
10. **events_manager can view CRM only**: Can viewAny Contact, cannot create Contact
11. **read_only can view everything permitted**: Can viewAny Contact, Post, Donation
12. **read_only cannot create anything**: Cannot create Contact, Post, Donation
13. **no-role user cannot access anything**: A user with no assigned role cannot viewAny any resource
14. **User and WidgetType are admin-only**: crm_manager, staff, finance_manager, events_manager, read_only all return false for viewAny User and WidgetType

Use `actingAs()` and the Gate facade or `$this->assertTrue($user->can(...))` style assertions. Create users with roles in each test using factories + role assignment.

---

## Files to Create

- `app/Providers/AuthServiceProvider.php`
- `app/Policies/ContactPolicy.php`
- `app/Policies/HouseholdPolicy.php`
- `app/Policies/OrganizationPolicy.php`
- `app/Policies/MembershipPolicy.php`
- `app/Policies/NotePolicy.php`
- `app/Policies/TagPolicy.php`
- `app/Policies/DonationPolicy.php`
- `app/Policies/TransactionPolicy.php`
- `app/Policies/FundPolicy.php`
- `app/Policies/CampaignPolicy.php`
- `app/Policies/PostPolicy.php`
- `app/Policies/PagePolicy.php`
- `app/Policies/CollectionPolicy.php`
- `app/Policies/CollectionItemPolicy.php`
- `app/Policies/CmsTagPolicy.php`
- `app/Policies/NavigationItemPolicy.php`
- `app/Policies/UserPolicy.php`
- `app/Policies/WidgetTypePolicy.php`
- `database/seeders/PermissionSeeder.php`
- `tests/Feature/PermissionTest.php`

## Files to Modify

- `database/seeders/DatabaseSeeder.php` — add `$this->call(PermissionSeeder::class)`
- `bootstrap/app.php` — register `AuthServiceProvider`

---

## Notes

- No changes to Filament resource files are needed. Filament v3 auto-discovers registered policies.
- The `Gate::before` bypass for `super_admin` must be set up **before** any test assertions run. In tests, the service provider boots automatically.
- All `Permission::firstOrCreate()` calls must specify `['guard_name' => 'web']` if there's any ambiguity, but the default guard should be fine.
- Do not create a `before()` method on individual policy classes — the Gate-level bypass handles `super_admin`.
- When testing, use `$user->givePermissionTo(...)` or `$role->givePermissionTo(...)` — permissions must exist in the DB for `can()` to work. In tests, either use `RefreshDatabase` + run `PermissionSeeder`, or create permissions inline.
