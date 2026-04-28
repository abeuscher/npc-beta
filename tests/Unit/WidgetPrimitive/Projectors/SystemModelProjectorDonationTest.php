<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects a Donation collection into a row-set DTO with declared concept-named fields only', function () {
    $contact = Contact::factory()->create();
    $fund = Fund::factory()->create(['name' => 'General Fund']);

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => '50.00',
        'type'       => 'one_off',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
        'started_at' => '2026-04-22 14:30:00',
    ]);

    $contract = new DataContract(
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
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$donation->fresh('fund')]));

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(1)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing([
            'donation_id',
            'donation_amount',
            'donation_date',
            'donation_fund_name',
            'donation_type',
            'donation_status',
            'donation_origin',
        ])
        ->and($dto['items'][0]['donation_id'])->toBe($donation->id)
        ->and($dto['items'][0]['donation_amount'])->toBe('50.00')
        ->and($dto['items'][0]['donation_date'])->toBe('Apr 22, 2026')
        ->and($dto['items'][0]['donation_fund_name'])->toBe('General Fund')
        ->and($dto['items'][0]['donation_type'])->toBe('one_off')
        ->and($dto['items'][0]['donation_status'])->toBe('active')
        ->and($dto['items'][0]['donation_origin'])->toBe('Stripe');
});

it('returns empty strings for undeclared fields like stripe_subscription_id, external_id, custom_fields', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id'             => $contact->id,
        'amount'                 => '25.00',
        'status'                 => 'active',
        'source'                 => Source::STRIPE_WEBHOOK,
        'stripe_subscription_id' => 'NOT_LEAKED_SUB_SENTINEL',
        'external_id'            => 'NOT_LEAKED_EXTERNAL_ID_SENTINEL',
        'custom_fields'          => ['NOT_LEAKED_CUSTOM_SENTINEL' => 'x'],
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['donation_id', 'stripe_subscription_id', 'external_id', 'custom_fields'],
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$donation]));

    expect($dto['items'][0]['donation_id'])->toBe($donation->id)
        ->and($dto['items'][0]['stripe_subscription_id'])->toBe('')
        ->and($dto['items'][0]['external_id'])->toBe('')
        ->and($dto['items'][0]['custom_fields'])->toBe('');
});

it('produces correct donation_origin labels for each accepted Source value', function () {
    $contact = Contact::factory()->create();

    $stripe = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '10.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);
    $imported = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '20.00',
        'status'     => 'active',
        'source'     => Source::IMPORT,
    ]);
    $manual = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '30.00',
        'status'     => 'active',
        'source'     => Source::HUMAN,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['donation_origin'],
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$stripe, $imported, $manual]));

    expect($dto['items'][0]['donation_origin'])->toBe('Stripe')
        ->and($dto['items'][1]['donation_origin'])->toBe('Imported')
        ->and($dto['items'][2]['donation_origin'])->toBe('Manual');
});

it('returns empty string for an unknown source value (defensive)', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->make([
        'contact_id' => $contact->id,
        'amount'     => '15.00',
        'status'     => 'active',
        'source'     => 'mystery_origin',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['donation_origin'],
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$donation]));

    expect($dto['items'][0]['donation_origin'])->toBe('');
});

it('returns empty string for donation_fund_name when fund_id is null (unrestricted)', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => null,
        'amount'     => '25.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['donation_fund_name'],
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$donation->fresh('fund')]));

    expect($dto['items'][0]['donation_fund_name'])->toBe('');
});

it('produces canonical "50.00" decimal shape for donation_amount', function () {
    $contact = Contact::factory()->create();

    $donation = Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => '50.00',
        'status'     => 'active',
        'source'     => Source::STRIPE_WEBHOOK,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['donation_amount'],
        model: 'donation',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect([$donation]));

    expect($dto['items'][0]['donation_amount'])->toBe('50.00');
});
