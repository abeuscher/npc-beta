<?php

use App\Models\CustomFieldDef;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\ImportExport\Import\BundleMediaArchive;
use App\Services\ImportExport\Import\EventImporter;
use App\Services\ImportExport\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises rich_text custom_fields through the reusable helper', function () {
    CustomFieldDef::create([
        'model_type' => 'event_registration',
        'handle'     => 'dietary_notes',
        'label'      => 'Dietary notes',
        'field_type' => 'rich_text',
        'sort_order' => 0,
    ]);

    $clean = EventRegistration::sanitizeRichTextCustomFields([
        'dietary_notes' => '<p>Vegan.</p><script>alert(1)</script><a href="javascript:x">y</a>',
    ]);

    expect($clean['dietary_notes'])->toBe('<p>Vegan.</p><a>y</a>');
});

it('leaves custom_fields untouched when no rich_text def exists for the model', function () {
    CustomFieldDef::create([
        'model_type' => 'event_registration',
        'handle'     => 'seat',
        'label'      => 'Seat',
        'field_type' => 'text',
        'sort_order' => 0,
    ]);

    $result = EventRegistration::sanitizeRichTextCustomFields(['seat' => 'A<b>1</b>']);

    expect($result['seat'])->toBe('A<b>1</b>');
});

it('sanitises event-registration rich_text custom_fields imported under withoutEvents()', function () {
    // The import path suppresses model events (withoutEvents) to skip the
    // observer's confirmation email + contact auto-create. This proves the
    // sanitizer still funnels the rich-text custom field despite the saving()
    // hook being suppressed — the S3 write-path bypass fix.
    User::factory()->create(); // BundleAuthorResolver resolves an author id

    CustomFieldDef::create([
        'model_type' => 'event_registration',
        'handle'     => 'dietary_notes',
        'label'      => 'Dietary notes',
        'field_type' => 'rich_text',
        'sort_order' => 0,
    ]);

    app(EventImporter::class)->import(
        [
            'event' => [
                'slug'      => 'gala',
                'title'     => 'Gala',
                'status'    => 'published',
                'starts_at' => now()->toIso8601String(),
            ],
            'tiers'         => [],
            'registrations' => [
                [
                    'name'          => 'Mallory',
                    'email'         => 'mallory@example.com',
                    'custom_fields' => [
                        'dietary_notes' => '<p>Vegan.</p><script>alert(document.cookie)</script>',
                    ],
                ],
            ],
        ],
        new ImportLog(),
        new BundleMediaArchive(),
    );

    $registration = EventRegistration::where('email', 'mallory@example.com')->firstOrFail();

    expect($registration->custom_fields['dietary_notes'])->toBe('<p>Vegan.</p>');
});
