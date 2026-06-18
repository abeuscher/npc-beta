<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function enrolledFor(string $email): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->forceFill([
        'two_factor_secret'         => 'fake-secret',
        'two_factor_recovery_codes' => 'fake-codes',
        'two_factor_confirmed_at'   => now(),
    ])->save();

    return $user->refresh();
}

it('refuses to run in demo mode, resetting nothing', function () {
    app()->instance('env', 'demo');
    expect(isDemoMode())->toBeTrue();

    $admin = enrolledFor('locked@example.org');

    $this->artisan('admin:recover', ['email' => $admin->email, '--reset-2fa' => true])
        ->assertExitCode(1);

    expect($admin->refresh()->hasConfirmedTwoFactor())->toBeTrue();
    expect(ActivityLog::where('event', 'admin_recovery')->count())->toBe(0);
});

it('errors when no action flag is passed', function () {
    $admin = User::factory()->create();

    $this->artisan('admin:recover', ['email' => $admin->email])
        ->assertExitCode(1);
});

it('errors when no user matches the email', function () {
    $this->artisan('admin:recover', ['email' => 'nobody@example.org', '--reset-2fa' => true])
        ->assertExitCode(1);
});

it('clears two-factor and audits when run with --reset-2fa', function () {
    $admin = enrolledFor('locked@example.org');

    $this->artisan('admin:recover', ['email' => $admin->email, '--reset-2fa' => true])
        ->assertExitCode(0);

    expect($admin->refresh()->hasConfirmedTwoFactor())->toBeFalse();

    $log = ActivityLog::query()
        ->where('event', 'admin_recovery')
        ->where('subject_id', (string) $admin->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['path'])->toBe('cli');
});

it('sets a temporary password when run with --reset-password', function () {
    $admin = User::factory()->create(); // factory password is 'password'

    $this->artisan('admin:recover', ['email' => $admin->email, '--reset-password' => true])
        ->assertExitCode(0);

    expect(Hash::check('password', $admin->refresh()->password))->toBeFalse();
});
