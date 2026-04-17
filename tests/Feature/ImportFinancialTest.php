<?php

use App\Filament\Pages\ImportDonationsProgressPage;
use App\Filament\Pages\ImportInvoiceDetailsProgressPage;
use App\Filament\Pages\ImportMembershipsProgressPage;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
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

function financialCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('fin-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function donationsLog(
    string $path,
    array $columnMap,
    int $rowCount,
    ?string $sourceId = null,
    string $contactMatchKey = 'contact:email',
): ImportLog {
    return ImportLog::create([
        'model_type'         => 'donation',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'skip',
        'match_key'          => $contactMatchKey,
        'contact_match_key'  => $contactMatchKey,
        'import_source_id'   => $sourceId,
        'status'             => 'pending',
    ]);
}

function donationsSession(User $admin, ?string $sourceId = null): ImportSession
{
    return ImportSession::create([
        'session_label'    => 'Donations run',
        'import_source_id' => $sourceId,
        'model_type'       => 'donation',
        'status'           => 'pending',
        'filename'         => 'donations.csv',
        'row_count'        => 10,
        'imported_by'      => $admin->id,
    ]);
}

// ── Donations ────────────────────────────────────────────────────────────────

it('creates a Donation and Transaction for each row', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    $path = financialCsv([
        ['Email', 'Amount', 'Donation date', 'Number'],
        ['alice@example.com', '50.00', '03/15/2025', 'INV-001'],
    ]);

    $session = donationsSession($this->admin, $source->id);
    $log     = donationsLog($path, [
        'Email'         => 'contact:email',
        'Amount'        => 'donation:amount',
        'Donation date' => 'donation:donated_at',
        'Number'        => 'donation:invoice_number',
    ], 1, $source->id);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['imported'])->toBe(1);
    expect($page->dryRunReport['entities']['donations']['would_create'])->toBe(1);

    // Dry-run rolled back — no residual writes.
    expect(Donation::count())->toBe(0);
    expect(Transaction::count())->toBe(0);

    // Now commit.
    $page->runCommit();
    $page->tick();

    expect(Donation::count())->toBe(1);

    $donation = Donation::first();
    expect($donation->contact_id)->toBe($contact->id);
    expect((float) $donation->amount)->toBe(50.0);
    expect($donation->status)->toBe('completed');
    expect($donation->import_session_id)->toBe($session->id);

    $tx = Transaction::where('subject_type', Donation::class)
        ->where('subject_id', $donation->id)
        ->first();

    expect($tx)->not->toBeNull();
    expect($tx->invoice_number)->toBe('INV-001');
    expect((float) $tx->amount)->toBe(50.0);
});

it('deduplicates Transactions on import_source_id + external_id', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    // Pre-create a transaction with the same external_id.
    Transaction::create([
        'type'             => 'payment',
        'direction'        => 'in',
        'status'           => 'pending',
        'amount'           => 0,
        'occurred_at'      => now(),
        'import_source_id' => $source->id,
        'external_id'      => 'PAY-100',
        'contact_id'       => $contact->id,
    ]);

    $path = financialCsv([
        ['Email', 'Amount', 'Number', 'Payment Method ID'],
        ['alice@example.com', '25.00', 'INV-002', 'PAY-100'],
    ]);

    $session = donationsSession($this->admin, $source->id);
    $log     = donationsLog($path, [
        'Email'             => 'contact:email',
        'Amount'            => 'donation:amount',
        'Number'            => 'donation:invoice_number',
        'Payment Method ID' => 'transaction:external_id',
    ], 1, $source->id);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['entities']['transactions']['would_match'])->toBe(1);
    expect($page->dryRunReport['entities']['transactions']['would_create'])->toBe(0);
});

