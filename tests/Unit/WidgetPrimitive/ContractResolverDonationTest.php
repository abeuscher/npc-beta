<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\User;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->resolver = app(ContractResolver::class);

    $this->crmEditor = User::factory()->create();
    $this->crmEditor->assignRole('crm_editor');

    $this->cmsEditor = User::factory()->create();
    $this->cmsEditor->assignRole('cms_editor');
});

function donationContract(array $filters = []): DataContract
{
    return new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: [
            'donation_id',
            'donation_amount',
            'donation_date',
            'donation_fund_name',
            'donation_type',
            'donation_status',
            'donation_origin',
        ],
        filters: $filters,
        model: 'donation',
    );
}

function recordDetailDonationSlot(\Illuminate\Database\Eloquent\Model $record): SlotContext
{
    return new SlotContext(new RecordDetailAmbientContext($record), publicSurface: false);
}

it('returns empty items when the authenticated user lacks view_donation', function () {
    $contact = Contact::factory()->create();
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($contact))[0];

    expect($dto)->toBe(['items' => []]);
});

it('returns empty items when the slot ambient is not record-detail', function () {
    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([donationContract()], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toBe(['items' => []]);
});

it('returns empty items when the ambient record is not a Contact', function () {
    $this->actingAs($this->crmEditor);

    $organization = \App\Models\Organization::factory()->create();
    $dto = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($organization))[0];

    expect($dto)->toBe(['items' => []]);
});

it('excludes pending donations and includes active, cancelled, and past_due', function () {
    $contact = Contact::factory()->create();

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'pending',
        'source'     => Source::STRIPE_WEBHOOK,
        'amount'     => '11.00',
        'started_at' => now()->subDays(1),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'amount'     => '22.00',
        'started_at' => now()->subDays(2),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'cancelled',
        'source'     => Source::STRIPE_WEBHOOK,
        'amount'     => '33.00',
        'started_at' => now()->subDays(3),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'status'     => 'past_due',
        'source'     => Source::STRIPE_WEBHOOK,
        'amount'     => '44.00',
        'started_at' => now()->subDays(4),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($contact))[0];

    $statuses = array_column($dto['items'], 'donation_status');
    expect($dto['items'])->toHaveCount(3)
        ->and($statuses)->toEqualCanonicalizing(['active', 'cancelled', 'past_due'])
        ->and($statuses)->not->toContain('pending');
});

it('returns donations attached to the ambient contact only — does not leak across contacts', function () {
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();

    Donation::factory()->create([
        'contact_id' => $a->id,
        'amount'     => '100.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);
    Donation::factory()->create([
        'contact_id' => $b->id,
        'amount'     => '200.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);

    $this->actingAs($this->crmEditor);

    $dtoA = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($a))[0];
    $dtoB = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($b))[0];

    expect($dtoA['items'])->toHaveCount(1)
        ->and($dtoA['items'][0]['donation_amount'])->toBe('100.00')
        ->and($dtoB['items'])->toHaveCount(1)
        ->and($dtoB['items'][0]['donation_amount'])->toBe('200.00');
});

it('orders by started_at desc by default', function () {
    $contact = Contact::factory()->create();

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '10.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDays(5),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '30.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDay(),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '20.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDays(3),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([donationContract()], recordDetailDonationSlot($contact))[0];

    expect(array_column($dto['items'], 'donation_amount'))->toBe(['30.00', '20.00', '10.00']);
});

it('honors order_by: created_at when supplied (whitelist)', function () {
    $contact = Contact::factory()->create();

    $first = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '11.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);
    $second = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '22.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDays(20),
        'created_at' => now()->subDays(1),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve(
        [donationContract(['order_by' => 'created_at'])],
        recordDetailDonationSlot($contact),
    )[0];

    expect(array_column($dto['items'], 'donation_amount'))->toBe(['22.00', '11.00']);
});

it('falls back to started_at when an unknown order_by is supplied', function () {
    $contact = Contact::factory()->create();

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '11.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDays(5),
    ]);
    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '22.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => now()->subDay(),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve(
        [donationContract(['order_by' => 'amount; DROP TABLE donations;--'])],
        recordDetailDonationSlot($contact),
    )[0];

    expect($dto['items'][0]['donation_amount'])->toBe('22.00');
});

it('clamps limit to 50 maximum and falls back to default for invalid values', function () {
    $contact = Contact::factory()->create();

    foreach (range(1, 60) as $i) {
        Donation::factory()->create([
            'contact_id' => $contact->id,
            'amount'     => sprintf('%.2f', $i),
            'status'     => 'active',
            'source'     => Source::STRIPE_WEBHOOK,
            'started_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($this->crmEditor);

    $dtoHigh = $this->resolver->resolve(
        [donationContract(['limit' => 9999])],
        recordDetailDonationSlot($contact),
    )[0];
    $dtoZero = $this->resolver->resolve(
        [donationContract(['limit' => 0])],
        recordDetailDonationSlot($contact),
    )[0];

    expect($dtoHigh['items'])->toHaveCount(50)
        ->and($dtoZero['items'])->toHaveCount(5);
});

it('hits the donations table exactly once per render and eager-loads the fund', function () {
    $contact = Contact::factory()->create();
    $fund = Fund::factory()->create();

    foreach (range(1, 3) as $i) {
        Donation::factory()->create([
            'contact_id' => $contact->id,
            'fund_id'    => $fund->id,
            'amount'     => sprintf('%.2f', $i * 10),
            'status'     => 'active',
            'source'     => Source::STRIPE_WEBHOOK,
            'started_at' => now()->subDays($i),
        ]);
    }

    $this->actingAs($this->crmEditor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->resolver->resolve([donationContract()], recordDetailDonationSlot($contact));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $donationSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "donations"')));
    $fundBatchSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select') && str_contains($sql, 'from "funds"') && str_contains($sql, '"id" in');
    }));

    expect(count($donationSelects))->toBe(1)
        ->and(count($fundBatchSelects))->toBe(1);
});
