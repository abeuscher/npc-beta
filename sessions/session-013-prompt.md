# Session 013 Prompt — Role Management UI

## Context

Session 012 delivered the full permissions infrastructure:
- `AuthServiceProvider` with 18 policy registrations and a `Gate::before` bypass for `super_admin`
- `PermissionSeeder` creating 90 permissions (18 resources × 5 actions: view_any, view, create, update, delete)
- Two seeded roles: `super_admin` (Gate bypass, no explicit permissions) and `cms_editor` (CMS-focused permissions)
- `UserResource` in the Settings group already handles role assignment via a multi-select field

This session builds a `RoleResource` so super admins can create, edit, and delete roles and assign any
combination of those 90 permissions to each role without touching code.

---

## Decisions

These decisions were made at session start and govern implementation:

| Question | Decision |
|---|---|
| Permission UI layout | Grouped checkboxes by resource area (CRM, Finance, CMS, Admin) — not a flat list |
| `super_admin` protection | Fully locked — no edit, no delete |
| `cms_editor` protection | Editable (permissions can change) but not deletable |
| Role deletion behaviour | Hard-delete with a confirmation modal that shows the number of currently assigned users |
| Role assignment location | Stays in `UserResource` — no change to that flow |

---

## Implementation Plan

### 1. RoleResource — `app/Filament/Resources/RoleResource.php`

Create `app/Filament/Resources/RoleResource.php` with:

**Navigation**
- Navigation group: `Settings`
- Navigation sort: `2` (UserResource is sort 1)
- Navigation icon: `heroicon-o-shield-check`
- Navigation label: `Roles`

**canAccess()**
Override `canAccess()` so only super admins can see/use this resource:
```php
public static function canAccess(): bool
{
    return auth()->check() && auth()->user()->isSuperAdmin();
}
```

**Form schema**

The form has two sections:

*Section 1 — Role name*
- `TextInput::make('name')` — required, unique (ignoring current record on edit), max 255

*Section 2 — Permissions (grouped checkboxes)*

Build a `CheckboxList` per resource area. The 18 resources map to four areas:

| Area | Resources |
|---|---|
| CRM | contact, household, organization, membership, note, tag |
| Finance | donation, transaction, fund, campaign |
| CMS | post, page, collection, collection_item, cms_tag, navigation_item |
| Admin | user, widget_type |

For each area, render a `Section` containing a `CheckboxList::make('permissions')` that:
- Is keyed by area label (e.g. `permissions_crm`, `permissions_finance`, etc.) so each section is a
  separate form field — then merge on save.
- Actually, the cleanest approach: use a single `CheckboxList::make('permissions')` with all 90
  options, but render it inside a `Grid` with custom option grouping. Filament's `CheckboxList`
  supports `->options()` as a flat array and `->columns(5)` to lay the checkboxes side-by-side.

**Recommended implementation:** Use four separate `Section` components (one per area), each containing
a `CheckboxList` whose field name is `permissions_{area}` (e.g. `permissions_crm`). Then override
`mutateFormDataBeforeFill` and `mutateFormDataBeforeSave` to split/merge permissions.

```php
// mutateFormDataBeforeFill — split the role's current permissions into per-area arrays
protected function mutateFormDataBeforeFill(array $data): array
{
    $assigned = $this->record->permissions->pluck('name')->toArray();
    foreach (RoleResource::permissionAreas() as $area => $resources) {
        $data["permissions_{$area}"] = array_values(array_filter(
            $assigned,
            fn($p) => collect($resources)->contains(fn($r) => str_ends_with($p, "_{$r}"))
        ));
    }
    return $data;
}

// mutateFormDataBeforeSave — merge per-area arrays back into a flat permissions list
protected function mutateFormDataBeforeSave(array $data): array
{
    $permissions = [];
    foreach (array_keys(RoleResource::permissionAreas()) as $area) {
        $permissions = array_merge($permissions, $data["permissions_{$area}"] ?? []);
        unset($data["permissions_{$area}"]);
    }
    $data['_permissions'] = $permissions;
    return $data;
}
```

Then in `afterSave()` (on both Create and Edit pages), sync the permissions:
```php
protected function afterSave(): void
{
    $this->record->syncPermissions($this->data['_permissions'] ?? []);
}
```