it('auto-creates a contact when the toggle is set', function () {
    $source = ImportSource::create(['name' => 'Source A']);

    $path = financialCsv([
        ['Email', 'First name', 'Last name', 'Amount'],
        ['new-person@example.com', 'Jane', 'Doe', '10.00'],
    ]);

    $session = donationsSession($this->admin, $source->id);
    $log     = donationsLog($path, [
        'Email'      => 'contact:email',
        'First name' => null,
        'Last name'  => null,
        'Amount'     => 'donation:amount',
    ], 1, $source->id);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'auto_create';
    $page->mount();

    expect($page->dryRunReport['imported'])->toBe(1);
    expect($page->dryRunReport['entities']['contacts']['would_create'])->toBe(1);

    // Dry-run rolled back.
    expect(Contact::withoutGlobalScopes()->count())->toBe(0);
});

it('skips rows with missing contacts when auto-create is off', function () {
    $source = ImportSource::create(['name' => 'Source A']);

    $path = financialCsv([
        ['Email', 'Amount'],
        ['nobody@example.com', '10.00'],
    ]);

    $session = donationsSession($this->admin, $source->id);
    $log     = donationsLog($path, [
        'Email'  => 'contact:email',
        'Amount' => 'donation:amount',
    ], 1, $source->id);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['skipped'])->toBe(1);
    expect($page->dryRunReport['skipReasons']['contact_not_found'])->toBe(1);
});

// ── Memberships ──────────────────────────────────────────────────────────────

