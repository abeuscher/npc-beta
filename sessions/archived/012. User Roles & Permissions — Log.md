# Session 012 Log — User Roles & Permissions

**Date:** 2026-03-14
**Branch:** ft-session-012
**Status:** Complete

---

## Summary

This session implemented a fine-grained permission system using Laravel Policies and Spatie
Laravel Permission. The session started with a full role/permission map across all 18 resources,
then pivoted mid-session after a design discussion — the predetermined role set was replaced with
just two roles (`super_admin`, `cms_editor`) and a 90-permission vocabulary that a future Role
Management UI (session 013) will expose to admins. The session also uncovered and fixed several
unrelated bugs, clarified the Collection Manager vs Collections content browser architecture, and
built the content-facing Collections browser.

---

## Architectural Pivot

The original plan seeded six roles with predetermined permissions (`crm_manager`, `staff`,
`finance_manager`, `events_manager`, `read_only`). Mid-session, the decision was made to abandon
this approach: every nonprofit has a different org structure and pre-defined roles are not
realistic. The correct model is:

- **Seed the permission vocabulary** (90 permissions across 18 resources) — these are fixed
- **Seed only two default roles**: `super_admin` (Gate bypass) and `cms_editor` (CMS content only)
- **Let super admins define their own roles** via a UI (session 013)

Campaign taxonomy was corrected: Campaign belongs to CRM, not Finance.

---

## What Was Built

### Permission Infrastructure

- `app/Providers/AuthServiceProvider.php` — registers 18 policies; `Gate::before` bypass for
  `super_admin`
- `bootstrap/app.php` — registers `AuthServiceProvider`
- 18 Policy classes in `app/Policies/` — one per model, each delegating to Spatie `can()` checks
- `database/seeders/PermissionSeeder.php` — idempotent; creates all 90 permissions; assigns
  cms_editor permissions; safe to re-run

### Roles Seeded

| Role | Permissions |
|------|-------------|
| `super_admin` | Gate bypass — no explicit permissions needed |
| `cms_editor` | view collection + collection_item (full) + post (full) + page (full) + cms_tag (full) |

### User Management Improvements

- `UserResource` — added Delete row action and bulk delete with protected-account skip
- Delete confirmation modal shows name and email: `"Delete Jane Smith (jane@example.com)?"`
- `User::isProtected()` — the oldest user account (the original admin) cannot be deleted;
  the delete button is hidden for that record; bulk delete skips it with a warning toast

### Settings Page Access Control

Custom Filament pages bypass the policy system. Both settings pages now explicitly restrict access:

- `CmsSettingsPage::canAccess()` — super_admin only
- `FinanceSettingsPage::canAccess()` — super_admin only

### Collection Architecture Clarification

The session identified a critical UX/architecture distinction:

- **Collection Manager** (`Tools` group, super_admin only) — developer tool for defining
  collection schemas (field definitions, handles, visibility). `CollectionResource::canAccess()`
  now explicitly enforces super_admin only.
- **Collections browser** (`Content` group, cms_editor accessible) — content editor tool for
  browsing existing collections and managing their items. Built as a new resource.

`CollectionItemResource` was deleted — it was a broken standalone resource superseded by the
relation manager inside `CollectionResource` and now also by the new content browser.

### New: ContentCollectionResource

A purpose-built content-facing collections UI:

- `app/Filament/Resources/ContentCollectionResource.php` — list page shows all collections;
  system collections (blog_posts, events) are read-only with a "System" badge; custom collections
  have a "Manage Items" action
- `Pages/ListContentCollections.php` — no create button (editors cannot create collection types)
- `Pages/ManageContentCollectionItems.php` — extends `ManageRelatedRecords`; form is dynamically
  built from the parent collection's `getFormSchema()`; full CRUD on items; supports drag
  reordering; back-link breadcrumb to the collection list

### Bug Fix: DatabaseSeeder

`DatabaseSeeder` was inserting `content` into the `pages` table — a column dropped in session 010
(`2026_03_14_000001_drop_content_from_pages.php`). The stale field reference was removed.

---

## Files Created

