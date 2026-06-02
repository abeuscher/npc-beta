<?php

use App\Models\Contact;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Standing guard for the demo-node restore primitive.
 *
 * demo:restore wipes and replaces the whole database from a pushed blob. Its
 * load-bearing safety property is the isDemoMode() hard-gate: a
 * restore-from-arbitrary-blob command must never be runnable on a real customer
 * node. These tests assert the command returns *before* any wipe whenever the
 * gate or its blob precondition is not met — if one starts passing, the demo
 * restore just gained the ability to wipe a production database and this guard
 * must be re-reviewed deliberately.
 */
it('refuses to run when the install is not in demo mode, wiping nothing', function () {
    expect(isDemoMode())->toBeFalse();

    // A pre-existing row must survive — the gate returns before any db:wipe.
    Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $this->artisan('demo:restore')->assertExitCode(1);

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(1);
});

it('refuses to run in demo mode when no baseline blob is present, wiping nothing', function () {
    app()->instance('env', 'demo');
    expect(isDemoMode())->toBeTrue();

    Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $missing = storage_path('app/backup-temp/does-not-exist-'.uniqid().'.zip');
    $this->artisan('demo:restore', ['blob' => $missing])->assertExitCode(1);

    expect(Contact::where('source', Source::SCRUB_DATA)->count())->toBe(1);
});
