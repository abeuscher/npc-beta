<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('projects only contract-declared fields onto EventsListing rows (fail-closed whitelist)', function () {
    Event::factory()->create([
        'title'          => 'Whitelisted Event',
        'slug'           => 'whitelisted-event',
        'status'         => 'published',
        'starts_at'      => now()->addDays(3),
        'ends_at'        => now()->addDays(3)->addHours(2),
        'address_line_1' => '123 Sentinel Lane',
        'city'           => 'Springfield',
        'state'          => 'IL',
        'meeting_label'  => 'MEETING_LABEL_NOTLEAKED_SENTINEL',
        'is_free'        => true,
    ]);

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();

    $host = Page::factory()->create([
        'title'  => 'Retrofit Host',
        'slug'   => 'retrofit-host',
        'status' => 'published',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="title">{{item.title}}</p><p class="loc">{{item.location}}</p><p class="free">{{item.is_free}}</p><p class="addr">{{item.address_line_1}}</p><p class="meeting">{{item.meeting_label}}</p><p class="internal">{{item.id}}</p>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Whitelisted Event')
        ->toContain('123 Sentinel Lane, Springfield, IL')
        ->toContain('<p class="addr"></p>')
        ->toContain('<p class="meeting"></p>')
        ->toContain('<p class="internal"></p>')
        ->not->toContain('MEETING_LABEL_NOTLEAKED_SENTINEL');

    preg_match('#<script x-ref="listingData" type="application/json">(.+?)</script>#s', $html, $match);
    $listing = json_decode($match[1], true);

    expect($listing['items'])->toHaveCount(1)
        ->and(array_keys($listing['items'][0]))->toEqualCanonicalizing(['title', 'slug', 'url', 'starts_at', 'starts_at_label', 'ends_at', 'ends_at_label', 'location', 'is_free', 'image']);
});

it('renders EventsListing through the contract resolver only, with a single events select and eager-loaded media + landingPage', function () {
    for ($i = 0; $i < 3; $i++) {
        $landing = Page::factory()->create([
            'title'  => 'Landing Page ' . $i,
            'slug'   => 'landing-page-' . $i,
            'status' => 'published',
        ]);
        Event::factory()->create([
            'title'           => 'Upcoming Event ' . $i,
            'slug'            => 'upcoming-event-' . $i,
            'status'          => 'published',
            'starts_at'       => now()->addDays($i + 1),
            'landing_page_id' => $landing->id,
        ]);
    }

    Event::factory()->create([
        'title'     => 'Draft Event Should Not Render',
        'slug'      => 'draft-event',
        'status'    => 'draft',
        'starts_at' => now()->addDays(2),
    ]);

    Event::factory()->create([
        'title'     => 'Past Event Should Not Render',
        'slug'      => 'past-event',
        'status'    => 'published',
        'starts_at' => now()->subDays(5),
    ]);

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();

    $host = Page::factory()->create([
        'title'  => 'Query Host',
        'slug'   => 'query-host',
        'status' => 'published',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<article class="card">{{item.title}}</article>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $eventSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, '"events"')
            && str_contains($sql, '"starts_at"')
            && str_contains($sql, '"status"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    $landingPageSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "pages"')
            && str_contains($sql, '"id" in');
    }));

    expect(substr_count($html, '<article class="card">'))->toBe(3)
        ->and($html)->not->toContain('Draft Event Should Not Render')
        ->and($html)->not->toContain('Past Event Should Not Render')
        ->and(count($eventSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($landingPageSelects))->toBe(1);
});
