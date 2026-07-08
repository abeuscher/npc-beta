<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Auth is mTLS at the TLS layer (nginx) — the application sees no auth signal;
// request arrival IS the auth proof. As with the other Fleet endpoints there are
// no application-layer auth tests here; the mTLS gate is verified out-of-band.

function enrolledTarget(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->forceFill([
        'two_factor_secret'         => 'fake-secret',
        'two_factor_recovery_codes' => 'fake-codes',
        'two_factor_confirmed_at'   => now(),
    ])->save();

    return $user->refresh();
}

it('returns the documented success envelope and resets the target', function () {
    $admin = enrolledTarget();

    $response = $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['reset_2fa', 'reset_password'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('contract_version', '2.6.0')
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('email', $admin->email)
        ->assertJsonPath('actions_applied', ['reset_2fa', 'reset_password'])
        ->assertJsonPath('message', null);

    expect($response->json('temporary_password'))->toBeString()->not->toBeEmpty()
        ->and($response->json('recovered_at'))->toBeString();

    $admin->refresh();
    expect($admin->hasConfirmedTwoFactor())->toBeFalse()
        ->and(Hash::check($response->json('temporary_password'), $admin->password))->toBeTrue();
});

it('shapes the success envelope with exactly the documented keys', function () {
    $admin = enrolledTarget();

    $response = $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['reset_2fa'],
    ]);

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'contract_version', 'status', 'email', 'actions_applied',
        'temporary_password', 'recovered_at', 'message',
    ]);
});

it('omits the temporary password when only reset_2fa is requested', function () {
    $admin = enrolledTarget();

    $response = $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['reset_2fa'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('actions_applied', ['reset_2fa'])
        ->assertJsonPath('temporary_password', null);

    expect($admin->refresh()->hasConfirmedTwoFactor())->toBeFalse();
});

it('can recover the protected super-admin', function () {
    $protected = enrolledTarget();
    expect($protected->isProtected())->toBeTrue();

    $this->postJson('/api/admin/recover', [
        'email'   => $protected->email,
        'actions' => ['reset_2fa'],
    ])->assertStatus(200)->assertJsonPath('status', 'success');

    expect($protected->refresh()->hasConfirmedTwoFactor())->toBeFalse();
});

it('returns a 200 failed envelope when no admin matches the email', function () {
    $response = $this->postJson('/api/admin/recover', [
        'email'   => 'nobody@example.org',
        'actions' => ['reset_2fa'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('email', 'nobody@example.org')
        ->assertJsonPath('actions_applied', [])
        ->assertJsonPath('temporary_password', null)
        ->assertJsonPath('recovered_at', null)
        ->assertJsonPath('message', 'no admin found for that email');
});

it('returns a 200 failed envelope for a malformed request and changes nothing', function () {
    $admin = enrolledTarget();

    $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => [],
    ])->assertStatus(200)->assertJsonPath('status', 'failed');

    $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['delete_user'],
    ])->assertStatus(200)->assertJsonPath('status', 'failed');

    expect($admin->refresh()->hasConfirmedTwoFactor())->toBeTrue();
});

it('audits the recovery with endpoint as the path', function () {
    $admin = enrolledTarget();

    $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['reset_2fa'],
    ])->assertStatus(200);

    $log = ActivityLog::query()
        ->where('event', 'admin_recovery')
        ->where('subject_id', (string) $admin->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['path'])->toBe('endpoint');
});

it('registers the recover route behind throttle:6,1', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/admin/recover' && in_array('POST', $r->methods(), true));

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:6,1');
});
