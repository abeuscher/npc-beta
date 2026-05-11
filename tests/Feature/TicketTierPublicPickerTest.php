<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Free path (zero tiers / single-tier $0 / picked $0 tier) ─────────────────

it('zero-tier event accepts registration without a quantities payload', function () {
    $event = Event::factory()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $reg = EventRegistration::where('email', 'jane@example.com')->first();
    expect($reg)->not->toBeNull()
        ->and($reg->ticket_tier_id)->toBeNull()
        ->and($reg->quantity)->toBe(1);
});

it('single-tier free event sets ticket_tier_id and quantity on the registration', function () {
    $event = Event::factory()->withCapacity(50)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $reg = EventRegistration::where('email', 'jane@example.com')->first();
    expect($reg->ticket_tier_id)->toBe($tier->id)
        ->and($reg->quantity)->toBe(1);
});

// ── Validation: quantities keys must belong to the event ─────────────────────

it('rejects a quantities key from a different event', function () {
    $eventA  = Event::factory()->withCapacity(50)->create(['status' => 'published']);
    $eventB  = Event::factory()->withCapacity(50)->create(['status' => 'published']);
    $foreign = $eventB->ticketTiers()->first();

    $this->post(route('events.register', $eventA->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$foreign->id => 1],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities');

    expect(EventRegistration::count())->toBe(0);
});

it('requires a non-empty quantities map when the event has tiers', function () {
    $event = Event::factory()->withCapacity(50)->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities');

    expect(EventRegistration::count())->toBe(0);
});

it('rejects a quantities map where every entry is zero', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $a     = TicketTier::factory()->for($event)->create(['name' => 'General', 'sort_order' => 0]);
    $b     = TicketTier::factory()->for($event)->create(['name' => 'VIP',     'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$a->id => 0, $b->id => 0],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities');

    expect(EventRegistration::count())->toBe(0);
});

// ── Paid path (chosen tier price > 0 routes to Stripe) ───────────────────────

it('routes to checkout when the chosen tier is paid', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $event = Event::factory()->paid(25.00)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    // Free path did not land a registration; the redirect handed off to checkout.
    expect(EventRegistration::where('email', 'jane@example.com')->where('source', 'human')->count())->toBe(0);
});

it('checkout rejects a $0-only quantities map with a register-this-is-free error', function () {
    $event = Event::factory()->withCapacity(20)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    $this->post(route('events.checkout', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1],
    ])->assertSessionHasErrors('register');
});

it('checkout rejects a quantities key from a different event', function () {
    $eventA  = Event::factory()->paid(25.00)->create(['status' => 'published']);
    $eventB  = Event::factory()->paid(50.00)->create(['status' => 'published']);
    $foreign = $eventB->ticketTiers()->first();

    $this->post(route('events.checkout', $eventA->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$foreign->id => 1],
    ])->assertSessionHasErrors('quantities');
});

// ── Per-tier capacity check ──────────────────────────────────────────────────

it('rejects registration when the chosen tier is at capacity', function () {
    $event = Event::factory()->withCapacity(1)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'status'         => 'registered',
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities');

    expect(EventRegistration::count())->toBe(1);
});

it('allows registration on a non-full tier even when a sibling tier is full', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $full  = TicketTier::factory()->for($event)->create(['name' => 'General', 'capacity' => 1, 'sort_order' => 0]);
    $open  = TicketTier::factory()->for($event)->create(['name' => 'VIP', 'capacity' => 5, 'sort_order' => 1]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $full->id,
        'status'         => 'registered',
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$open->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    expect(EventRegistration::where('email', 'jane@example.com')->first()->ticket_tier_id)->toBe($open->id);
});

// ── Notes field passthrough ──────────────────────────────────────────────────

it('persists the notes field when submitted with the free registration', function () {
    $event = Event::factory()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'notes'       => 'Bringing a +1; vegetarian; wheelchair accessible please.',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $reg = EventRegistration::where('email', 'jane@example.com')->first();
    expect($reg->notes)->toBe('Bringing a +1; vegetarian; wheelchair accessible please.');
});

it('caps the notes field at 2000 chars', function () {
    $event = Event::factory()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'notes'       => str_repeat('x', 2001),
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('notes');

    expect(EventRegistration::count())->toBe(0);
});

// ── Contract / template surface ──────────────────────────────────────────────

it('projects tiers onto the EventRegistration contract with remaining_capacity', function () {
    $event = Event::factory()->paid(25.00, 10)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    $contract = (new \App\Widgets\EventRegistration\EventRegistrationDefinition())
        ->dataContract(['event_slug' => $event->slug]);

    $context = new \App\WidgetPrimitive\SlotContext(new \App\WidgetPrimitive\AmbientContexts\PageAmbientContext());
    $dto = app(\App\WidgetPrimitive\ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto['item'])->toHaveKey('tiers')
        ->and($dto['item']['tiers'])->toHaveCount(1)
        ->and($dto['item']['tiers'][0]['id'])->toBe($tier->id)
        ->and($dto['item']['tiers'][0]['name'])->toBe('General')
        ->and((float) $dto['item']['tiers'][0]['price'])->toBe(25.00)
        ->and($dto['item']['tiers'][0]['capacity'])->toBe(10)
        ->and($dto['item']['tiers'][0]['remaining_capacity'])->toBe(10)
        ->and($dto['item']['tiers'][0]['is_at_capacity'])->toBeFalse();
});
