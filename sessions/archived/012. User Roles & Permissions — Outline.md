# Session 011 Outline — User Roles & Permissions

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline alongside the existing role seeds, the
> Spatie Permission package configuration, and the current Filament resource list. Expand
> into a full implementation prompt. Ensure Events roles are accounted for since Events
> sessions follow this one.

---

## Goal

Move from hard-coded role names to a working fine-grained permission system enforced across all Filament resources. Roles already exist in seed data (super_admin, crm_manager, staff, finance_manager, events_manager, read_only). This session defines what each role can actually do, enforces it in the UI, and optionally adds a Filament UI for managing roles and permissions.

---

## Key Decisions to Make at Session Start

- **Permission granularity**: Per-resource CRUD (view, create, update, delete per resource), or coarser (e.g. "can manage CRM data")? Finer is more flexible but more setup work.
- **Filament policy approach**: Laravel Policies per model (standard, testable) vs Filament's built-in `canAccess()` / `canCreate()` overrides vs Spatie's `@can` directives. Policies are the most correct approach.
- **Role management UI**: Should there be a Filament UI for creating roles and assigning permissions, or are roles/permissions seeded and code-managed? A UI is nicer but adds scope. Decide scope before building.
- **`super_admin` bypass**: Should super_admin bypass all policy checks (Spatie's gate bypass), or be explicitly granted all permissions?
- **Events roles**: The `events_manager` role needs to be defined before Events session 012. Ensure it's covered here.

---

## Scope

**In:**
- Define a permission map: which roles can do what across which resources
- Create Laravel Policies for all major models (Contact, Organization, Membership, Donation, Post, Page, Collection, etc.)
- Register policies in `AuthServiceProvider`
- Wire Filament resources to respect policies (`canAccess()`, `canCreate()`, `canEdit()`, `canDelete()`)
- Seed permissions via Spatie (using `Permission::create()` and role assignment)
- Test that a `read_only` user cannot create or edit; a `crm_manager` can manage CRM but not Finance

**Out (unless small):**
- Full Filament UI for role/permission management (nice to have — assess scope at session start)
- Per-record ownership permissions (e.g. "can only edit posts they authored")
- API-level permission enforcement

---

## Rough Build List

- Define permission map document (role → resource → allowed actions)
- Create Policy classes for each major model
- Register policies
- Update Filament resources with policy-aware methods
- Seed permissions with Spatie
- Test suite: verify role enforcement across key scenarios
- Optional: Filament role/permission management UI

---

## Open Questions at Planning Time

- Is the `events_manager` role definition fully clear by the time this session runs? (Depends on Events planning discussions.)
- Should `staff` have write access to CMS content (Pages, Posts) but not CRM data?
- Does `finance_manager` have read-only CRM access, or no CRM access at all?

---

## What This Unlocks

- Events session can use `events_manager` role with confidence
- All subsequent features are built permission-aware from the start
- Prevents accidental data exposure as the product grows
