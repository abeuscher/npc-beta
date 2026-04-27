<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects a Membership into a single-row DTO with declared concept-named fields only', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create([
        'name'             => 'Sustaining',
        'billing_interval' => 'annual',
    ]);

    $membership = Membership::factory()->create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'active',
        'starts_on'   => '2026-04-01',
        'expires_on'  => '2027-04-01',
        'amount_paid' => '250.00',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: [
            'membership_id',
            'membership_tier_name',
            'membership_billing_interval',
            'membership_status',
            'membership_starts_on',
            'membership_expires_on',
            'membership_amount_paid',
        ],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, $membership->fresh('tier'));

    expect($dto)->toHaveKey('item')
        ->and($dto['item'])->not->toBeNull()
        ->and(array_keys($dto['item']))->toEqualCanonicalizing([
            'membership_id',
            'membership_tier_name',
            'membership_billing_interval',
            'membership_status',
            'membership_starts_on',
            'membership_expires_on',
            'membership_amount_paid',
        ])
        ->and($dto['item']['membership_id'])->toBe($membership->id)
        ->and($dto['item']['membership_tier_name'])->toBe('Sustaining')
        ->and($dto['item']['membership_billing_interval'])->toBe('annual')
        ->and($dto['item']['membership_status'])->toBe('active')
        ->and($dto['item']['membership_starts_on'])->toBe('Apr 1, 2026')
        ->and($dto['item']['membership_expires_on'])->toBe('Apr 1, 2027')
        ->and($dto['item']['membership_amount_paid'])->toBe('250.00');
});

it('returns empty strings for undeclared fields like notes, stripe_session_id, external_id, and custom_fields', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();

    $membership = Membership::factory()->create([
        'contact_id'        => $contact->id,
        'tier_id'           => $tier->id,
        'status'            => 'active',
        'notes'             => 'NOT_LEAKED_NOTES_SENTINEL',
        'stripe_session_id' => 'NOT_LEAKED_STRIPE_SENTINEL',
        'external_id'       => 'NOT_LEAKED_EXTERNAL_ID_SENTINEL',
        'custom_fields'     => ['NOT_LEAKED_CUSTOM' => 'x'],
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['membership_id', 'notes', 'stripe_session_id', 'external_id', 'custom_fields'],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, $membership->fresh('tier'));

    expect($dto['item']['membership_id'])->toBe($membership->id)
        ->and($dto['item']['notes'])->toBe('')
        ->and($dto['item']['stripe_session_id'])->toBe('')
        ->and($dto['item']['external_id'])->toBe('')
        ->and($dto['item']['custom_fields'])->toBe('');
});

it('returns empty strings for null tier (defensive — schema allows tier_id NULL)', function () {
    $contact = Contact::factory()->create();

    $membership = Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => null,
        'status'     => 'active',
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['membership_tier_name', 'membership_billing_interval'],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, $membership->fresh('tier'));

    expect($dto['item']['membership_tier_name'])->toBe('')
        ->and($dto['item']['membership_billing_interval'])->toBe('');
});

it('returns an empty string for null expires_on (lifetime memberships)', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create([
        'billing_interval' => 'lifetime',
    ]);

    $membership = Membership::factory()->create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'active',
        'starts_on'   => '2026-01-15',
        'expires_on'  => null,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['membership_starts_on', 'membership_expires_on', 'membership_billing_interval'],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, $membership->fresh('tier'));

    expect($dto['item']['membership_starts_on'])->toBe('Jan 15, 2026')
        ->and($dto['item']['membership_expires_on'])->toBe('')
        ->and($dto['item']['membership_billing_interval'])->toBe('lifetime');
});

it('returns an empty string for null amount_paid', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();

    $membership = Membership::factory()->create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'active',
        'amount_paid' => null,
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['membership_amount_paid'],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, $membership->fresh('tier'));

    expect($dto['item']['membership_amount_paid'])->toBe('');
});

it('returns null item when projectOne is called with a null model', function () {
    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['membership_id'],
        model: 'membership',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $dto = app(SystemModelProjector::class)->projectOne($contract, null);

    expect($dto)->toBe(['item' => null]);
});
