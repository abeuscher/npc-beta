<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
});

// Auth is enforced at the TLS layer by nginx (mTLS). The application sees no
// auth signal — request arrival IS the auth proof. There are no
// application-layer auth tests for this endpoint at v2.2.0; auth verification
// lives in the manual-testing curl scenarios and in FM-side integration tests.

// ── Success path ─────────────────────────────────────────────────────────────

it('returns the documented envelope keys on a successful trigger', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andReturnUsing(function () {
            Storage::disk('local')->put('fleet/last-backup-at', now()->toIso8601String());

            return 0;
        });

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200);
    expect(array_keys($response->json()))
        ->toEqualCanonicalizing(['contract_version', 'status', 'last_backup_at', 'duration_ms', 'message']);
});

it('reports contract_version 2.3.0', function () {
    Artisan::shouldReceive('call')
        ->andReturnUsing(function () {
            Storage::disk('local')->put('fleet/last-backup-at', now()->toIso8601String());

            return 0;
        });

    $response = $this->postJson('/api/backup/trigger');

    $response->assertJsonPath('contract_version', '2.3.0');
});

it('returns status success with the freshly-written last_backup_at when artisan exits 0 and the success record moves forward', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andReturnUsing(function () {
            Storage::disk('local')->put('fleet/last-backup-at', now()->toIso8601String());

            return 0;
        });

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', null);

    expect($response->json('last_backup_at'))->toBeString()
        ->and(Carbon::parse($response->json('last_backup_at'))->diffInMinutes(now()))->toBeLessThan(5)
        ->and($response->json('duration_ms'))->toBeInt()
        ->and($response->json('duration_ms'))->toBeGreaterThanOrEqual(0);
});

// ── Failure paths ────────────────────────────────────────────────────────────

it('returns status failed when artisan exits non-zero, sourcing the message from Artisan::output()', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andReturn(1);
    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Backup destination disk "local" is not writable');

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('contract_version', '2.3.0');

    expect($response->json('message'))
        ->toBeString()
        ->toContain('Backup destination disk "local" is not writable');
    expect(strlen($response->json('message')))->toBeLessThanOrEqual(500);
});

it('preserves the previous last_backup_at on non-zero-exit failure when a prior success record existed', function () {
    $previousIso = now()->subDay()->toIso8601String();
    Storage::disk('local')->put('fleet/last-backup-at', $previousIso);

    Artisan::shouldReceive('call')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('something broke');

    $response = $this->postJson('/api/backup/trigger');

    $response->assertJsonPath('status', 'failed');
    expect($response->json('last_backup_at'))->toBeString()
        ->and(Carbon::parse($response->json('last_backup_at'))->diffInHours(Carbon::parse($previousIso), absolute: true))->toBeLessThan(1);
});

it('returns last_backup_at null on non-zero-exit failure when no prior success record exists', function () {
    Artisan::shouldReceive('call')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('first-ever backup failed');

    $response = $this->postJson('/api/backup/trigger');

    $response->assertJsonPath('status', 'failed')
        ->assertJsonPath('last_backup_at', null);
});

it('returns status failed when artisan throws, sourcing the message from Throwable::getMessage', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andThrow(new RuntimeException('pg_dump failed: connection refused'));

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed');

    expect($response->json('message'))
        ->toContain('pg_dump failed: connection refused');
});

// ── Integrity guard — success-record mtime cross-check ───────────────────────

it('downgrades to status failed with the integrity-guard message when artisan exits 0 but the success record did not move forward', function () {
    $staleIso = now()->subHours(48)->toIso8601String();
    Storage::disk('local')->put('fleet/last-backup-at', $staleIso);

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andReturn(0);

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'backup:run exited cleanly but success record was not updated');

    expect($response->json('last_backup_at'))->toBeString()
        ->and(Carbon::parse($response->json('last_backup_at'))->diffInHours(Carbon::parse($staleIso), absolute: true))->toBeLessThan(1);
});

it('downgrades to status failed when artisan exits 0 but the success record file is missing entirely', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run')
        ->andReturn(0);

    $response = $this->postJson('/api/backup/trigger');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'backup:run exited cleanly but success record was not updated')
        ->assertJsonPath('last_backup_at', null);
});

// ── Sanitisation pipeline ────────────────────────────────────────────────────

it('strips the absolute application-root prefix from error messages', function () {
    $absolute = base_path('app/Services/Foo.php');

    Artisan::shouldReceive('call')
        ->andThrow(new RuntimeException("backup failed at {$absolute} during run"));

    $response = $this->postJson('/api/backup/trigger');

    $response->assertJsonPath('status', 'failed');
    expect($response->json('message'))
        ->toContain('app/Services/Foo.php')
        ->not->toContain(base_path() . '/');
});

it('collapses newlines in error messages to a single-line separator', function () {
    Artisan::shouldReceive('call')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn("first line\nsecond line\nthird line");

    $response = $this->postJson('/api/backup/trigger');

    $message = $response->json('message');

    expect($message)->toBeString()
        ->and($message)->not->toContain("\n")
        ->and($message)->toContain('first line')
        ->and($message)->toContain('second line')
        ->and($message)->toContain('third line')
        ->and($message)->toContain(' | ');
});

it('caps error messages at 500 characters with a trailing ellipsis', function () {
    $longMessage = str_repeat('A', 1000);

    Artisan::shouldReceive('call')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn($longMessage);

    $response = $this->postJson('/api/backup/trigger');

    $message = $response->json('message');

    expect(mb_strlen($message))->toBe(500)
        ->and(mb_substr($message, -1))->toBe('…');
});

// ── Method enforcement ──────────────────────────────────────────────────────

it('does not invoke the controller on GET to /api/backup/trigger', function () {
    // Mockery expectation: the controller must NOT call Artisan::call. If it did,
    // shouldReceive without an explicit expectation would still allow it; instead
    // we assert the response is non-2xx and does not carry the trigger envelope.
    // In production routing, GET falls through to the public-site page-slug
    // catchall and resolves to 404 — the spec doc names this behaviour
    // explicitly so FM consumers don't depend on a specific non-POST status code.
    $response = $this->getJson('/api/backup/trigger');

    expect($response->status())->toBeIn([404, 405])
        ->and($response->json('contract_version'))->toBeNull();
});

// ── Throttle ────────────────────────────────────────────────────────────────

it('applies the throttle:6,1 middleware on the /api/backup/trigger route', function () {
    $contents = file_get_contents(base_path('routes/api.php'));

    expect($contents)
        ->toContain("'throttle:6,1'")
        ->toContain('/backup/trigger');
});

it('returns 429 once the per-minute limit is exceeded on /api/backup/trigger', function () {
    Artisan::shouldReceive('call')
        ->andReturnUsing(function () {
            Storage::disk('local')->put('fleet/last-backup-at', now()->toIso8601String());

            return 0;
        });
    Artisan::shouldReceive('output')->andReturn('');

    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/backup/trigger')->assertStatus(200);
    }

    $this->postJson('/api/backup/trigger')->assertStatus(429);
})->group('slow');
