<?php

use App\Models\Form;
use App\Services\PageBuilderDataSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Plugins\FormsRemovedTestCase;

uses(FormsRemovedTestCase::class, RefreshDatabase::class);

// Session 397 (Plugin Architecture arc D7): the schema removal mirror.
// Disabled ≠ uninstalled (contract surface 5): the fixture registers the
// plugin's migration path directly, so the two form tables exist and data is
// kept — while the route-presence-gated core reads prove they gate against
// LIVE rows, not an empty table.

it('keeps both form tables via the fixture migrator path', function () {
    expect(Schema::hasTable('forms'))->toBeTrue()
        ->and(Schema::hasTable('form_submissions'))->toBeTrue();
});

it('returns an empty forms picker even with an active form row present — the gate, not an empty table', function () {
    Form::create([
        'title'     => 'Live Row',
        'handle'    => 'live-row',
        'fields'    => [],
        'settings'  => [],
        'is_active' => true,
    ]);

    expect(PageBuilderDataSources::resolve('forms'))->toBe([]);
});

it('resolves a null PageContext form lookup even with a live row present', function () {
    Form::create([
        'title'     => 'Live Row',
        'handle'    => 'live-row',
        'fields'    => [],
        'settings'  => [],
        'is_active' => true,
    ]);

    expect((new \App\Services\PageContext())->form('live-row'))->toBeNull();
});
