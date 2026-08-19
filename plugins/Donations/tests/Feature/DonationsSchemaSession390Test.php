<?php

use App\Services\Setup\SetupChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

// Session 390 (Plugin Architecture arc D3): the squash-boundary redraw —
// enabled-twin side. The four donation tables are plugin-owned (created by
// plugins/Donations/database/migrations via the provider's loadMigrationsFrom,
// contract surface 5), and the setup checklist's fund row renders because the
// funds admin route exists.

it('creates the four plugin-owned donation tables through the plugin migrations', function () {
    foreach (['funds', 'donations', 'donation_credits', 'donation_receipts'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist on the full composition");
    }
});

it('registers the plugin migration path on the migrator', function () {
    expect(app('migrator')->paths())->toContain(base_path('plugins/Donations/database/migrations'));
});

it('renders the default-fund checklist row with its funds link', function () {
    $items = collect(app(SetupChecklist::class)->items());
    $row   = $items->firstWhere('key', 'default_fund');

    expect($row)->not->toBeNull()
        ->and($row['configure_url'])->toBe(route('filament.admin.resources.funds.index'));
});
