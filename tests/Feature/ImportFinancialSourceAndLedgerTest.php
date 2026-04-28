<?php

use App\Filament\Pages\ImportDonationsProgressPage;
use App\Filament\Pages\ImportEventsProgressPage;
use App\Filament\Pages\ImportInvoiceDetailsProgressPage;
use App\Filament\Pages\ImportMembershipsProgressPage;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Transaction;
use App\Models\User;
use App\WidgetPrimitive\Source;
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

function csvFor233(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('s233-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

// ── Donation imports ─────────────────────────────────────────────────────────

it('donation imports stamp source=import on Donation and Transaction', function () {
    $source  = ImportSource::create(['name' => 'D']);
    Contact::factory()->create(['email' => 'd@example.com']);

    $path = csvFor233([
        ['Email', 'Amount', 'Number'],
        ['d@example.com', '50.00', 'INV-D1'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'D run',
        'import_source_id' => $source->id,
        'model_type'       => 'donation',
        'status'           => 'pending',
        'filename'         => 'd.csv',
        'row_count'        => 1,
        'imported_by'      => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type'         => 'donation',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'Email'  => 'contact:email',
            'Amount' => 'donation:amount',
            'Number' => 'donation:invoice_number',
        ],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'contact:email',
        'contact_match_key'  => 'contact:email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    $donation = Donation::first();
    expect($donation->source)->toBe(Source::IMPORT);

    $tx = Transaction::where('subject_type', Donation::class)->first();
    expect($tx->source)->toBe(Source::IMPORT);
});

// ── Membership imports ───────────────────────────────────────────────────────

it('membership imports stamp source=import on the Membership row', function () {
    $source  = ImportSource::create(['name' => 'M']);
    Contact::factory()->create(['email' => 'm@example.com']);

    $path = csvFor233([
        ['Email', 'Membership level', 'Membership status', 'Member since'],
        ['m@example.com', 'Gold', 'Active', '01/15/2024'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'M run',
        'import_source_id' => $source->id,
        'model_type'       => 'membership',
        'status'           => 'pending',
        'filename'         => 'm.csv',
        'row_count'        => 1,
        'imported_by'      => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type'         => 'membership',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'Email'             => 'contact:email',
            'Membership level'  => 'membership:tier',
            'Membership status' => 'membership:status',
            'Member since'      => 'membership:starts_on',
        ],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'contact:email',
        'contact_match_key'  => 'contact:email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    $membership = Membership::first();
    expect($membership->source)->toBe(Source::IMPORT);
});

// ── Membership-import ledger fix (Phase 5 — Decision 4) ─────────────────────

it('membership-import ledger writes Transaction when amount_paid > 0 AND status is active', function () {
    $source  = ImportSource::create(['name' => 'L1']);
    Contact::factory()->create(['email' => 'paid@example.com']);

    $path = csvFor233([
        ['Email', 'Membership level', 'Membership status', 'Amount Paid', 'Member since'],
        ['paid@example.com', 'Gold', 'Active', '100.00', '01/15/2024'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'L1', 'import_source_id' => $source->id,
        'model_type' => 'membership', 'status' => 'pending',
        'filename' => 'l1.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'membership', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email'             => 'contact:email',
            'Membership level'  => 'membership:tier',
            'Membership status' => 'membership:status',
            'Amount Paid'       => 'membership:amount_paid',
            'Member since'      => 'membership:starts_on',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'contact:email', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    $membership = Membership::first();
    expect($membership->status)->toBe('active');

    $tx = Transaction::where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->first();

    expect($tx)->not->toBeNull()
        ->and($tx->source)->toBe(Source::IMPORT)
        ->and($tx->status)->toBe('completed')
        ->and((float) $tx->amount)->toBe(100.0)
        ->and($tx->import_source_id)->toBe($source->id)
        ->and($tx->import_session_id)->toBe($session->id);
});

it('membership-import ledger skips Transaction when amount_paid is zero/null (comp / lifetime)', function () {
    $source  = ImportSource::create(['name' => 'L2']);
    Contact::factory()->create(['email' => 'comp@example.com']);

    $path = csvFor233([
        ['Email', 'Membership level', 'Membership status', 'Amount Paid', 'Member since'],
        ['comp@example.com', 'Comp', 'Active', '0.00', '01/15/2024'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'L2', 'import_source_id' => $source->id,
        'model_type' => 'membership', 'status' => 'pending',
        'filename' => 'l2.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'membership', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email'             => 'contact:email',
            'Membership level'  => 'membership:tier',
            'Membership status' => 'membership:status',
            'Amount Paid'       => 'membership:amount_paid',
            'Member since'      => 'membership:starts_on',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'contact:email', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    expect(Membership::count())->toBe(1);
    expect(Transaction::where('subject_type', Membership::class)->count())->toBe(0);
});

it('membership-import ledger skips Transaction when status is pending/cancelled (no money moved)', function () {
    $source  = ImportSource::create(['name' => 'L3']);
    Contact::factory()->create(['email' => 'pending@example.com']);

    $path = csvFor233([
        ['Email', 'Membership level', 'Membership status', 'Amount Paid', 'Member since'],
        ['pending@example.com', 'Gold', 'Pending', '100.00', '01/15/2024'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'L3', 'import_source_id' => $source->id,
        'model_type' => 'membership', 'status' => 'pending',
        'filename' => 'l3.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'membership', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email'             => 'contact:email',
            'Membership level'  => 'membership:tier',
            'Membership status' => 'membership:status',
            'Amount Paid'       => 'membership:amount_paid',
            'Member since'      => 'membership:starts_on',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'contact:email', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    $membership = Membership::first();
    expect($membership->status)->toBe('pending');
    expect(Transaction::where('subject_type', Membership::class)->count())->toBe(0);
});

it('membership-import ledger writes Transaction for expired memberships (money moved historically)', function () {
    $source  = ImportSource::create(['name' => 'L4']);
    Contact::factory()->create(['email' => 'lapsed@example.com']);

    $path = csvFor233([
        ['Email', 'Membership level', 'Membership status', 'Amount Paid', 'Member since'],
        ['lapsed@example.com', 'Gold', 'Lapsed', '75.00', '01/15/2022'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'L4', 'import_source_id' => $source->id,
        'model_type' => 'membership', 'status' => 'pending',
        'filename' => 'l4.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'membership', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email'             => 'contact:email',
            'Membership level'  => 'membership:tier',
            'Membership status' => 'membership:status',
            'Amount Paid'       => 'membership:amount_paid',
            'Member since'      => 'membership:starts_on',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'contact:email', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportMembershipsProgressPage();
    $page->importLogId = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    $page->tick();

    $membership = Membership::first();
    expect($membership->status)->toBe('expired');

    $tx = Transaction::where('subject_type', Membership::class)->first();
    expect($tx)->not->toBeNull()
        ->and($tx->source)->toBe(Source::IMPORT)
        ->and((float) $tx->amount)->toBe(75.0);
});

// ── Event imports ───────────────────────────────────────────────────────────

it('event imports stamp source=import on EventRegistration and Transaction', function () {
    $source = ImportSource::create(['name' => 'E']);
    Contact::factory()->create(['email' => 'e@example.com']);

    $path = csvFor233([
        ['Event ID', 'Title', 'Start date', 'Email', 'Invoice #', 'Amount'],
        ['EV-S1', 'Workshop', '03/01/2026 10:00:00', 'e@example.com', 'INV-S1', '25.00'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'E', 'import_source_id' => $source->id,
        'model_type' => 'event', 'status' => 'pending',
        'filename' => 'e.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'event', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Event ID'   => 'event:external_id',
            'Title'      => 'event:title',
            'Start date' => 'event:starts_at',
            'Email'      => 'contact:email',
            'Invoice #'  => 'transaction:external_id',
            'Amount'     => 'transaction:amount',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'event:external_id', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportEventsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }

    $registration = EventRegistration::first();
    expect($registration)->not->toBeNull()
        ->and($registration->source)->toBe(Source::IMPORT);

    $tx = Transaction::where('subject_type', EventRegistration::class)->first();
    expect($tx)->not->toBeNull()
        ->and($tx->source)->toBe(Source::IMPORT);
});

// ── Invoice Details ────────────────────────────────────────────────────────

it('invoice-detail imports stamp source=import on Transaction', function () {
    $source = ImportSource::create(['name' => 'I']);
    Contact::factory()->create(['email' => 'i@example.com']);

    $path = csvFor233([
        ['Email', 'Invoice #', 'Invoice date', 'Item', 'Item amount', 'Status'],
        ['i@example.com', 'INV-X', '01/15/2025', 'Workshop', '25.00', 'Paid'],
    ]);

    $session = ImportSession::create([
        'session_label' => 'I', 'import_source_id' => $source->id,
        'model_type' => 'invoice_detail', 'status' => 'pending',
        'filename' => 'i.csv', 'row_count' => 1, 'imported_by' => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type' => 'invoice_detail', 'filename' => basename($path), 'storage_path' => $path,
        'column_map' => [
            'Email'        => 'contact:email',
            'Invoice #'    => 'invoice:invoice_number',
            'Invoice date' => 'invoice:invoice_date',
            'Item'         => 'invoice:item',
            'Item amount'  => 'invoice:item_amount',
            'Status'       => 'invoice:status',
        ],
        'row_count' => 1, 'duplicate_strategy' => 'skip',
        'match_key' => 'contact:email', 'contact_match_key' => 'contact:email',
        'import_source_id' => $source->id, 'status' => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();
    $page->runCommit();

    $tx = Transaction::first();
    expect($tx)->not->toBeNull()
        ->and($tx->source)->toBe(Source::IMPORT);
});
