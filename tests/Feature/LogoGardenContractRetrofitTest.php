<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Widgets\LogoGarden\LogoGardenDefinition;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function logoGardenRetrofitSamplePath(): string
{
    $files = glob(resource_path('sample-images/logos/*'));
    $files = array_values(array_filter($files, fn ($p) => is_file($p) && ! str_starts_with(basename($p), '.')));
    if (empty($files)) {
        throw new RuntimeException('No sample logos available in resources/sample-images/logos/');
    }
    return $files[0];
}

it('projects only contract-declared fields onto LogoGarden rows (fail-closed whitelist)', function () {
    Storage::fake('public');

    $collection = CmsCollection::create([
        'handle'      => 'audit-logos',
        'name'        => 'Audit Logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'logo',           'type' => 'image'],
            ['key' => 'partner_name',   'type' => 'text'],
            ['key' => 'internal_notes', 'type' => 'text'],
            ['key' => 'legacy_id',      'type' => 'text'],
        ],
    ]);

    $names = ['Acme Corp', 'Beta Industries', 'Gamma Foundation'];
    foreach ($names as $i => $name) {
        $item = CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => [
                'partner_name'   => $name,
                'internal_notes' => 'NOTES_NOTLEAKED_SENTINEL',
                'legacy_id'      => 'LEGACY_ID_SENTINEL',
            ],
        ]);

        $item->addMedia(logoGardenRetrofitSamplePath())
            ->preservingOriginal()
            ->toMediaCollection('logo');
    }

    // Resolver-level invariant: contract-declared shape only.
    $resolver = app(ContractResolver::class);
    $contract = (new LogoGardenDefinition())->dataContract([
        'collection_handle' => 'audit-logos',
        'image_field'       => 'logo',
        'name_field'        => 'partner_name',
    ]);
    $dto = $resolver->resolve([$contract], new SlotContext(new PageContext()))[0];

    expect($dto['items'])->toHaveCount(3)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['logo', 'partner_name', '_media'])
        ->and(array_keys($dto['items'][1]))->toEqualCanonicalizing(['logo', 'partner_name', '_media'])
        ->and(array_keys($dto['items'][2]))->toEqualCanonicalizing(['logo', 'partner_name', '_media'])
        ->and($dto['items'][0])->not->toHaveKey('internal_notes')
        ->and($dto['items'][0])->not->toHaveKey('legacy_id')
        ->and($dto['items'][0]['_media'])->toHaveKey('logo');

    // HTML-level invariant: names render; sentinels do not leak.
    $wt = WidgetType::where('handle', 'logo_garden')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Logo Host', 'slug' => 'logo-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'audit-logos',
            'image_field'       => 'logo',
            'name_field'        => 'partner_name',
            'show_name'         => true,
            'display_mode'      => 'static',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Acme Corp')
        ->toContain('Beta Industries')
        ->toContain('Gamma Foundation')
        ->not->toContain('NOTES_NOTLEAKED_SENTINEL')
        ->not->toContain('LEGACY_ID_SENTINEL');
});

it('renders LogoGarden through the contract resolver only, with one collections + one collection_items + one media select', function () {
    Storage::fake('public');

    $collection = CmsCollection::create([
        'handle'      => 'audit-logos',
        'name'        => 'Audit Logos',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'logo',         'type' => 'image'],
            ['key' => 'partner_name', 'type' => 'text'],
        ],
    ]);

    $names = ['Live Acme', 'Live Beta', 'Live Gamma'];
    foreach ($names as $i => $name) {
        $item = CollectionItem::create([
            'collection_id' => $collection->id,
            'sort_order'    => $i,
            'is_published'  => true,
            'data'          => ['partner_name' => $name],
        ]);

        $item->addMedia(logoGardenRetrofitSamplePath())
            ->preservingOriginal()
            ->toMediaCollection('logo');
    }

    $unpublished = CollectionItem::create([
        'collection_id' => $collection->id,
        'sort_order'    => 99,
        'is_published'  => false,
        'data'          => ['partner_name' => 'Unpublished Logo Should Not Render'],
    ]);
    $unpublished->addMedia(logoGardenRetrofitSamplePath())
        ->preservingOriginal()
        ->toMediaCollection('logo');

    $wt = WidgetType::where('handle', 'logo_garden')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Logo Query Host', 'slug' => 'logo-query-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'audit-logos',
            'image_field'       => 'logo',
            'name_field'        => 'partner_name',
            'show_name'         => true,
            'display_mode'      => 'static',
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

    expect(substr_count($html, '<div class="logo-garden__cell">'))->toBe(3)
        ->and($html)->toContain('Live Acme')
        ->and($html)->toContain('Live Beta')
        ->and($html)->toContain('Live Gamma')
        ->and($html)->not->toContain('Unpublished Logo Should Not Render')
        ->and(count($collectionSelects))->toBe(1)
        ->and(count($itemSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1);
});
