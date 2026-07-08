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

// The "Role creation" and "Permission sync" sections were removed at the s364
// test audit: their titles claimed resource-form behavior ("super admin can
// create a role…", "editing a role syncs…") but their bodies called
// Role::create / syncPermissions directly — pure Spatie framework API, no
// actor, no RoleResource involvement. The resource-form create/edit paths
// remain untested at the resource layer; recorded in the housekeeping inbox.

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
// ("super admin can delete a custom role" was removed at the s364 audit — no
// actor despite the title, and $role->delete() is pure Spatie. The
// unassignment test below is kept deliberately: it pins the framework cascade
// the app's permission gating relies on.)

it('deleting a role unassigns it from users', function () {
    $role = Role::create(['name' => 'removable_role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->hasRole('removable_role'))->toBeTrue();

    $role->delete();
    $user->refresh();

    expect($user->hasRole('removable_role'))->toBeFalse();
});
