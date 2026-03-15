<?php

use App\Mail\EventReminder;
use App\Models\Event;
use App\Models\EventDate;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sends reminders for event dates within the days window', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventDate::factory()->create([
        'event_id'  => $event->id,
        'starts_at' => now()->addHours(12),
        'status'    => 'inherited',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $this->artisan('events:send-reminders', ['--days' => 1])
        ->assertSuccessful();

    Mail::assertSent(EventReminder::class, 1);
});

it('skips past event dates', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventDate::factory()->create([
        'event_id'  => $event->id,
        'starts_at' => now()->subDay(),
        'status'    => 'inherited',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $this->artisan('events:send-reminders', ['--days' => 1])
        ->assertSuccessful();

    Mail::assertNotSent(EventReminder::class);
});

it('skips event dates outside the days window', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventDate::factory()->create([
        'event_id'  => $event->id,
        'starts_at' => now()->addDays(10),
        'status'    => 'inherited',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'attendee@example.com',
        'status'   => 'registered',
    ]);

    $this->artisan('events:send-reminders', ['--days' => 3])
        ->assertSuccessful();

    Mail::assertNotSent(EventReminder::class);
});

it('does not send reminders to registrants without email', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventDate::factory()->create([
        'event_id'  => $event->id,
        'starts_at' => now()->addHours(12),
        'status'    => 'inherited',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => '',
        'status'   => 'registered',
    ]);

    $this->artisan('events:send-reminders', ['--days' => 1])
        ->assertSuccessful();

    Mail::assertNotSent(EventReminder::class);
});

it('does not send reminders to waitlisted or cancelled registrants', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    EventDate::factory()->create([
        'event_id'  => $event->id,
        'starts_at' => now()->addHours(12),
        'status'    => 'inherited',
    ]);
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'email'    => 'waitlisted@example.com',
        'status'   => 'waitlisted',
    ]);

    $this->artisan('events:send-reminders', ['--days' => 1])
        ->assertSuccessful();

    Mail::assertNotSent(EventReminder::class);
});
