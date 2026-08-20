<?php

use App\Models\MembershipTier;
use App\Models\User;
use Database\Seeders\SystemPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 393 (Plugin Architecture arc D4): the squash-boundary redraw —
// enabled-twin side. The two membership tables are plugin-owned (created by
// the plugin's database/migrations via the provider's loadMigrationsFrom,
// contract surface 5), and the seeded portal signup page renders its tier
// picker because the membership.checkout route exists.

it('creates the two plugin-owned membership tables through the plugin migrations', function () {
    foreach (['membership_tiers', 'memberships'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist on the full composition");
    }
});

it('registers the plugin migration path on the migrator', function () {
    expect(app('migrator')->paths())->toContain(base_path('plugins/Memberships/database/migrations'));
});

it('renders the seeded signup page with the tier picker on the full composition', function () {
    User::factory()->create();
    $this->seed(SystemPageSeeder::class);
    MembershipTier::factory()->create(['is_active' => true, 'is_archived' => false]);

    $this->get('/signup')
        ->assertOk()
        ->assertSee('Membership tier')
        // The checkout URL lands in the @json signup-config block, so it
        // appears with JSON-escaped slashes.
        ->assertSee(json_encode(route('membership.checkout')), false);
});
