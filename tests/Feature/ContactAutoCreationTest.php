<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
    Mail::fake();
});

it('creates a Contact record when auto_create_contacts is true and email is present', function () {
    $event = Event::factory()->create([
        'status'               => 'published',
        'registration_mode'    => 'open',
        'auto_create_contacts' => true,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect();

    expect(Contact::where('email', 'jane@example.com')->exists())->toBeTrue();

    $contact = Contact::where('email', 'jane@example.com')->first();
    expect($contact->first_name)->toBe('Jane');
    expect($contact->last_name)->toBe('Doe');
});

it('does not create a Contact record when auto_create_contacts is false', function () {
    $event = Event::factory()->create([
        'status'               => 'published',
        'registration_mode'    => 'open',
        'auto_create_contacts' => false,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'John Smith',
        'email'       => 'john@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect();

    expect(Contact::where('email', 'john@example.com')->exists())->toBeFalse();
    expect(EventRegistration::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('does not create a duplicate Contact when email already exists', function () {
    $event = Event::factory()->create([
        'status'               => 'published',
        'registration_mode'    => 'open',
        'auto_create_contacts' => true,
    ]);

    Contact::factory()->create(['email' => 'existing@example.com']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Existing Person',
        'email'       => 'existing@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect();

    expect(Contact::where('email', 'existing@example.com')->count())->toBe(1);
});

it('populates contact_id on the registration after Contact creation', function () {
    $event = Event::factory()->create([
        'status'               => 'published',
        'registration_mode'    => 'open',
        'auto_create_contacts' => true,
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Jane Doe',
        'email'       => 'jane@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect();

    $registration = EventRegistration::where('email', 'jane@example.com')->first();
    $contact      = Contact::where('email', 'jane@example.com')->first();

    expect($registration->contact_id)->toBe($contact->id);
});

it('skips Contact creation when email is empty', function () {
    // Create registration directly (bypassing the controller's email-required validation)
    $event = Event::factory()->create([
        'status'               => 'published',
        'registration_mode'    => 'open',
        'auto_create_contacts' => true,
    ]);

    EventRegistration::create([
        'event_id'      => $event->id,
        'name'          => 'Anonymous',
        'email'         => '',
        'registered_at' => now(),
        'status'        => 'registered',
    ]);

    expect(Contact::count())->toBe(0);
});
