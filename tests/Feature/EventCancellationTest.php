<?php

use App\Mail\EventCancellation;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sends cancellation emails to registered attendees when event is cancelled', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);

    $reg1 = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'one@example.com',
        'status'   => 'registered',
    ]);
    $reg2 = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'two@example.com',
        'status'   => 'registered',
    ]);

    // Disable the observer for these factory creations so no confirmation mail fires
    // (Mail::fake() already captures all mail, so this just verifies cancellation count)

    $event->update(['status' => 'cancelled']);

    Mail::assertSent(EventCancellation::class, 2);

    Mail::assertSent(EventCancellation::class, fn ($mail) => $mail->hasTo('one@example.com'));
    Mail::assertSent(EventCancellation::class, fn ($mail) => $mail->hasTo('two@example.com'));
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

it('cancellation email has the correct subject', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published', 'title' => 'Spring Conference']);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $event->update(['status' => 'cancelled']);

    Mail::assertSent(EventCancellation::class, function ($mail) {
        return str_contains($mail->envelope()->subject, 'Spring Conference');
    });
});
