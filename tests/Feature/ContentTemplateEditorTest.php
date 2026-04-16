<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\PageLayout;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

// ── Polymorphic ownership ────────────────────────────────────────────────────

it('template widgets are owned by the template via morphMany', function () {
    $template = Template::factory()->create(['type' => 'content']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = $template->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Test'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect($widget->owner_type)->toBe(Template::class);
    expect($widget->owner_id)->toBe($template->id);
    expect($widget->owner)->toBeInstanceOf(Template::class);
    expect($template->widgets()->count())->toBe(1);
});

// ── copyOwnedStack ──────────────────────────────────────────────────────────

it('copies a template stack to a page including layouts and nested widgets', function () {
    $template = Template::factory()->create(['type' => 'content']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $template->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Root'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $layout = $template->layouts()->create([
        'label'      => 'Two Col',
        'display'    => 'grid',
        'columns'    => 2,
        'sort_order' => 1,
    ]);

    $template->widgets()->create([
        'widget_type_id' => $wt->id,
        'layout_id'      => $layout->id,
        'column_index'   => 0,
        'config'         => ['heading' => 'Left'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    PageWidget::copyOwnedStack($template, $page);

    expect($page->widgets()->count())->toBe(2);
    expect($page->layouts()->count())->toBe(1);

    $rootWidget = $page->widgets()->whereNull('layout_id')->first();
    expect($rootWidget->config)->toBe(['heading' => 'Root']);
    expect($rootWidget->owner_type)->toBe(Page::class);

    $nestedWidget = $page->widgets()->whereNotNull('layout_id')->first();
    expect($nestedWidget->config)->toBe(['heading' => 'Left']);
    expect($nestedWidget->layout->owner_id)->toBe($page->id);
});

// ── Per-type default ────────────────────────────────────────────────────────

it('page creation uses per-type default template when no template is selected', function () {
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);
    $ct = Template::factory()->create(['type' => 'content', 'name' => 'Default Page Layout']);

    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();
    $ct->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'From Default'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    SiteSetting::set('default_content_template_default', $ct->id);

    // The form's default() callback reads the per-type default setting, so
    // not overriding content_template_id preserves the configured default.
    \Livewire\Livewire::test(\App\Filament\Resources\PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title'       => 'Test Default Page',
            'type'        => 'default',
            'template_id' => $pt->id,
        ])
        ->call('create');

    $page = Page::where('title', 'Test Default Page')->firstOrFail();
    $widgets = $page->widgets()->with('widgetType')->get();

    expect($widgets)->toHaveCount(1);
    expect($widgets->first()->config)->toBe(['heading' => 'From Default']);
});

it('selecting None suppresses the per-type default', function () {
    $pt = Template::factory()->create(['type' => 'page', 'is_default' => true]);
    $ct = Template::factory()->create(['type' => 'content']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();
    $ct->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Should Not Appear'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    SiteSetting::set('default_content_template_default', $ct->id);

    \Livewire\Livewire::test(\App\Filament\Resources\PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title'               => 'Blank On Purpose',
            'type'                => 'default',
            'template_id'         => $pt->id,
            'content_template_id' => 'none',
        ])
        ->call('create');

    $page = Page::where('title', 'Blank On Purpose')->firstOrFail();
    expect($page->widgets()->count())->toBe(0);
});

// ── Post-type selector ──────────────────────────────────────────────────────

it('post creation can use a content template', function () {
    $ct = Template::factory()->create(['type' => 'content', 'name' => 'Blog Starter']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $ct->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Blog Intro'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    \Livewire\Livewire::test(\App\Filament\Resources\PostResource\Pages\CreatePost::class)
        ->fillForm([
            'title'               => 'My First Post',
            'content_template_id' => $ct->id,
        ])
        ->call('create');

    $post = Page::where('title', 'My First Post')->firstOrFail();
    $widgets = $post->widgets()->with('widgetType')->get();

    expect($widgets)->toHaveCount(1);
    expect($widgets->first()->widgetType->handle)->toBe('text_block');
    expect($widgets->first()->config)->toBe(['heading' => 'Blog Intro']);
});

it('post creation with None produces a blank post', function () {
    \Livewire\Livewire::test(\App\Filament\Resources\PostResource\Pages\CreatePost::class)
        ->fillForm([
            'title'               => 'Blank Post',
            'content_template_id' => 'none',
        ])
        ->call('create');

    $post = Page::where('title', 'Blank Post')->firstOrFail();
    expect($post->widgets()->count())->toBe(0);
});

// ── Editing a template does not mutate existing pages ────────────────────────

it('editing a template widget does not affect pages hydrated from it', function () {
    $template = Template::factory()->create(['type' => 'content']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $template->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['heading' => 'Original'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);
    PageWidget::copyOwnedStack($template, $page);

    // Edit the template widget.
    $template->widgets()->first()->update(['config' => ['heading' => 'Changed']]);

    // Page's copy is unaffected.
    $pageWidget = $page->widgets()->first();
    expect($pageWidget->config)->toBe(['heading' => 'Original']);
});

// ── Cascade delete ──────────────────────────────────────────────────────────

it('deleting a template cascades to its owned widgets and layouts', function () {
    $template = Template::factory()->create(['type' => 'content']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $template->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $layout = $template->layouts()->create([
        'label'      => 'Grid',
        'display'    => 'grid',
        'columns'    => 2,
        'sort_order' => 1,
    ]);

    $widgetId = $template->widgets()->first()->id;
    $layoutId = $layout->id;

    $template->delete();

    expect(PageWidget::find($widgetId))->toBeNull();
    expect(PageLayout::find($layoutId))->toBeNull();
});
