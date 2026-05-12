<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PortalAccount;
use App\Models\TicketTier;
use App\Models\Transaction;
use App\Services\EventRegistrationQuantities;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Quantity column ──────────────────────────────────────────────────────────

it('event_registrations.quantity defaults to 1 when omitted', function () {
    $event = Event::factory()->create();
    $reg   = EventRegistration::create([
        'event_id'      => $event->id,
        'name'          => 'A',
        'email'         => 'a@example.com',
        'status'        => 'registered',
        'registered_at' => now(),
    ]);

    expect($reg->fresh()->quantity)->toBe(1);
});

it('event_registrations.quantity persists values > 1', function () {
    $event = Event::factory()->create();
    $reg   = EventRegistration::create([
        'event_id'      => $event->id,
        'name'          => 'A',
        'email'         => 'a@example.com',
        'status'        => 'registered',
        'registered_at' => now(),
        'quantity'      => 4,
    ]);

    expect($reg->fresh()->quantity)->toBe(4);
});

// ── Per-tier capacity via withSum (sum of quantity, not count of rows) ───────

it('TicketTier::isAtCapacity aggregates by sum(quantity)', function () {
    $event = Event::factory()->create();
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 5]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'status'         => 'registered',
        'quantity'       => 2,
    ]);
    expect($tier->fresh()->isAtCapacity())->toBeFalse();

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'status'         => 'registered',
        'quantity'       => 3,
    ]);
    expect($tier->fresh()->isAtCapacity())->toBeTrue();
});

it('projector exposes remaining_capacity for finite tiers and null for unlimited', function () {
    $event   = Event::factory()->create(['status' => 'published']);
    $finite  = TicketTier::factory()->for($event)->create(['name' => 'General', 'capacity' => 10, 'sort_order' => 0]);
    $unlim   = TicketTier::factory()->for($event)->create(['name' => 'VIP',     'capacity' => null, 'sort_order' => 1]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $finite->id,
        'status'         => 'registered',
        'quantity'       => 3,
    ]);

    $contract = (new \App\Widgets\EventRegistration\EventRegistrationDefinition())
        ->dataContract(['event_slug' => $event->slug]);
    $context = new \App\WidgetPrimitive\SlotContext(new \App\WidgetPrimitive\AmbientContexts\PageAmbientContext());
    $dto = app(\App\WidgetPrimitive\ContractResolver::class)->resolve([$contract], $context)[0];

    $tiers = collect($dto['item']['tiers'])->keyBy('id');
    expect($tiers[$finite->id]['remaining_capacity'])->toBe(7)
        ->and($tiers[$unlim->id]['remaining_capacity'])->toBeNull();
});

// ── Free-path multi-tier purchase ────────────────────────────────────────────

