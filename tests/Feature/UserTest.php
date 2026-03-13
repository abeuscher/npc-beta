<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('creates a user with is_active defaulting to true', function () {
    $user = User::factory()->create();

    expect($user->is_active)->toBeTrue();
});

it('casts is_active as boolean', function () {
    $active = User::factory()->create(['is_active' => true]);
    $inactive = User::factory()->create(['is_active' => false]);

    expect($active->is_active)->toBeTrue()
        ->and($inactive->is_active)->toBeFalse();
});

it('returns true for isSuperAdmin when user has super_admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect($user->isSuperAdmin())->toBeTrue();
});

it('returns false for isSuperAdmin when user has no roles', function () {
    $user = User::factory()->create();

    expect($user->isSuperAdmin())->toBeFalse();
});

it('returns false for isSuperAdmin when user has staff role only', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect($user->isSuperAdmin())->toBeFalse();
});

it('allows authentication via the web guard', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user, 'web');

    expect(auth('web')->check())->toBeTrue()
        ->and(auth('web')->id())->toBe($user->id);
});
