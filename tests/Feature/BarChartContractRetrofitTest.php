<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\BarChart\BarChartDefinition;
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

// guards: BarChart whitelist (chart-config JSON shape, no _media key, sentinel non-leak); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('projects only contract-declared fields onto BarChart rows (fail-closed whitelist)', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-bars',
        'name'        => 'Audit Bars',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'label',          'type' => 'text'],
            ['key' => 'value',          'type' => 'text'],
            ['key' => 'internal_notes', 'type' => 'text'],
            ['key' => 'legacy_id',      'type' => 'text'],
        ],
    ]);

    $rows = [
        ['label' => 'A', 'value' => '10'],
        ['label' => 'B', 'value' => '20'],
        ['label' => 'C', 'value' => '30'],
    ];

    foreach ($rows as $i => $row) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => array_merge($row, [
                'internal_notes' => 'NOTES_NOTLEAKED_SENTINEL',
                'legacy_id'      => 'LEGACY_ID_SENTINEL',
            ]),
        ]);
    }

    // Resolver-level invariant: contract-declared shape only, no _media key
    // (BarChart's content type declares no image fields).
    $resolver = app(ContractResolver::class);
    $contract = (new BarChartDefinition())->dataContract([
        'collection_handle' => 'audit-bars',
        'x_field'           => 'label',
        'y_field'           => 'value',
    ]);
    $dto = $resolver->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];

    expect($dto['items'])->toHaveCount(3)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['label', 'value'])
        ->and(array_keys($dto['items'][1]))->toEqualCanonicalizing(['label', 'value'])
        ->and(array_keys($dto['items'][2]))->toEqualCanonicalizing(['label', 'value'])
        ->and($dto['items'][0])->not->toHaveKey('internal_notes')
        ->and($dto['items'][0])->not->toHaveKey('legacy_id')
        ->and($dto['items'][0])->not->toHaveKey('_media');

    // HTML-level invariant: labels and values reach the chart-config JSON;
    // sentinels in undeclared columns do not leak.
    $wt = WidgetType::where('handle', 'bar_chart')->firstOrFail();
    $host = Page::factory()->create(['title' => 'BarChart Host', 'slug' => 'bar-chart-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'           => 'Audit Chart',
            'collection_handle' => 'audit-bars',
            'x_field'           => 'label',
            'y_field'           => 'value',
            'bar_fill_color'    => '#0172ad',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('"A"')
        ->toContain('"B"')
        ->toContain('"C"')
        ->toContain('10')
        ->toContain('20')
        ->toContain('30')
        ->not->toContain('NOTES_NOTLEAKED_SENTINEL')
        ->not->toContain('LEGACY_ID_SENTINEL');
});

// guards: BarChart query pattern (1 collections + 1 collection_items + 1 media); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('renders BarChart through the contract resolver only, with one collections + one collection_items + one media select', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-bars',
        'name'        => 'Audit Bars',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'label', 'type' => 'text'],
            ['key' => 'value', 'type' => 'text'],
        ],
    ]);

    $rows = [
        ['label' => 'Live A', 'value' => '11'],
        ['label' => 'Live B', 'value' => '22'],
        ['label' => 'Live C', 'value' => '33'],
    ];

    foreach ($rows as $i => $row) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => $row,
        ]);
    }

    CollectionItem::create([
        'collection_id' => $collection->id,
        'sort_order'    => 99,
        'is_published'  => false,
        'data'          => ['label' => 'Unpublished Bar Should Not Render', 'value' => '999'],
    ]);

    $wt = WidgetType::where('handle', 'bar_chart')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Query Host', 'slug' => 'bar-chart-query-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'           => 'Query Chart',
            'collection_handle' => 'audit-bars',
            'x_field'           => 'label',
            'y_field'           => 'value',
            'bar_fill_color'    => '#0172ad',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $collectionSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "collections"')
            && str_contains($sql, '"handle"')
            && str_contains($sql, '"is_active"')
            && str_contains($sql, '"is_public"');
    }));

    $itemSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "collection_items"')
            && str_contains($sql, '"is_published"')
            && str_contains($sql, '"sort_order"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    expect($html)
        ->toContain('"Live A"')
        ->toContain('"Live B"')
        ->toContain('"Live C"')
        ->not->toContain('Unpublished Bar Should Not Render')
        ->and(count($collectionSelects))->toBe(1)
        ->and(count($itemSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1);
});