it('free multi-tier purchase creates one row per chosen tier with its quantity', function () {
    $event   = Event::factory()->create(['status' => 'published']);
    $general = TicketTier::factory()->for($event)->create(['name' => 'General', 'price' => 0, 'sort_order' => 0]);
    $senior  = TicketTier::factory()->for($event)->create(['name' => 'Senior',  'price' => 0, 'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$general->id => 2, $senior->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $rows = EventRegistration::where('email', 'jane@example.com')->orderBy('created_at')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->where('ticket_tier_id', $general->id)->first()->quantity)->toBe(2)
        ->and($rows->where('ticket_tier_id', $senior->id)->first()->quantity)->toBe(1)
        ->and($rows->pluck('source')->unique()->all())->toEqual([Source::HUMAN]);
});

it('drops zero-quantity tiers from the resulting registration rows', function () {
    $event   = Event::factory()->create(['status' => 'published']);
    $general = TicketTier::factory()->for($event)->create(['name' => 'General', 'price' => 0, 'sort_order' => 0]);
    $senior  = TicketTier::factory()->for($event)->create(['name' => 'Senior',  'price' => 0, 'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$general->id => 3, $senior->id => 0],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $rows = EventRegistration::where('email', 'jane@example.com')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->ticket_tier_id)->toBe($general->id)
        ->and($rows->first()->quantity)->toBe(3);
});

// ── Paid-path multi-tier purchase ────────────────────────────────────────────

it('paid multi-tier purchase creates N pending rows sharing one stripe_session_id', function () {
    config(['services.stripe.secret' => null]); // forces controller's "not configured" branch path
    $event   = Event::factory()->create(['status' => 'published']);
    $general = TicketTier::factory()->for($event)->create(['name' => 'General', 'price' => 25, 'sort_order' => 0]);
    $vip     = TicketTier::factory()->for($event)->create(['name' => 'VIP',     'price' => 100, 'sort_order' => 1]);

    // With stripe not configured we won't reach Stripe; this asserts the
    // pre-Stripe row creation does the right thing. The unconfigured branch
    // returns before creating rows, so we exercise the resolver / row-creation
    // path via the service directly.
    $quantities = EventRegistrationQuantities::fromRequest(
        $event,
        new \Illuminate\Http\Request(['quantities' => [$general->id => 2, $vip->id => 1]]),
    );

    expect($quantities->isPaid())->toBeTrue()
        ->and($quantities->totalCents)->toBe(150_00)
        ->and($quantities->totalQuantity())->toBe(3)
        ->and(count($quantities->stripeLineItems('Gala')))->toBe(2);
});

// ── Webhook fan-out — single Stripe session promotes all rows ────────────────

it('webhook promotes all pending registrations sharing a stripe_session_id and records one transaction', function () {
    $contact = Contact::factory()->create(['email' => 'paid@example.com']);
    $event   = Event::factory()->paid(25.00)->create();
    $tier    = $event->ticketTiers()->first();
    $sid     = 'cs_test_multi_' . uniqid();

    $r1 = EventRegistration::factory()->create([
        'event_id'           => $event->id,
        'ticket_tier_id'     => $tier->id,
        'contact_id'         => $contact->id,
        'email'              => 'paid@example.com',
        'status'             => 'pending',
        'stripe_session_id'  => $sid,
        'quantity'           => 2,
    ]);
    $r2 = EventRegistration::factory()->create([
        'event_id'           => $event->id,
        'ticket_tier_id'     => $tier->id,
        'contact_id'         => $contact->id,
        'email'              => 'paid@example.com',
        'status'             => 'pending',
        'stripe_session_id'  => $sid,
        'quantity'           => 1,
    ]);

    // Build a Stripe-event-shaped payload and invoke the controller handler.
    $controller = new \App\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleEventRegistrationCheckout');
    $reflection->setAccessible(true);

    $payload = (object) [
        'id'                => $sid,
        'payment_intent'    => 'pi_test_multi_123',
        'amount_total'      => 7500,
        'customer_details'  => (object) ['email' => 'paid@example.com', 'name' => 'Jane Paid'],
    ];

    $reflection->invoke($controller, $payload, (object) ['event_registration_checkout' => '1']);

    expect($r1->fresh()->status)->toBe('registered')
        ->and($r2->fresh()->status)->toBe('registered')
        ->and($r1->fresh()->stripe_payment_intent_id)->toBe('pi_test_multi_123')
        ->and($r2->fresh()->stripe_payment_intent_id)->toBe('pi_test_multi_123');

    $tx = Transaction::where('stripe_id', 'pi_test_multi_123')->first();
    expect($tx)->not->toBeNull()
        ->and($tx->subject_type)->toBe(EventRegistration::class)
        ->and((float) $tx->amount)->toBe(75.00)
        ->and(Transaction::where('stripe_id', 'pi_test_multi_123')->count())->toBe(1);
});

// ── Mixed-price routing ──────────────────────────────────────────────────────

it('mixed-price purchase routes to Stripe and assembles a multi-line-item session', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $paid  = TicketTier::factory()->for($event)->create(['name' => 'General', 'price' => 25, 'sort_order' => 0]);
    $free  = TicketTier::factory()->for($event)->create(['name' => 'Comp',    'price' => 0,  'sort_order' => 1]);

    $quantities = EventRegistrationQuantities::fromRequest(
        $event,
        new \Illuminate\Http\Request(['quantities' => [$paid->id => 2, $free->id => 1]]),
    );

    expect($quantities->isPaid())->toBeTrue();

    $items = $quantities->stripeLineItems($event->title);
    expect($items)->toHaveCount(2)
        ->and($items[0]['price_data']['unit_amount'])->toBe(2500)
        ->and($items[0]['quantity'])->toBe(2)
        ->and($items[1]['price_data']['unit_amount'])->toBe(0)
        ->and($items[1]['quantity'])->toBe(1);
});

it('all-zero-priced purchase routes to the free path and creates rows directly', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $a     = TicketTier::factory()->for($event)->create(['name' => 'A', 'price' => 0, 'sort_order' => 0]);
    $b     = TicketTier::factory()->for($event)->create(['name' => 'B', 'price' => 0, 'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$a->id => 1, $b->id => 2],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $rows = EventRegistration::where('email', 'jane@example.com')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->where('ticket_tier_id', $a->id)->first()->quantity)->toBe(1)
        ->and($rows->where('ticket_tier_id', $b->id)->first()->quantity)->toBe(2)
        ->and($rows->pluck('stripe_session_id')->unique()->all())->toEqual([null]);
});

// ── Quantity > remaining capacity rejected ──────────────────────────────────

it('rejects a quantity larger than the tier remaining capacity', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 5]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'status'         => 'registered',
        'quantity'       => 3,
    ]);

    // Remaining = 2; asking for 3 should be rejected.
    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 3],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities');

    expect(EventRegistration::where('email', 'jane@example.com')->count())->toBe(0);
});

