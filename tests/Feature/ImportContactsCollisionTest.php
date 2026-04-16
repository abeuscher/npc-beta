<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
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

function collisionCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('coll-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function collisionLog(string $path, string $strategy): ImportLog
{
    return ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => $strategy,
        'match_key'          => 'email',
        'status'             => 'pending',
    ]);
}

function runCollisionCommit(ImportLog $log, User $admin): ImportProgressPage
{
    $session = ImportSession::create([
        'model_type'  => 'contact',
        'status'      => 'pending',
        'filename'    => 'collision.csv',
        'row_count'   => 100,
        'imported_by' => $admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->mount();
    $page->runCommit();

    while (! $page->done) {
        $page->tick();
    }

    return $page;
}

it('strategy "skip" leaves an existing match untouched and creates nothing', function () {
    Contact::factory()->create(['email' => 'a@example.com', 'first_name' => 'Original']);

    $path = collisionCsv([
        ['first_name', 'email'],
        ['Incoming', 'a@example.com'],
    ]);

    runCollisionCommit(collisionLog($path, 'skip'), $this->admin);

    expect(Contact::withoutGlobalScopes()->where('email', 'a@example.com')->count())->toBe(1)
        ->and(Contact::withoutGlobalScopes()->where('email', 'a@example.com')->first()->first_name)->toBe('Original')
        ->and(ImportStagedUpdate::count())->toBe(0);
});

it('strategy "update" stages non-blank changes against the existing match', function () {
    $existing = Contact::factory()->create(['email' => 'b@example.com', 'first_name' => 'Old']);

    $path = collisionCsv([
        ['first_name', 'email'],
        ['Updated', 'b@example.com'],
    ]);

    runCollisionCommit(collisionLog($path, 'update'), $this->admin);

    expect(ImportStagedUpdate::where('contact_id', $existing->id)->count())->toBe(1)
        ->and(Contact::withoutGlobalScopes()->where('email', 'b@example.com')->count())->toBe(1);
});

it('strategy "duplicate" bypasses the match key and always creates a new contact', function () {
    Contact::factory()->create(['email' => 'c@example.com', 'first_name' => 'First']);

    $path = collisionCsv([
        ['first_name', 'email'],
        ['Second', 'c@example.com'],
    ]);

    runCollisionCommit(collisionLog($path, 'duplicate'), $this->admin);

    $duplicates = Contact::withoutGlobalScopes()->where('email', 'c@example.com')->get();

    expect($duplicates)->toHaveCount(2)
        ->and($duplicates->pluck('first_name')->sort()->values()->all())->toBe(['First', 'Second'])
        ->and(ImportStagedUpdate::count())->toBe(0);
});

it('strategy "duplicate" does not trip the ambiguous-match error even when two existing rows share the match value', function () {
    Contact::factory()->create(['email' => 'dup@example.com']);
    Contact::factory()->create(['email' => 'dup@example.com']);

    $path = collisionCsv([
        ['first_name', 'email'],
        ['Imported', 'dup@example.com'],
    ]);

    $page = runCollisionCommit(collisionLog($path, 'duplicate'), $this->admin);

    expect($page->errorCount)->toBe(0)
        ->and(Contact::withoutGlobalScopes()->where('email', 'dup@example.com')->count())->toBe(3);
});
