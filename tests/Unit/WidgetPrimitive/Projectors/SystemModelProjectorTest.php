<?php

use App\Models\Page;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects a pre-fetched post collection into a row-set DTO with declared fields only', function () {
    $posts = collect([
        Page::factory()->create([
            'type'             => 'post',
            'title'            => 'Post A',
            'slug'             => 'post-a',
            'meta_description' => 'Hidden excerpt',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]),
    ]);

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title', 'slug'],
        model: 'post',
    );

    $dto = app(SystemModelProjector::class)->project($contract, $posts);

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(1)
        ->and($dto['items'][0])->toBe(['title' => 'Post A', 'slug' => 'post-a'])
        ->and($dto['items'][0])->not->toHaveKey('excerpt')
        ->and($dto['items'][0])->not->toHaveKey('meta_description');
});

it('returns an empty item list for models other than post', function () {
    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['title'],
        model: 'unknown_model',
    );

    $dto = app(SystemModelProjector::class)->project($contract, collect());

    expect($dto)->toBe(['items' => []]);
});
