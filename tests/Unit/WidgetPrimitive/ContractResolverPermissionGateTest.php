<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
use App\Models\User;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function gateNoteContract(?string $requiredPermission): DataContract
{
    return new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'],
        filters: ['limit' => 5, 'order_by' => 'occurred_at', 'direction' => 'desc'],
        model: 'note',
        requiredPermission: $requiredPermission,
    );
}

function gateMembershipContract(?string $requiredPermission): DataContract
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
        requiredPermission: $requiredPermission,
    );
}

function gateRecordDetailSlot(\Illuminate\Database\Eloquent\Model $record): SlotContext
{
    return new SlotContext(new RecordDetailAmbientContext($record), publicSurface: false);
}

it('returns empty items for a CARDINALITY_MANY contract when the user lacks the declared permission', function () {
    $contact = Contact::factory()->create();
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Hidden',
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve(
        [gateNoteContract('view_note')],
        gateRecordDetailSlot($contact),
    )[0];

    expect($dto)->toBe(['items' => []]);
});

it('returns null item for a CARDINALITY_ONE contract when the user lacks the declared permission', function () {
    $contact = Contact::factory()->create();
    $tier = MembershipTier::factory()->create();
    Membership::factory()->create([
        'contact_id' => $contact->id,
        'tier_id'    => $tier->id,
        'status'     => 'active',
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve(
        [gateMembershipContract('view_membership')],
        gateRecordDetailSlot($contact),
    )[0];

    expect($dto)->toBe(['item' => null]);
});

it('dispatches to the source arm when the user has the declared permission', function () {
    $contact = Contact::factory()->create();
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Visible',
    ]);

    $this->actingAs($this->crmEditor);

    $dto = $this->resolver->resolve(
        [gateNoteContract('view_note')],
        gateRecordDetailSlot($contact),
    )[0];

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['note_subject'])->toBe('Visible');
});

it('skips the gate entirely when requiredPermission is null', function () {
    $contact = Contact::factory()->create();
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'Ungated',
    ]);

    $this->actingAs($this->cmsEditor);

    $dto = $this->resolver->resolve(
        [gateNoteContract(null)],
        gateRecordDetailSlot($contact),
    )[0];

    expect($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0]['note_subject'])->toBe('Ungated');
});

it('fail-closes when no user is authenticated and requiredPermission is set', function () {
    $contact = Contact::factory()->create();
    Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
        'subject'      => 'GuestHidden',
    ]);

    $manyDto = $this->resolver->resolve(
        [gateNoteContract('view_note')],
        gateRecordDetailSlot($contact),
    )[0];

    $oneDto = $this->resolver->resolve(
        [gateMembershipContract('view_membership')],
        gateRecordDetailSlot($contact),
    )[0];

    expect($manyDto)->toBe(['items' => []])
        ->and($oneDto)->toBe(['item' => null]);
});
