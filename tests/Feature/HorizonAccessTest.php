<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

it('returns 403 when horizon is disabled', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});

it('returns 403 for non-super-admin even when enabled', function () {
    SiteSetting::set('horizon_enabled', 'true');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});

it('allows super admin access when horizon is enabled', function () {
    SiteSetting::set('horizon_enabled', 'true');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get('/horizon')
        ->assertOk();
});

it('returns 403 for unauthenticated users', function () {
    SiteSetting::set('horizon_enabled', 'true');

    $this->get('/horizon')
        ->assertForbidden();
});
