# Session 013 Outline — Role Management UI

> **Session Preparation**: This is a planning outline, not a complete implementation prompt.
> At the start of this session, read this outline alongside the completed permissions infrastructure
> from session 012 (AuthServiceProvider, 18 policies, PermissionSeeder, 90 permissions). The
> vocabulary is in place — this session builds the UI on top of it.

---

## Goal

Give super admins a Filament UI to create, edit, and delete roles, and to assign any combination
of the 90 seeded permissions to each role. This replaces the current code-managed permission
assignments with an admin-controlled system. The two seeded roles (`super_admin`, `cms_editor`)
remain as defaults but everything else is admin-defined.

---

## Key Decisions to Make at Session Start

- **Permission UI layout**: Should permissions be presented as a flat searchable list, or grouped
  by resource area (CRM, Finance, CMS, Admin) with checkboxes per action? Grouping is more usable.
- **Protecting built-in roles**: Should `super_admin` be editable at all, or locked? At minimum
  its permissions should not be manageable (it uses Gate bypass). `cms_editor` could be editable.
- **Role assignment from users**: Should role assignment live in UserResource (current), in the
  role editor (assign users to a role), or both? Current UserResource approach is probably enough.
- **Deleting roles**: If a role is deleted while users still have it assigned, Spatie handles the
  pivot cleanup automatically. But should we warn? Soft-delete or hard-delete?

---

## Scope

**In:**
- Filament `RoleResource` (Settings group, super_admin only via `canAccess()`)
- List roles with user count per role
- Create / edit a role: name field + permission checkboxes grouped by area (CRM, Finance, CMS, Admin)
- Delete role (with confirmation showing assigned user count)
- Protect `super_admin` role from edit/delete
- `cms_editor` editable but not deletable (it's the only demo role)

**Out:**
- Per-user custom permissions (Spatie supports this, but it's complexity for later)
- Role hierarchy / inheritance
- API-level permission enforcement

---

## Rough Build List

- `RoleResource` with list, create, edit pages
- Permission checkbox component grouped by resource area
- `canAccess()` restricted to super_admin
- Guard `super_admin` from edit/delete in policy or resource
- Tests: super_admin can manage roles; cms_editor cannot access RoleResource

---

## What This Unlocks

- Clients can define their own org structure (Development Director, Volunteer Coordinator, etc.)
- Events session can use `events_manager` role if the client defines it
- Import/Export session can assign narrow import-only roles
