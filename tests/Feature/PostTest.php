<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Ensure blog_prefix setting exists
    SiteSetting::create([
        'key'   => 'blog_prefix',
        'value' => 'news',
        'group' => 'general',
        'type'  => 'string',
    ]);
    config(['site.blog_prefix' => 'news']);

    // Ensure the text_block widget type exists for content seeding
    WidgetType::firstOrCreate(
        ['handle' => 'text_block'],
        [
            'label'         => 'Text Block',
            'render_mode'   => 'server',
            'collections'   => [],
            'config_schema' => [['key' => 'content', 'type' => 'richtext', 'label' => 'Content']],
            'template'      => "{!! \$config['content'] ?? '' !!}",
        ]
    );

    // Seed the blog index page so index requests succeed
    Page::firstOrCreate(
        ['slug' => 'news'],
        [
            'title'        => 'News',
            'type'         => 'default',
            'is_published' => true,
            'published_at' => now(),
        ]
    );
});

/**
 * Helper: create a blog post Page and attach a text_block widget with the given content.
 */
function makePost(array $attributes): Page
{
    $blogPrefix = config('site.blog_prefix', 'news');
    $slug       = $attributes['slug'] ?? null;

    if ($slug && ! str_starts_with($slug, $blogPrefix . '/')) {
        $slug = $blogPrefix . '/' . $slug;
    }

    $page = Page::create(array_merge([
        'type'         => 'post',
        'is_published' => true,
        'published_at' => now(),
    ], $attributes, ['slug' => $slug]));

    $widgetType = WidgetType::where('handle', 'text_block')->first();
    if ($widgetType && isset($attributes['content'])) {
        PageWidget::create([
            'page_id'        => $page->id,
            'widget_type_id' => $widgetType->id,
            'label'          => 'Post Content',
            'config'         => ['content' => $attributes['content']],
            'sort_order'     => 1,
            'is_active'      => true,
        ]);
    }

    return $page;
}

it('slug is stored with the blog prefix', function () {
    $page = makePost([
        'title' => 'Hello World',
        'slug'  => 'news/hello-world',
    ]);

    expect($page->slug)->toBe('news/hello-world');
    expect(Page::where('type', 'post')->where('slug', 'news/hello-world')->exists())->toBeTrue();
});

it('published post is accessible at the blog prefix URL', function () {
    makePost([
        'title'   => 'Hello World',
        'slug'    => 'news/hello-world',
        'content' => '<p>First post.</p>',
    ]);

    $this->get('/news/hello-world')->assertOk()->assertSee('Hello World');
});

it('unpublished post returns 404', function () {
    makePost([
        'title'        => 'Draft Post',
        'slug'         => 'news/draft-post',
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->get('/news/draft-post')->assertNotFound();
});

it('missing slug returns 404', function () {
    $this->get('/news/does-not-exist')->assertNotFound();
});

it('post index renders the blog index page', function () {
    $this->get('/news')->assertOk()->assertSee('News');
});
