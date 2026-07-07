<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('flips the event to sold out when a registration fills the last tier', function () {
    $event = Event::factory()->create(['sold_out' => false]);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 2]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'quantity'       => 1,
    ]);

    expect($event->fresh()->sold_out)->toBeFalse();

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'quantity'       => 1,
    ]);

    expect($event->fresh()->sold_out)->toBeTrue();
});

it('does not flip sold out while any tier still has capacity', function () {
    $event = Event::factory()->create(['sold_out' => false]);
    $full  = TicketTier::factory()->for($event)->create(['capacity' => 1]);
    TicketTier::factory()->for($event)->create(['capacity' => 5]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $full->id,
        'quantity'       => 1,
    ]);

    expect($event->fresh()->sold_out)->toBeFalse();
});

it('never flips sold out for an uncapped tier', function () {
    $event = Event::factory()->create(['sold_out' => false]);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => null]);

    EventRegistration::factory()->count(3)->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'quantity'       => 10,
    ]);

    expect($event->fresh()->sold_out)->toBeFalse();
});

it('never flips sold out for an event with no tiers', function () {
    $event = Event::factory()->create(['sold_out' => false]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => null,
    ]);

    expect($event->fresh()->sold_out)->toBeFalse();
});

it('counts multi-quantity registrations toward capacity', function () {
    $event = Event::factory()->create(['sold_out' => false]);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 4]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'quantity'       => 4,
    ]);

    expect($event->fresh()->sold_out)->toBeTrue();
});
