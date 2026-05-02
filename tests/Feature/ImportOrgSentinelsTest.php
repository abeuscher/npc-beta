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
use App\Models\Organization;
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

function orgSentinelCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $path = 'imports/' . uniqid('orgsent-', true) . '.csv';
    Storage::disk('local')->put($path, $content);

    return $path;
}

function orgSentinelImportLog(
    string $modelType,
    string $path,
    array $columnMap,
    array $relationalMap,
    int $rowCount,
    string $sourceId,
    array $customFieldMap = [],
    string $contactMatchKey = 'contact:email',
    string $eventMatchKey = 'event:external_id',
): ImportLog {
    return ImportLog::create([
        'model_type'         => $modelType,
        'filename'           => basename($path),
        'storage_path'       => $path,
        'column_map'         => $columnMap,
        'custom_field_map'   => $customFieldMap ?: null,
        'relational_map'     => $relationalMap,
        'row_count'          => $rowCount,
        'duplicate_strategy' => 'skip',
        'match_key'          => $modelType === 'event' ? $eventMatchKey : $contactMatchKey,
        'contact_match_key'  => $contactMatchKey,
        'import_source_id'   => $sourceId,
        'status'             => 'pending',
    ]);
}

function orgSentinelSession(User $admin, string $modelType, string $sourceId): ImportSession
{
    return ImportSession::create([
        'session_label'    => 'Org sentinel run',
        'import_source_id' => $sourceId,
        'model_type'       => $modelType,
        'status'           => 'pending',
        'filename'         => 'orgsent.csv',
        'row_count'        => 10,
        'imported_by'      => $admin->id,
    ]);
}

function orgSentinelRunDonations(ImportLog $log, ImportSession $session, string $sourceId): ImportDonationsProgressPage
{
    $page = new ImportDonationsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->contactStrategy = 'auto_create';
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }
    return $page;
}

function orgSentinelRunMemberships(ImportLog $log, ImportSession $session, string $sourceId): ImportMembershipsProgressPage
{
    $page = new ImportMembershipsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->contactStrategy = 'auto_create';
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }
    return $page;
}

function orgSentinelRunEvents(ImportLog $log, ImportSession $session, string $sourceId): ImportEventsProgressPage
{
    $page = new ImportEventsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }
    return $page;
}

function orgSentinelRunInvoices(ImportLog $log, ImportSession $session, string $sourceId): ImportInvoiceDetailsProgressPage
{
    $page = new ImportInvoiceDetailsProgressPage();
    $page->importLogId     = $log->id;
    $page->importSessionId = $session->id;
    $page->importSourceId  = $sourceId;
    $page->contactStrategy = 'auto_create';
    $page->mount();
    $page->runCommit();
    while (! $page->done) {
        $page->tick();
    }
    return $page;
}

// ── __org_donor__ ────────────────────────────────────────────────────────────

it('__org_donor__ auto_create links existing org and creates missing one', function () {
    $source = ImportSource::create(['name' => 'Donor Run']);
    Organization::create(['name' => 'ACME Foundation']);
    Contact::factory()->create(['email' => 'alice@example.com']);
    Contact::factory()->create(['email' => 'bob@example.com']);

    $path = orgSentinelCsv([
        ['Email', 'Amount', 'Donor Org'],
        ['alice@example.com', '100.00', 'ACME Foundation'],
        ['bob@example.com',   '50.00',  'New Charity'],
    ]);

    $log = orgSentinelImportLog('donation', $path, [
        'Email'     => 'contact:email',
        'Amount'    => 'donation:amount',
        'Donor Org' => '__org_donor__',
    ], [
        'Donor Org' => ['type' => 'donation_organization', 'strategy' => 'auto_create'],
    ], 2, $source->id);

    $session = orgSentinelSession($this->admin, 'donation', $source->id);
    orgSentinelRunDonations($log, $session, $source->id);

    expect(Donation::count())->toBe(2);
    $acme = Organization::where('name', 'ACME Foundation')->first();
    $charity = Organization::where('name', 'New Charity')->first();
    expect($charity)->not->toBeNull();

    $alice = Contact::where('email', 'alice@example.com')->first();
    $bob   = Contact::where('email', 'bob@example.com')->first();
    expect(Donation::where('contact_id', $alice->id)->first()->organization_id)->toBe($acme->id);
    expect(Donation::where('contact_id', $bob->id)->first()->organization_id)->toBe($charity->id);
});

it('__org_donor__ match_only leaves unknown orgs unlinked', function () {
    $source = ImportSource::create(['name' => 'Donor Run']);
    Organization::create(['name' => 'Known Org']);
    Contact::factory()->create(['email' => 'alice@example.com']);
    Contact::factory()->create(['email' => 'bob@example.com']);

    $path = orgSentinelCsv([
        ['Email', 'Amount', 'Donor Org'],
        ['alice@example.com', '100.00', 'Known Org'],
        ['bob@example.com',   '50.00',  'Unknown Org'],
    ]);

    $log = orgSentinelImportLog('donation', $path, [
        'Email'     => 'contact:email',
        'Amount'    => 'donation:amount',
        'Donor Org' => '__org_donor__',
    ], [
        'Donor Org' => ['type' => 'donation_organization', 'strategy' => 'match_only'],
    ], 2, $source->id);

    $session = orgSentinelSession($this->admin, 'donation', $source->id);
    orgSentinelRunDonations($log, $session, $source->id);

    expect(Organization::count())->toBe(1); // Unknown Org NOT created
    $known = Organization::where('name', 'Known Org')->first();
    $alice = Contact::where('email', 'alice@example.com')->first();
    $bob   = Contact::where('email', 'bob@example.com')->first();
    expect(Donation::where('contact_id', $alice->id)->first()->organization_id)->toBe($known->id);
    expect(Donation::where('contact_id', $bob->id)->first()->organization_id)->toBeNull();
});

