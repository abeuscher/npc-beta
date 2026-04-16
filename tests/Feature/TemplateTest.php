<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Template model: resolved() ──────────────────────────────────────────────

it('resolved() returns own value when set on default template', function () {
    $template = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#ff0000',
    ]);

    expect($template->resolved('primary_color'))->toBe('#ff0000');
});

it('resolved() returns own value when non-default template has a value', function () {
    Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#ff0000',
    ]);

    $child = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => false,
        'primary_color' => '#00ff00',
    ]);

    expect($child->resolved('primary_color'))->toBe('#00ff00');
});

it('resolved() falls back to default value when non-default template field is null', function () {
    Template::factory()->create([
        'type'            => 'page',
        'is_default'      => true,
        'primary_color'   => '#ff0000',
        'header_bg_color' => '#abcdef',
    ]);

    $child = Template::factory()->create([
        'type'            => 'page',
        'is_default'      => false,
        'primary_color'   => null,
        'header_bg_color' => null,
    ]);

    expect($child->resolved('primary_color'))->toBe('#ff0000');
    expect($child->resolved('header_bg_color'))->toBe('#abcdef');
});

// ── Scopes ──────────────────────────────────────────────────────────────────

it('scopeDefault returns the default page template', function () {
    $default = Template::factory()->create(['type' => 'page', 'is_default' => true]);
    Template::factory()->create(['type' => 'page', 'is_default' => false]);
    Template::factory()->create(['type' => 'content', 'is_default' => false]);

    $result = Template::query()->default()->first();
    expect($result->id)->toBe($default->id);
});

it('scopePage filters to page type', function () {
    Template::factory()->create(['type' => 'page']);
    Template::factory()->create(['type' => 'content']);

    expect(Template::query()->page()->count())->toBe(1);
});

it('scopeContent filters to content type', function () {
    Template::factory()->create(['type' => 'page']);
    Template::factory()->create(['type' => 'content']);

    expect(Template::query()->content()->count())->toBe(1);
});

// ── TemplateSeeder ──────────────────────────────────────────────────────────

it('TemplateSeeder creates default page template with correct values', function () {
    // Create system pages for header/footer
    $header = Page::factory()->create(['slug' => '_header', 'type' => 'system', 'status' => 'published']);
    $footer = Page::factory()->create(['slug' => '_footer', 'type' => 'system', 'status' => 'published']);

    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $default = Template::query()->default()->first();

    expect($default)->not->toBeNull();
    expect($default->name)->toBe('Default');
    expect($default->primary_color)->toBe('#0172ad');
    expect($default->header_bg_color)->toBe('#ffffff');
    expect($default->header_page_id)->toBe($header->id);
    expect($default->footer_page_id)->toBe($footer->id);
});

it('TemplateSeeder creates all built-in content templates', function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $names = Template::query()->content()->pluck('name')->all();

    expect($names)->toContain('Contact Page');
    expect($names)->toContain('About Page');
    expect($names)->toContain('Event Landing Page');
    expect($names)->toContain('Blog Post');
    expect($names)->toContain('Blank');
});

// ── Event landing page creation via createLandingPageForEvent ────────────────

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
});

it('createLandingPageForEvent creates a page with correct slug and type', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create(['slug' => 'test-event', 'title' => 'Test Event']);

    \App\Filament\Resources\EventResource::createLandingPageForEvent($event);

    $page = Page::find($event->fresh()->landing_page_id);
    expect($page)->not->toBeNull();
    expect($page->type)->toBe('event');
    expect($page->slug)->toBe('events/test-event');
});

it('createLandingPageForEvent uses content template widget definitions', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $event = Event::factory()->create(['slug' => 'tpl-event']);

    \App\Filament\Resources\EventResource::createLandingPageForEvent($event);

    $page = Page::find($event->fresh()->landing_page_id);
    $handles = PageWidget::forOwner($page)
        ->join('widget_types', 'widget_types.id', '=', 'page_widgets.widget_type_id')
        ->orderBy('page_widgets.sort_order')
        ->pluck('widget_types.handle')
        ->all();

    expect($handles)->toBe(['event_description', 'event_registration']);
});

it('createLandingPageForEvent falls back to hardcoded widgets when template is missing', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create(['slug' => 'fallback-event']);

    \App\Filament\Resources\EventResource::createLandingPageForEvent($event);

    $page = Page::find($event->fresh()->landing_page_id);
    $handles = PageWidget::forOwner($page)
        ->join('widget_types', 'widget_types.id', '=', 'page_widgets.widget_type_id')
        ->orderBy('page_widgets.sort_order')
        ->pluck('widget_types.handle')
        ->all();

    expect($handles)->toBe(['event_description', 'event_registration']);
});

// ── Page rendering: template resolution ─────────────────────────────────────

it('page with custom template uses that template colors', function () {
    $default = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#111111',
    ]);

    $custom = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => false,
        'primary_color' => '#222222',
    ]);

    $page = Page::factory()->create([
        'template_id' => $custom->id,
        'status'      => 'published',
        'slug'        => 'custom-tpl-page',
    ]);

    $response = $this->get('/custom-tpl-page');
    $response->assertStatus(200);
    $response->assertSee('#222222');
});

it('page with no template uses default template colors', function () {
    $default = Template::factory()->create([
        'type'          => 'page',
        'is_default'    => true,
        'primary_color' => '#333333',
    ]);

    $page = Page::factory()->create([
        'template_id' => null,
        'status'      => 'published',
        'slug'        => 'default-tpl-page',
    ]);

    $response = $this->get('/default-tpl-page');
    $response->assertStatus(200);
    $response->assertSee('#333333');
});
