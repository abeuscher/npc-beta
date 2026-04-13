<?php

use App\Livewire\PageBuilder;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('exposes widget presets through the page-builder bootstrap payload', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    expect($bootstrap)->toHaveKey('widget_types');

    $hero = collect($bootstrap['widget_types'])->firstWhere('handle', 'hero');

    expect($hero)->not->toBeNull();
    expect($hero)->toHaveKey('presets');
    expect($hero['presets'])->toBeArray()->not->toBeEmpty();

    $first = $hero['presets'][0];
    expect($first)->toHaveKeys(['handle', 'label', 'config', 'appearance_config']);
    expect($first['handle'])->toBeString();
    expect($first['label'])->toBeString();
    expect($first['config'])->toBeArray();
    expect($first['appearance_config'])->toBeArray();
});
