<?php

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
});

it('createLandingPageForEvent creates a page of type event at the correct slug', function () {
    $event = Event::factory()->create(['slug' => 'my-event', 'title' => 'My Event']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/my-event')->first();
    expect($page)->not->toBeNull();
    expect($page->type)->toBe('event');
    expect($page->status)->toBe('published');
    expect($page->title)->toBe('My Event');
});

it('createLandingPageForEvent creates the three standard widgets', function () {
    $event = Event::factory()->create(['slug' => 'my-event']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/my-event')->first();
    $handles = PageWidget::where('page_id', $page->id)
        ->join('widget_types', 'widget_types.id', '=', 'page_widgets.widget_type_id')
        ->pluck('widget_types.handle')
        ->all();

    expect($handles)->toContain('event_description')
        ->toContain('event_registration')
        ->not->toContain('event_dates');
});

it('createLandingPageForEvent sets landing_page_id on the event', function () {
    $event = Event::factory()->create(['slug' => 'my-event']);

    EventResource::createLandingPageForEvent($event);

    $page = Page::where('slug', 'events/my-event')->first();
    expect($event->fresh()->landing_page_id)->toBe($page->id);
});

it('createLandingPageForEvent is a no-op when landing_page_id is already set', function () {
    $existing = Page::factory()->create(['slug' => 'events/other', 'type' => 'event']);
    $event = Event::factory()->create(['slug' => 'my-event', 'landing_page_id' => $existing->id]);

    EventResource::createLandingPageForEvent($event);

    // No new page should have been created for this event
    expect(Page::where('slug', 'events/my-event')->exists())->toBeFalse();
    expect($event->fresh()->landing_page_id)->toBe($existing->id);
});