it('creates a Membership with auto-created tier', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    $path = financialCsv([
        ['Email', 'Membership level', 'Membership status', 'Member since', 'Renewal due'],
        ['alice@example.com', 'Gold Patron', 'Active', '01/15/2023', '01/15/2026'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'Memberships run',
        'import_source_id' => $source->id,
        'model_type'       => 'membership',
        'status'           => 'pending',
        'filename'         => 'memberships.csv',
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
            'Renewal due'       => 'membership:expires_on',
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

    expect($page->dryRunReport['imported'])->toBe(1);
    expect($page->dryRunReport['entities']['tiers']['would_create'])->toBe(1);

    // Dry-run rolled back.
    expect(Membership::count())->toBe(0);
    expect(MembershipTier::where('name', 'Gold Patron')->exists())->toBeFalse();

    // Commit.
    $page->runCommit();
    $page->tick();

    expect(Membership::count())->toBe(1);

    $membership = Membership::first();
    expect($membership->contact_id)->toBe($contact->id);
    expect($membership->status)->toBe('active');

    $tier = MembershipTier::where('name', 'Gold Patron')->first();
    expect($tier)->not->toBeNull();
    expect($membership->tier_id)->toBe($tier->id);
});

it('normalises WA membership status values', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'bob@example.com']);

    $path = financialCsv([
        ['Email', 'Membership level', 'Membership status'],
        ['bob@example.com', 'Standard', 'Lapsed'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'Memberships run',
        'import_source_id' => $source->id,
        'model_type'       => 'membership',
        'status'           => 'pending',
        'filename'         => 'memberships.csv',
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

    // Commit.
    $page->runCommit();
    $page->tick();

    $membership = Membership::first();
    expect($membership->status)->toBe('expired');
});

// ── Invoice Details ──────────────────────────────────────────────────────────

it('collapses multiple line-item rows into one Transaction with line_items JSON', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    $path = financialCsv([
        ['Email', 'Invoice #', 'Invoice date', 'Item', 'Item quantity', 'Item price', 'Item amount', 'Status'],
        ['alice@example.com', 'INV-100', '01/15/2025', 'Event registration', '1', '25.00', '25.00', 'Paid'],
        ['alice@example.com', 'INV-100', '01/15/2025', 'T-shirt', '2', '15.00', '30.00', 'Paid'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'Invoices run',
        'import_source_id' => $source->id,
        'model_type'       => 'invoice_detail',
        'status'           => 'pending',
        'filename'         => 'invoices.csv',
        'row_count'        => 2,
        'imported_by'      => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type'         => 'invoice_detail',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'Email'         => 'contact:email',
            'Invoice #'     => 'invoice:invoice_number',
            'Invoice date'  => 'invoice:invoice_date',
            'Item'          => 'invoice:item',
            'Item quantity' => 'invoice:item_quantity',
            'Item price'    => 'invoice:item_price',
            'Item amount'   => 'invoice:item_amount',
            'Status'        => 'invoice:status',
        ],
        'row_count'          => 2,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'contact:email',
        'contact_match_key'  => 'contact:email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['imported'])->toBe(2);
    expect($page->dryRunReport['entities']['transactions']['would_create'])->toBe(1);
    expect($page->dryRunReport['entities']['line_items']['count'])->toBe(2);

    // Dry-run rolled back.
    expect(Transaction::count())->toBe(0);

    // Commit.
    $page->runCommit();

    expect(Transaction::count())->toBe(1);

    $tx = Transaction::first();
    expect($tx->invoice_number)->toBe('INV-100');
    expect((float) $tx->amount)->toBe(55.0);
    expect($tx->status)->toBe('completed');
    expect($tx->line_items)->toHaveCount(2);
    expect($tx->line_items[0]['item'])->toBe('Event registration');
    expect($tx->line_items[1]['item'])->toBe('T-shirt');
});

it('enriches an existing Transaction with fill-blanks-only semantics', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    // Pre-create a Transaction (as if from the events import).
    $existingTx = Transaction::create([
        'type'             => 'payment',
        'direction'        => 'in',
        'status'           => 'completed',
        'amount'           => 25.00,
        'occurred_at'      => now(),
        'contact_id'       => $contact->id,
        'external_id'      => 'INV-200',
        'import_source_id' => $source->id,
    ]);

    $path = financialCsv([
        ['Email', 'Invoice #', 'Item', 'Item amount', 'Status'],
        ['alice@example.com', 'INV-200', 'Workshop fee', '25.00', 'Unpaid'],
    ]);

    $session = ImportSession::create([
        'session_label'    => 'Invoices run',
        'import_source_id' => $source->id,
        'model_type'       => 'invoice_detail',
        'status'           => 'pending',
        'filename'         => 'invoices.csv',
        'row_count'        => 1,
        'imported_by'      => $this->admin->id,
    ]);

    $log = ImportLog::create([
        'model_type'         => 'invoice_detail',
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => [
            'Email'       => 'contact:email',
            'Invoice #'   => 'invoice:invoice_number',
            'Item'        => 'invoice:item',
            'Item amount' => 'invoice:item_amount',
            'Status'      => 'invoice:status',
        ],
        'row_count'          => 1,
        'duplicate_strategy' => 'skip',
        'match_key'          => 'contact:email',
        'contact_match_key'  => 'contact:email',
        'import_source_id'   => $source->id,
        'status'             => 'pending',
    ]);

    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    expect($page->dryRunReport['entities']['transactions']['would_match'])->toBe(1);
    expect($page->dryRunReport['entities']['transactions']['would_create'])->toBe(0);

    // Dry-run rolled back — original transaction untouched.
    $existingTx->refresh();
    expect($existingTx->status)->toBe('completed');
    expect($existingTx->invoice_number)->toBeNull();

    // Commit.
    $page->runCommit();

    $existingTx->refresh();
    // invoice_number was blank, so filled.
    expect($existingTx->invoice_number)->toBe('INV-200');
    // status was already 'completed', so NOT overwritten to 'pending' (fill-blanks-only).
    expect($existingTx->status)->toBe('completed');
    // line_items added.
    expect($existingTx->line_items)->toHaveCount(1);
});

it('populates invoice_number independently of external_id', function () {
    $source  = ImportSource::create(['name' => 'Source A']);
    $contact = Contact::factory()->create(['email' => 'alice@example.com']);

    $path = financialCsv([
        ['Email', 'Amount', 'Number', 'Payment Method ID'],
        ['alice@example.com', '75.00', 'RCPT-999', 'STRIPE-PI-123'],
    ]);

    $session = donationsSession($this->admin, $source->id);
    $log     = donationsLog($path, [
        'Email'             => 'contact:email',
        'Amount'            => 'donation:amount',
        'Number'            => 'donation:invoice_number',
        'Payment Method ID' => 'transaction:external_id',
    ], 1, $source->id);

    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $source->id;
    $page->contactStrategy = 'error';
    $page->mount();

    $page->runCommit();
    $page->tick();

    $tx = Transaction::first();
    expect($tx->external_id)->toBe('STRIPE-PI-123');
    expect($tx->invoice_number)->toBe('RCPT-999');
});
