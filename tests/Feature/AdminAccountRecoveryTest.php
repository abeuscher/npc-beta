<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AdminAccountRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function enrolledAdmin(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    // Mark the user as 2FA-enrolled. The columns are guarded (set by Fortify,
    // not mass-assignable), so force-fill arbitrary non-null values — the reset
    // only needs them non-null before and null after.
    $user->forceFill([
        'two_factor_secret'         => 'fake-encrypted-secret',
        'two_factor_recovery_codes' => 'fake-encrypted-codes',
        'two_factor_confirmed_at'   => now(),
    ])->save();

    return $user->refresh();
}

it('clears two-factor enrollment on reset_2fa', function () {
    $admin = enrolledAdmin();
    expect($admin->hasConfirmedTwoFactor())->toBeTrue();

    $result = app(AdminAccountRecovery::class)->recover(
        $admin,
        [AdminAccountRecovery::ACTION_RESET_2FA],
        AdminAccountRecovery::PATH_CLI,
    );

    $admin->refresh();

    expect($admin->two_factor_secret)->toBeNull()
        ->and($admin->two_factor_recovery_codes)->toBeNull()
        ->and($admin->two_factor_confirmed_at)->toBeNull()
        ->and($admin->hasConfirmedTwoFactor())->toBeFalse()
        ->and($result->actionsApplied)->toBe([AdminAccountRecovery::ACTION_RESET_2FA])
        ->and($result->temporaryPassword)->toBeNull();
});

it('can reset the protected super-admin — the likeliest lockout victim', function () {
    $protected = enrolledAdmin();
    expect($protected->isProtected())->toBeTrue();

    app(AdminAccountRecovery::class)->recover(
        $protected,
        [AdminAccountRecovery::ACTION_RESET_2FA],
        AdminAccountRecovery::PATH_ENDPOINT,
    );

    expect($protected->refresh()->hasConfirmedTwoFactor())->toBeFalse();
});

it('sets a fresh temporary password on reset_password and returns it once', function () {
    $admin = User::factory()->create(); // factory password is 'password'
    expect(Hash::check('password', $admin->password))->toBeTrue();

    $result = app(AdminAccountRecovery::class)->recover(
        $admin,
        [AdminAccountRecovery::ACTION_RESET_PASSWORD],
        AdminAccountRecovery::PATH_CLI,
    );

    expect($result->temporaryPassword)->toBeString()->not->toBeEmpty();

    $admin->refresh();
    expect(Hash::check('password', $admin->password))->toBeFalse()
        ->and(Hash::check($result->temporaryPassword, $admin->password))->toBeTrue();
});

it('applies both actions when both are requested', function () {
    $admin = enrolledAdmin();

    $result = app(AdminAccountRecovery::class)->recover(
        $admin,
        [AdminAccountRecovery::ACTION_RESET_2FA, AdminAccountRecovery::ACTION_RESET_PASSWORD],
        AdminAccountRecovery::PATH_ENDPOINT,
    );

    $admin->refresh();

    expect($admin->hasConfirmedTwoFactor())->toBeFalse()
        ->and(Hash::check($result->temporaryPassword, $admin->password))->toBeTrue()
        ->and($result->actionsApplied)->toBe([
            AdminAccountRecovery::ACTION_RESET_2FA,
            AdminAccountRecovery::ACTION_RESET_PASSWORD,
        ]);
});

it('audits every recovery to the activity log with the target, actions, and path', function () {
    $admin = enrolledAdmin();

    app(AdminAccountRecovery::class)->recover(
        $admin,
        [AdminAccountRecovery::ACTION_RESET_2FA],
        AdminAccountRecovery::PATH_ENDPOINT,
    );

    $log = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', (string) $admin->id)
        ->where('event', 'admin_recovery')
        ->first();

    expect($log)->not->toBeNull()
        // No app-layer identity under the mTLS endpoint or the break-glass CLI.
        ->and($log->actor_type)->toBe('system')
        ->and($log->meta['actions'])->toBe([AdminAccountRecovery::ACTION_RESET_2FA])
        ->and($log->meta['path'])->toBe(AdminAccountRecovery::PATH_ENDPOINT);
});

it('rejects an empty or unrecognised action set', function () {
    $admin = User::factory()->create();

    expect(fn () => app(AdminAccountRecovery::class)->recover($admin, [], AdminAccountRecovery::PATH_CLI))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(AdminAccountRecovery::class)->recover($admin, ['delete_user'], AdminAccountRecovery::PATH_CLI))
        ->toThrow(InvalidArgumentException::class);
});
