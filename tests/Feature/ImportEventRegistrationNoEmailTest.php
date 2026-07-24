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
 * (contact auto-create; the confirmation email until its session-374 move to
 * the confirm-points) must not fire. Both importer seams wrap the create in
 * EventRegistration::withoutEvents(); this file pins the bundle
 * (ContentImporter) seam, and asserts a normal registration still emails so
 * the suppression isn't global.
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
    // Session 374 relocated the dispatch from the observer to the confirm-
    // points, so the contrast case goes through the public controller — the
    // free-path confirm-point — rather than a bare model create.
    Mail::fake();

    $event = Event::factory()->create(['auto_create_contacts' => true, 'status' => 'published']);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Walk-up Guest',
        'email'       => 'walkup@example.com',
        '_form_start' => time() - 10,
    ])->assertRedirect();

    Mail::assertSent(RegistrationConfirmation::class);
});
