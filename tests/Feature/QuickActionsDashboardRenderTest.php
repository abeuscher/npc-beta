<?php

use App\Filament\Resources\ContactResource;
use App\Models\PageWidget;
use App\Widgets\QuickActions\QuickActionsDefinition;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('renders an anchor to the Contact create URL when new_contact is selected', function () {
    $wt = WidgetType::where('handle', 'quick_actions')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['actions' => ['new_contact']],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)
        ->toContain('href="' . ContactResource::getUrl('create') . '"')
        ->toContain('New Contact');
});

it('renders the empty-state copy when no actions are selected', function () {
    $wt = WidgetType::where('handle', 'quick_actions')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['actions' => []],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)->toContain('No actions selected');
});

it('action registry resolves every URL to an internal admin path — no external destinations', function () {
    $base = config('app.url', 'http://localhost');
    foreach (QuickActionsDefinition::actionRegistry() as $key => $entry) {
        $url = ($entry['url'])();

        expect($url)->toBeString()
            ->and($url)->toStartWith($base . '/admin/');
    }
});

it('ignores unknown action keys without breaking the render', function () {
    $wt = WidgetType::where('handle', 'quick_actions')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['actions' => ['new_contact', 'totally_bogus_key']],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)
        ->toContain('New Contact')
        ->not->toContain('totally_bogus_key');
});
