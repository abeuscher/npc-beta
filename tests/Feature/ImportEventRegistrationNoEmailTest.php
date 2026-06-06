<?php

use App\Mail\RegistrationConfirmation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Flag 344-C (session 345): registrations created by the importers are
 * historical data, not live sign-ups, so the EventRegistrationObserver
 * (synchronous confirmation email + contact auto-create) must not fire. Both
 * importer seams wrap the create in EventRegistration::withoutEvents(); this
 * file pins the bundle (ContentImporter) seam, and asserts the observer still
 * fires for a normal registration so the suppression isn't global.
 */
it('does not send a confirmation email for bundle-imported event registrations', function () {
    Mail::fake();

    // resolveAuthorId() needs at least one user on the install.
    User::factory()->create();

    $bundle = [
        'format_version' => '1.1.0',
        'payload'        => [
            'events' => [
                [
                    'event' => [
                        'slug'                 => 'imported-gala-2026',
                        'title'                => 'Imported Gala',
                        'starts_at'            => '2026-05-02 13:00:00',
                        'auto_create_contacts' => true,
                    ],
                    'registrations' => [
                        ['name' => 'Bundle Guest', 'email' => 'bundle-guest@example.com', 'status' => 'registered'],
                    ],
                ],
            ],
        ],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog());

    // The registration landed (suppression, not absence) …
    expect(EventRegistration::where('email', 'bundle-guest@example.com')->count())->toBe(1);
    // … with no confirmation email.
    Mail::assertNotSent(RegistrationConfirmation::class);
});

it('still sends a confirmation email for a normal (non-import) registration', function () {
    Mail::fake();

    $event = Event::factory()->create(['auto_create_contacts' => true]);

    EventRegistration::create([
        'event_id'      => $event->id,
        'name'          => 'Walk-up Guest',
        'email'         => 'walkup@example.com',
        'status'        => 'registered',
        'registered_at' => now(),
    ]);

    Mail::assertSent(RegistrationConfirmation::class);
});
