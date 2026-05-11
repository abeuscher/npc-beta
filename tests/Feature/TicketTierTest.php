<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Relations ────────────────────────────────────────────────────────────────

it('an event has many ticket tiers', function () {
    $event = Event::factory()->create();
    TicketTier::factory()->for($event)->create(['name' => 'General',  'sort_order' => 0]);
    TicketTier::factory()->for($event)->create(['name' => 'VIP',      'sort_order' => 1, 'price' => 100]);

    expect($event->ticketTiers)->toHaveCount(2)
        ->and($event->ticketTiers->pluck('name')->all())->toEqual(['General', 'VIP']);
});

it('a ticket tier belongs to one event and has many registrations', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create();
    EventRegistration::factory()->count(2)->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
    ]);

    expect($tier->event->is($event))->toBeTrue()
        ->and($tier->registrations)->toHaveCount(2);
});

it('a registration belongs to one ticket tier', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create();
    $reg   = EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
    ]);

    expect($reg->ticketTier->is($tier))->toBeTrue();
});

it('a registration may have a null ticket_tier (truly free + uncapped event)', function () {
    $event = Event::factory()->create();
    $reg   = EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => null,
    ]);

    expect($reg->ticket_tier_id)->toBeNull()
        ->and($reg->ticketTier)->toBeNull();
});

it('deleting an event cascades to its ticket tiers', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create();
    $tierId = $tier->id;

    $event->delete();

    expect(TicketTier::find($tierId))->toBeNull();
});

it('deleting a ticket tier nulls the FK on linked registrations', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create();
    $reg   = EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
    ]);

    $tier->delete();

    expect($reg->fresh()->ticket_tier_id)->toBeNull();
});

// ── Capacity ─────────────────────────────────────────────────────────────────

it('TicketTier::isAtCapacity returns false when capacity is null', function () {
    $tier = TicketTier::factory()->create(['capacity' => null]);
    expect($tier->isAtCapacity())->toBeFalse();
});

it('TicketTier::isAtCapacity counts non-cancelled registrations', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 2]);

    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $tier->id, 'status' => 'registered']);
    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $tier->id, 'status' => 'cancelled']);
    expect($tier->fresh()->isAtCapacity())->toBeFalse();

    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $tier->id, 'status' => 'pending']);
    expect($tier->fresh()->isAtCapacity())->toBeTrue();
});

it('Event::isAtCapacity is false when the event has no tiers', function () {
    $event = Event::factory()->create();
    expect($event->isAtCapacity())->toBeFalse();
});

it('Event::isAtCapacity is true only when every tier is at capacity', function () {
    $event   = Event::factory()->create();
    $general = TicketTier::factory()->for($event)->create(['name' => 'General', 'capacity' => 1, 'sort_order' => 0]);
    $vip     = TicketTier::factory()->for($event)->create(['name' => 'VIP',     'capacity' => 1, 'sort_order' => 1]);

    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $general->id, 'status' => 'registered']);
    expect($event->fresh()->isAtCapacity())->toBeFalse();

    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $vip->id, 'status' => 'registered']);
    expect($event->fresh()->isAtCapacity())->toBeTrue();
});

it('Event::isAtCapacity stays false while any tier has unlimited capacity', function () {
    $event = Event::factory()->create();
    TicketTier::factory()->for($event)->create(['capacity' => 1, 'sort_order' => 0]);
    TicketTier::factory()->for($event)->create(['capacity' => null, 'sort_order' => 1]);

    EventRegistration::factory()->create(['event_id' => $event->id, 'ticket_tier_id' => $event->ticketTiers()->first()->id, 'status' => 'registered']);
    expect($event->fresh()->isAtCapacity())->toBeFalse();
});

// ── is_free ──────────────────────────────────────────────────────────────────

it('Event::is_free is true when the event has no tiers', function () {
    $event = Event::factory()->create();
    expect($event->is_free)->toBeTrue();
});

it('Event::is_free is true when every tier has price 0', function () {
    $event = Event::factory()->create();
    TicketTier::factory()->for($event)->create(['price' => 0]);
    expect($event->fresh()->is_free)->toBeTrue();
});

it('Event::is_free is false when any tier has price > 0', function () {
    $event = Event::factory()->create();
    TicketTier::factory()->for($event)->create(['price' => 0]);
    TicketTier::factory()->for($event)->create(['price' => 50, 'sort_order' => 1]);
    expect($event->fresh()->is_free)->toBeFalse();
});
