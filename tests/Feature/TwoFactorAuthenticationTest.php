<?php

use App\Filament\Pages\TwoFactorChallenge;
use App\Filament\Pages\TwoFactorSetup;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    // The testing-env opt-in is static state — reset it so an enabling test
    // never leaks the active gate into a later, unrelated test in the process.
    EnsureTwoFactorAuthenticated::disableInTesting();
});

/** Compute the current valid TOTP for a user's stored (encrypted) secret. */
function currentOtp(User $user): string
{
    return (new Google2FA)->getCurrentOtp(Crypt::decrypt($user->two_factor_secret));
}

/** Fully enroll a user: generate a secret + recovery codes, then confirm it. */
function enrollUser(User $user): void
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $user->refresh();
}

// ── Test-context posture ─────────────────────────────────────────────────────

it('bypasses the gate in the test environment by default so existing admin tests stay green', function () {
    // No enableInTesting() — the default posture. An un-enrolled admin reaches
    // the panel with no enrollment redirect, exactly as the rest of the suite
    // (which never touches 2FA) relies on.
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertSuccessful();
});

// ── Enforcement gate ─────────────────────────────────────────────────────────

it('redirects an un-enrolled admin to the enrollment page', function () {
    EnsureTwoFactorAuthenticated::enableInTesting();
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertRedirect(TwoFactorSetup::getUrl());
});

it('redirects an enrolled admin whose session has not cleared the challenge', function () {
    EnsureTwoFactorAuthenticated::enableInTesting();
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);

    $this->get('/admin')->assertRedirect(TwoFactorChallenge::getUrl());
});

it('lets an enrolled, session-cleared admin reach the panel', function () {
    EnsureTwoFactorAuthenticated::enableInTesting();
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);
    session()->put(EnsureTwoFactorAuthenticated::SESSION_KEY, true);

    $this->get('/admin')->assertSuccessful();
});

// ── Enrollment ───────────────────────────────────────────────────────────────

it('confirms enrollment with a valid code and sets two_factor_confirmed_at', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(TwoFactorSetup::class);

    // mount() generated the pending secret; compute its live code and confirm.
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();

    $component->set('data.code', currentOtp($user))
        ->call('confirm')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    expect(session(EnsureTwoFactorAuthenticated::SESSION_KEY))->toBeTrue();
});

it('rejects enrollment confirmation with an invalid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(TwoFactorSetup::class)
        ->set('data.code', '000000')
        ->call('confirm')
        ->assertHasErrors('data.code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

// ── Enrollment QR labelling ──────────────────────────────────────────────────

it('labels the enrollment QR with the configured brand name and the user email', function () {
    \App\Models\SiteSetting::set('admin_brand_name', 'Acme Foundation');
    $user = User::factory()->create(['email' => 'dana@acme.org']);
    app(EnableTwoFactorAuthentication::class)($user);

    $url = $user->twoFactorQrCodeUrl();

    expect($url)->toContain('issuer=Acme%20Foundation')
        ->and($url)->toContain('dana%40acme.org');
});

// ── Challenge ────────────────────────────────────────────────────────────────

it('passes the challenge with a valid TOTP code', function () {
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);

    Livewire::test(TwoFactorChallenge::class)
        ->set('data.code', currentOtp($user))
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(session(EnsureTwoFactorAuthenticated::SESSION_KEY))->toBeTrue();
});

it('rejects the challenge with an invalid code', function () {
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);

    Livewire::test(TwoFactorChallenge::class)
        ->set('data.code', '000000')
        ->call('authenticate')
        ->assertHasErrors('data.code');

    expect(session(EnsureTwoFactorAuthenticated::SESSION_KEY))->toBeNull();
});

it('passes the challenge with a recovery code and consumes it (single-use)', function () {
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);

    $recoveryCode = $user->recoveryCodes()[0];

    Livewire::test(TwoFactorChallenge::class)
        ->set('data.code', $recoveryCode)
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(session(EnsureTwoFactorAuthenticated::SESSION_KEY))->toBeTrue();

    // The used code is replaced, so it no longer appears in the recovery set.
    expect($user->fresh()->recoveryCodes())->not->toContain($recoveryCode);
});

it('rejects a recovery code that was already consumed', function () {
    $user = User::factory()->create();
    enrollUser($user);
    $this->actingAs($user);

    $recoveryCode = $user->recoveryCodes()[0];

    Livewire::test(TwoFactorChallenge::class)
        ->set('data.code', $recoveryCode)
        ->call('authenticate')
        ->assertHasNoErrors();

    // A fresh session attempt with the same (now-consumed) code must fail.
    session()->forget(EnsureTwoFactorAuthenticated::SESSION_KEY);

    Livewire::test(TwoFactorChallenge::class)
        ->set('data.code', $recoveryCode)
        ->call('authenticate')
        ->assertHasErrors('data.code');
});

// ── Demo-mode exemption (hard requirement) ──────────────────────────────────

it('exempts demo mode entirely — /demo/enter reaches the panel with no enrollment and no 2FA state', function () {
    $this->app->detectEnvironment(fn () => 'demo');
    Role::firstOrCreate(['name' => 'demo', 'guard_name' => 'web']);

    // Single-button auto-login redirects to the panel...
    $this->get('/demo/enter')->assertRedirect();

    // ...and the panel itself is reachable with no enrollment/challenge redirect.
    $this->followingRedirects()->get('/demo/enter')->assertSuccessful();

    $demo = User::where('email', 'demo@demo.local')->first();
    expect($demo)->not->toBeNull();
    expect($demo->two_factor_secret)->toBeNull();
    expect($demo->two_factor_confirmed_at)->toBeNull();
});

// ── Fleet Manager API path is untouched ─────────────────────────────────────

it('never gates the Fleet Manager API path, even with the gate active', function () {
    // Gate active and no authenticated user: the stateless /api/* endpoints must
    // still answer, never redirect to login or the 2FA flow.
    EnsureTwoFactorAuthenticated::enableInTesting();

    $this->getJson('/api/health')->assertStatus(200);
});
