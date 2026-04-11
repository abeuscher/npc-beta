<?php

use App\Livewire\PageBuilder;
use App\Models\Page;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('exposes theme_palette in bootstrap data with the expected shape', function () {
    Template::factory()->create([
        'type'             => 'page',
        'is_default'       => true,
        'primary_color'    => '#aabbcc',
        'header_bg_color'  => '#ffffff',
        'footer_bg_color'  => '#000000',
        'nav_link_color'   => '#111111',
        'nav_hover_color'  => '#222222',
        'nav_active_color' => '#333333',
    ]);

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    expect($bootstrap)->toHaveKey('theme_palette');

    $palette = $bootstrap['theme_palette'];
    expect($palette)->toBeArray()->toHaveCount(6);

    foreach ($palette as $entry) {
        expect($entry)->toHaveKeys(['key', 'label', 'value']);
        expect($entry['key'])->toBeString();
        expect($entry['label'])->toBeString();
    }

    $byKey = collect($palette)->keyBy('key');
    expect($byKey)->toHaveKeys([
        'primary_color',
        'header_bg_color',
        'footer_bg_color',
        'nav_link_color',
        'nav_hover_color',
        'nav_active_color',
    ]);

    expect($byKey['primary_color']['value'])->toBe('#aabbcc');
    expect($byKey['header_bg_color']['value'])->toBe('#ffffff');
    expect($byKey['nav_active_color']['value'])->toBe('#333333');
});

it('sources theme_palette from the page-assigned template when one is set', function () {
    Template::factory()->create([
        'type'             => 'page',
        'is_default'       => true,
        'primary_color'    => '#000000',
        'header_bg_color'  => '#000000',
        'footer_bg_color'  => '#000000',
        'nav_link_color'   => '#000000',
        'nav_hover_color'  => '#000000',
        'nav_active_color' => '#000000',
    ]);

    $custom = Template::factory()->create([
        'type'             => 'page',
        'is_default'       => false,
        'primary_color'    => '#ff0000',
        'header_bg_color'  => '#00ff00',
        'footer_bg_color'  => '#0000ff',
        'nav_link_color'   => '#ffff00',
        'nav_hover_color'  => '#ff00ff',
        'nav_active_color' => '#00ffff',
    ]);

    $page = Page::factory()->create([
        'type'        => 'default',
        'status'      => 'published',
        'template_id' => $custom->id,
    ]);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    $byKey = collect($bootstrap['theme_palette'])->keyBy('key');

    expect($byKey['primary_color']['value'])->toBe('#ff0000');
    expect($byKey['header_bg_color']['value'])->toBe('#00ff00');
    expect($byKey['footer_bg_color']['value'])->toBe('#0000ff');
    expect($byKey['nav_link_color']['value'])->toBe('#ffff00');
    expect($byKey['nav_hover_color']['value'])->toBe('#ff00ff');
    expect($byKey['nav_active_color']['value'])->toBe('#00ffff');
});

it('falls back to default template values for null fields on the assigned template', function () {
    Template::factory()->create([
        'type'             => 'page',
        'is_default'       => true,
        'primary_color'    => '#aaaaaa',
        'header_bg_color'  => '#bbbbbb',
        'footer_bg_color'  => '#cccccc',
        'nav_link_color'   => '#dddddd',
        'nav_hover_color'  => '#eeeeee',
        'nav_active_color' => '#ffffff',
    ]);

    $custom = Template::factory()->create([
        'type'             => 'page',
        'is_default'       => false,
        'primary_color'    => '#123456',
        // Other colour fields left null — should inherit from default template.
    ]);

    $page = Page::factory()->create([
        'type'        => 'default',
        'status'      => 'published',
        'template_id' => $custom->id,
    ]);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    $byKey = collect($bootstrap['theme_palette'])->keyBy('key');

    expect($byKey['primary_color']['value'])->toBe('#123456');
    expect($byKey['header_bg_color']['value'])->toBe('#bbbbbb');
    expect($byKey['footer_bg_color']['value'])->toBe('#cccccc');
    expect($byKey['nav_link_color']['value'])->toBe('#dddddd');
    expect($byKey['nav_hover_color']['value'])->toBe('#eeeeee');
    expect($byKey['nav_active_color']['value'])->toBe('#ffffff');
});

it('returns an empty theme_palette when no templates exist at all', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $bootstrap = Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();

    expect($bootstrap)->toHaveKey('theme_palette');
    expect($bootstrap['theme_palette'])->toBeArray()->toBeEmpty();
});
