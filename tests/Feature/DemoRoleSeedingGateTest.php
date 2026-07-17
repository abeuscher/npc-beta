<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Demo-role seeding gate (session 370, Security S1). The `demo` role — a broad
 * product-feel allow-list bound to the public shared demo account — is only
 * seeded where it can be legitimately used (demo mode). A production node carries
 * no vestigial demo role.
 */

it('does not seed the demo role on a non-demo install', function () {
    expect(isDemoMode())->toBeFalse();

    (new \Database\Seeders\PermissionSeeder())->run();

    expect(Role::where('name', 'demo')->exists())->toBeFalse();

    // The real staff roles still seed normally.
    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
    expect(Role::where('name', 'crm_editor')->exists())->toBeTrue();
    expect(Role::where('name', 'cms_editor')->exists())->toBeTrue();
});

it('seeds the demo role under demo mode', function () {
    app()->instance('env', 'demo');
    expect(isDemoMode())->toBeTrue();

    (new \Database\Seeders\PermissionSeeder())->run();

    $demo = Role::where('name', 'demo')->first();
    expect($demo)->not->toBeNull();
    // The role keeps its product-feel allow-list.
    expect($demo->hasPermissionTo('create_contact'))->toBeTrue();
});

it('is idempotent under demo mode — re-running keeps a single demo role', function () {
    app()->instance('env', 'demo');

    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();

    expect(Role::where('name', 'demo')->count())->toBe(1);
});
