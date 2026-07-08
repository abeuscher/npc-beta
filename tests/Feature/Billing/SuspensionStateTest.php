<?php

use App\Services\Billing\SuspensionState;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('resolves each recognized flag value to its case', function () {
    expect(SuspensionState::resolve('none'))->toBe(SuspensionState::None)
        ->and(SuspensionState::resolve('admin_locked'))->toBe(SuspensionState::AdminLocked)
        ->and(SuspensionState::resolve('site_off'))->toBe(SuspensionState::SiteOff);
});

it('resolves an absent (null) flag to none — every existing install is unaffected', function () {
    Log::spy();

    expect(SuspensionState::resolve(null))->toBe(SuspensionState::None);

    Log::shouldNotHaveReceived('warning');
});

it('resolves an empty / whitespace flag to none without logging', function () {
    Log::spy();

    expect(SuspensionState::resolve(''))->toBe(SuspensionState::None)
        ->and(SuspensionState::resolve('   '))->toBe(SuspensionState::None);

    Log::shouldNotHaveReceived('warning');
});

it('fails an unrecognized flag value safe to none and logs a warning', function () {
    Log::spy();

    // A typo in a pushed key must never brick a paying client's admin — fail open
    // toward access, but surface the misconfiguration so the operator notices.
    expect(SuspensionState::resolve('admin_lock'))->toBe(SuspensionState::None);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Unrecognized SUSPENSION_STATE'));
});

it('reads the enforced state from config via current()', function () {
    config(['fleet.suspension.state' => 'site_off']);
    expect(SuspensionState::current())->toBe(SuspensionState::SiteOff);

    config(['fleet.suspension.state' => 'none']);
    expect(SuspensionState::current())->toBe(SuspensionState::None);
});
