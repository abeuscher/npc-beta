<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Plugins\Events\Filament\Resources\EventResource\Pages\EditEvent;
use Plugins\Events\Mail\EventCancellation;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not auto-send cancellation emails when event status changes to cancelled', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);

    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'one@example.com',
        'status'   => 'registered',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'two@example.com',
        'status'   => 'registered',
    ]);

    // Cancellation emails are no longer sent automatically by the observer.
    // They are sent via the Cancel Event wizard action in the admin UI.
    $event->update(['status' => 'cancelled']);

    Mail::assertNotSent(EventCancellation::class);
});

it('does not send cancellation emails to waitlisted or cancelled registrants', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);

    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'waitlisted@example.com',
        'status'   => 'waitlisted',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'cancelled@example.com',
        'status'   => 'cancelled',
    ]);

    $event->update(['status' => 'cancelled']);

    Mail::assertNotSent(EventCancellation::class);
});

it('does not send cancellation emails when other fields change', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $event->update(['title' => 'Updated Title']);

    Mail::assertNotSent(EventCancellation::class);
});

it('does not send cancellation emails to registrants without email', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => '',
        'status'   => 'registered',
    ]);

    $event->update(['status' => 'cancelled']);

    Mail::assertNotSent(EventCancellation::class);
});

// ── The live send path: EditEvent's Cancel Event wizard ──────────────────────
// Backfill for the [test-integrity] [0.363.03] coverage gap item (4): the
// wizard action is the ONLY sender of EventCancellation (the observer
// auto-send was retired), and it had no test. Exercises the real Filament
// action, not a re-implemented copy.

it('the Cancel Event wizard sends one email per registered registrant with an email and cancels the event', function () {
    Mail::fake();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $event = Event::factory()->create(['status' => 'published']);

    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'one@example.com',
        'status'   => 'registered',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'two@example.com',
        'status'   => 'registered',
    ]);
    // Skipped: not `registered`, or no email address.
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'waitlisted@example.com',
        'status'   => 'waitlisted',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => '',
        'status'   => 'registered',
    ]);

    Livewire::actingAs($admin)
        ->test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->callAction('cancelEvent');

    Mail::assertSent(EventCancellation::class, 2);
    Mail::assertSent(EventCancellation::class, fn (EventCancellation $mail) => $mail->hasTo('one@example.com'));
    Mail::assertSent(EventCancellation::class, fn (EventCancellation $mail) => $mail->hasTo('two@example.com'));

    expect($event->fresh()->status)->toBe('cancelled');
});

it('the Cancel Event wizard is hidden on an already-cancelled event', function () {
    Mail::fake();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $event = Event::factory()->create(['status' => 'cancelled']);

    Livewire::actingAs($admin)
        ->test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->assertActionHidden('cancelEvent');

    Mail::assertNotSent(EventCancellation::class);
});

it('cancellation email subject contains the event title', function () {
    $event = Event::factory()->create(['status' => 'published', 'title' => 'Spring Conference']);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $registration->loadMissing('event', 'contact');
    $mail = new EventCancellation($registration);

    expect($mail->envelope()->subject)->toContain('Spring Conference');
});