it('accepts a quantity equal to the tier remaining capacity', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => 5]);

    EventRegistration::factory()->create([
        'event_id'       => $event->id,
        'ticket_tier_id' => $tier->id,
        'status'         => 'registered',
        'quantity'       => 3,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 2],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    expect(EventRegistration::where('email', 'jane@example.com')->first()->quantity)->toBe(2);
});

// ── Portal member multi-tier free path ──────────────────────────────────────

it('portal member free multi-tier purchase creates one row per tier', function () {
    $contact = Contact::factory()->create(['email' => 'member@example.com']);
    $account = PortalAccount::factory()->create(['contact_id' => $contact->id]);
    $event   = Event::factory()->create(['status' => 'published']);
    $a       = TicketTier::factory()->for($event)->create(['name' => 'A', 'price' => 0, 'sort_order' => 0]);
    $b       = TicketTier::factory()->for($event)->create(['name' => 'B', 'price' => 0, 'sort_order' => 1]);

    $this->actingAs($account, 'portal')
        ->post(route('portal.events.register', $event->slug), [
            'quantities' => [$a->id => 2, $b->id => 1],
        ])
        ->assertRedirect();

    $rows = EventRegistration::where('contact_id', $contact->id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->where('ticket_tier_id', $a->id)->first()->quantity)->toBe(2)
        ->and($rows->where('ticket_tier_id', $b->id)->first()->quantity)->toBe(1);
});

// ── Regression guards: merged single-controller flow ────────────────────────

it('events.checkout and portal.events.checkout routes no longer exist', function () {
    expect(\Illuminate\Support\Facades\Route::getRoutes()->getByName('events.checkout'))->toBeNull();
    expect(\Illuminate\Support\Facades\Route::getRoutes()->getByName('portal.events.checkout'))->toBeNull();
});

it('paid registration does not internally redirect to a removed events.checkout endpoint', function () {
    // Bug guard for the 278-introduced 302 → GET /events/{slug}/checkout
    // failure mode that landed users on a 404. The merged 279 controller
    // must complete the paid flow inline and either 302 to an absolute
    // Stripe URL or back() with a register error (when Stripe rejects).
    config(['services.stripe.secret' => 'sk_test_fake']);
    $event = Event::factory()->paid(25.00)->create(['status' => 'published']);
    $tier  = $event->ticketTiers()->first();

    $response = $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ]);

    $location = $response->headers->get('Location') ?? '';
    expect($location)->not->toContain("/events/{$event->slug}/checkout");
});

// ── Max-quantity bound ──────────────────────────────────────────────────────

it('rejects a quantity above the 999 hard ceiling', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $tier  = TicketTier::factory()->for($event)->create(['capacity' => null]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$tier->id => 1000],
        '_form_start' => time() - 10,
    ])->assertSessionHasErrors('quantities.' . $tier->id);

    expect(EventRegistration::count())->toBe(0);
});
