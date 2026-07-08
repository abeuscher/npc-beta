<?php

use App\Http\Controllers\Api\Fleet\HealthController;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['fleet.agent.app_version' => 'testver1']);
    Storage::fake('local');
    // The data_hygiene subcheck's audit walks the media disk; fake it so the
    // count is a deterministic 0 in the one test that exercises the real audit,
    // rather than depending on whatever cas/ tree exists on the host filesystem.
    Storage::fake(config('media-library.disk_name', 'public'));
    // The subcheck reads its counts through a short-TTL cache (the audit walks the
    // filesystem + scans media). Seed a zero default (array store, per phpunit.xml)
    // so unrelated cases — including the DB-mock failure paths — never trigger the
    // audit's DB/FS access; data_hygiene cases override it, and one case clears it
    // to exercise the real cache-miss audit path.
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, [
        'orphan_event_pages' => 0,
        'scrub_records'      => 0,
        'orphan_media_dirs'  => 0,
        'dead_owner_media'   => 0,
    ]);
});

// Auth is enforced at the TLS layer by nginx (mTLS). The application sees no
// auth signal — request arrival IS the auth proof. There are no
// application-layer auth tests in v2.0.0; auth verification lives in the
// manual-testing curl scenarios and in FM-side integration tests.

// ── Response shape ──────────────────────────────────────────────────────────

// guards: FM contract envelope clause (spec-mirror); s364 mutation pass flagged it redundant with sibling shape tests — the overlap is deliberate
it('returns the documented top-level keys on a successful poll', function () {
    $response = $this->getJson('/api/health');

    $response->assertStatus(200);
    expect(array_keys($response->json()))
        ->toEqualCanonicalizing(['status', 'version', 'timestamp', 'contract_version', 'subchecks']);
});

// guards: the contract_version pin FM's ContractValidator keys on; redundant catches are expected (every envelope test sees it)
it('reports contract_version 2.5.0', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('contract_version', '2.5.0');
});

// guards: FM contract envelope clause (spec-mirror); deliberate overlap with the other shape tests
it('returns the seven documented subcheck keys', function () {
    $response = $this->getJson('/api/health');

    expect(array_keys($response->json('subchecks')))
        ->toEqualCanonicalizing(['app', 'database', 'redis', 'disk', 'last_backup_at', 'version', 'data_hygiene']);
});

// guards: FM contract envelope clause (spec-mirror); deliberate overlap with the other shape tests
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

// guards: the version field FM reads for per-client upgrade verification (s291); redundancy with checkVersion shape tests is deliberate
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

// ── data_hygiene subcheck (v2.4.0 — count-only, informational) ───────────────

it('shapes data_hygiene with the four named integer counts and nothing else', function () {
    // Drive the counts from a seeded cache so the value is deterministic and
    // independent of the host filesystem (the audit walks the media disk).
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, [
        'orphan_event_pages' => 4,
        'scrub_records'      => 0,
        'orphan_media_dirs'  => 1,
        'dead_owner_media'   => 2,
    ]);

    $response = $this->getJson('/api/health');

    $value = $response->json('subchecks.data_hygiene.value');

    expect(array_keys($value))
        ->toEqualCanonicalizing(['orphan_event_pages', 'scrub_records', 'orphan_media_dirs', 'dead_owner_media']);

    foreach ($value as $count) {
        expect($count)->toBeInt();
    }
});

it('emits counts only — no raw records, slugs, titles, ids, or paths cross the wire', function () {
    // The privacy boundary: the entire data_hygiene payload is aggregate
    // integers; the only non-integer fields are the uniform subcheck envelope
    // (status string, integer threshold, null/summary message — never a record).
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, [
        'orphan_event_pages' => 7,
        'scrub_records'      => 3,
        'orphan_media_dirs'  => 0,
        'dead_owner_media'   => 0,
    ]);

    $entry = $this->getJson('/api/health')->json('subchecks.data_hygiene');

    // value carries only non-negative integers — no nested record data.
    foreach ($entry['value'] as $count) {
        expect($count)->toBeInt()->toBeGreaterThanOrEqual(0);
    }

    // message, when present, is a count-only summary — it must not echo any
    // record identifier. It only ever mentions the numeric total.
    if ($entry['message'] !== null) {
        expect($entry['message'])->toContain('counts only');
    }
});

