<?php

use App\Filament\Pages\ImportDonationsProgressPage;
use App\Filament\Pages\ImportEventsProgressPage;
use App\Filament\Pages\ImportInvoiceDetailsProgressPage;
use App\Filament\Pages\ImportMembershipsProgressPage;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Transaction;
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

function s194Csv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('s194-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

// ── Polymorphic schema: subject() returns the right model ────────────────────

it('resolves the subject() morph relation for event-typed staged updates', function () {
    $event = Event::factory()->create(['title' => 'Test Event']);

    $session = ImportSession::create([
        'model_type'  => 'event',
        'status'      => 'reviewing',
        'filename'    => 't.csv',
        'row_count'   => 1,
        'imported_by' => $this->admin->id,
    ]);

    $update = ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type'      => Event::class,
        'subject_id'        => $event->id,
        'attributes'        => ['title' => 'Updated Title'],
    ]);

    expect($update->subject)->not->toBeNull()
        ->and($update->subject->id)->toBe($event->id)
        ->and($update->subject)->toBeInstanceOf(Event::class);
});

// ── Events: dry-run with update strategy stages, doesn't mutate ──────────────

it('events: dry-run with update strategy reports updated > 0 and does not mutate DB', function () {
    $source   = ImportSource::create(['name' => 'EvSource']);
    $existing = Event::factory()->create(['title' => 'Original']);
    Contact::factory()->create(['email' => 'a@example.com']);

    ImportIdMap::create([
        'import_source_id' => $source->id,
        'model_type'       => 'event',
        'source_id'        => 'EV-1',
        'model_uuid'       => $existing->id,
    ]);

    $path = s194Csv([
        ['Event ID', 'Event title', 'Email'],
        ['EV-1', 'New Title', 'a@example.com'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'event', 'status' => 'pending', 'filename' => 'e.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'event', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => ['Event ID' => 'event:external_id', 'Event title' => 'event:title', 'Email' => 'contact:email'],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'event:external_id',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportEventsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->mount();

    expect($page->dryRunReport['updated'])->toBe(1)
        ->and($page->dryRunReport['imported'])->toBe(0)
        ->and($existing->fresh()->title)->toBe('Original')
        ->and(ImportStagedUpdate::count())->toBe(0);
});

it('events: commit with update strategy creates staged rows; underlying record unchanged', function () {
    $source   = ImportSource::create(['name' => 'EvSource2']);
    $existing = Event::factory()->create(['title' => 'OriginalE']);
    Contact::factory()->create(['email' => 'b@example.com']);

    ImportIdMap::create([
        'import_source_id' => $source->id, 'model_type' => 'event',
        'source_id' => 'EV-2', 'model_uuid' => $existing->id,
    ]);

    $path = s194Csv([
        ['Event ID', 'Event title', 'Email'],
        ['EV-2', 'Staged Title', 'b@example.com'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'event', 'status' => 'pending', 'filename' => 'e.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'event', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => ['Event ID' => 'event:external_id', 'Event title' => 'event:title', 'Email' => 'contact:email'],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'event:external_id',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportEventsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->mount();

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(1)
        ->and($existing->fresh()->title)->toBe('OriginalE');

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->first();
    expect($staged->subject_type)->toBe(Event::class)
        ->and($staged->subject_id)->toBe($existing->id)
        ->and($staged->attributes['title'])->toBe('Staged Title');
});

// ── Donations: dry-run with update strategy ──────────────────────────────────

it('donations: dry-run with update strategy on matched external_id stages and does not mutate', function () {
    $source   = ImportSource::create(['name' => 'DonSource']);
    $contact  = Contact::factory()->create(['email' => 'd@example.com']);
    $existing = Donation::create([
        'contact_id' => $contact->id, 'type' => 'one_off', 'amount' => 50, 'currency' => 'usd',
        'status' => 'completed', 'import_source_id' => $source->id, 'external_id' => 'DON-1',
    ]);

    $path = s194Csv([
        ['Email', 'Amount', 'External ID'],
        ['d@example.com', '75.00', 'DON-1'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'donation', 'status' => 'pending', 'filename' => 'd.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'donation', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => ['Email' => 'contact:email', 'Amount' => 'donation:amount', 'External ID' => 'donation:external_id'],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'contact:email',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportDonationsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['updated'])->toBe(1)
        ->and((float) $existing->fresh()->amount)->toBe(50.0)
        ->and(ImportStagedUpdate::count())->toBe(0);
});

it('donations: commit with update strategy stages; approval applies updates', function () {
    $source   = ImportSource::create(['name' => 'DonSource2']);
    $contact  = Contact::factory()->create(['email' => 'd2@example.com']);
    $existing = Donation::create([
        'contact_id' => $contact->id, 'type' => 'one_off', 'amount' => 25, 'currency' => 'usd',
        'status' => 'completed', 'import_source_id' => $source->id, 'external_id' => 'DON-2',
    ]);

    $path = s194Csv([
        ['Email', 'Amount', 'External ID'],
        ['d2@example.com', '99.50', 'DON-2'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'donation', 'status' => 'pending', 'filename' => 'd.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'donation', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => ['Email' => 'contact:email', 'Amount' => 'donation:amount', 'External ID' => 'donation:external_id'],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'contact:email',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportDonationsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    expect(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(1)
        ->and((float) $existing->fresh()->amount)->toBe(25.0);

    // Approval (simulating ImporterPage::approve action).
    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->get();
    foreach ($staged as $update) {
        $subject = $update->subject_type::find($update->subject_id);
        $subject?->fill($update->attributes)->save();
    }
    $staged->each->delete();

    expect((float) $existing->fresh()->amount)->toBe(99.5)
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});

it('donations: rollback discards staged rows and leaves the underlying donation unchanged', function () {
    $source   = ImportSource::create(['name' => 'DonRb']);
    $contact  = Contact::factory()->create(['email' => 'r@example.com']);
    $existing = Donation::create([
        'contact_id' => $contact->id, 'type' => 'one_off', 'amount' => 10, 'currency' => 'usd',
        'status' => 'completed', 'import_source_id' => $source->id, 'external_id' => 'DON-RB',
    ]);

    $session = ImportSession::create([
        'model_type' => 'donation', 'status' => 'reviewing', 'filename' => 'r.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type' => Donation::class,
        'subject_id' => $existing->id,
        'attributes' => ['amount' => 999],
    ]);

    // Rollback (cascadeOnDelete via session deletion).
    $session->delete();

    expect((float) $existing->fresh()->amount)->toBe(10.0)
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});

// ── Memberships: dry-run with update strategy ────────────────────────────────

it('memberships: dry-run with update strategy on matched external_id reports updated', function () {
    $source   = ImportSource::create(['name' => 'MemSource']);
    $contact  = Contact::factory()->create(['email' => 'm@example.com']);
    $existing = Membership::create([
        'contact_id' => $contact->id, 'status' => 'active',
        'import_source_id' => $source->id, 'external_id' => 'MEM-1',
    ]);

    $path = s194Csv([
        ['Email', 'Notes', 'External ID'],
        ['m@example.com', 'Updated note', 'MEM-1'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'membership', 'status' => 'pending', 'filename' => 'm.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'membership', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => ['Email' => 'contact:email', 'Notes' => 'membership:notes', 'External ID' => 'membership:external_id'],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'contact:email',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['updated'])->toBe(1)
        ->and($existing->fresh()->notes)->toBeNull()
        ->and(ImportStagedUpdate::count())->toBe(0);
});

// ── Invoice details: dry-run with update strategy ────────────────────────────

it('invoice details: dry-run with update strategy on matched invoice_number reports updated', function () {
    $source   = ImportSource::create(['name' => 'InvSource']);
    $contact  = Contact::factory()->create(['email' => 'i@example.com']);
    $existing = Transaction::create([
        'type' => 'payment', 'direction' => 'in', 'status' => 'completed',
        'amount' => 100, 'occurred_at' => now(),
        'contact_id' => $contact->id, 'invoice_number' => 'INV-194', 'external_id' => 'INV-194',
        'import_source_id' => $source->id,
    ]);

    $path = s194Csv([
        ['Email', 'Invoice #', 'Item', 'Item amount'],
        ['i@example.com', 'INV-194', 'New line item', '50.00'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'invoice_detail', 'status' => 'pending', 'filename' => 'i.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'invoice_detail', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email' => 'contact:email', 'Invoice #' => 'invoice:invoice_number',
            'Item' => 'invoice:item', 'Item amount' => 'invoice:item_amount',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'contact:email',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['updated'])->toBe(1)
        ->and($existing->fresh()->line_items)->toBeNull()
        ->and(ImportStagedUpdate::count())->toBe(0);
});

it('invoice details: runCommit handles multiple rows when at least one has a custom field (regression)', function () {
    $source = ImportSource::create(['name' => 'InvCFShadowReg']);
    Contact::factory()->create(['email' => 'inv1@example.com']);
    Contact::factory()->create(['email' => 'inv2@example.com']);

    $path = s194Csv([
        ['Email',             'Invoice #',  'Item',   'Item amount', 'Project'],
        ['inv1@example.com',  'INV-CF-1',   'Widget', '10.00',       'Project A'],
        ['inv2@example.com',  'INV-CF-2',   'Gadget', '20.00',       'Project B'],
    ]);

    $session = ImportSession::create([
        'model_type'       => 'invoice_detail',
        'status'           => 'pending',
        'filename'         => 'i.csv',
        'row_count'        => 2,
        'imported_by'      => $this->admin->id,
        'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type'        => 'invoice_detail',
        'filename'          => basename($path),
        'storage_path'      => $path,
        'column_map'        => [
            'Email'       => 'contact:email',
            'Invoice #'   => 'invoice:invoice_number',
            'Item'        => 'invoice:item',
            'Item amount' => 'invoice:item_amount',
            'Project'     => '__custom_invoice__',
        ],
        'custom_field_map'  => [
            'Project' => ['handle' => 'project', 'label' => 'Project', 'field_type' => 'text'],
        ],
        'row_count'         => 2,
        'duplicate_strategy'=> 'skip',
        'match_key'         => 'contact:email',
        'contact_match_key' => 'contact:email',
        'import_source_id'  => $source->id,
        'status'            => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    // Without the file-handle / CF-handle scope fix in runCommit, this throws
    // TypeError on the second fgetcsv() call.
    $page->runCommit();

    expect($page->errorCount)->toBe(0)
        ->and($page->processed)->toBe(2);
});

it('invoice details: commit with update strategy stages without modifying transaction', function () {
    $source   = ImportSource::create(['name' => 'InvCommit']);
    $contact  = Contact::factory()->create(['email' => 'ic@example.com']);
    $existing = Transaction::create([
        'type' => 'payment', 'direction' => 'in', 'status' => 'completed',
        'amount' => 100, 'occurred_at' => now(),
        'contact_id' => $contact->id, 'invoice_number' => 'INV-COM', 'external_id' => 'INV-COM',
        'import_source_id' => $source->id,
    ]);

    $path = s194Csv([
        ['Email', 'Invoice #', 'Item', 'Item amount'],
        ['ic@example.com', 'INV-COM', 'Staged item', '25.00'],
    ]);

    $session = ImportSession::create([
        'model_type' => 'invoice_detail', 'status' => 'pending', 'filename' => 'i.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id, 'import_source_id' => $source->id,
    ]);
    $log = ImportLog::create([
        'model_type' => 'invoice_detail', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email' => 'contact:email', 'Invoice #' => 'invoice:invoice_number',
            'Item' => 'invoice:item', 'Item amount' => 'invoice:item_amount',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'update', 'match_key' => 'contact:email',
        'contact_match_key' => 'contact:email', 'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    $page->runCommit();

    expect(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(1)
        ->and($existing->fresh()->line_items)->toBeNull();

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->first();
    expect($staged->subject_type)->toBe(Transaction::class)
        ->and($staged->subject_id)->toBe($existing->id)
        ->and($staged->attributes['line_items'])->toHaveCount(1);
});

// ── Contacts regression: existing update flow still works ────────────────────

it('contacts regression: staged update writes subject_type=Contact and approval still applies', function () {
    $contact = Contact::factory()->create(['city' => 'OldCity']);

    $session = ImportSession::create([
        'model_type' => 'contact', 'status' => 'reviewing', 'filename' => 'c.csv',
        'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    ImportStagedUpdate::create([
        'import_session_id' => $session->id,
        'subject_type' => Contact::class,
        'subject_id' => $contact->id,
        'attributes' => ['city' => 'NewCity'],
    ]);

    $staged = ImportStagedUpdate::where('import_session_id', $session->id)->get();
    foreach ($staged as $update) {
        $subject = Contact::withoutGlobalScopes()->find($update->subject_id);
        $subject?->fill($update->attributes)->save();
    }
    $staged->each->delete();

    expect($contact->fresh()->city)->toBe('NewCity')
        ->and(ImportStagedUpdate::where('import_session_id', $session->id)->count())->toBe(0);
});
