<?php

use App\Models\Post;
use App\Models\SiteSetting;
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
});

it('published post is accessible at the blog prefix URL', function () {
    Post::create([
        'title'        => 'Hello World',
        'slug'         => 'hello-world',
        'content'      => '<p>First post.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/news/hello-world')->assertOk()->assertSee('Hello World');
});

it('unpublished post returns 404', function () {
    Post::create([
        'title'        => 'Draft Post',
        'slug'         => 'draft-post',
        'content'      => '<p>Not ready.</p>',
        'is_published' => false,
    ]);

    $this->get('/news/draft-post')->assertNotFound();
});

it('missing slug returns 404', function () {
    $this->get('/news/does-not-exist')->assertNotFound();
});

it('post index returns only published posts', function () {
    Post::create([
        'title'        => 'Published One',
        'slug'         => 'published-one',
        'content'      => '<p>Live.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    Post::create([
        'title'        => 'Draft Two',
        'slug'         => 'draft-two',
        'content'      => '<p>Draft.</p>',
        'is_published' => false,
    ]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('Published One')
        ->assertDontSee('Draft Two');
});

it('post index paginates results', function () {
    for ($i = 1; $i <= 20; $i++) {
        Post::create([
            'title'        => "Post {$i}",
            'slug'         => "post-{$i}",
            'content'      => '<p>Content.</p>',
            'is_published' => true,
            'published_at' => now()->subDays($i),
        ]);
    }

    $response = $this->get('/news');
    $response->assertOk();

    // Default pagination is 15
    $viewPosts = $response->viewData('posts');
    expect($viewPosts->count())->toBe(15);
});
