<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves the home page at /', function () {
    Page::create([
        'title'        => 'Home',
        'slug'         => 'home',
        'content'      => '<p>Welcome.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/')->assertOk()->assertSee('Home');
});

it('serves a published page at /{slug}', function () {
    Page::create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'content'      => '<p>About content.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/about')->assertOk()->assertSee('About Us');
});

it('returns 404 for an unpublished page', function () {
    Page::create([
        'title'        => 'Draft Page',
        'slug'         => 'draft',
        'content'      => '<p>Not ready.</p>',
        'is_published' => false,
    ]);

    $this->get('/draft')->assertNotFound();
});

it('returns 404 for a non-existent slug', function () {
    $this->get('/does-not-exist')->assertNotFound();
});

it('auto-generates a slug from the title', function () {
    $page = Page::create([
        'title'        => 'Our Mission Statement',
        'content'      => '<p>Content.</p>',
        'is_published' => false,
    ]);

    expect($page->slug)->toBe('our-mission-statement');
});

it('returns 404 at / when no published home page exists', function () {
    $this->get('/')->assertNotFound();
});
