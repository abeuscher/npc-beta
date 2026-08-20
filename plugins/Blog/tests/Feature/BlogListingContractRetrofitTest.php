<?php

use App\Models\Page;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

// guards: BlogListing whitelist (post DTO + listingData JSON shape, meta_title/head_snippet/id non-leak); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('projects only contract-declared fields onto BlogListing rows (fail-closed whitelist)', function () {
    $author = User::factory()->create();

    $post = Page::factory()->create([
        'type'             => 'post',
        'title'            => 'Whitelisted Title',
        'slug'             => 'whitelisted-post',
        'status'           => 'published',
        'published_at'     => now()->subDay(),
        'meta_title'       => 'META_TITLE_NOTLEAKED_SENTINEL',
        'meta_description' => 'Derived excerpt text',
        'head_snippet'     => 'HEAD_SNIPPET_NOTLEAKED_SENTINEL',
        'author_id'        => $author->id,
    ]);

    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    $host = Page::factory()->create([
        'title'  => 'Retrofit Host',
        'slug'   => 'retrofit-host',
        'status' => 'published',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="title">{{item.title}}</p><p class="meta">{{item.meta_description}}</p><p class="author">{{item.author_id}}</p><p class="internal">{{item.id}}</p><p class="metatitle">{{item.meta_title}}</p><p class="head">{{item.head_snippet}}</p>',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Whitelisted Title')
        ->toContain('<p class="meta"></p>')
        ->toContain('<p class="author"></p>')
        ->toContain('<p class="internal"></p>')
        ->toContain('<p class="metatitle"></p>')
        ->toContain('<p class="head"></p>')
        ->not->toContain('META_TITLE_NOTLEAKED_SENTINEL')
        ->not->toContain('HEAD_SNIPPET_NOTLEAKED_SENTINEL')
        ->not->toContain($post->id);

    preg_match('#<script x-ref="listingData" type="application/json">(.+?)</script>#s', $html, $match);
    $listing = json_decode($match[1], true);

    expect($listing['items'])->toHaveCount(1)
        ->and(array_keys($listing['items'][0]))->toEqualCanonicalizing(['title', 'slug', 'url', 'published_at', 'post_date', 'excerpt', 'image']);
});

// guards: BlogListing query pattern (1 pages + 1 media + 1 user-batch select, COALESCE(published_at, created_at) ordering); N>=2 redundant for ContractResolver mutations per session-241 audit.
it('renders BlogListing through the contract resolver only, with a single pages select and eager-loaded media', function () {
    for ($i = 0; $i < 3; $i++) {
        Page::factory()->create([
            'type'         => 'post',
            'title'        => 'Published Post ' . $i,
            'slug'         => 'published-post-' . $i,
            'status'       => 'published',
            'published_at' => now()->subDays($i + 1),
        ]);
    }

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Draft Post Should Not Render',
        'slug'         => 'draft-post',
        'status'       => 'draft',
        'published_at' => null,
    ]);

    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

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
            'sort_default'     => 'newest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
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

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], '"media"')));

    $userBatchSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "users"')
            && str_contains($sql, '"id" in');
    }));

    expect(substr_count($html, '<article class="card">'))->toBe(3)
        ->and($html)->not->toContain('Draft Post Should Not Render')
        ->and(count($postSelects))->toBe(1)
        ->and($postSelects[0]['query'])->toContain('COALESCE(published_at, created_at)')
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($userBatchSelects))->toBe(1);
});
