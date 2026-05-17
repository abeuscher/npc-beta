<?php

// Session 297 relocated colour off templates to the site-wide Theme palette.
// PageBuilder's `theme_palette` bootstrap key now sources the tier-1
// --np-color-* tokens via ColorTokenResolver (not Template::resolvedPalette),
// so the swatch picker reflects the real Theme palette on every surface and is
// always populated with concrete values.

use App\Livewire\PageBuilder;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\ColorTokenResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('exposes theme_palette as the tier-1 token set with concrete values', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    expect($bootstrap)->toHaveKey('theme_palette');

    $palette = $bootstrap['theme_palette'];
    expect($palette)->toBeArray()->toHaveCount(count(ColorTokenResolver::TIER1));

    foreach ($palette as $entry) {
        expect($entry)->toHaveKeys(['key', 'label', 'value']);
        expect($entry['key'])->toBeString();
        expect($entry['label'])->toBeString();
        // Concrete-values rule: never null/blank.
        expect($entry['value'])->toMatch('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/');
    }

    $byKey = collect($palette)->keyBy('key');
    expect($byKey->keys()->all())->toEqualCanonicalizing(ColorTokenResolver::TIER1);
    expect($byKey['brand']['value'])->toBe('#0172ad');
    expect($byKey['header-bg']['value'])->toBe('#ffffff');
    expect($byKey['nav-link']['value'])->toBe('#373c44');
});

it('reflects saved theme_colors overrides', function () {
    SiteSetting::create([
        'key'   => 'theme_colors',
        'value' => json_encode(['brand' => '#ff0000', 'nav-link' => '#00ff00']),
        'type'  => 'json',
        'group' => 'design',
    ]);

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    $byKey = collect($bootstrap['theme_palette'])->keyBy('key');

    expect($byKey['brand']['value'])->toBe('#ff0000');
    expect($byKey['nav-link']['value'])->toBe('#00ff00');
    // Untouched tokens stay at their concrete default.
    expect($byKey['surface']['value'])->toBe(ColorTokenResolver::defaults()['surface']);
});

it('is populated from defaults even when no template or theme_colors exists', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    expect($bootstrap['theme_palette'])->toBeArray()->not->toBeEmpty();

    $byKey = collect($bootstrap['theme_palette'])->keyBy('key');
    foreach (ColorTokenResolver::defaults() as $token => $hex) {
        expect($byKey[$token]['value'])->toBe($hex);
    }
});
