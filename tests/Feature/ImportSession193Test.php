<?php

use App\Filament\Pages\ImporterPage;
use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    putenv('IMPORTER_SKIP_PII_CHECK=1');
    $_ENV['IMPORTER_SKIP_PII_CHECK']    = '1';
    $_SERVER['IMPORTER_SKIP_PII_CHECK'] = '1';

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('import_data');
    $this->admin->givePermissionTo('review_imports');
    $this->actingAs($this->admin);
});

function writeBulkCsv(int $rowCount): string
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['first_name', 'last_name', 'email']);

    for ($i = 1; $i <= $rowCount; $i++) {
        fputcsv($handle, ["First{$i}", "Last{$i}", "user{$i}@example.com"]);
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('bulk-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function bulkImportLog(string $path, int $rowCount): ImportLog
{
    return ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'email'      => 'email',
        ],
        'custom_field_map'   => [],
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'update',
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);
}

function bulkImportSession(User $admin): ImportSession
{
    return ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => 'bulk.csv',
        'row_count'   => 1000,
        'imported_by' => $admin->id,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1k-row commit regression (slow)
// ─────────────────────────────────────────────────────────────────────────────

it('commits a 1000-row contacts CSV end-to-end and reaches the done phase', function () {
    $path    = writeBulkCsv(1000);
    $log     = bulkImportLog($path, 1000);
    $session = bulkImportSession($this->admin);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = '';
    $page->mount();

    expect($page->phase)->toBe('awaitingDecision')
        ->and($page->dryRunReport['imported'])->toBe(1000)
        ->and($page->dryRunReport['errorCount'])->toBe(0);

    $page->runCommit();
    expect($page->phase)->toBe('committing');

    $ticks = 0;
    while (! $page->done && $ticks < 50) {
        $page->tick();
        $ticks++;
    }

    expect($page->done)->toBeTrue()
        ->and($page->phase)->toBe('done')
        ->and($page->imported)->toBe(1000)
        ->and($page->errorCount)->toBe(0)
        ->and($page->processed)->toBe(1000)
        ->and(Contact::withoutGlobalScopes()->count())->toBe(1000)
        ->and($session->fresh()->status)->toBe('reviewing');
})->group('slow');

// ─────────────────────────────────────────────────────────────────────────────
// ImporterPage query-count regression
// ─────────────────────────────────────────────────────────────────────────────

it('ImporterPage renders with a bounded query count even with many sessions', function () {
    // Simulate a CRM with hundreds of historical sessions spanning all types.
    $types = ['contact', 'event', 'donation', 'membership', 'invoice_detail'];

    for ($i = 0; $i < 250; $i++) {
        ImportSession::create([
            'model_type'  => $types[$i % count($types)],
            'status'      => 'approved',
            'filename'    => "hist-{$i}.csv",
            'row_count'   => 100,
            'imported_by' => $this->admin->id,
        ]);
    }

    // Plus a few blocking sessions across types.
    ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => 'blocking-contact.csv',
        'row_count'   => 10,
        'imported_by' => $this->admin->id,
    ]);
    ImportSession::create([
        'model_type'  => 'event',
        'status'      => 'reviewing',
        'filename'    => 'blocking-event.csv',
        'row_count'   => 10,
        'imported_by' => $this->admin->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($this->admin)->test(ImporterPage::class);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Current baseline is ~5 queries regardless of historical session count
    // (getBlockedTypes + paginated table query + auth/permission checks).
    // Guard against regressions that add N+1 queries per row.
    expect(count($queries))->toBeLessThan(15);
});

// ─────────────────────────────────────────────────────────────────────────────
// getBlockedTypes behaviour
// ─────────────────────────────────────────────────────────────────────────────

it('getBlockedTypes returns distinct model types with pending or reviewing status', function () {
    ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => 'a.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);
    ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'reviewing',
        'filename'    => 'b.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);
    ImportSession::create([
        'model_type'  => 'event',
        'status'      => 'reviewing',
        'filename'    => 'c.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);
    ImportSession::create([
        'model_type'  => 'donation',
        'status'      => 'approved', // not blocking
        'filename'    => 'd.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);

    $page = new ImporterPage();
    $blocked = $page->getBlockedTypes();

    sort($blocked);

    expect($blocked)->toBe(['contact', 'event'])
        ->and($page->getBlockedTypes())->toBe($blocked); // memoized call returns same
});
