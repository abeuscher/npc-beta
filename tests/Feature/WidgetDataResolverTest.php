<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Post;
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

it('returns published collection items for a valid public custom collection', function () {
    $collection = Collection::create([
        'name'        => 'FAQ',
        'handle'      => 'faq',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
        ],
    ]);

    CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['question' => 'What is this?'],
        'sort_order'    => 1,
        'is_published'  => true,
    ]);

    // Unpublished item — should be excluded
    CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['question' => 'Draft question'],
        'sort_order'    => 2,
        'is_published'  => false,
    ]);

    $result = WidgetDataResolver::resolve('faq');

    expect($result)->toHaveCount(1)
        ->and($result[0]['question'])->toBe('What is this?');
});

it('returns published posts for the blog_posts handle', function () {
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

    Post::create([
        'title'        => 'Published Post',
        'slug'         => 'published-post',
        'content'      => 'Content here',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    Post::create([
        'title'        => 'Draft Post',
        'slug'         => 'draft-post',
        'content'      => 'Draft content',
        'is_published' => false,
        'published_at' => null,
    ]);

    $result = WidgetDataResolver::resolve('blog_posts');

    expect($result)->toHaveCount(1)
        ->and($result[0]['title'])->toBe('Published Post');
});

it('respects the limit parameter', function () {
    $collection = Collection::create([
        'name'        => 'Items',
        'handle'      => 'items',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
        ],
    ]);

    foreach (range(1, 5) as $i) {
        CollectionItem::create([
            'collection_id' => $collection->id,
            'data'          => ['name' => "Item $i"],
            'sort_order'    => $i,
            'is_published'  => true,
        ]);
    }

    $result = WidgetDataResolver::resolve('items', ['limit' => 3]);

    expect($result)->toHaveCount(3);
});
