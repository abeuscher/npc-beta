<?php

use App\Models\Collection;
use App\WidgetPrimitive\Source;
use Database\Seeders\MemosCollectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds a memos collection with admin-only, human-only policy', function () {
    (new MemosCollectionSeeder())->run();

    $memos = Collection::where('handle', 'memos')->first();

    expect($memos)->not->toBeNull()
        ->and($memos->name)->toBe('Memos')
        ->and($memos->source_type)->toBe('custom')
        ->and($memos->is_public)->toBeFalse()
        ->and($memos->is_active)->toBeTrue()
        ->and($memos->accepted_sources)->toBe([Source::HUMAN]);
});

it('declares the title/body/posted_at fields expected by the memos widget contract', function () {
    (new MemosCollectionSeeder())->run();

    $memos = Collection::where('handle', 'memos')->first();
    $fieldKeys = array_column($memos->fields, 'key');

    expect($fieldKeys)->toBe(['title', 'body', 'posted_at']);
});

it('is idempotent — running twice produces a single row', function () {
    (new MemosCollectionSeeder())->run();
    (new MemosCollectionSeeder())->run();

    expect(Collection::where('handle', 'memos')->count())->toBe(1);
});
