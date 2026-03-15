<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves the home page at /', function () {
    Page::create([
        'title'        => 'Home',
        'slug'         => 'home',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/')->assertOk()->assertSee('Home');
});

it('serves a published page at /{slug}', function () {
    Page::create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/about')->assertOk()->assertSee('About Us');
});

it('serves a published page with a nested slug', function () {
    Page::create([
        'title'        => 'Board Meeting',
        'slug'         => 'events/board-meeting',
        'type'         => 'event',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->get('/events/board-meeting')->assertOk()->assertSee('Board Meeting');
});

it('returns 404 for an unpublished page', function () {
    Page::create([
        'title'        => 'Draft Page',
        'slug'         => 'draft',
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
        'is_published' => false,
    ]);

    expect($page->slug)->toBe('our-mission-statement');
});

it('returns 404 at / when no published home page exists', function () {
    $this->get('/')->assertNotFound();
});

it('page type defaults to default', function () {
    $page = Page::create([
        'title'        => 'Simple Page',
        'slug'         => 'simple',
        'is_published' => false,
    ]);

    expect($page->type)->toBe('default');
});
