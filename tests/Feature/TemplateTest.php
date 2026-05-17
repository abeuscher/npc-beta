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

// Colour was relocated off the templates table to the site-wide Theme palette
// at session 297; resolved()/INHERITABLE_FIELDS now covers custom_scss +
// header_page_id + footer_page_id only. These exercise that surviving
// inheritance machinery on custom_scss.

it('resolved() returns own value when set on default template', function () {
    $template = Template::factory()->create([
        'type'        => 'page',
        'is_default'  => true,
        'custom_scss' => '.a{}',
    ]);

    expect($template->resolved('custom_scss'))->toBe('.a{}');
});

it('resolved() returns own value when non-default template has a value', function () {
    Template::factory()->create([
        'type'        => 'page',
        'is_default'  => true,
        'custom_scss' => '.a{}',
    ]);

    $child = Template::factory()->create([
        'type'        => 'page',
        'is_default'  => false,
        'custom_scss' => '.b{}',
    ]);

    expect($child->resolved('custom_scss'))->toBe('.b{}');
});

it('resolved() falls back to default value when non-default template field is null', function () {
    Template::factory()->create([
        'type'        => 'page',
        'is_default'  => true,
        'custom_scss' => '.default{}',
    ]);

    $child = Template::factory()->create([
        'type'        => 'page',
        'is_default'  => false,
        'custom_scss' => null,
    ]);

    expect($child->resolved('custom_scss'))->toBe('.default{}');
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

// Session 297 relocated colour off templates to the site-wide Theme palette
// (delivered as .np-site --np-color-* tokens in the public bundle). The
// per-template inline `:root { --color-primary: … }` <style> the page layout
// used to emit is gone; per-template colour deliberately does not return until
// the 299 schemes. (Computed-value verification across the three surfaces is
// the real-browser Phase-4 step, not asserted from emitted HTML here.)

it('page renders without the removed per-template colour inline style', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true]);

    $custom = Template::factory()->create(['type' => 'page', 'is_default' => false]);

    $page = Page::factory()->create([
        'template_id' => $custom->id,
        'status'      => 'published',
        'slug'        => 'custom-tpl-page',
    ]);

    $response = $this->get('/custom-tpl-page');
    $response->assertStatus(200);
    $response->assertDontSee(':root { --color-primary:', false);
});

it('page with no assigned template still renders', function () {
    Template::factory()->create(['type' => 'page', 'is_default' => true]);

    Page::factory()->create([
        'template_id' => null,
        'status'      => 'published',
        'slug'        => 'default-tpl-page',
    ]);

    $this->get('/default-tpl-page')->assertStatus(200);
});
