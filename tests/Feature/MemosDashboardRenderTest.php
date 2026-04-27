<?php

use App\Models\CollectionItem;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use App\Widgets\Memos\MemosDefinition;
use Database\Seeders\MemosCollectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new MemosCollectionSeeder())->run();

    $this->memosCollection = \App\Models\Collection::where('handle', 'memos')->firstOrFail();
});

it('renders memos items in the dashboard_grid slot even though the collection is is_public = false', function () {
    CollectionItem::create([
        'collection_id' => $this->memosCollection->id,
        'sort_order'    => 0,
        'is_published'  => true,
        'data'          => [
            'title'     => 'Server upgrade Thursday',
            'body'      => '<p>Expect a 15-minute outage.</p>',
            'posted_at' => '2026-04-22',
        ],
    ]);

    $wt = WidgetType::where('handle', 'memos')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['limit' => 5],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)
        ->toContain('Server upgrade Thursday')
        ->toContain('Expect a 15-minute outage.');
});

it('returns empty items for an admin-only collection on a public slot (publicSurface = true)', function () {
    CollectionItem::create([
        'collection_id' => $this->memosCollection->id,
        'sort_order'    => 0,
        'is_published'  => true,
        'data'          => ['title' => 'Internal memo', 'body' => 'admin-only', 'posted_at' => '2026-04-22'],
    ]);

    $contract = (new MemosDefinition())->dataContract(['limit' => 5]);

    $publicCtx = new SlotContext(new PageAmbientContext(), publicSurface: true);
    $adminCtx  = new SlotContext(new PageAmbientContext(), publicSurface: false);

    $resolver = app(ContractResolver::class);
    $publicDto = $resolver->resolve([$contract], $publicCtx)[0];
    $adminDto  = $resolver->resolve([$contract], $adminCtx)[0];

    expect($publicDto)->toBe(['items' => []])
        ->and($adminDto['items'])->toHaveCount(1)
        ->and($adminDto['items'][0]['title'])->toBe('Internal memo');
});

it('honors the limit filter on SOURCE_WIDGET_CONTENT_TYPE reads', function () {
    foreach (range(1, 8) as $i) {
        CollectionItem::create([
            'collection_id' => $this->memosCollection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['title' => "Memo {$i}", 'body' => 'x', 'posted_at' => '2026-04-22'],
        ]);
    }

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: ['title'],
        filters: ['limit' => 3],
        resourceHandle: 'memos',
        contentType: (new MemosDefinition())->dataContract(['limit' => 3])->contentType,
    );

    $ctx = new SlotContext(new PageAmbientContext(), publicSurface: false);
    $dto = app(ContractResolver::class)->resolve([$contract], $ctx)[0];

    expect($dto['items'])->toHaveCount(3);
});

it('renders the empty-state copy when no memos exist', function () {
    $wt = WidgetType::where('handle', 'memos')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['limit' => 5],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)->toContain('No memos posted yet');
});
