<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\EventDescription\EventDescriptionDefinition;
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

// guards: EventDescription whitelist (single-row DTO derived event_date/event_time/event_location/is_in_person/is_virtual, meeting_label/ends_at/id non-leak); N>=2 redundant for ContractResolver mutations per session-241 audit (Q stays load-bearing).
it('projects only contract-declared fields onto the EventDescription single-row DTO (fail-closed whitelist)', function () {
    Event::factory()->create([
        'title'          => 'Whitelisted Event',
        'slug'           => 'whitelisted-event',
        'status'         => 'published',
        'starts_at'      => now()->addDays(3)->setTime(17, 30),
        'ends_at'        => now()->addDays(3)->setTime(19, 0),
        'address_line_1' => '123 Sentinel Lane',
        'city'           => 'Springfield',
        'state'          => 'IL',
        'meeting_label'  => 'MEETING_LABEL_NOTLEAKED_SENTINEL',
        'description'    => 'Some event description copy.',
    ]);

    $contract = (new EventDescriptionDefinition())->dataContract(['event_slug' => 'whitelisted-event']);
    $context = new SlotContext(new PageAmbientContext());
    $dto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto)->toHaveKey('item')
        ->and($dto['item'])->not->toBeNull()
        ->and(array_keys($dto['item']))->toEqualCanonicalizing(['title', 'starts_at', 'event_date', 'event_time', 'event_location', 'description', 'is_in_person', 'is_virtual', 'city', 'state'])
        ->and($dto['item'])->not->toHaveKey('meeting_label')
        ->and($dto['item'])->not->toHaveKey('ends_at')
        ->and($dto['item'])->not->toHaveKey('id')
        ->and($dto['item']['title'])->toBe('Whitelisted Event')
        ->and($dto['item']['city'])->toBe('Springfield');

    $wt = WidgetType::where('handle', 'event_description')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'whitelisted-event'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Whitelisted Event')
        ->not->toContain('MEETING_LABEL_NOTLEAKED_SENTINEL');
});

it('renders EventDescription through the contract resolver only and short-circuits on missing slug', function () {
    $landing = Page::factory()->create([
        'title'  => 'Event Landing',
        'slug'   => 'event-landing',
        'status' => 'published',
    ]);

    Event::factory()->create([
        'title'           => 'Published Event',
        'slug'            => 'published-event',
        'status'          => 'published',
        'starts_at'       => now()->addDays(2),
        'landing_page_id' => $landing->id,
    ]);

    Event::factory()->create([
        'title'     => 'Draft Event Should Not Resolve',
        'slug'      => 'draft-event',
        'status'    => 'draft',
        'starts_at' => now()->addDays(2),
    ]);

    $wt = WidgetType::where('handle', 'event_description')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'event-host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'published-event'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $eventSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, '"events"')
            && str_contains($sql, '"slug"')
            && str_contains($sql, '"status"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    $landingPageSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "pages"')
            && str_contains($sql, '"id" in');
    }));

    expect($html)->toContain('Published Event')
        ->and(count($eventSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($landingPageSelects))->toBe(1);

    $missingPw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'does-not-exist'],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $contract = (new EventDescriptionDefinition())->dataContract(['event_slug' => 'does-not-exist']);
    $context = new SlotContext(new PageAmbientContext());
    $missingDto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    $missingHtml = WidgetRenderer::render($missingPw)['html'];

    expect($missingDto)->toBe(['item' => null])
        ->and(trim((string) $missingHtml))->toBe('');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $emptyContract = (new EventDescriptionDefinition())->dataContract(['event_slug' => '']);
    $emptyDto = app(ContractResolver::class)->resolve([$emptyContract], $context)[0];
    $emptyQueries = DB::getQueryLog();
    DB::disableQueryLog();

    $emptyEventSelects = array_values(array_filter($emptyQueries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, '"events"');
    }));

    expect($emptyDto)->toBe(['item' => null])
        ->and(count($emptyEventSelects))->toBe(0);
});
