<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves the home page at /', function () {
    Page::factory()->create([
        'title'        => 'Home',
        'slug'         => 'home',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')->assertOk()->assertSee('Home');
});

it('serves a published page at /{slug}', function () {
    Page::factory()->create([
        'title'        => 'About Us',
        'slug'         => 'about',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')->assertOk()->assertSee('About Us');
});

it('serves a published page with a nested slug', function () {
    Page::factory()->create([
        'title'        => 'Board Meeting',
        'slug'         => 'events/board-meeting',
        'type'         => 'event',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get('/events/board-meeting')->assertOk()->assertSee('Board Meeting');
});

it('returns 404 for an unpublished page', function () {
    Page::factory()->create([
        'title'        => 'Draft Page',
        'slug'         => 'draft',
        'status' => 'draft',
    ]);

    $this->get('/draft')->assertNotFound();
});

it('returns 404 for a non-existent slug', function () {
    $this->get('/does-not-exist')->assertNotFound();
});

it('auto-generates a slug from the title', function () {
    $user = \App\Models\User::factory()->create();
    $page = Page::create([
        'author_id'    => $user->id,
        'title'        => 'Our Mission Statement',
        'status' => 'draft',
    ]);

    expect($page->slug)->toBe('our-mission-statement');
});

it('returns 404 at / when no published home page exists', function () {
    $this->get('/')->assertNotFound();
});

it('page type defaults to default', function () {
    $page = Page::factory()->create([
        'title'        => 'Simple Page',
        'slug'         => 'simple',
        'status' => 'draft',
    ]);

    expect($page->type)->toBe('default');
});
