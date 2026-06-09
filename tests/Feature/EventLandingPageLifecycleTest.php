<?php

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('event landing pages inherit the event source', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create(['source' => Source::SCRUB_DATA, 'landing_page_id' => null]);
    EventResource::createLandingPageForEvent($event);
    $event->refresh();

    $page = Page::find($event->landing_page_id);

    expect($page)->not->toBeNull()
        ->and($page->type)->toBe('event')
        ->and($page->source)->toBe(Source::SCRUB_DATA);
});

it('deleting an event force-deletes its landing page and the page widgets', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create(['landing_page_id' => null]);
    EventResource::createLandingPageForEvent($event);
    $event->refresh();
    $pageId = $event->landing_page_id;

    expect(Page::find($pageId))->not->toBeNull()
        ->and(PageWidget::where('owner_id', $pageId)->count())->toBeGreaterThan(0);

    $event->delete();

    // Force-deleted (not merely soft) so it leaves the export + admin entirely,
    // and PageObserver::deleting tears down the owned widgets/layouts.
    expect(Page::withTrashed()->find($pageId))->toBeNull()
        ->and(PageWidget::where('owner_id', $pageId)->count())->toBe(0);
});

it('prunes orphan event landing pages but keeps pages with a live event', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    // Orphan: a type=event page with no backing event.
    $orphan = Page::factory()->create(['type' => 'event', 'title' => 'Orphan Event Page']);

    // Linked: an event with its landing page.
    $linked = Event::factory()->create(['landing_page_id' => null]);
    EventResource::createLandingPageForEvent($linked);
    $linked->refresh();
    $linkedPageId = $linked->landing_page_id;

    // Dry-run touches nothing.
    $this->artisan('pages:prune-orphan-events')->assertExitCode(0);
    expect(Page::withTrashed()->find($orphan->id))->not->toBeNull();

    // --force removes the orphan, keeps the linked page.
    $this->artisan('pages:prune-orphan-events --force')->assertExitCode(0);
    expect(Page::withTrashed()->find($orphan->id))->toBeNull()
        ->and(Page::find($linkedPageId))->not->toBeNull();
});