| File | Purpose |
|------|---------|
| `app/Providers/AuthServiceProvider.php` | Policy registration + super_admin Gate bypass |
| `app/Policies/ContactPolicy.php` | CRM |
| `app/Policies/HouseholdPolicy.php` | CRM |
| `app/Policies/OrganizationPolicy.php` | CRM |
| `app/Policies/MembershipPolicy.php` | CRM |
| `app/Policies/NotePolicy.php` | CRM |
| `app/Policies/TagPolicy.php` | CRM |
| `app/Policies/DonationPolicy.php` | Finance |
| `app/Policies/TransactionPolicy.php` | Finance |
| `app/Policies/FundPolicy.php` | Finance |
| `app/Policies/CampaignPolicy.php` | Finance |
| `app/Policies/PostPolicy.php` | CMS |
| `app/Policies/PagePolicy.php` | CMS |
| `app/Policies/CollectionPolicy.php` | CMS |
| `app/Policies/CollectionItemPolicy.php` | CMS |
| `app/Policies/CmsTagPolicy.php` | CMS |
| `app/Policies/NavigationItemPolicy.php` | CMS |
| `app/Policies/UserPolicy.php` | Admin |
| `app/Policies/WidgetTypePolicy.php` | Admin |
| `database/seeders/PermissionSeeder.php` | Idempotent permission + role seeder |
| `app/Filament/Resources/ContentCollectionResource.php` | Content-facing collections browser |
| `app/Filament/Resources/ContentCollectionResource/Pages/ListContentCollections.php` | Collection list |
| `app/Filament/Resources/ContentCollectionResource/Pages/ManageContentCollectionItems.php` | Items CRUD |
| `database/factories/OrganizationFactory.php` | Test support |
| `database/factories/DonationFactory.php` | Test support |
| `database/factories/PostFactory.php` | Test support |
| `database/factories/PageFactory.php` | Test support |
| `database/factories/FundFactory.php` | Test support |
| `database/factories/WidgetTypeFactory.php` | Test support |
| `database/factories/CollectionFactory.php` | Test support |
| `database/factories/CollectionItemFactory.php` | Test support |
| `database/factories/CmsTagFactory.php` | Test support |
| `tests/Feature/PermissionTest.php` | 14 tests, 47 assertions |

---

## Files Modified

| File | Change |
|------|--------|
| `bootstrap/app.php` | Register AuthServiceProvider |
| `database/seeders/DatabaseSeeder.php` | Remove stale `content` field from Page seed; simplify roles to super_admin + cms_editor; call PermissionSeeder |
| `app/Models/User.php` | Add `isProtected()` helper |
| `app/Filament/Resources/UserResource.php` | Add delete action, bulk delete, protected-account guard, custom modal copy |
| `app/Filament/Resources/CollectionResource.php` | Add `canAccess()` super_admin gate |
| `app/Filament/Pages/Settings/CmsSettingsPage.php` | Add `canAccess()` super_admin only |
| `app/Filament/Pages/Settings/FinanceSettingsPage.php` | Add `canAccess()` super_admin only |

## Files Deleted

| File | Reason |
|------|--------|
| `app/Filament/Resources/CollectionItemResource.php` | Broken standalone resource; superseded by relation manager and ContentCollectionResource |
| `app/Filament/Resources/CollectionItemResource/Pages/` | (all three pages) — same |

---

## Session Renumbering

Role Management UI was added as session 013, shifting Events and all subsequent sessions forward
by one (013 → 014 through 019 → 020). `future-sessions.md` updated accordingly.

---

## Test Results

```
Tests: 69 passed (164 assertions)
```

All pre-existing tests pass. 14 new permission tests added.

---

## Deferred / Follow-on

- **Session 013**: Role Management UI — Filament resource for super_admin to create roles and
  assign permissions via grouped checkboxes. The 90 permissions are already seeded and waiting.
- **Batch list controls** (bulk select + actions) on all resource list pages — nice-to-have UX
  noted during testing; not scoped to a specific session yet.
- **Sortable column headers** on all resource tables — same as above, UX polish pass.
