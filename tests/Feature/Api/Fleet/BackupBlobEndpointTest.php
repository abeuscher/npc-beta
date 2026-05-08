<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'backup.backup.name'              => 'NonProfitCRM',
        'backup.backup.destination.disks' => ['local'],
    ]);
    Storage::fake('local');
});

// Auth is enforced at the TLS layer by nginx (mTLS). The application sees no
// auth signal — request arrival IS the auth proof. There are no
// application-layer auth tests for this endpoint at v2.3.0; auth verification
// lives in the manual-testing curl scenarios and in FM-side integration tests.

// ── Success paths ────────────────────────────────────────────────────────────

it('streams the freshest blob from the local disk on a successful fetch', function () {
    $filename = '2026-05-08-12-30-00.zip';
    $bytes = "PK\x03\x04 fake zip body bytes";
    Storage::disk('local')->put("NonProfitCRM/{$filename}", $bytes);

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/zip');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain($filename);
    expect($response->streamedContent())->toBe($bytes);
});

it('streams the newest blob when multiple blobs exist on the same disk', function () {
    $older = '2026-05-01-00-00-00.zip';
    $newer = '2026-05-08-12-30-00.zip';
    Storage::disk('local')->put("NonProfitCRM/{$older}", 'old');
    Storage::disk('local')->put("NonProfitCRM/{$newer}", 'new');

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Disposition'))->toContain($newer);
    expect($response->streamedContent())->toBe('new');
});

it('reports a Content-Length header matching the blob byte count', function () {
    $filename = '2026-05-08-12-30-00.zip';
    $bytes = str_repeat('A', 4096);
    Storage::disk('local')->put("NonProfitCRM/{$filename}", $bytes);

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200);
    expect((int) $response->headers->get('Content-Length'))->toBe(strlen($bytes));
});

it('passes through the spatie filename pattern verbatim in Content-Disposition', function () {
    $filename = '2026-05-08-12-30-00.zip';
    Storage::disk('local')->put("NonProfitCRM/{$filename}", 'x');

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Disposition'))
        ->toMatch('/filename="?2026-05-08-12-30-00\.zip"?/');
});

// ── Disk preference + fallback ───────────────────────────────────────────────

it('prefers the local disk when local is configured but listed after another disk', function () {
    config(['backup.backup.destination.disks' => ['spaces', 'local']]);
    Storage::fake('spaces');

    Storage::disk('local')->put('NonProfitCRM/2026-05-08-12-30-00.zip', 'local-bytes');
    Storage::disk('spaces')->put('NonProfitCRM/2026-05-08-12-30-00.zip', 'spaces-bytes');

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200);
    expect($response->streamedContent())->toBe('local-bytes');
});

it('falls through to the next configured disk when the preferred disk is empty', function () {
    config(['backup.backup.destination.disks' => ['local', 'spaces']]);
    Storage::fake('spaces');

    Storage::disk('spaces')->put('NonProfitCRM/2026-05-08-12-30-00.zip', 'spaces-bytes');

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(200);
    expect($response->streamedContent())->toBe('spaces-bytes');
});

// ── Error paths ──────────────────────────────────────────────────────────────

it('returns 404 with the no_backup_available envelope when all configured disks are empty', function () {
    config(['backup.backup.destination.disks' => ['local', 'spaces']]);
    Storage::fake('spaces');

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(404)
        ->assertJsonPath('error', 'no_backup_available');

    expect($response->json('message'))
        ->toBeString()
        ->toContain('NonProfitCRM');
    expect($response->json())->not->toHaveKey('contract_version');
});

it('returns 500 with backup_destinations_not_configured when BACKUP_DISKS resolves to an empty list', function () {
    config(['backup.backup.destination.disks' => []]);

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(500)
        ->assertJsonPath('error', 'backup_destinations_not_configured');

    expect($response->json('message'))->toBeString();
});

it('returns 500 with backup_destinations_not_configured when only empty-string entries are configured', function () {
    config(['backup.backup.destination.disks' => ['', '   ']]);

    $response = $this->get('/api/backup/blob');

    $response->assertStatus(500)
        ->assertJsonPath('error', 'backup_destinations_not_configured');
});

// ── Method enforcement ──────────────────────────────────────────────────────

it('does not accept POST on /api/backup/blob', function () {
    Storage::disk('local')->put('NonProfitCRM/2026-05-08-12-30-00.zip', 'x');

    $response = $this->postJson('/api/backup/blob');

    expect($response->status())->toBeIn([404, 405])
        ->and($response->json('error'))->not->toBe('no_backup_available');
});

// ── Throttle ────────────────────────────────────────────────────────────────

it('applies the throttle:60,1 middleware on the /api/backup/blob route', function () {
    $contents = file_get_contents(base_path('routes/api.php'));

    expect($contents)
        ->toContain("'throttle:60,1'")
        ->toContain('/backup/blob');
});

it('returns 429 once the per-minute limit is exceeded on /api/backup/blob', function () {
    Storage::disk('local')->put('NonProfitCRM/2026-05-08-12-30-00.zip', 'x');

    for ($i = 0; $i < 60; $i++) {
        $this->get('/api/backup/blob')->assertStatus(200);
    }

    $this->get('/api/backup/blob')->assertStatus(429);
})->group('slow');
