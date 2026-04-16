<?php

use App\Filament\Pages\ImportProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Note;
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

function timelineCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('t-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

it('import notes include the source name in the body and link back via import_source_id', function () {
    $source = ImportSource::create(['name' => 'Wild Apricot']);

    $path = timelineCsv([
        ['first_name', 'email'],
        ['Alice', 'alice@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $session = ImportSession::create([
        'import_source_id' => $source->id,
        'model_type'       => 'contact',
        'status'           => 'pending',
        'filename'         => basename($path),
        'session_label'    => 'April Import',
        'row_count'        => 1,
        'imported_by'      => $this->admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->mount();
    $page->runCommit();

    while (! $page->done) {
        $page->tick();
    }

    $contact = Contact::withoutGlobalScopes()->where('email', 'alice@example.com')->first();
    $note    = Note::where('notable_id', $contact->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toContain('Wild Apricot')
        ->and($note->import_source_id)->toBe($source->id)
        ->and($note->importSource->name)->toBe('Wild Apricot');
});

it('staged-update notes also reference the source by id and name', function () {
    $source   = ImportSource::create(['name' => 'Bloomerang']);
    $existing = Contact::factory()->create(['email' => 'hit@example.com', 'first_name' => 'Old']);

    $path = timelineCsv([
        ['first_name', 'email'],
        ['Updated', 'hit@example.com'],
    ]);

    $log = ImportLog::create([
        'model_type'         => 'contact',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => ['first_name' => 'first_name', 'email' => 'email'],
        'row_count'          => 1,
        'duplicate_strategy' => 'update',
        'match_key'          => 'email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $session = ImportSession::create([
        'import_source_id' => $source->id,
        'model_type'       => 'contact',
        'status'           => 'pending',
        'filename'         => basename($path),
        'session_label'    => 'April Stage',
        'row_count'        => 1,
        'imported_by'      => $this->admin->id,
    ]);

    $page = new ImportProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->mount();
    $page->runCommit();

    while (! $page->done) {
        $page->tick();
    }

    $note = Note::where('notable_id', $existing->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toContain('Bloomerang')
        ->and($note->body)->toContain('staged')
        ->and($note->import_source_id)->toBe($source->id);
});
