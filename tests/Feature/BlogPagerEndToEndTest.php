<?php

use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    SiteSetting::create([
        'key'   => 'blog_prefix',
        'value' => 'news',
        'group' => 'general',
        'type'  => 'string',
    ]);
    config(['site.blog_prefix' => 'news']);

    Page::factory()->create([
        'slug'         => 'news',
        'title'        => 'News',
        'type'         => 'default',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('renders BlogPager prev/next links when a post is viewed via PostController::show', function () {
    $author = User::factory()->create(['name' => 'Author One']);
    $blogPrefix = config('site.blog_prefix', 'news');

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Newer Post',
        'slug'         => $blogPrefix . '/newer-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'author_id'    => $author->id,
    ]);

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => $blogPrefix . '/host-post',
        'status'       => 'published',
        'published_at' => now()->subDays(2),
        'author_id'    => $author->id,
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Older Post',
        'slug'         => $blogPrefix . '/older-post',
        'status'       => 'published',
        'published_at' => now()->subDays(3),
        'author_id'    => $author->id,
    ]);

    $blogPagerType = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    $host->widgets()->create([
        'widget_type_id' => $blogPagerType->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $response = $this->get('/' . $blogPrefix . '/host-post');

    $response->assertOk();
    $html = $response->getContent();

    expect($html)
        ->toContain('class="pager-link__anchor"')
        ->toContain('href="' . url('/' . $blogPrefix . '/newer-post') . '"')
        ->toContain('href="' . url('/' . $blogPrefix . '/older-post') . '"')
        ->toContain('Newer Post')
        ->toContain('Older Post')
        ->not->toContain('{{item.title}}');
});

it('renders BlogPager only the next link when the host post is the oldest', function () {
    $author = User::factory()->create(['name' => 'Author One']);
    $blogPrefix = config('site.blog_prefix', 'news');

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Newer Post',
        'slug'         => $blogPrefix . '/newer-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'author_id'    => $author->id,
    ]);

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post (Oldest)',
        'slug'         => $blogPrefix . '/host-post',
        'status'       => 'published',
        'published_at' => now()->subDays(5),
        'author_id'    => $author->id,
    ]);

    $blogPagerType = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    $host->widgets()->create([
        'widget_type_id' => $blogPagerType->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $response = $this->get('/' . $blogPrefix . '/host-post');

    $response->assertOk();
    $html = $response->getContent();

    expect($html)
        ->toContain('href="' . url('/' . $blogPrefix . '/newer-post') . '"')
        ->toContain('Newer Post')
        ->not->toContain('{{item.title}}');

    expect(substr_count($html, 'class="pager-link__anchor"'))->toBe(1);
});
