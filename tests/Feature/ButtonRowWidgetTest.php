<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function makeButtonRow(string $slug, array $config): \App\Models\PageWidget
{
    $page = Page::factory()->create([
        'type'         => 'default',
        'slug'         => $slug,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $wt = WidgetType::where('handle', 'button_row')->firstOrFail();

    return $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

it('creates the button_row widget type with buttons and alignment in the schema', function () {
    $wt = WidgetType::where('handle', 'button_row')->firstOrFail();

    expect($wt->label)->toBe('Button Row');

    $keys = collect($wt->config_schema)->pluck('key')->all();
    expect($keys)->toBe(['buttons', 'alignment']);
});

it('renders the configured buttons through the shared buttons partial', function () {
    $pw = makeButtonRow('button-row-basic', [
        'buttons' => [
            ['text' => 'Donate Now', 'url' => '/donate', 'style' => 'primary'],
            ['text' => 'Learn More', 'url' => '/about', 'style' => 'secondary'],
        ],
        'alignment' => 'center',
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('widget-button-row')
        ->toContain('btn-group--center')
        ->toContain('Donate Now')
        ->toContain('btn--primary')
        ->toContain('Learn More')
        ->toContain('btn--secondary')
        ->toContain('href="/donate"');
});

it('renders nothing when no buttons are configured', function () {
    $pw = makeButtonRow('button-row-empty', ['buttons' => [], 'alignment' => 'left']);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->not->toContain('widget-button-row');
});

it('falls back to left alignment for unknown alignment values', function () {
    $pw = makeButtonRow('button-row-bad-align', [
        'buttons'   => [['text' => 'Go', 'url' => '/', 'style' => 'primary']],
        'alignment' => 'diagonal',
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->toContain('btn-group--left');
});

it('skips buttons with no text', function () {
    $pw = makeButtonRow('button-row-blank-text', [
        'buttons' => [
            ['text' => '', 'url' => '/nowhere', 'style' => 'primary'],
            ['text' => 'Visible', 'url' => '/somewhere', 'style' => 'primary'],
        ],
        'alignment' => 'left',
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Visible')
        ->not->toContain('/nowhere');
});
