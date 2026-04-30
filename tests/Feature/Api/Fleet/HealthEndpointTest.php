<?php

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['fleet.agent.app_version' => 'testver1']);
    Storage::fake('local');
});

// Auth is enforced at the TLS layer by nginx (mTLS). The application sees no
// auth signal — request arrival IS the auth proof. There are no
// application-layer auth tests in v2.0.0; auth verification lives in the
// manual-testing curl scenarios and in FM-side integration tests.

// ── Response shape ──────────────────────────────────────────────────────────

it('returns the documented top-level keys on a successful poll', function () {
    $response = $this->getJson('/api/health');

    $response->assertStatus(200);
    expect(array_keys($response->json()))
        ->toEqualCanonicalizing(['status', 'version', 'timestamp', 'contract_version', 'subchecks']);
});

it('reports contract_version 2.0.0', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('contract_version', '2.0.0');
});

it('returns the six documented subcheck keys', function () {
    $response = $this->getJson('/api/health');

    expect(array_keys($response->json('subchecks')))
        ->toEqualCanonicalizing(['app', 'database', 'redis', 'disk', 'last_backup_at', 'version']);
});

it('shapes each subcheck with status, value, threshold, message keys', function () {
    $response = $this->getJson('/api/health');

    foreach ($response->json('subchecks') as $name => $entry) {
        expect(array_keys($entry))
            ->toEqualCanonicalizing(['status', 'value', 'threshold', 'message'])
            ->and($entry['status'])->toBeIn(['green', 'yellow', 'red', 'unknown']);
    }
});

// ── Subcheck happy paths ─────────────────────────────────────────────────────

it('returns yellow overall in a healthy test environment because last_backup_at is unknown (no successful backup yet)', function () {
    $response = $this->getJson('/api/health');

    $subchecks = $response->json('subchecks');

    expect($subchecks['app']['status'])->toBe('green')
        ->and($subchecks['database']['status'])->toBe('green')
        ->and($subchecks['redis']['status'])->toBe('green')
        ->and($subchecks['last_backup_at']['status'])->toBe('unknown')
        ->and($subchecks['last_backup_at']['threshold'])->toBe([24, 36])
        ->and($subchecks['version']['status'])->toBe('green');

    expect($response->json('status'))->toBe(
        $subchecks['disk']['status'] === 'red' ? 'red' : 'yellow'
    );
});

it('mirrors config(fleet.agent.app_version) in both the top-level and subcheck version fields', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('version', 'testver1')
        ->assertJsonPath('subchecks.version.value', 'testver1');
});

// ── last_backup_at threshold-driven semantics (v1.2.0 — unchanged in v2.0.0) ─

it('emits last_backup_at as unknown with null value and "no successful backup yet" when the file is missing', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'unknown')
        ->assertJsonPath('subchecks.last_backup_at.value', null)
        ->assertJsonPath('subchecks.last_backup_at.threshold', [24, 36])
        ->assertJsonPath('subchecks.last_backup_at.message', 'no successful backup yet');
});

it('emits last_backup_at as green when the success-record timestamp is recent', function () {
    $iso = now()->subHours(2)->toIso8601String();
    Storage::disk('local')->put('fleet/last-backup-at', $iso);

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'green')
        ->assertJsonPath('subchecks.last_backup_at.threshold', [24, 36])
        ->assertJsonPath('subchecks.last_backup_at.message', null);

    expect($response->json('subchecks.last_backup_at.value'))->toBeString()
        ->and(\Illuminate\Support\Carbon::parse($response->json('subchecks.last_backup_at.value'))->diffInMinutes(now()))->toBeLessThan(180);
});

it('emits last_backup_at as yellow when the success-record timestamp is between 24 and 36 hours old', function () {
    Storage::disk('local')->put('fleet/last-backup-at', now()->subHours(30)->toIso8601String());

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'yellow')
        ->assertJsonPath('subchecks.last_backup_at.threshold', [24, 36])
        ->assertJsonPath('subchecks.last_backup_at.message', null);
});

