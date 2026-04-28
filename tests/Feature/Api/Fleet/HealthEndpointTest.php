<?php

use App\Http\Middleware\AuthenticateFleetManagerAgent;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const FLEET_TEST_KEY = 'test-fleet-agent-key-do-not-reuse-elsewhere';

beforeEach(function () {
    config(['fleet.agent.api_key' => FLEET_TEST_KEY]);
    config(['fleet.agent.app_version' => 'testver1']);
});

function fleetAuthHeaders(?string $token = FLEET_TEST_KEY): array
{
    return $token === null
        ? []
        : ['Authorization' => 'Bearer '.$token];
}

// ── Auth ─────────────────────────────────────────────────────────────────────

it('rejects requests with no Authorization header as 401', function () {
    $response = $this->getJson('/api/health');

    $response->assertStatus(401)
        ->assertExactJson(['error' => 'unauthorized']);
});

it('rejects requests with an empty bearer token as 401', function () {
    $response = $this->getJson('/api/health', ['Authorization' => 'Bearer ']);

    $response->assertStatus(401)
        ->assertExactJson(['error' => 'unauthorized']);
});

it('rejects requests with a wrong bearer token as 401', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders('definitely-not-the-key'));

    $response->assertStatus(401)
        ->assertExactJson(['error' => 'unauthorized']);
});

it('returns 500 misconfigured when the server has no agent key set', function () {
    config(['fleet.agent.api_key' => null]);

    $response = $this->getJson('/api/health', fleetAuthHeaders('anything'));

    $response->assertStatus(500)
        ->assertExactJson(['error' => 'misconfigured']);
});

it('accepts requests with the correct bearer token as 200', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertStatus(200);
});

it('uses hash_equals for timing-safe token comparison', function () {
    $source = file_get_contents((new ReflectionClass(AuthenticateFleetManagerAgent::class))->getFileName());

    expect($source)->toContain('hash_equals(');
});

// ── Response shape ──────────────────────────────────────────────────────────

it('returns the documented top-level keys on a successful poll', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertStatus(200);
    expect(array_keys($response->json()))
        ->toEqualCanonicalizing(['status', 'version', 'timestamp', 'contract_version', 'subchecks']);
});

it('reports contract_version 1.0.0', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertJsonPath('contract_version', '1.0.0');
});

it('returns the six documented subcheck keys', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    expect(array_keys($response->json('subchecks')))
        ->toEqualCanonicalizing(['app', 'database', 'redis', 'disk', 'last_backup_at', 'version']);
});

it('shapes each subcheck with status, value, threshold, message keys', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    foreach ($response->json('subchecks') as $name => $entry) {
        expect(array_keys($entry))
            ->toEqualCanonicalizing(['status', 'value', 'threshold', 'message'])
            ->and($entry['status'])->toBeIn(['green', 'yellow', 'red']);
    }
});

// ── Subcheck happy paths ─────────────────────────────────────────────────────

it('returns green across all subchecks in a healthy test environment', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $subchecks = $response->json('subchecks');

    expect($subchecks['app']['status'])->toBe('green')
        ->and($subchecks['database']['status'])->toBe('green')
        ->and($subchecks['redis']['status'])->toBe('green')
        ->and($subchecks['last_backup_at']['status'])->toBe('green')
        ->and($subchecks['version']['status'])->toBe('green');

    expect($response->json('status'))->toBe(in_array($subchecks['disk']['status'], ['yellow', 'red'], true)
        ? $subchecks['disk']['status']
        : 'green');
});

it('mirrors config(fleet.agent.app_version) in both the top-level and subcheck version fields', function () {
    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertJsonPath('version', 'testver1')
        ->assertJsonPath('subchecks.version.value', 'testver1');
});

// ── Subcheck failure paths ───────────────────────────────────────────────────

it('marks database red and overall red when DB::getPdo throws, while still returning 200', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getPdo')->andThrow(new PDOException('simulated db failure'));

    DB::shouldReceive('connection')->andReturn($connection);

    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertStatus(200)
        ->assertJsonPath('status', 'red')
        ->assertJsonPath('subchecks.database.status', 'red')
        ->assertJsonPath('subchecks.database.value', 'unreachable');

    expect($response->json('subchecks.database.message'))->toBe('PDOException');
});

it('marks redis red and overall red when Redis::ping throws, while still returning 200', function () {
    Redis::shouldReceive('ping')->andThrow(new RuntimeException('simulated redis failure'));

    $response = $this->getJson('/api/health', fleetAuthHeaders());

    $response->assertStatus(200)
        ->assertJsonPath('status', 'red')
        ->assertJsonPath('subchecks.redis.status', 'red')
        ->assertJsonPath('subchecks.redis.value', 'unreachable');

    expect($response->json('subchecks.redis.message'))->toBe('RuntimeException');
});

// ── Rate limit ───────────────────────────────────────────────────────────────

it('applies the throttle:60,1 middleware on the route', function () {
    $contents = file_get_contents(base_path('routes/api.php'));

    expect($contents)->toContain("'throttle:60,1'");
});

it('returns 429 once the per-minute limit is exceeded', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/health', fleetAuthHeaders())->assertStatus(200);
    }

    $this->getJson('/api/health', fleetAuthHeaders())->assertStatus(429);
})->group('slow');
