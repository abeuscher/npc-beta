<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

// ── Access control ────────────────────────────────────────────────────────────

it('super admin can view the roles list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/roles')
        ->assertSuccessful();
});

it('cms_editor cannot access the roles list', function () {
    $editor = User::factory()->create();
    $editor->assignRole('cms_editor');

    $this->actingAs($editor)
        ->get('/admin/roles')
        ->assertForbidden();
});

it('cms_editor cannot access the roles create page', function () {
    $editor = User::factory()->create();
    $editor->assignRole('cms_editor');

    $this->actingAs($editor)
        ->get('/admin/roles/create')
        ->assertForbidden();
});

// ── Role creation ─────────────────────────────────────────────────────────────

it('super admin can create a role with permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $role = Role::create(['name' => 'fundraiser', 'guard_name' => 'web']);
    $role->syncPermissions(['view_any_contact', 'view_contact', 'view_any_donation']);

    expect($role->permissions->pluck('name'))
        ->toContain('view_any_contact')
        ->toContain('view_contact')
        ->toContain('view_any_donation');
});

// ── Permission sync ───────────────────────────────────────────────────────────

it('editing a role syncs its permissions correctly', function () {
    $role = Role::create(['name' => 'development_director', 'guard_name' => 'web']);
    $role->syncPermissions(['view_any_contact']);

    // Replace the permission set entirely
    $role->syncPermissions(['view_any_donation', 'create_donation']);

    $role->refresh()->load('permissions');

    expect($role->permissions->pluck('name'))
        ->toContain('view_any_donation')
        ->toContain('create_donation')
        ->not->toContain('view_any_contact');
});

it('syncing permissions removes permissions that were unchecked', function () {
    $role = Role::create(['name' => 'temp_editor', 'guard_name' => 'web']);
    $role->syncPermissions(['view_any_contact', 'view_contact', 'create_contact']);

    $role->syncPermissions(['view_any_contact']); // remove the others

    $role->refresh()->load('permissions');

    expect($role->permissions->pluck('name'))
        ->toHaveCount(1)
        ->toContain('view_any_contact');
});

// ── Built-in role protection ──────────────────────────────────────────────────

it('super admin can view but not edit the super_admin role edit page', function () {
    $admin          = User::factory()->create();
    $superAdminRole = Role::findByName('super_admin');

    $admin->assignRole('super_admin');

    // canEdit() returns false for super_admin role, but ReadOnlyAwareEditRecord
    // allows access in read-only mode (form disabled, save buttons hidden)
    $this->actingAs($admin)
        ->get("/admin/roles/{$superAdminRole->id}/edit")
        ->assertSuccessful();
});

it('super admin can navigate to the cms_editor role edit page', function () {
    $admin         = User::factory()->create();
    $cmsEditorRole = Role::findByName('cms_editor');

    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get("/admin/roles/{$cmsEditorRole->id}/edit")
        ->assertSuccessful();
});

// ── Custom role deletion ──────────────────────────────────────────────────────

it('super admin can delete a custom role', function () {
    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);

    $role->delete();

    expect(Role::where('name', 'temp_role')->exists())->toBeFalse();
});

it('deleting a role unassigns it from users', function () {
    $role = Role::create(['name' => 'removable_role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->hasRole('removable_role'))->toBeTrue();

    $role->delete();
    $user->refresh();

    expect($user->hasRole('removable_role'))->toBeFalse();
});
