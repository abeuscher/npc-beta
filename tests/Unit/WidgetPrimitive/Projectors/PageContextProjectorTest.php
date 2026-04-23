<?php

use App\Models\Page;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\PageContextProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects only the declared fields of a page-context contract', function () {
    $page = Page::factory()->create([
        'title'            => 'Hello',
        'meta_description' => 'Hidden excerpt',
        'published_at'     => '2026-04-22 00:00:00',
    ]);

    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title']);

    $dto = app(PageContextProjector::class)->project($contract, $page);

    expect($dto)->toBe(['title' => 'Hello'])
        ->and($dto)->not->toHaveKey('excerpt')
        ->and($dto)->not->toHaveKey('date');
});

it('returns empty strings for declared fields when the page is null', function () {
    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT, ['title', 'date']);

    $dto = app(PageContextProjector::class)->project($contract, null);

    expect($dto)->toBe(['title' => '', 'date' => '']);
});

it('returns the full page-context token map when the contract declares no fields', function () {
    $page = Page::factory()->create([
        'title'        => 'Contextual',
        'published_at' => '2026-04-22 00:00:00',
    ]);

    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT);

    $dto = app(PageContextProjector::class)->project($contract, $page);

    expect($dto)->toHaveKeys(['title', 'date', 'excerpt', 'author', 'starts_at', 'location'])
        ->and($dto['title'])->toBe('Contextual')
        ->and($dto['date'])->toBe('April 22, 2026');
});

it('returns empty strings for every token when fields are omitted and the page is null', function () {
    $contract = new DataContract('1.0.0', DataContract::SOURCE_PAGE_CONTEXT);

    $dto = app(PageContextProjector::class)->project($contract, null);

    expect($dto)->toBe([
        'title'     => '',
        'date'      => '',
        'excerpt'   => '',
        'author'    => '',
        'starts_at' => '',
        'location'  => '',
    ]);
});
