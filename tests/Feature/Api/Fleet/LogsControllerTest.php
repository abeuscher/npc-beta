<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('logs');
});

// Auth is enforced at the TLS layer by nginx (mTLS). The application sees no
// auth signal — request arrival IS the auth proof. There are no
// application-layer auth tests in v2.1.0; auth verification lives in the
// manual-testing curl scenarios and in FM-side integration tests.

// ── Happy path ───────────────────────────────────────────────────────────────

it('returns the documented envelope keys on a successful fetch', function () {
    Storage::disk('logs')->put('laravel.log', "line one\nline two\nline three\n");

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200);
    expect(array_keys($response->json()))
        ->toEqualCanonicalizing(['lines', 'lines_returned', 'lines_truncated', 'source']);
});

it('returns the tail of the log file in chronological order', function () {
    $lines = [];
    for ($i = 1; $i <= 1000; $i++) {
        $lines[] = "line {$i}";
    }
    Storage::disk('logs')->put('laravel.log', implode("\n", $lines) . "\n");

    $response = $this->getJson('/api/logs?lines=10');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 10)
        ->assertJsonPath('lines_truncated', true)
        ->assertJsonPath('source', 'laravel.log');

    expect($response->json('lines'))->toBe([
        'line 991', 'line 992', 'line 993', 'line 994', 'line 995',
        'line 996', 'line 997', 'line 998', 'line 999', 'line 1000',
    ]);
});

it('returns the entire file when fewer lines exist than the line cap and reports lines_truncated false', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\nbravo\ncharlie\n");

    $response = $this->getJson('/api/logs?lines=500');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 3)
        ->assertJsonPath('lines_truncated', false);

    expect($response->json('lines'))->toBe(['alpha', 'bravo', 'charlie']);
});

it('uses the default of 500 lines when no parameter is supplied', function () {
    $lines = [];
    for ($i = 1; $i <= 600; $i++) {
        $lines[] = "row {$i}";
    }
    Storage::disk('logs')->put('laravel.log', implode("\n", $lines) . "\n");

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 500)
        ->assertJsonPath('lines_truncated', true);

    $returned = $response->json('lines');
    expect($returned[0])->toBe('row 101')
        ->and($returned[499])->toBe('row 600');
});

// ── Range parameter clipping ────────────────────────────────────────────────

it('clips the lines parameter silently to the 10000 cap', function () {
    $line = str_repeat('x', 20) . "\n";
    Storage::disk('logs')->put('laravel.log', str_repeat($line, 12000));

    $response = $this->getJson('/api/logs?lines=15000');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 10000)
        ->assertJsonPath('lines_truncated', true);
});

// ── Byte cap ─────────────────────────────────────────────────────────────────

it('caps the encoded response body at 1 MB on a synthetic large log file', function () {
    $line = str_repeat('a', 100) . "\n";
    Storage::disk('logs')->put('laravel.log', str_repeat($line, 30000));

    $response = $this->getJson('/api/logs?lines=10000');

    $response->assertStatus(200)
        ->assertJsonPath('lines_truncated', true);

    expect(strlen($response->getContent()))->toBeLessThanOrEqual(1048576);
});

// ── Long-line regression ────────────────────────────────────────────────────

it('returns a single >32 KB line near EOF intact when ?lines=1', function () {
    $longLine = str_repeat('Q', 40000);
    $content = "first\nsecond\n" . $longLine . "\n";
    Storage::disk('logs')->put('laravel.log', $content);

    $response = $this->getJson('/api/logs?lines=1');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 1);

    expect($response->json('lines.0'))->toBe($longLine)
        ->and(strlen($response->json('lines.0')))->toBe(40000);
});

it('returns a long line plus its predecessors when ?lines=3 and the long line is in the middle', function () {
    $longLine = str_repeat('M', 20000);
    $content = "first\nsecond\n" . $longLine . "\nlast\n";
    Storage::disk('logs')->put('laravel.log', $content);

    $response = $this->getJson('/api/logs?lines=3');

    $response->assertStatus(200);
    expect($response->json('lines'))->toBe(['second', $longLine, 'last']);
});

// ── Missing file ─────────────────────────────────────────────────────────────

it('returns 404 with the documented envelope when the log file does not exist', function () {
    $response = $this->getJson('/api/logs');

    $response->assertStatus(404)
        ->assertJsonPath('error', 'log_not_found')
        ->assertJsonPath('message', 'log file does not exist');
});

// ── Bad parameters ──────────────────────────────────────────────────────────

it('returns 422 when lines is negative', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\n");

    $response = $this->getJson('/api/logs?lines=-1');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_lines');
});

it('returns 422 when lines is zero', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\n");

    $response = $this->getJson('/api/logs?lines=0');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_lines');
});

it('returns 422 when lines is non-numeric', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\n");

    $response = $this->getJson('/api/logs?lines=abc');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_lines');
});

it('returns 422 when lines is a fractional value', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\n");

    $response = $this->getJson('/api/logs?lines=12.5');

    $response->assertStatus(422)
        ->assertJsonPath('error', 'invalid_lines');
});

// ── Throttle (source-grep, fast) ─────────────────────────────────────────────

it('applies the throttle:60,1 middleware on the /api/logs route', function () {
    $contents = file_get_contents(base_path('routes/api.php'));

    expect($contents)
        ->toContain("'throttle:60,1'")
        ->toContain("/logs");
});

// ── Edge cases ───────────────────────────────────────────────────────────────

it('handles a file with no trailing newline', function () {
    Storage::disk('logs')->put('laravel.log', "first\nsecond\nthird");

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 3);

    expect($response->json('lines'))->toBe(['first', 'second', 'third']);
});

it('preserves real empty lines in the middle of a log', function () {
    Storage::disk('logs')->put('laravel.log', "alpha\n\nbravo\n\ncharlie\n");

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200);
    expect($response->json('lines'))->toBe(['alpha', '', 'bravo', '', 'charlie']);
});

it('returns lines_returned 0 with truncated false when the file is empty', function () {
    Storage::disk('logs')->put('laravel.log', '');

    $response = $this->getJson('/api/logs');

    $response->assertStatus(200)
        ->assertJsonPath('lines_returned', 0)
        ->assertJsonPath('lines_truncated', false);

    expect($response->json('lines'))->toBe([]);
});