it('emits last_backup_at as red when the success-record timestamp is older than 36 hours', function () {
    Storage::disk('local')->put('fleet/last-backup-at', now()->subHours(48)->toIso8601String());

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'red')
        ->assertJsonPath('status', 'red');
});

it('emits last_backup_at as unknown when the success-record file is empty', function () {
    Storage::disk('local')->put('fleet/last-backup-at', '');

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'unknown')
        ->assertJsonPath('subchecks.last_backup_at.value', null)
        ->assertJsonPath('subchecks.last_backup_at.message', 'last-backup-at file is empty');
});

it('emits last_backup_at as unknown when the success-record file is unparseable', function () {
    Storage::disk('local')->put('fleet/last-backup-at', 'not a timestamp');

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.last_backup_at.status', 'unknown')
        ->assertJsonPath('subchecks.last_backup_at.value', null)
        ->assertJsonPath('subchecks.last_backup_at.message', 'last-backup-at file unparseable');
});

it('returns overall yellow when subchecks are only green and unknown', function () {
    $response = $this->getJson('/api/health');

    $subchecks = $response->json('subchecks');

    if ($subchecks['disk']['status'] !== 'green') {
        $this->markTestSkipped('disk subcheck is not green in this environment; another case covers the unknown-with-yellow path');
    }

    expect($subchecks['last_backup_at']['status'])->toBe('unknown')
        ->and(array_filter(array_column($subchecks, 'status'), fn ($s) => in_array($s, ['yellow', 'red'], true)))->toBe([])
        ->and($response->json('status'))->toBe('yellow');
});

it('returns overall red when any subcheck is red, even though last_backup_at is unknown', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getPdo')->andThrow(new PDOException('simulated db failure'));

    DB::shouldReceive('connection')->andReturn($connection);

    $response = $this->getJson('/api/health');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'red')
        ->assertJsonPath('subchecks.database.status', 'red')
        ->assertJsonPath('subchecks.last_backup_at.status', 'unknown');
});

it('ranks unknown equivalently to yellow in the worst-of overall derivation', function () {
    $statuses = ['app' => 'green', 'last_backup_at' => 'unknown', 'extra' => 'yellow'];

    $reflection = new ReflectionMethod(\App\Http\Controllers\Api\Fleet\HealthController::class, 'overallStatus');
    $reflection->setAccessible(true);

    $controller = new \App\Http\Controllers\Api\Fleet\HealthController();

    $subchecks = array_map(
        fn ($status) => ['status' => $status, 'value' => null, 'threshold' => null, 'message' => null],
        $statuses
    );

    expect($reflection->invoke($controller, $subchecks))->toBe('yellow');

    $subchecks['extra']['status'] = 'unknown';
    expect($reflection->invoke($controller, $subchecks))->toBe('yellow');

    $subchecks['extra']['status'] = 'green';
    expect($reflection->invoke($controller, $subchecks))->toBe('yellow');
});

// ── Subcheck failure paths ───────────────────────────────────────────────────

it('marks database red and overall red when DB::getPdo throws, while still returning 200', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getPdo')->andThrow(new PDOException('simulated db failure'));

    DB::shouldReceive('connection')->andReturn($connection);

    $response = $this->getJson('/api/health');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'red')
        ->assertJsonPath('subchecks.database.status', 'red')
        ->assertJsonPath('subchecks.database.value', 'unreachable');

    expect($response->json('subchecks.database.message'))->toBe('PDOException');
});

it('marks redis red and overall red when Redis::ping throws, while still returning 200', function () {
    Redis::shouldReceive('ping')->andThrow(new RuntimeException('simulated redis failure'));

    $response = $this->getJson('/api/health');

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
        $this->getJson('/api/health')->assertStatus(200);
    }

    $this->getJson('/api/health')->assertStatus(429);
})->group('slow');