`permissionAreas()` is a static helper on `RoleResource`:
```php
public static function permissionAreas(): array
{
    return [
        'crm'     => ['contact', 'household', 'organization', 'membership', 'note', 'tag'],
        'finance' => ['donation', 'transaction', 'fund', 'campaign'],
        'cms'     => ['post', 'page', 'collection', 'collection_item', 'cms_tag', 'navigation_item'],
        'admin'   => ['user', 'widget_type'],
    ];
}
```

Each `CheckboxList` options array is built from the resources in that area:
```php
$actions = ['view_any', 'view', 'create', 'update', 'delete'];
$options = [];
foreach ($resources as $resource) {
    foreach ($actions as $action) {
        $perm = "{$action}_{$resource}";
        $options[$perm] = str($perm)->replace('_', ' ')->title()->toString();
    }
}
```

Set `->columns(5)` on each `CheckboxList` so the five actions appear on one row per resource.

**Table columns**
- `TextColumn::make('name')` — sortable, searchable
- `TextColumn::make('users_count')` — label "Users", computed via `withCount('users')` on the query,
  using `->counts('users')` on the relationship (Spatie's Role model has a `users()` relation)
- `TextColumn::make('permissions_count')` — label "Permissions", via `->counts('permissions')`

**Table actions**
- Standard `EditAction` — disabled (greyed out, not hidden) for `super_admin` role
- `DeleteAction` — disabled for `super_admin` and `cms_editor` roles; for all other roles show a
  confirmation modal that includes the assigned user count in the description

Use `->disabled(fn($record) => in_array($record->name, ['super_admin', 'cms_editor']))` to gate edit.
Use `->disabled(fn($record) => in_array($record->name, ['super_admin', 'cms_editor']))` to gate delete.

**Bulk actions**
Exclude `DeleteBulkAction` entirely — role deletion is always one-at-a-time so the user reads the warning.

---

### 2. Pages

Create standard Filament pages under `app/Filament/Resources/RoleResource/Pages/`:
- `ListRoles.php`
- `CreateRole.php`
- `EditRole.php`

On `EditRole` and `CreateRole`, override `mutateFormDataBeforeFill`, `mutateFormDataBeforeSave`, and
`afterSave` as described above.

On `EditRole`, also override `canAccess()` at the page level to block editing `super_admin`:
```php
public function canAccess(array $parameters = []): bool  // Filament 3 page-level guard
{
    $record = $parameters['record'] ?? $this->record;
    if ($record && $record->name === 'super_admin') {
        return false;
    }
    return parent::canAccess($parameters);
}
```

---

### 3. Flush permission cache after sync

After `syncPermissions()`, clear the Spatie cache:
```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

Add this to `afterSave()` on both Create and Edit pages.

---

### 4. Migration — none required

Spatie's tables already exist. No new migrations are needed.

---

### 5. Tests — `tests/Feature/RoleResourceTest.php`

Write a Pest feature test file with the following cases:

```
it('super admin can view the roles list')
it('super admin can create a role with permissions')
it('super admin can edit a role and sync permissions')
it('super admin cannot edit the super_admin role')
it('super admin cannot delete the super_admin role')
it('super admin cannot delete the cms_editor role')
it('super admin can delete a custom role')
it('cms_editor cannot access the roles list')
```

Use Filament's Livewire testing helpers (`livewire(ListRoles::class)`, `livewire(CreateRole::class)`,
etc.) with authenticated users created via `User::factory()` and roles assigned via `$user->assignRole()`.

---

## Acceptance Criteria

- [ ] `RoleResource` appears in Settings navigation for super_admin only
- [ ] Permissions are displayed in four grouped sections (CRM, Finance, CMS, Admin) with 5 checkboxes per row
- [ ] Creating a role and assigning permissions persists correctly (verify via tinker: `Role::findByName('x')->permissions`)
- [ ] Editing a role correctly pre-fills the checkboxes with current permissions
- [ ] `super_admin` row in the list has Edit and Delete disabled
- [ ] `cms_editor` row has Delete disabled but Edit enabled
- [ ] Deleting a custom role shows a confirmation modal
- [ ] A user with only `cms_editor` role cannot navigate to `/admin/roles`
- [ ] All 8 Pest tests pass
- [ ] `php artisan test` passes with no failures