it('is green with a null message while the total is under the soft threshold', function () {
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, [
        'orphan_event_pages' => 5,
        'scrub_records'      => 4,
        'orphan_media_dirs'  => 0,
        'dead_owner_media'   => 0,
    ]);

    $response = $this->getJson('/api/health');

    $response->assertJsonPath('subchecks.data_hygiene.status', 'green')
        ->assertJsonPath('subchecks.data_hygiene.threshold', 100)
        ->assertJsonPath('subchecks.data_hygiene.message', null);
});

it('reports a soft yellow with a count-only summary once the total reaches the threshold, never red', function () {
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, [
        'orphan_event_pages' => 120,
        'scrub_records'      => 0,
        'orphan_media_dirs'  => 30,
        'dead_owner_media'   => 0,
    ]);

    $response = $this->getJson('/api/health');

    expect($response->json('subchecks.data_hygiene.status'))->toBe('yellow')
        ->and($response->json('subchecks.data_hygiene.status'))->not->toBe('red');

    $response->assertJsonPath('subchecks.data_hygiene.message', '150 items of accumulated cruft (counts only)');
});

it('computes the real audit on a cache miss — a fresh node reports all-zero counts and green', function () {
    Cache::forget(HealthController::DATA_HYGIENE_CACHE_KEY);

    $response = $this->getJson('/api/health');

    // Fresh DB + faked (empty) media disk → every category is zero, status green.
    $response->assertJsonPath('subchecks.data_hygiene.status', 'green')
        ->assertJsonPath('subchecks.data_hygiene.value', [
            'orphan_event_pages' => 0,
            'scrub_records'      => 0,
            'orphan_media_dirs'  => 0,
            'dead_owner_media'   => 0,
        ])
        ->assertJsonPath('subchecks.data_hygiene.message', null);
});

it('reads the audit counts through the cache rather than recomputing per poll', function () {
    $seeded = [
        'orphan_event_pages' => 11,
        'scrub_records'      => 0,
        'orphan_media_dirs'  => 0,
        'dead_owner_media'   => 0,
    ];
    Cache::put(HealthController::DATA_HYGIENE_CACHE_KEY, $seeded);

    $response = $this->getJson('/api/health');

    // The endpoint returns the cached value verbatim — proof it reads through the
    // cache and does not re-run the FS-walking / media-scanning audit on the hot path.
    expect($response->json('subchecks.data_hygiene.value'))->toBe($seeded);
});

it('excludes data_hygiene from the worst-of overall status — even a yellow (or hypothetical red) never drags the top level', function () {
    $reflection = new ReflectionMethod(HealthController::class, 'overallStatus');
    $reflection->setAccessible(true);
    $controller = new HealthController();

    $build = fn (array $statuses) => array_map(
        fn ($status) => ['status' => $status, 'value' => null, 'threshold' => null, 'message' => null],
        $statuses,
    );

    // All health subchecks green; data_hygiene yellow → overall stays green.
    expect($reflection->invoke($controller, $build([
        'app' => 'green', 'database' => 'green', 'redis' => 'green',
        'disk' => 'green', 'last_backup_at' => 'green', 'version' => 'green',
        'data_hygiene' => 'yellow',
    ])))->toBe('green');

    // data_hygiene never emits red, but even a hypothetical red must not propagate.
    expect($reflection->invoke($controller, $build([
        'app' => 'green', 'data_hygiene' => 'red',
    ])))->toBe('green');

    // A genuine health-subcheck yellow still drags overall, unchanged by the carve-out.
    expect($reflection->invoke($controller, $build([
        'app' => 'green', 'disk' => 'yellow', 'data_hygiene' => 'green',
    ])))->toBe('yellow');
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
}); // s364 D4: 2.4s local — under the 5s slow boundary.
