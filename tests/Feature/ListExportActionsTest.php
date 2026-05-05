<?php

use App\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Filament\Resources\DonationResource\Pages\ListDonations;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventResource\Pages\ViewRegistrations;
use App\Filament\Resources\FundResource\Pages\ListFunds;
use App\Filament\Resources\MembershipResource\Pages\ListMemberships;
use App\Filament\Resources\NoteResource\Pages\ListNotes;
use App\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Fund;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

function downloadedContent(\Livewire\Features\SupportTesting\Testable $test): string
{
    return base64_decode(data_get($test->effects, 'download.content'));
}

function parseCsv(string $body): array
{
    return array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($body)));
}

// ── Contacts ─────────────────────────────────────────────────────────────

it('exports contacts as CSV', function () {
    Contact::factory()->create(['first_name' => 'Ada', 'email' => 'ada@example.com']);

    $test = Livewire::actingAs($this->admin)
        ->test(ListContacts::class)
        ->callAction('exportContacts');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('first_name', 'email');
    expect($rows[1])->toContain('Ada', 'ada@example.com');
});

it('exports contacts as JSON', function () {
    Contact::factory()->create(['first_name' => 'Ada', 'email' => 'ada@example.com']);

    $test = Livewire::actingAs($this->admin)
        ->test(ListContacts::class)
        ->callAction('exportContactsJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data)->toBeArray()->toHaveCount(1);
    expect($data[0]['first_name'])->toBe('Ada');
    expect($data[0]['email'])->toBe('ada@example.com');
});

// ── Organizations ────────────────────────────────────────────────────────

it('exports organizations as CSV with custom field columns', function () {
    CustomFieldDef::create([
        'model_type' => 'organization',
        'handle'     => 'industry',
        'label'      => 'Industry',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Organization::factory()->create([
        'name'          => 'Acme Corp',
        'custom_fields' => ['industry' => 'Tech'],
    ]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListOrganizations::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('name');
    expect($rows[0])->toContain('Industry');
    expect($rows[1])->toContain('Acme Corp', 'Tech');
});

it('exports organizations as JSON with nested custom fields', function () {
    CustomFieldDef::create([
        'model_type' => 'organization',
        'handle'     => 'industry',
        'label'      => 'Industry',
        'field_type' => 'text',
        'sort_order' => 1,
    ]);

    Organization::factory()->create([
        'name'          => 'Acme Corp',
        'custom_fields' => ['industry' => 'Tech'],
    ]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListOrganizations::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['name'])->toBe('Acme Corp');
    expect($data[0]['custom_fields'])->toBe(['industry' => 'Tech']);
});

// ── Donations ────────────────────────────────────────────────────────────

it('exports donations as CSV with contact_email column', function () {
    $contact = Contact::factory()->create(['email' => 'don@example.com']);
    Donation::factory()->create(['contact_id' => $contact->id, 'amount' => 250]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListDonations::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('amount', 'contact_email');
    expect($rows[1])->toContain('don@example.com');
});

it('exports donations as JSON resolving contact_email through the relation', function () {
    $contact = Contact::factory()->create(['email' => 'don@example.com']);
    Donation::factory()->create(['contact_id' => $contact->id, 'amount' => 250]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListDonations::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['contact_email'])->toBe('don@example.com');
    expect((float) $data[0]['amount'])->toBe(250.0);
});

// ── Events ───────────────────────────────────────────────────────────────

it('exports events as CSV', function () {
    Event::factory()->create(['title' => 'Annual Gala', 'starts_at' => now()->addDays(7)]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListEvents::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('title', 'starts_at');
    expect($rows[1])->toContain('Annual Gala');
});

it('exports events as JSON', function () {
    Event::factory()->create(['title' => 'Annual Gala', 'starts_at' => now()->addDays(7)]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListEvents::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['title'])->toBe('Annual Gala');
});

// ── Event registrations (event-scoped) ───────────────────────────────────

it('exports event registrations as CSV scoped to the parent event', function () {
    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    EventRegistration::factory()->create(['event_id' => $eventA->id, 'name' => 'Alice']);
    EventRegistration::factory()->create(['event_id' => $eventB->id, 'name' => 'Bob']);

    $test = Livewire::actingAs($this->admin)
        ->test(ViewRegistrations::class, ['record' => $eventA])
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('name', 'email', 'event_id');
    expect(count($rows))->toBe(2);
    expect($rows[1])->toContain('Alice');
});

it('exports event registrations as JSON scoped to the parent event', function () {
    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    EventRegistration::factory()->create(['event_id' => $eventA->id, 'name' => 'Alice']);
    EventRegistration::factory()->create(['event_id' => $eventB->id, 'name' => 'Bob']);

    $test = Livewire::actingAs($this->admin)
        ->test(ViewRegistrations::class, ['record' => $eventA])
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('Alice');
});

// ── Memberships ──────────────────────────────────────────────────────────

it('exports memberships as CSV resolving tier through the relation', function () {
    Membership::factory()->create(['status' => 'active', 'amount_paid' => 100]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListMemberships::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('tier', 'status', 'amount_paid');
});

it('exports memberships as JSON', function () {
    Membership::factory()->create(['status' => 'active', 'amount_paid' => 100]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListMemberships::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['status'])->toBe('active');
    expect((float) $data[0]['amount_paid'])->toBe(100.0);
});

// ── Transactions ─────────────────────────────────────────────────────────

it('exports transactions as CSV', function () {
    Transaction::factory()->create(['amount' => 75, 'direction' => 'in', 'status' => 'completed']);

    $test = Livewire::actingAs($this->admin)
        ->test(ListTransactions::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('amount', 'direction', 'status');
});

it('exports transactions as JSON', function () {
    Transaction::factory()->create(['amount' => 75, 'direction' => 'in', 'status' => 'completed']);

    $test = Livewire::actingAs($this->admin)
        ->test(ListTransactions::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['direction'])->toBe('in');
    expect($data[0]['status'])->toBe('completed');
});

// ── Funds ────────────────────────────────────────────────────────────────

it('exports funds as CSV', function () {
    Fund::factory()->create(['name' => 'General Fund', 'is_active' => true]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListFunds::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('name', 'code', 'is_active');
    expect($rows[1])->toContain('General Fund');
});

it('exports funds as JSON', function () {
    Fund::factory()->create(['name' => 'General Fund']);

    $test = Livewire::actingAs($this->admin)
        ->test(ListFunds::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['name'])->toBe('General Fund');
});

// ── Campaigns ────────────────────────────────────────────────────────────

it('exports campaigns as CSV', function () {
    Campaign::factory()->create(['name' => 'Spring 2026', 'goal_amount' => 50000]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListCampaigns::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('name', 'goal_amount');
    expect($rows[1])->toContain('Spring 2026');
});

it('exports campaigns as JSON', function () {
    Campaign::factory()->create(['name' => 'Spring 2026', 'goal_amount' => 50000]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListCampaigns::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['name'])->toBe('Spring 2026');
});

// ── Notes ────────────────────────────────────────────────────────────────

it('exports notes as CSV with contact_email synthetic column', function () {
    $contact = Contact::factory()->create(['email' => 'noted@example.com']);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Followup call',
        'type'         => 'call',
    ]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListNotes::class)
        ->callAction('exportCsv');

    $test->assertFileDownloaded(null, null, 'text/csv');

    $rows = parseCsv(downloadedContent($test));
    expect($rows[0])->toContain('subject', 'contact_email', 'organization_name');
    expect($rows[1])->toContain('noted@example.com');
});

it('exports notes as JSON resolving contact identity through the polymorphic relation', function () {
    $contact = Contact::factory()->create(['email' => 'noted@example.com']);
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Followup call',
        'type'         => 'call',
    ]);

    $test = Livewire::actingAs($this->admin)
        ->test(ListNotes::class)
        ->callAction('exportJson');

    $test->assertFileDownloaded(null, null, 'application/json');

    $data = json_decode(downloadedContent($test), true);
    expect($data[0]['subject'])->toBe('Followup call');
    expect($data[0]['contact_email'])->toBe('noted@example.com');
    expect($data[0]['organization_name'])->toBeNull();
});

// ── Permission gating (Gate-level) ───────────────────────────────────────
//
// The action's `->hidden(fn () => ! auth()->user()?->can('view_any_X'))` is
// declarative UI sugar; the load-bearing gate is the resource policy's
// `viewAny`. A user without the permission can't even access the list page,
// so the action never renders. Verify the underlying capability check.

it('denies the view_any capability to users without role permissions', function (string $ability) {
    $regular = User::factory()->create();

    expect($regular->can($ability))->toBeFalse();
})->with([
    'view_any_contact',
    'view_any_organization',
    'view_any_donation',
    'view_any_event',
    'view_any_membership',
    'view_any_transaction',
    'view_any_fund',
    'view_any_campaign',
    'view_any_note',
]);
