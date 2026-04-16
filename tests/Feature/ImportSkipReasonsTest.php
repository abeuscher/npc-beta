<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    putenv('IMPORTER_SKIP_PII_CHECK=1');
    $_ENV['IMPORTER_SKIP_PII_CHECK']    = '1';
    $_SERVER['IMPORTER_SKIP_PII_CHECK'] = '1';
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

function skipCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('skip-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

it('dry-run report breaks skip counts down by reason', function () {
    // Existing contact to trigger match_skip
    Contact::factory()->create(['email' => 'existing@example.com']);

    $path = skipCsv([
        ['first_name', 'email'],
        ['New',        'new1@example.com'],
        ['',           ''],                       // no_identifier
        ['',           ''],                       // no_identifier
        ['Dup',        'existing@example.com'],   // match_skip (strategy=skip)
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 4,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);

    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => basename($path),
        'row_count'   => 4,
        'imported_by' => $this->admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->mount();

    expect($page->dryRunReport['skipped'])->toBe(3)
        ->and($page->dryRunReport['skipReasons']['no_identifier'])->toBe(2)
        ->and($page->dryRunReport['skipReasons']['match_skip'])->toBe(1)
        ->and($page->dryRunReport['imported'])->toBe(1);
});
