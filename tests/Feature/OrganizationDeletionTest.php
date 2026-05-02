<?php

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\OrganizationResource\RelationManagers\EventsSponsoredRelationManager;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

it('registers only the EventsSponsored panel on OrganizationResource', function () {
    $relations = OrganizationResource::getRelations();

    expect($relations)->toBe([EventsSponsoredRelationManager::class]);
});

it('guardDeletion returns true for an Org with no related records', function () {
    $org = Organization::factory()->create();

    expect(OrganizationResource::guardDeletion($org))->toBeTrue();
});

it('guardDeletion returns false and surfaces a notification when the Org has related records', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Donation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'type'            => 'one_off',
        'amount'          => 100,
        'currency'        => 'usd',
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);

    Contact::factory()->count(2)->create(['organization_id' => $org->id]);

    expect(OrganizationResource::guardDeletion($org))->toBeFalse();
});

it('counts each related-records bucket', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Contact::factory()->count(2)->create(['organization_id' => $org->id]);
    Donation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'type'            => 'one_off',
        'amount'          => 50,
        'currency'        => 'usd',
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);
    Membership::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);
    Event::factory()->create(['sponsor_organization_id' => $org->id]);
    Transaction::create([
        'type'            => 'payment',
        'direction'       => 'in',
        'status'          => 'completed',
        'source'          => Source::IMPORT,
        'amount'          => 100,
        'occurred_at'     => now(),
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
    ]);

    // 2 members + 1 donation + 1 membership + 1 event + 1 transaction = 6
    expect(OrganizationResource::countRelatedRecords($org))->toBe(6);
});

it('force-delete cascades SET NULL on every related record without deleting them', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create(['organization_id' => $org->id]);

    $donation = Donation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'type'            => 'one_off',
        'amount'          => 100,
        'currency'        => 'usd',
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);

    $membership = Membership::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);

    $event = Event::factory()->create(['sponsor_organization_id' => $org->id]);

    $registration = EventRegistration::create([
        'event_id'        => $event->id,
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'name'            => 'Alice Tester',
        'email'           => 'alice-test@example.com',
        'status'          => 'registered',
        'source'          => Source::IMPORT,
        'registered_at'   => now(),
    ]);

    $transaction = Transaction::create([
        'type'            => 'payment',
        'direction'       => 'in',
        'status'          => 'completed',
        'source'          => Source::IMPORT,
        'amount'          => 100,
        'occurred_at'     => now(),
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
    ]);

    $org->forceDelete();

    expect(Organization::withTrashed()->find($org->id))->toBeNull();

    // Every related record survives, with org link nulled.
    expect(Contact::find($contact->id)->organization_id)->toBeNull();
    expect(Donation::find($donation->id)->organization_id)->toBeNull();
    expect(Membership::find($membership->id)->organization_id)->toBeNull();
    expect(Event::find($event->id)->sponsor_organization_id)->toBeNull();
    expect(EventRegistration::find($registration->id)->organization_id)->toBeNull();
    expect(Transaction::find($transaction->id)->organization_id)->toBeNull();
});

it('Organization::donations relation is now functional', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Donation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'type'            => 'one_off',
        'amount'          => 25,
        'currency'        => 'usd',
        'status'          => 'active',
        'source'          => Source::IMPORT,
    ]);

    expect($org->donations()->count())->toBe(1);
});
