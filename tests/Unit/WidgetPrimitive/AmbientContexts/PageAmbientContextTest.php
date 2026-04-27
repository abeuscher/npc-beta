<?php

use App\Models\Page;
use App\WidgetPrimitive\AmbientContext;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('round-trips a Page via its readonly currentPage field', function () {
    $page = Page::factory()->create(['title' => 'Ambient Page']);

    $ambient = new PageAmbientContext($page);

    expect($ambient)->toBeInstanceOf(AmbientContext::class)
        ->and($ambient->currentPage)->not->toBeNull()
        ->and($ambient->currentPage->id)->toBe($page->id);
});

it('defaults currentPage to null when constructed without args', function () {
    $ambient = new PageAmbientContext();

    expect($ambient)->toBeInstanceOf(AmbientContext::class)
        ->and($ambient->currentPage)->toBeNull();
});
