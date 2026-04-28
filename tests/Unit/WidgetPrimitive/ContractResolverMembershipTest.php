<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\User;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
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

function membershipContract(): DataContract
{
    return new DataContract(
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
        requiredPermission: 'view_membership',
    );
}

function recordDetailMembershipSlot(\Illuminate\Database\Eloquent\Model $record): SlotContext
{
    return new SlotContext(new RecordDetailAmbientContext($record), publicSurface: false);
}

it('returns null item when the authenticated user lacks view_membership', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'active',
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($contact))[0];

    expect($dto)->toBe(['item' => null]);
});

it('returns null item when the slot ambient is not record-detail', function () {
    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([membershipContract()], new SlotContext(new PageAmbientContext()))[0];

    expect($dto)->toBe(['item' => null]);
});

it('returns null item when the ambient record is not a Contact', function () {
    $this->actingAs($this->crmEditor);

    $organization = \App\Models\Organization::factory()->create();
    $dto = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($organization))[0];

    expect($dto)->toBe(['item' => null]);
});

it('returns the active membership only — does not return pending, expired, or cancelled rows', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create([
        'name'             => 'Annual',
        'billing_interval' => 'annual',
    ]);

    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'pending',
        'starts_on'  => now()->subYear(),
    ]);
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'expired',
        'starts_on'  => now()->subYears(2),
    ]);
    $active = Membership::factory()->create([
        'contact_id'  => $contact->id,
        'tier_id'     => $tier->id,
        'status'      => 'active',
        'starts_on'   => now()->subMonths(3),
        'expires_on'  => now()->addMonths(9),
        'amount_paid' => '125.00',
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($contact))[0];

    expect($dto)->toHaveKey('item')
        ->and($dto['item'])->not->toBeNull()
        ->and($dto['item']['membership_id'])->toBe($active->id)
        ->and($dto['item']['membership_status'])->toBe('active')
        ->and($dto['item']['membership_tier_name'])->toBe('Annual')
        ->and($dto['item']['membership_billing_interval'])->toBe('annual');
});

it('returns null item when the contact has no active membership', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'expired',
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($contact))[0];

    expect($dto)->toBe(['item' => null]);
});

it('returns the membership attached to the ambient contact only — does not leak across contacts', function () {
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();
    $tierA = MembershipTier::factory()->create(['name' => 'Patron']);
    $tierB = MembershipTier::factory()->create(['name' => 'Standard']);

    Membership::factory()->create([
        'contact_id' => $a->id,
        'tier_id'    => $tierA->id,
        'status'     => 'active',
    ]);
    Membership::factory()->create([
        'contact_id' => $b->id,
        'tier_id'    => $tierB->id,
        'status'     => 'active',
    ]);

    $this->actingAs($this->crmEditor);

    $dtoA = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($a))[0];
    $dtoB = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($b))[0];

    expect($dtoA['item']['membership_tier_name'])->toBe('Patron')
        ->and($dtoB['item']['membership_tier_name'])->toBe('Standard');
});

it('orders by starts_on desc when the contact has multiple active rows', function () {
    $contact = Contact::factory()->create();
    $oldTier = MembershipTier::factory()->create(['name' => 'Old']);
    $newTier = MembershipTier::factory()->create(['name' => 'New']);

    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $oldTier->id,
        'status'     => 'active',
        'starts_on'  => now()->subYears(2),
    ]);
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $newTier->id,
        'status'     => 'active',
        'starts_on'  => now()->subMonths(2),
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($contact))[0];

    expect($dto['item']['membership_tier_name'])->toBe('New');
});

it('hits the memberships table exactly once per render and eager-loads the tier', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'active',
    ]);

    $this->actingAs($this->crmEditor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->resolver->resolve([membershipContract()], recordDetailMembershipSlot($contact));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $membershipSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "memberships"')));
    $tierBatchSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select') && str_contains($sql, 'from "membership_tiers"') && str_contains($sql, '"id" in');
    }));

    expect(count($membershipSelects))->toBe(1)
        ->and(count($tierBatchSelects))->toBe(1);
});
