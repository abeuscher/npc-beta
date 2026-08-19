<?php

use App\Services\Setup\SetupChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Plugins\DonationsRemovedTestCase;

uses(DonationsRemovedTestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

// Session 390 (Plugin Architecture arc D3): the squash-boundary redraw —
// removed-mirror side. With the Donations line stripped, the setup checklist
// skips its fund row entirely (the composition-safety gate on the funds admin
// route — decision 6; the funds table is plugin-owned and core must not
// assume it), while the fixture's migrator path keeps the schema present for
// the data-kept assertions (disabled ≠ uninstalled; a never-installed
// composition is proven by the fresh-install identity check, not here).

it('skips the default-fund checklist row when the funds admin surface is absent', function () {
    $items = collect(app(SetupChecklist::class)->items());

    expect($items->firstWhere('key', 'default_fund'))->toBeNull()
        ->and($items->firstWhere('key', 'admin_user'))->not->toBeNull();
});

it('keeps the four donation tables through the fixture migrator path', function () {
    foreach (['funds', 'donations', 'donation_credits', 'donation_receipts'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist — disabled ≠ uninstalled");
    }
});
