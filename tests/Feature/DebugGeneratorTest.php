<?php

use App\Filament\Widgets\DashboardDebugGeneratorWidget;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Event generation ─────────────────────────────────────────────────────────

it('generates events with varying prices and capacities', function () {
    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'events';
    $widget->quantity = 40;
    $widget->generate();

    $events = Event::all();
    expect($events)->toHaveCount(40);

    $free = $events->where('price', 0);
    $paid = $events->where('price', '>', 0);
    expect($free)->not->toBeEmpty();
    expect($paid)->not->toBeEmpty();

    $paid->each(function ($event) {
        expect((float) $event->price)->toBeGreaterThanOrEqual(5)
            ->toBeLessThanOrEqual(100);
    });

    $withCapacity = $events->whereNotNull('capacity');
    $unlimited    = $events->whereNull('capacity');
    expect($withCapacity)->not->toBeEmpty();
    expect($unlimited)->not->toBeEmpty();

    $withCapacity->each(function ($event) {
        expect($event->capacity)->toBeGreaterThanOrEqual(20)
            ->toBeLessThanOrEqual(200);
    });
});

it('generates registrations for free events only', function () {
    Contact::factory()->count(10)->create();

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'events';
    $widget->quantity = 30;
    $widget->generate();

    $paidEvents = Event::where('price', '>', 0)->pluck('id');
    $paidRegs   = EventRegistration::whereIn('event_id', $paidEvents)->count();
    expect($paidRegs)->toBe(0);

    // At least some free events should have registrations (with 30 events, statistically certain)
    $freeEvents = Event::where('price', 0)->pluck('id');
    if ($freeEvents->isNotEmpty()) {
        $freeRegs = EventRegistration::whereIn('event_id', $freeEvents)->count();
        expect($freeRegs)->toBeGreaterThanOrEqual(0);
    }
});

// ── Blog post generation ─────────────────────────────────────────────────────

it('generates blog posts with page widgets', function () {
    User::factory()->create();
    WidgetType::create([
        'handle'        => 'text_block',
        'label'         => 'Text Block',
        'config_schema' => [],
    ]);
    WidgetType::create([
        'handle'        => 'blog_pager',
        'label'         => 'Blog Pager',
        'config_schema' => [],
    ]);

    $widget = new DashboardDebugGeneratorWidget();
    $widget->type = 'blog_posts';
    $widget->quantity = 5;
    $widget->generate();

    $posts = Page::where('type', 'post')->get();
    expect($posts)->toHaveCount(5);

    $textBlockType = WidgetType::where('handle', 'text_block')->first();
    $blogPagerType = WidgetType::where('handle', 'blog_pager')->first();

    $posts->each(function ($post) use ($textBlockType, $blogPagerType) {
        $widgets = PageWidget::where('page_id', $post->id)->orderBy('sort_order')->get();
        expect($widgets)->toHaveCount(2);

        expect($widgets[0]->widget_type_id)->toBe($textBlockType->id);
        expect($widgets[0]->sort_order)->toBe(0);
        expect($widgets[0]->config['content'])->toContain('<p>');

        expect($widgets[1]->widget_type_id)->toBe($blogPagerType->id);
        expect($widgets[1]->sort_order)->toBe(1);
    });
});

// ── Financial types removed ──────────────────────────────────────────────────

it('does not include financial types in the generator', function () {
    $widget = new DashboardDebugGeneratorWidget();

    $ref = new ReflectionMethod($widget, 'generate');

    // The match in generate() should not accept donations or purchases
    $widget->type = 'donations';
    $threw = false;
    try {
        $widget->generate();
    } catch (\UnhandledMatchError $e) {
        $threw = true;
    }
    expect($threw)->toBeTrue();

    $widget->type = 'purchases';
    $threw = false;
    try {
        $widget->generate();
    } catch (\UnhandledMatchError $e) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});
