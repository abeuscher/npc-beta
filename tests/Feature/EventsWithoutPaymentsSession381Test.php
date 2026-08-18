<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Plugins\Events\Mail\RegistrationConfirmation;
use Tests\Fixtures\Plugins\PaymentsRemovedTestCase;

uses(PaymentsRemovedTestCase::class, RefreshDatabase::class);

// Session 381 (Plugin Architecture arc P5): the Events-without-Payments cell
// of the presence matrix — the dependency-rules claim ("Events without
// Payments = free events only") as executable tests. Boots with the Payments
// line stripped while Events ships; free and complimentary registration work
// end to end with confirmations, paid degrades to the existing not-configured
// response. (PaymentsPluginRemovalSession380Test carries the base free/$0
// path proofs; this file adds the confirmation sends and the comp tier.)

it('keeps the Events plugin fully loaded without Payments', function () {
    expect(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeFalse();
});

it('sends the confirmation email on a free registration', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Free User',
        'email'       => 'free@example.com',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    Mail::assertSent(RegistrationConfirmation::class, 1);
});

it('registers a complimentary (labelled, $0) tier through the free path with a confirmation', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    $comp  = TicketTier::factory()->for($event)->create([
        'name'             => 'Comp',
        'price'            => 0,
        'is_complimentary' => true,
        'sort_order'       => 0,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Comp Guest',
        'email'       => 'comp@example.com',
        'quantities'  => [$comp->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $row = EventRegistration::where('email', 'comp@example.com')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('registered')
        ->and($row->stripe_session_id)->toBeNull();

    Mail::assertSent(RegistrationConfirmation::class, 1);
});

it('degrades a paid portal registration to the existing not-configured response', function () {
    $contact = \App\Models\Contact::factory()->create(['email' => 'portalnopay@example.com']);
    $account = \App\Models\PortalAccount::factory()->create(['contact_id' => $contact->id]);
    $event   = Event::factory()->paid(25.00)->create();
    $tier    = $event->ticketTiers()->first();

    $this->actingAs($account, 'portal')
        ->post(route('portal.events.register', $event->slug), ['quantities' => [$tier->id => 1]])
        ->assertSessionHasErrors('register');

    expect(EventRegistration::where('event_id', $event->id)->count())->toBe(0);
});
