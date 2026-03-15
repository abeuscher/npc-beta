<?php

use App\Mail\RegistrationConfirmation;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sends a confirmation email when a registration is created with an email address', function () {
    Mail::fake();

    $event        = Event::factory()->create(['title' => 'Annual Gala']);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'name'     => 'Jane Doe',
    ]);

    Mail::assertSent(RegistrationConfirmation::class, function ($mail) use ($registration) {
        return $mail->hasTo('attendee@example.com')
            && $mail->registration->id === $registration->id;
    });
});

it('does not send a confirmation email when email is empty', function () {
    Mail::fake();

    $event = Event::factory()->create();
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => '',
    ]);

    Mail::assertNotSent(RegistrationConfirmation::class);
});

it('confirmation email has the correct subject', function () {
    Mail::fake();

    $event = Event::factory()->create(['title' => 'Tech Summit']);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
    ]);

    Mail::assertSent(RegistrationConfirmation::class, function ($mail) {
        return str_contains($mail->envelope()->subject, 'Tech Summit');
    });
});
