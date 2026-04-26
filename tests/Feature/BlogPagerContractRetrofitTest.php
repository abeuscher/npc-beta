<?php

use App\Models\Page;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Widgets\BlogPager\BlogPagerDefinition;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('projects only contract-declared fields onto BlogPager rows (fail-closed whitelist with author projection)', function () {
    $author = User::factory()->create(['name' => 'Authored Name']);

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => 'host-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'head_snippet' => 'HEAD_SNIPPET_NOTLEAKED_SENTINEL',
        'author_id'    => $author->id,
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Older Post',
        'slug'         => 'older-post',
        'status'       => 'published',
        'published_at' => now()->subDays(2),
        'author_id'    => $author->id,
    ]);

    $wt = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    $contract = (new BlogPagerDefinition())->dataContract([]);
    $context = new SlotContext(new PageContext($host), $host);
    $dto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto['items'])->toHaveCount(2)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['id', 'title', 'slug', 'url', 'published_at_label', 'image', 'author_name'])
        ->and($dto['items'][0])->not->toHaveKey('head_snippet')
        ->and($dto['items'][0])->not->toHaveKey('meta_title')
        ->and($dto['items'][0])->not->toHaveKey('excerpt');

    $hostRow = collect($dto['items'])->firstWhere('id', $host->id);
    expect($hostRow['author_name'])->toBe('Authored Name');

    app()->instance(PageContext::class, new PageContext($host));

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Authored Name')
        ->not->toContain('HEAD_SNIPPET_NOTLEAKED_SENTINEL');
});

it('renders BlogPager through the contract resolver only, with a single pages select and eager-loaded media + author', function () {
    $author = User::factory()->create(['name' => 'Author One']);

    $newer = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Newer Post',
        'slug'         => 'newer-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'author_id'    => $author->id,
    ]);

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => 'host-post',
        'status'       => 'published',
        'published_at' => now()->subDays(2),
        'author_id'    => $author->id,
    ]);

    $older = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Older Post',
        'slug'         => 'older-post',
        'status'       => 'published',
        'published_at' => now()->subDays(3),
        'author_id'    => $author->id,
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Draft Post Should Not Render',
        'slug'         => 'draft-post',
        'status'       => 'draft',
        'published_at' => null,
        'author_id'    => $author->id,
    ]);

    $wt = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    app()->instance(PageContext::class, new PageContext($host));

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $postSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, '"pages"')
            && str_contains($sql, '"type"')
            && str_contains($sql, '"status"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    $userBatchSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "users"')
            && str_contains($sql, '"id" in');
    }));

    expect(substr_count($html, 'class="pager-link__anchor"'))->toBe(2)
        ->and($html)->toContain('href="http://localhost/newer-post"')
        ->and($html)->toContain('href="http://localhost/older-post"')
        ->and($html)->toContain('Newer Post')
        ->and($html)->toContain('Older Post')
        ->and($html)->not->toContain('Draft Post Should Not Render')
        ->and(count($postSelects))->toBe(1)
        ->and($postSelects[0]['query'])->toContain('COALESCE(published_at, created_at)')
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($userBatchSelects))->toBe(1);
});
