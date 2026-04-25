<?php

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Widgets\BoardMembers\BoardMembersDefinition;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('projects only contract-declared fields onto BoardMembers rows (fail-closed whitelist)', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-board',
        'name'        => 'Audit Board',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'photo',            'type' => 'image'],
            ['key' => 'name',             'type' => 'text'],
            ['key' => 'job_title',        'type' => 'text'],
            ['key' => 'department',       'type' => 'text'],
            ['key' => 'bio',              'type' => 'text'],
            ['key' => 'linkedin',         'type' => 'text'],
            ['key' => 'github',           'type' => 'text'],
            ['key' => 'extra_url',        'type' => 'text'],
            ['key' => 'extra_url_label',  'type' => 'text'],
            ['key' => 'internal_notes',   'type' => 'text'],
            ['key' => 'legacy_id',        'type' => 'text'],
        ],
    ]);

    $rows = [
        ['name' => 'Alice Adams',  'job_title' => 'Chair',     'department' => 'Exec',    'bio' => 'Bio A', 'linkedin' => 'https://linkedin.com/in/alice', 'github' => 'https://github.com/alice', 'extra_url' => 'https://a.example', 'extra_url_label' => 'Site A'],
        ['name' => 'Bob Brown',    'job_title' => 'Treasurer', 'department' => 'Finance', 'bio' => 'Bio B', 'linkedin' => 'https://linkedin.com/in/bob',   'github' => 'https://github.com/bob',   'extra_url' => 'https://b.example', 'extra_url_label' => 'Site B'],
        ['name' => 'Carol Chen',   'job_title' => 'Secretary', 'department' => 'Gov',     'bio' => 'Bio C', 'linkedin' => 'https://linkedin.com/in/carol', 'github' => 'https://github.com/carol', 'extra_url' => 'https://c.example', 'extra_url_label' => 'Site C'],
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

    // Resolver-level invariant: contract-declared shape only.
    $resolver = app(ContractResolver::class);
    $contract = (new BoardMembersDefinition())->dataContract([
        'collection_handle'     => 'audit-board',
        'image_field'           => 'photo',
        'name_field'            => 'name',
        'title_field'           => 'job_title',
        'department_field'      => 'department',
        'description_field'     => 'bio',
        'linkedin_field'        => 'linkedin',
        'github_field'          => 'github',
        'extra_url_field'       => 'extra_url',
        'extra_url_label_field' => 'extra_url_label',
    ]);
    $dto = $resolver->resolve([$contract], new SlotContext(new PageContext()))[0];

    $expectedKeys = ['photo', 'name', 'job_title', 'department', 'bio', 'linkedin', 'github', 'extra_url', 'extra_url_label', '_media'];

    expect($dto['items'])->toHaveCount(3)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing($expectedKeys)
        ->and(array_keys($dto['items'][1]))->toEqualCanonicalizing($expectedKeys)
        ->and(array_keys($dto['items'][2]))->toEqualCanonicalizing($expectedKeys)
        ->and($dto['items'][0])->not->toHaveKey('internal_notes')
        ->and($dto['items'][0])->not->toHaveKey('legacy_id')
        ->and($dto['items'][0]['_media'])->toHaveKey('photo');

    // HTML-level invariant: names/titles render; sentinels do not leak.
    $wt = WidgetType::where('handle', 'board_members')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Board Host', 'slug' => 'board-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'               => 'Audit Board',
            'collection_handle'     => 'audit-board',
            'image_field'           => 'photo',
            'name_field'            => 'name',
            'title_field'           => 'job_title',
            'department_field'      => 'department',
            'description_field'     => 'bio',
            'linkedin_field'        => 'linkedin',
            'github_field'          => 'github',
            'extra_url_field'       => 'extra_url',
            'extra_url_label_field' => 'extra_url_label',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Alice Adams')
        ->toContain('Bob Brown')
        ->toContain('Carol Chen')
        ->toContain('Chair')
        ->toContain('Treasurer')
        ->not->toContain('NOTES_NOTLEAKED_SENTINEL')
        ->not->toContain('LEGACY_ID_SENTINEL');
});

it('renders BoardMembers through the contract resolver only, with one collections + one collection_items + one media select', function () {
    $collection = CmsCollection::create([
        'handle'      => 'audit-board',
        'name'        => 'Audit Board',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'photo', 'type' => 'image'],
            ['key' => 'name',  'type' => 'text'],
            ['key' => 'job_title', 'type' => 'text'],
        ],
    ]);

    $rows = [
        ['name' => 'Live Alice', 'job_title' => 'Chair'],
        ['name' => 'Live Bob',   'job_title' => 'Treasurer'],
        ['name' => 'Live Carol', 'job_title' => 'Secretary'],
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
        'data'          => ['name' => 'Unpublished Member Should Not Render', 'job_title' => 'Hidden'],
    ]);

    $wt = WidgetType::where('handle', 'board_members')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Query Host', 'slug' => 'board-query-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'           => 'Query Board',
            'collection_handle' => 'audit-board',
            'image_field'       => 'photo',
            'name_field'        => 'name',
            'title_field'       => 'job_title',
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

    expect(substr_count($html, '<article class="board-member'))->toBe(3)
        ->and($html)->toContain('Live Alice')
        ->and($html)->toContain('Live Bob')
        ->and($html)->toContain('Live Carol')
        ->and($html)->not->toContain('Unpublished Member Should Not Render')
        ->and(count($collectionSelects))->toBe(1)
        ->and(count($itemSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1);
});
