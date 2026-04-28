<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\EventRegistration\EventRegistrationDefinition;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

// guards: EventRegistration whitelist (capacity-aware single-row DTO with is_at_capacity aggregate, address_line_1/meeting_url/description non-leak); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('projects only contract-declared fields onto the EventRegistration single-row DTO with is_at_capacity aggregate', function () {
    $event = Event::factory()->create([
        'title'                     => 'Capacity Test Event',
        'slug'                      => 'capacity-test',
        'status'                    => 'published',
        'starts_at'                 => now()->addDays(5),
        'capacity'                  => 2,
        'price'                     => 25.00,
        'registration_mode'         => 'open',
        'external_registration_url' => 'https://example.test/register',
        'address_line_1'            => '999 Capacity Way',
        'meeting_url'               => 'https://meet.example.test/full',
    ]);

    foreach (range(1, 3) as $i) {
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status'   => 'registered',
        ]);
    }

    $contract = (new EventRegistrationDefinition())->dataContract(['event_slug' => 'capacity-test']);
    $context = new SlotContext(new PageAmbientContext());
    $dto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto)->toHaveKey('item')
        ->and($dto['item'])->not->toBeNull()
        ->and(array_keys($dto['item']))->toEqualCanonicalizing([
            'slug', 'title', 'status', 'registration_mode', 'is_free',
            'is_in_person', 'mailing_list_opt_in_enabled', 'external_registration_url',
            'price', 'is_at_capacity',
        ])
        ->and($dto['item'])->not->toHaveKey('address_line_1')
        ->and($dto['item'])->not->toHaveKey('meeting_url')
        ->and($dto['item'])->not->toHaveKey('description')
        ->and($dto['item']['is_at_capacity'])->toBeTrue()
        ->and($dto['item']['is_free'])->toBeFalse()
        ->and($dto['item']['price'])->toBeString()->toBe('25.00')
        ->and($dto['item']['external_registration_url'])->toBe('https://example.test/register')
        ->and($dto['item']['slug'])->toBe('capacity-test');
});

// guards: EventRegistration query pattern (events query with withCount as sole path to is_at_capacity, no standalone event_registrations select); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('renders EventRegistration through the contract resolver with withCount as the only path to is_at_capacity', function () {
    $landing = Page::factory()->create([
        'title'  => 'Reg Landing',
        'slug'   => 'reg-landing',
        'status' => 'published',
    ]);

    $event = Event::factory()->create([
        'title'             => 'Open Registration Event',
        'slug'              => 'open-reg-event',
        'status'            => 'published',
        'starts_at'         => now()->addDays(10),
        'capacity'          => 10,
        'price'             => 0,
        'registration_mode' => 'closed',
        'landing_page_id'   => $landing->id,
    ]);

    foreach (range(1, 3) as $i) {
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status'   => 'registered',
        ]);
    }

    $wt = WidgetType::where('handle', 'event_registration')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Reg Host', 'slug' => 'reg-host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'open-reg-event'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    DB::enableQueryLog();
    WidgetRenderer::render($pw);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $eventSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "events"')
            && str_contains($sql, '"slug"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    $landingPageSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "pages"')
            && str_contains($sql, '"id" in');
    }));

    $standaloneRegistrationSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "event_registrations"')
            && ! str_contains($sql, 'from "events"');
    }));

    expect(count($eventSelects))->toBe(1)
        ->and($eventSelects[0]['query'])->toContain('from "event_registrations"')
        ->and($eventSelects[0]['query'])->toContain('as "registered_count"')
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($landingPageSelects))->toBe(1)
        ->and(count($standaloneRegistrationSelects))->toBe(0);
});
