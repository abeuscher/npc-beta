<?php

use App\Filament\Pages\ImportNotesProgressPage;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
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

function notesCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('notes-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function notesLog(
    string $path,
    array $columnMap,
    int $rowCount,
    ?string $sourceId = null,
    string $duplicateStrategy = 'skip',
    array $customFieldMap = [],
): ImportLog {
    return ImportLog::create([
        'model_type'         => 'note',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'custom_field_map'   => $customFieldMap ?: null,
        'row_count'          => $rowCount,
        'duplicate_strategy' => $duplicateStrategy,
        'match_key'          => 'contact:email',
        'contact_match_key'  => 'contact:email',
        'import_source_id'   => $sourceId,
        'status'             => 'pending',
    ]);
}

function notesSession(User $admin, ?string $sourceId = null, int $rowCount = 1): ImportSession
{
    return ImportSession::create([
        'session_label'    => 'Notes run',
        'import_source_id' => $sourceId,
        'model_type'       => 'note',
        'status'           => 'pending',
        'filename'         => 'notes.csv',
        'row_count'        => $rowCount,
        'imported_by'      => $admin->id,
    ]);
}

function runNotesPage(string $logId, string $sessionId, ?string $sourceId = null): ImportNotesProgressPage
{
    $page = new ImportNotesProgressPage();
    $page->importLogId     = $logId;
    $page->importSessionId = $sessionId;
    $page->importSourceId  = $sourceId ?? '';
    $page->mount();

    return $page;
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('creates a Note attached to the matched Contact for each row', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    $alice  = Contact::factory()->create(['email' => 'alice@example.com']);
    $bob    = Contact::factory()->create(['email' => 'bob@example.com']);
    $carol  = Contact::factory()->create(['email' => 'carol@example.com']);

    $path = notesCsv([
        ['Note Type', 'Note Subject', 'Note Body', 'Note Occurred At', 'Note External ID', 'Email'],
        ['call',    'Intro call',   'Left voicemail.', '2025-03-15 10:00:00', 'N-100', 'alice@example.com'],
        ['meeting', 'Follow-up',    'Discussed gala.', '2025-03-16 14:30:00', 'N-101', 'bob@example.com'],
        ['email',   'Thank-you',    'Sent receipt.',   '2025-03-17 09:15:00', 'N-102', 'carol@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 3);
    $log     = notesLog($path, [
        'Note Type'        => 'note:type',
        'Note Subject'     => 'note:subject',
        'Note Body'        => 'note:body',
        'Note Occurred At' => 'note:occurred_at',
        'Note External ID' => 'note:external_id',
        'Email'            => 'contact:email',
    ], 3, $source->id);

    $page = runNotesPage($log->id, $session->id, $source->id);

    expect($page->dryRunReport['imported'])->toBe(3);
    expect($page->dryRunReport['entities']['notes']['would_create'])->toBe(3);
    expect(Note::count())->toBe(0);

    $page->runCommit();
    $page->tick();

    expect(Note::count())->toBe(3);

    $aliceNote = Note::where('notable_id', $alice->id)->first();
    expect($aliceNote->type)->toBe('call');
    expect($aliceNote->subject)->toBe('Intro call');
    expect($aliceNote->body)->toBe('Left voicemail.');
    expect($aliceNote->occurred_at->format('Y-m-d H:i:s'))->toBe('2025-03-15 10:00:00');
    expect($aliceNote->external_id)->toBe('N-100');
    expect($aliceNote->import_source_id)->toBe($source->id);
    expect($aliceNote->import_session_id)->toBe($session->id);

    expect(Note::where('notable_id', $bob->id)->value('type'))->toBe('meeting');
    expect(Note::where('notable_id', $carol->id)->value('subject'))->toBe('Thank-you');
});

it('preserves non-canonical type strings verbatim', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = notesCsv([
        ['Note Type', 'Note Body', 'Email'],
        ['phone call', 'Non-canonical type variant.', 'alice@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Type' => 'note:type',
        'Note Body' => 'note:body',
        'Email'     => 'contact:email',
    ], 1, $source->id);

    $page = runNotesPage($log->id, $session->id, $source->id);
    $page->runCommit();
    $page->tick();

    expect(Note::count())->toBe(1);
    expect(Note::first()->type)->toBe('phone call');
});

// ── Contact-not-found errors ─────────────────────────────────────────────────

it('errors (not skips) when the contact cannot be matched', function () {
    $source = ImportSource::create(['name' => 'Source A']);

    $path = notesCsv([
        ['Note Body', 'Email'],
        ['Orphan note.', 'nobody@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Body' => 'note:body',
        'Email'     => 'contact:email',
    ], 1, $source->id);

    $page = runNotesPage($log->id, $session->id, $source->id);

    expect($page->dryRunReport['errorCount'])->toBe(1);
    expect($page->dryRunReport['imported'])->toBe(0);
    expect($page->dryRunReport['skipped'])->toBe(0);

    $err = $page->dryRunReport['errors'][0];
    expect($err['message'])->toContain('Contact not found for email = nobody@example.com');
    expect($err['message'])->toContain('Run the contacts importer first');
});

// ── Update strategy ──────────────────────────────────────────────────────────

it('stages updates on second pass under the update strategy', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $firstPath = notesCsv([
        ['Note Type', 'Note Body', 'Note Outcome', 'Note External ID', 'Email'],
        ['call', 'First-pass body.', 'Left message', 'N-200', 'alice@example.com'],
    ]);

    $firstSession = notesSession($this->admin, $source->id, 1);
    $firstLog     = notesLog($firstPath, [
        'Note Type'        => 'note:type',
        'Note Body'        => 'note:body',
        'Note Outcome'     => 'note:outcome',
        'Note External ID' => 'note:external_id',
        'Email'            => 'contact:email',
    ], 1, $source->id);

    $firstPage = runNotesPage($firstLog->id, $firstSession->id, $source->id);
    $firstPage->runCommit();
    $firstPage->tick();

    expect(Note::count())->toBe(1);
    $originalNote = Note::first();
    expect($originalNote->body)->toBe('First-pass body.');

    // Approve: flip session status so finalize doesn't block a new import.
    $firstSession->update(['status' => 'approved']);

    // Second pass — mutated body + outcome, update strategy.
    $secondPath = notesCsv([
        ['Note Type', 'Note Body', 'Note Outcome', 'Note External ID', 'Email'],
        ['call', 'Second-pass body.', 'Connected', 'N-200', 'alice@example.com'],
    ]);

    $secondSession = notesSession($this->admin, $source->id, 1);
    $secondLog     = notesLog($secondPath, [
        'Note Type'        => 'note:type',
        'Note Body'        => 'note:body',
        'Note Outcome'     => 'note:outcome',
        'Note External ID' => 'note:external_id',
        'Email'            => 'contact:email',
    ], 1, $source->id, 'update');

    $secondPage = runNotesPage($secondLog->id, $secondSession->id, $source->id);

    expect($secondPage->dryRunReport['updated'])->toBe(1);
    expect($secondPage->dryRunReport['entities']['notes']['would_update'])->toBe(1);

    $secondPage->runCommit();
    $secondPage->tick();

    // Existing note unchanged; staged update pending approval.
    expect(Note::count())->toBe(1);
    expect(Note::first()->body)->toBe('First-pass body.');

    $staged = ImportStagedUpdate::where('import_session_id', $secondSession->id)->first();
    expect($staged)->not->toBeNull();
    expect($staged->subject_type)->toBe(Note::class);
    expect($staged->subject_id)->toBe($originalNote->id);
    expect($staged->attributes['body'])->toBe('Second-pass body.');
    expect($staged->attributes['outcome'])->toBe('Connected');
});

it('skips duplicates under the skip strategy', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    $alice  = Contact::factory()->create(['email' => 'alice@example.com']);

    Note::create([
        'notable_type'     => Contact::class,
        'notable_id'       => $alice->id,
        'body'             => 'Existing.',
        'occurred_at'      => now(),
        'import_source_id' => $source->id,
        'external_id'      => 'N-300',
    ]);

    $path = notesCsv([
        ['Note Body', 'Note External ID', 'Email'],
        ['New body.', 'N-300', 'alice@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Body'        => 'note:body',
        'Note External ID' => 'note:external_id',
        'Email'            => 'contact:email',
    ], 1, $source->id, 'skip');

    $page = runNotesPage($log->id, $session->id, $source->id);

    expect($page->dryRunReport['skipped'])->toBe(1);
    expect($page->dryRunReport['skipReasons']['duplicate_skipped'])->toBe(1);
});

it('creates a new note when duplicate strategy is duplicate', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    $alice  = Contact::factory()->create(['email' => 'alice@example.com']);

    Note::create([
        'notable_type'     => Contact::class,
        'notable_id'       => $alice->id,
        'body'             => 'Existing.',
        'occurred_at'      => now(),
        'import_source_id' => $source->id,
        'external_id'      => 'N-400',
    ]);

    $path = notesCsv([
        ['Note Body', 'Note External ID', 'Email'],
        ['Second copy.', 'N-400', 'alice@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Body'        => 'note:body',
        'Note External ID' => 'note:external_id',
        'Email'            => 'contact:email',
    ], 1, $source->id, 'duplicate');

    $page = runNotesPage($log->id, $session->id, $source->id);
    $page->runCommit();
    $page->tick();

    expect(Note::where('notable_id', $alice->id)->count())->toBe(2);
});

// ── Meta (unmapped columns) ──────────────────────────────────────────────────

it('writes __custom_note__ columns into notes.meta under the slugged handle', function () {
    $source = ImportSource::create(['name' => 'Source A']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = notesCsv([
        ['Note Body', 'Email', 'Priority', 'Location Name'],
        ['Body.', 'alice@example.com', 'High', 'Main Office'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Body'      => 'note:body',
        'Email'          => 'contact:email',
        'Priority'       => '__custom_note__',
        'Location Name'  => '__custom_note__',
    ], 1, $source->id, 'skip', [
        'Priority'      => ['handle' => 'priority',      'label' => 'Priority',      'field_type' => 'text'],
        'Location Name' => ['handle' => 'location_name', 'label' => 'Location Name', 'field_type' => 'text'],
    ]);

    $page = runNotesPage($log->id, $session->id, $source->id);
    $page->runCommit();
    $page->tick();

    $note = Note::first();
    expect($note)->not->toBeNull();
    expect($note->meta)->toBe([
        'priority'      => 'High',
        'location_name' => 'Main Office',
    ]);
});

// ── Duration parsing ─────────────────────────────────────────────────────────

it('parses duration variants correctly', function (string $input, ?int $expected) {
    $source = ImportSource::create(['name' => 'Source A']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = notesCsv([
        ['Note Body', 'Note Duration (minutes)', 'Email'],
        ['Body.', $input, 'alice@example.com'],
    ]);

    $session = notesSession($this->admin, $source->id, 1);
    $log     = notesLog($path, [
        'Note Body'               => 'note:body',
        'Note Duration (minutes)' => 'note:duration_minutes',
        'Email'                   => 'contact:email',
    ], 1, $source->id);

    $page = runNotesPage($log->id, $session->id, $source->id);
    $page->runCommit();
    $page->tick();

    $note = Note::first();
    expect($note)->not->toBeNull();
    expect($note->duration_minutes)->toBe($expected);
})->with([
    ['30',          30],
    ['30 min',      30],
    ['45 minutes',  45],
    ['00:30:00',    30],
    ['01:15:00',    75],
    ['45:00',       45],
    ['soon',        null],
]);
