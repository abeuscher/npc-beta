<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('prefixes slug with events/ when page type is changed to event', function () {
    config(['site.events_prefix' => 'events']);

    $page = Page::factory()->create(['slug' => 'my-event', 'type' => 'default']);

    $page->update(['type' => 'event']);

    expect($page->fresh()->slug)->toBe('events/my-event');
});

it('does not double-prefix slug when page type is changed to event and slug already has prefix', function () {
    config(['site.events_prefix' => 'events']);

    $page = Page::factory()->create(['slug' => 'events/my-event', 'type' => 'default']);

    $page->update(['type' => 'event']);

    expect($page->fresh()->slug)->toBe('events/my-event');
});

it('does not change slug when type changes to something other than event', function () {
    config(['site.events_prefix' => 'events']);

    $page = Page::factory()->create(['slug' => 'my-page', 'type' => 'default']);

    $page->update(['type' => 'default']);

    expect($page->fresh()->slug)->toBe('my-page');
});
