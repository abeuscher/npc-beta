<?php

use App\Mail\RegistrationConfirmation;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PortalAccount;
use App\Models\TicketTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Session 374 (C3c): the thank-you fires once per ORDER, at confirm time —
 * free path in the registration controllers, paid path on the webhook's
 * pending → registered promotion. Row creation alone never emails: paid rows
 * are created `pending` before payment, and one order spans multiple rows.
 */

// ── Row creation is not order confirmation ───────────────────────────────────

it('does not send a confirmation email on bare registration-row creation', function () {
    Mail::fake();

    $event = Event::factory()->create(['title' => 'Annual Gala']);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'name'     => 'Jane Doe',
    ]);

    Mail::assertNothingOutgoing();
});

it('does not send a confirmation email when paid pending rows are created', function () {
    Mail::fake();

    $event = Event::factory()->paid(25.00)->create();
    $tier  = $event->ticketTiers()->first();

    EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'ticket_tier_id'    => $tier->id,
        'email'             => 'buyer@example.com',
        'status'            => 'pending',
        'stripe_session_id' => 'cs_test_' . uniqid(),
    ]);

    Mail::assertNothingOutgoing();
});

// ── Free path — the controllers are the confirm-point ────────────────────────

it('sends exactly one confirmation for a tier-less free registration', function () {
    Mail::fake();

    $event = Event::factory()->create(['title' => 'Tech Summit', 'status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    Mail::assertSent(RegistrationConfirmation::class, 1);
    Mail::assertSent(RegistrationConfirmation::class, function ($mail) {
        return $mail->hasTo('jane@example.com')
            && str_contains($mail->envelope()->subject, 'Tech Summit');
    });
});

it('sends exactly one confirmation for a multi-tier pure-$0 order, not one per line', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    $a     = TicketTier::factory()->for($event)->create(['name' => 'A', 'price' => 0, 'sort_order' => 0]);
    $b     = TicketTier::factory()->for($event)->create(['name' => 'B', 'price' => 0, 'sort_order' => 1]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$a->id => 1, $b->id => 2],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    expect(EventRegistration::where('email', 'jane@example.com')->count())->toBe(2);
    Mail::assertSent(RegistrationConfirmation::class, 1);
});

it('comp-only order skips Stripe, lands registered, and sends exactly one confirmation', function () {
    // C3c lock-in: the pure-$0 path never touches Stripe — no session, no
    // pending rows — and confirms server-side with a single thank-you.
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_fake']);

    $event = Event::factory()->create(['status' => 'published']);
    $comp  = TicketTier::factory()->for($event)->create([
        'name'             => 'Comp',
        'price'            => 0,
        'is_complimentary' => true,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane',
        'email'       => 'jane@example.com',
        'quantities'  => [$comp->id => 2],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $rows = EventRegistration::where('email', 'jane@example.com')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('registered')
        ->and($rows[0]->stripe_session_id)->toBeNull()
        ->and($rows[0]->quantity)->toBe(2);
    Mail::assertSent(RegistrationConfirmation::class, 1);
});

it('sends exactly one confirmation for a portal member free registration', function () {
    Mail::fake();

    $contact = Contact::factory()->create(['email' => 'member@example.com']);
    $account = PortalAccount::factory()->create(['contact_id' => $contact->id]);
    $event   = Event::factory()->create();

    $this->actingAs($account, 'portal')
        ->post(route('portal.events.register', $event->slug))
        ->assertRedirect();

    Mail::assertSent(RegistrationConfirmation::class, 1);
    Mail::assertSent(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('member@example.com'));
});

// ── Paid path — the webhook promotion is the confirm-point ───────────────────

function invokeEventRegistrationWebhook(string $sessionId, string $email = 'paid@example.com'): void
{
    $controller = new \App\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleEventRegistrationCheckout');
    $reflection->setAccessible(true);

    $payload = (object) [
        'id'               => $sessionId,
        'payment_intent'   => 'pi_' . $sessionId,
        'amount_total'     => 7500,
        'customer_details' => (object) ['email' => $email, 'name' => 'Jane Paid'],
    ];

    $reflection->invoke($controller, $payload, (object) ['event_registration_checkout' => '1']);
}

it('webhook promotion sends exactly one confirmation for a multi-row paid order', function () {
    Mail::fake();

    $event = Event::factory()->paid(25.00)->create();
    $tier  = $event->ticketTiers()->first();
    $vip   = TicketTier::factory()->for($event)->create(['name' => 'VIP', 'price' => 50, 'sort_order' => 1]);
    $sid   = 'cs_test_promo_' . uniqid();

    foreach ([$tier, $vip] as $t) {
        EventRegistration::factory()->create([
            'event_id'          => $event->id,
            'ticket_tier_id'    => $t->id,
            'email'             => 'paid@example.com',
            'status'            => 'pending',
            'stripe_session_id' => $sid,
        ]);
    }

    invokeEventRegistrationWebhook($sid);

    expect(EventRegistration::where('stripe_session_id', $sid)->where('status', 'registered')->count())->toBe(2);
    // The webhook queues (not sends) so a mail hiccup can't fail the 200.
    Mail::assertQueued(RegistrationConfirmation::class, 1);
    Mail::assertQueued(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('paid@example.com'));
});

it('a webhook replay sends no additional confirmation', function () {
    Mail::fake();

    $event = Event::factory()->paid(25.00)->create();
    $tier  = $event->ticketTiers()->first();
    $sid   = 'cs_test_replay_' . uniqid();

    EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'ticket_tier_id'    => $tier->id,
        'email'             => 'paid@example.com',
        'status'            => 'pending',
        'stripe_session_id' => $sid,
    ]);

    invokeEventRegistrationWebhook($sid);
    invokeEventRegistrationWebhook($sid);

    Mail::assertQueued(RegistrationConfirmation::class, 1);
});

// ── Observer side-effects survive the dispatch relocation ────────────────────

it('contact auto-create still fires on registration create without an email send', function () {
    Mail::fake();

    $event = Event::factory()->create(['auto_create_contacts' => true]);

    $registration = EventRegistration::create([
        'event_id'      => $event->id,
        'name'          => 'Auto Created',
        'email'         => 'auto-created@example.com',
        'status'        => 'registered',
        'registered_at' => now(),
    ]);

    $contact = Contact::where('email', 'auto-created@example.com')->first();
    expect($contact)->not->toBeNull()
        ->and($registration->fresh()->contact_id)->toBe($contact->id);
    Mail::assertNothingOutgoing();
});
