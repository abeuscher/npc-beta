<?php

use App\Mail\EventCancellation;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
