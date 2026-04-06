<?php

use App\Livewire\PageBuilderBlock;
use App\Livewire\PageBuilderInspector;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

function seedWidgetTypes(): void
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();
}

function createPageWidget(string $handle, array $config = []): PageWidget
{
    $wt = WidgetType::where('handle', $handle)->firstOrFail();
    $page = Page::factory()->create(['title' => 'Test', 'slug' => 'test-' . uniqid(), 'status' => 'published']);

    return PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => $wt->label . ' Test',
        'config'         => array_merge($wt->getDefaultConfig(), $config),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

// -------------------------------------------------------------------------
// Phase 1: data-config-key attributes in rendered HTML
// -------------------------------------------------------------------------

it('hero template includes data-config-key="content" on the content div', function () {
    seedWidgetTypes();
    $pw = createPageWidget('hero', ['content' => '<h1>Hello World</h1>']);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('data-config-key="content"')
        ->toContain('data-config-type="richtext"')
        ->toContain('Hello World');
});

it('text_block template wraps content in a data-config-key div', function () {
    seedWidgetTypes();
    $pw = createPageWidget('text_block', ['content' => '<p>Some text content</p>']);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('data-config-key="content"')
        ->toContain('data-config-type="richtext"')
        ->toContain('Some text content');
});

it('events_listing template source includes data-config-key="heading"', function () {
    seedWidgetTypes();
    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();

    // The events_listing template is an @include — check the blade source directly.
    $bladeSource = file_get_contents(resource_path('views/widgets/events-listing.blade.php'));

    expect($bladeSource)
        ->toContain('data-config-key="heading"')
        ->toContain('data-config-type="text"');
});

it('donation_form template includes data-config-key="heading" on the heading', function () {
    seedWidgetTypes();
    $pw = createPageWidget('donation_form', ['heading' => 'Make a Gift']);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('data-config-key="heading"')
        ->toContain('data-config-type="text"')
        ->toContain('Make a Gift');
});

it('three_buckets template includes data-config-key for headings and bodies', function () {
    seedWidgetTypes();
    $pw = createPageWidget('three_buckets', [
        'heading_1' => 'Bucket One',
        'body_1'    => '<p>Body one</p>',
        'heading_2' => 'Bucket Two',
        'body_2'    => '<p>Body two</p>',
        'heading_3' => 'Bucket Three',
        'body_3'    => '<p>Body three</p>',
    ]);

    $result = WidgetRenderer::render($pw);

    expect($result['html'])
        ->toContain('data-config-key="heading_1"')
        ->toContain('data-config-key="body_1"')
        ->toContain('data-config-key="heading_2"')
        ->toContain('data-config-key="body_2"')
        ->toContain('data-config-key="heading_3"')
        ->toContain('data-config-key="body_3"')
        ->toContain('data-config-type="text"')
        ->toContain('data-config-type="richtext"');
});

// -------------------------------------------------------------------------
// Phase 2: updateInlineConfig persists correctly
// -------------------------------------------------------------------------

it('updateInlineConfig saves the config value to the database', function () {
    seedWidgetTypes();
    $pw = createPageWidget('hero', ['content' => '<h1>Old</h1>']);
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');

    Livewire::actingAs($user)
        ->test(PageBuilderBlock::class, ['blockId' => $pw->id])
        ->call('updateInlineConfig', 'content', '<h1>New heading</h1>');

    $pw->refresh();
    expect($pw->config['content'])->toBe('<h1>New heading</h1>');
});

it('updateInlineConfig dispatches inline-config-updated event', function () {
    seedWidgetTypes();
    $pw = createPageWidget('text_block', ['content' => '<p>Original</p>']);
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');

    Livewire::actingAs($user)
        ->test(PageBuilderBlock::class, ['blockId' => $pw->id])
        ->call('updateInlineConfig', 'content', '<p>Updated</p>')
        ->assertDispatched('inline-config-updated', fn ($name, $params) =>
            $params['blockId'] === $pw->id &&
            $params['key'] === 'content' &&
            $params['value'] === '<p>Updated</p>'
        );
});

it('updateInlineConfig requires update_page permission', function () {
    seedWidgetTypes();
    $pw = createPageWidget('hero', ['content' => '<h1>Secret</h1>']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(PageBuilderBlock::class, ['blockId' => $pw->id])
        ->call('updateInlineConfig', 'content', '<h1>Hacked</h1>')
        ->assertForbidden();
});

// -------------------------------------------------------------------------
// Phase 4: Inspector receives inline-config-updated
// -------------------------------------------------------------------------

it('inspector updates its config when inline-config-updated fires', function () {
    seedWidgetTypes();
    $pw = createPageWidget('text_block', ['content' => '<p>Original</p>']);
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');

    $component = Livewire::actingAs($user)
        ->test(PageBuilderInspector::class, ['blockId' => $pw->id]);

    // Simulate an inline-config-updated event
    $component->dispatch('inline-config-updated', blockId: $pw->id, key: 'content', value: '<p>From inline</p>');

    expect($component->get('block.config.content'))->toBe('<p>From inline</p>');
});

it('inspector ignores inline-config-updated for other blocks', function () {
    seedWidgetTypes();
    $pw = createPageWidget('text_block', ['content' => '<p>Original</p>']);
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');

    $component = Livewire::actingAs($user)
        ->test(PageBuilderInspector::class, ['blockId' => $pw->id]);

    $component->dispatch('inline-config-updated', blockId: 'some-other-id', key: 'content', value: '<p>Nope</p>');

    expect($component->get('block.config.content'))->toBe('<p>Original</p>');
});