it('__org_donor__ as_custom routes value to donation custom_fields, no FK', function () {
    $source = ImportSource::create(['name' => 'Donor Run']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = orgSentinelCsv([
        ['Email', 'Amount', 'Donor Org'],
        ['alice@example.com', '100.00', 'ACME Foundation'],
    ]);

    // Mirrors what serializeColumnMaps does in the as_custom branch:
    // the sentinel is replaced with __custom_donation__, the relational
    // map drops the org entry, and the custom_field_map carries the entry.
    $log = orgSentinelImportLog('donation', $path, [
        'Email'     => 'contact:email',
        'Amount'    => 'donation:amount',
        'Donor Org' => '__custom_donation__',
    ], [], 1, $source->id, customFieldMap: [
        'Donor Org' => ['handle' => 'donor_org', 'label' => 'Donor Org', 'field_type' => 'text'],
    ]);

    $session = orgSentinelSession($this->admin, 'donation', $source->id);
    orgSentinelRunDonations($log, $session, $source->id);

    expect(Organization::count())->toBe(0);
    $alice = Contact::where('email', 'alice@example.com')->first();
    $donation = Donation::where('contact_id', $alice->id)->first();
    expect($donation->organization_id)->toBeNull();
    expect($donation->custom_fields['donor_org'] ?? null)->toBe('ACME Foundation');
});

// ── __org_member__ ───────────────────────────────────────────────────────────

it('__org_member__ auto_create sets memberships.organization_id', function () {
    $source = ImportSource::create(['name' => 'Member Run']);
    Organization::create(['name' => 'Big Co']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = orgSentinelCsv([
        ['Email', 'Member Org'],
        ['alice@example.com', 'Big Co'],
        ['alice@example.com', 'Tiny Inc'],
    ]);

    $log = orgSentinelImportLog('membership', $path, [
        'Email'      => 'contact:email',
        'Member Org' => '__org_member__',
    ], [
        'Member Org' => ['type' => 'membership_organization', 'strategy' => 'auto_create'],
    ], 2, $source->id);

    $session = orgSentinelSession($this->admin, 'membership', $source->id);
    orgSentinelRunMemberships($log, $session, $source->id);

    $bigCo = Organization::where('name', 'Big Co')->first();
    $tiny  = Organization::where('name', 'Tiny Inc')->first();
    expect($tiny)->not->toBeNull();

    expect(Membership::count())->toBe(2);
    expect(Membership::where('organization_id', $bigCo->id)->exists())->toBeTrue();
    expect(Membership::where('organization_id', $tiny->id)->exists())->toBeTrue();
});

// ── __org_sponsor__ ──────────────────────────────────────────────────────────

it('__org_sponsor__ auto_create sets events.sponsor_organization_id from first row', function () {
    $source = ImportSource::create(['name' => 'Events Run']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = orgSentinelCsv([
        ['Event ID', 'Event title', 'Event date', 'Email', 'Sponsor'],
        ['EV-1', 'Annual Gala', '2026-06-15', 'alice@example.com', 'Mega Sponsor'],
    ]);

    $log = orgSentinelImportLog('event', $path, [
        'Event ID'    => 'event:external_id',
        'Event title' => 'event:title',
        'Event date'  => 'event:starts_at',
        'Email'       => 'contact:email',
        'Sponsor'     => '__org_sponsor__',
    ], [
        'Sponsor' => ['type' => 'event_sponsor_organization', 'strategy' => 'auto_create'],
    ], 1, $source->id);

    $session = orgSentinelSession($this->admin, 'event', $source->id);
    orgSentinelRunEvents($log, $session, $source->id);

    $mega = Organization::where('name', 'Mega Sponsor')->first();
    expect($mega)->not->toBeNull();
    $event = Event::where('title', 'Annual Gala')->first();
    expect($event)->not->toBeNull();
    expect($event->sponsor_organization_id)->toBe($mega->id);
});

// ── __org_invoice_party__ ────────────────────────────────────────────────────

it('__org_invoice_party__ auto_create sets transactions.organization_id', function () {
    $source = ImportSource::create(['name' => 'Invoices Run']);
    Contact::factory()->create(['email' => 'alice@example.com']);

    $path = orgSentinelCsv([
        ['Email', 'Invoice #', 'Item', 'Item amount', 'Bill To'],
        ['alice@example.com', 'INV-100', 'Sponsorship', '500.00', 'BillTo Corp'],
    ]);

    $log = orgSentinelImportLog('invoice_detail', $path, [
        'Email'        => 'contact:email',
        'Invoice #'    => 'invoice:invoice_number',
        'Item'         => 'invoice:item',
        'Item amount'  => 'invoice:item_amount',
        'Bill To'      => '__org_invoice_party__',
    ], [
        'Bill To' => ['type' => 'invoice_organization', 'strategy' => 'auto_create'],
    ], 1, $source->id);

    $session = orgSentinelSession($this->admin, 'invoice_detail', $source->id);
    orgSentinelRunInvoices($log, $session, $source->id);

    $billTo = Organization::where('name', 'BillTo Corp')->first();
    expect($billTo)->not->toBeNull();
    $tx = Transaction::where('invoice_number', 'INV-100')->first();
    expect($tx)->not->toBeNull();
    expect($tx->organization_id)->toBe($billTo->id);
});
