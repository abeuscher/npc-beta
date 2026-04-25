<?php

use App\Models\Collection;
use App\Models\Page;
use App\Services\WidgetDataResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns empty array for a non-public collection handle', function () {
    Collection::create([
        'name'        => 'Private',
        'handle'      => 'private-stuff',
        'source_type' => 'custom',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => [],
    ]);

    $result = WidgetDataResolver::resolve('private-stuff');

    expect($result)->toBe([]);
});

it('returns empty array for a non-existent handle', function () {
    $result = WidgetDataResolver::resolve('does-not-exist');

    expect($result)->toBe([]);
});

it('returns published Page records of type post for the blog_posts handle', function () {
    $blogPostsCollection = Collection::where('handle', 'blog_posts')->first();

    // Ensure the blog_posts system collection exists and is public
    if (! $blogPostsCollection) {
        Collection::create([
            'name'        => 'Blog Posts',
            'handle'      => 'blog_posts',
            'source_type' => 'blog_posts',
            'is_public'   => true,
            'is_active'   => true,
            'fields'      => [],
        ]);
    } else {
        $blogPostsCollection->update(['is_public' => true, 'is_active' => true]);
    }

    Page::factory()->create([
        'title'        => 'Published Post',
        'slug'         => 'news/published-post',
        'type'         => 'post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    Page::factory()->create([
        'title'        => 'Draft Post',
        'slug'         => 'news/draft-post',
        'type'         => 'post',
        'status'       => 'draft',
        'published_at' => null,
    ]);

    $result = WidgetDataResolver::resolve('blog_posts');

    expect($result)->toHaveCount(1)
        ->and($result[0]['title'])->toBe('Published Post');
});
