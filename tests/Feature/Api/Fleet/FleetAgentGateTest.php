<?php

use App\Http\Middleware\VerifyFleetAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| App-layer second gate on the FM /api/* endpoints (contract v2.7.0, S2)
|--------------------------------------------------------------------------
|
| The gate (VerifyFleetAgent) is the independent second lock behind the nginx
| mTLS termination. Pest never involves nginx, so a request that reaches the
| application here is exactly the "nginx mTLS bypassed / not present" case the
| gate exists to backstop — these tests therefore prove the app-layer lock
| holds on its own, independent of the TLS layer.
|
| Worst-first: /api/backup/blob (whole donor DB) and /api/admin/recover (admin
| credential reset) are proven first, then the pattern across all five.
|
| The gate's secret comes from config('fleet.gate.secret') (env FLEET_GATE_SECRET).
| Absent/empty = inert (mTLS the only lock, exactly v2.6.0). In the test env the
| key is unset by default, so every OTHER Fleet endpoint test runs gate-inert and
| is unaffected by this session.
*/

const GATE_SECRET = 'test-gate-secret-value';

function enrollGate(): void
{
    config(['fleet.gate.secret' => GATE_SECRET]);
}

// ── Worst-first: reject missing credential on the two worst-blast-radius routes ──

it('rejects /api/backup/blob with a 401 envelope when the gate is enforced and no credential is sent', function () {
    enrollGate();

    $response = $this->getJson('/api/backup/blob');

    $response->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized')
        ->assertJsonPath('message', 'missing or invalid Fleet Manager gate credential');
});

it('rejects /api/admin/recover with a 401 envelope when the gate is enforced and no credential is sent', function () {
    enrollGate();

    // A well-formed recover body that WOULD succeed if it reached the controller —
    // proving the gate short-circuits before any recovery logic runs.
    $admin = User::factory()->create();

    $response = $this->postJson('/api/admin/recover', [
        'email'   => $admin->email,
        'actions' => ['reset_2fa'],
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized');
});

it('rejects the worst-first routes when the credential is present but wrong', function () {
    enrollGate();

    $this->withHeader(VerifyFleetAgent::HEADER, 'the-wrong-secret')
        ->getJson('/api/backup/blob')
        ->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized');

    $this->withHeader(VerifyFleetAgent::HEADER, 'the-wrong-secret')
        ->postJson('/api/admin/recover', ['email' => 'a@b.co', 'actions' => ['reset_2fa']])
        ->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized');
});

// ── Accept the valid credential — the gate lets a correctly-credentialed FM through ──

it('lets /api/backup/blob through to the controller with a valid credential', function () {
    enrollGate();

    // A request that PASSES the gate reaches the controller and gets the
    // controller's own outcome — a streamed blob (200), a 404 no_backup_available,
    // or a 500 config envelope — but never the gate's 401. Assert on the status
    // code only, since a successful blob is a StreamedResponse with no JSON body.
    $response = $this->withHeader(VerifyFleetAgent::HEADER, GATE_SECRET)
        ->getJson('/api/backup/blob');

    expect($response->getStatusCode())->not->toBe(401);
});

it('lets /api/admin/recover through to the controller with a valid credential', function () {
    enrollGate();

    // Valid credential + a target that does not exist → the controller runs and
    // returns its documented 200 "failed / no admin found" envelope. Proves the
    // gate passed control to the controller.
    $response = $this->withHeader(VerifyFleetAgent::HEADER, GATE_SECRET)
        ->postJson('/api/admin/recover', [
            'email'   => 'nobody@example.org',
            'actions' => ['reset_2fa'],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'no admin found for that email');
});

it('performs a real recovery when the gate passes a valid credential end to end', function () {
    enrollGate();

    $admin = User::factory()->create();
    $admin->forceFill([
        'two_factor_secret'         => 'fake-secret',
        'two_factor_recovery_codes' => 'fake-codes',
        'two_factor_confirmed_at'   => now(),
    ])->save();

    $response = $this->withHeader(VerifyFleetAgent::HEADER, GATE_SECRET)
        ->postJson('/api/admin/recover', [
            'email'   => $admin->email,
            'actions' => ['reset_2fa', 'reset_password'],
        ]);

    $response->assertStatus(200)->assertJsonPath('status', 'success');

    $admin->refresh();
    expect($admin->hasConfirmedTwoFactor())->toBeFalse()
        ->and(Hash::check($response->json('temporary_password'), $admin->password))->toBeTrue();
});

// ── Independence from the mTLS layer (the whole point of S2) ──

it('holds the app gate even though the mTLS layer is absent — arrival is not proof of auth when enforced', function () {
    enrollGate();

    // This request reaches PHP with no client cert and no nginx in the loop — the
    // exact "nginx if bypassed / dropped" scenario. The app gate still rejects it.
    $this->getJson('/api/backup/blob')
        ->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized');
});

// ── No-flag-day: inert until a secret is provisioned (backward compat with v2.6.0) ──

it('is inert when no secret is configured — a request with no credential reaches the controller', function () {
    config(['fleet.gate.secret' => null]);

    // /api/health returns its 200 envelope with no credential when the gate is
    // inert — exactly the v2.6.0 behaviour, so a v2.7.0 image is a no-op until FM
    // provisions a secret.
    $this->getJson('/api/health')
        ->assertStatus(200)
        ->assertJsonPath('contract_version', '2.7.0');
});

it('treats an empty-string secret as unset (inert)', function () {
    config(['fleet.gate.secret' => '']);

    $this->getJson('/api/health')->assertStatus(200);
});

// ── The gate wraps the whole group, not a subset ──

it('rejects every one of the five fleet endpoints when enforced and uncredentialed', function (string $method, string $uri) {
    enrollGate();

    $this->json(strtoupper($method), $uri)
        ->assertStatus(401)
        ->assertJsonPath('error', 'fleet_gate_unauthorized');
})->with([
    'health'         => ['get', '/api/health'],
    'logs'           => ['get', '/api/logs'],
    'backup trigger' => ['post', '/api/backup/trigger'],
    'backup blob'    => ['get', '/api/backup/blob'],
    'admin recover'  => ['post', '/api/admin/recover'],
]);

it('registers VerifyFleetAgent on all five fleet routes (and preserves the per-route throttles)', function () {
    $expected = [
        'api/health'         => 'throttle:60,1',
        'api/logs'           => 'throttle:60,1',
        'api/backup/trigger' => 'throttle:6,1',
        'api/backup/blob'    => 'throttle:60,1',
        'api/admin/recover'  => 'throttle:6,1',
    ];

    foreach ($expected as $uri => $throttle) {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);

        expect($route)->not->toBeNull("fleet route {$uri} not registered")
            ->and($route->gatherMiddleware())->toContain(VerifyFleetAgent::class)
            ->and($route->gatherMiddleware())->toContain($throttle);
    }
});
