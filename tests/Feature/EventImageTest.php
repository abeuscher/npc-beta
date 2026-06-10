<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
});

function renderEventImage(string $eventSlug, array $config = []): string
{
    $page = Page::factory()->create(['status' => 'published']);
    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'event_image')->firstOrFail()->id,
        'config'         => array_merge(['event_slug' => $eventSlug], $config),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return WidgetRenderer::render($pw->fresh('widgetType'))['html'] ?? '';
}

function attachEventImage(Event $event, string $collection, string $file): void
{
    $event->addMedia(UploadedFile::fake()->image($file, 1200, 600))
        ->usingFileName($file)
        ->toMediaCollection($collection, 'public');
}

it('renders the event header image by default', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala', 'title' => 'Spring Gala']);
    attachEventImage($event, 'event_header', 'header.jpg');

    $html = renderEventImage('gala');

    expect($html)->toContain('<img')
        ->toContain('widget-event-image')
        ->toContain('alt="Spring Gala"');
});

it('renders a different image when image_source switches between header and thumbnail', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala']);
    attachEventImage($event, 'event_header', 'header.jpg');
    attachEventImage($event, 'event_thumbnail', 'thumb.jpg');

    $headerHtml = renderEventImage('gala', ['image_source' => 'header']);
    $thumbHtml  = renderEventImage('gala', ['image_source' => 'thumbnail']);

    // Both render an image, but the two sources resolve to different URLs.
    expect($headerHtml)->toContain('<img')
        ->and($thumbHtml)->toContain('<img')
        ->and($headerHtml)->not->toBe($thumbHtml);
});

it('falls back to the other image when the chosen source is empty', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala']);
    // Only a thumbnail exists; the widget asks for the header.
    attachEventImage($event, 'event_thumbnail', 'thumb.jpg');

    $html = renderEventImage('gala', ['image_source' => 'header']);

    expect($html)->toContain('<img');
});

it('renders nothing when the event has no image', function () {
    Storage::fake('public');
    Event::factory()->create(['slug' => 'gala']);

    expect(renderEventImage('gala'))->not->toContain('<img');
});

it('uses the alt text override when provided', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala', 'title' => 'Spring Gala']);
    attachEventImage($event, 'event_header', 'header.jpg');

    expect(renderEventImage('gala', ['alt_text' => 'Custom alt']))
        ->toContain('alt="Custom alt"')
        ->not->toContain('alt="Spring Gala"');
});

it('lazy-loads by default and adds no fetchpriority', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala']);
    attachEventImage($event, 'event_header', 'header.jpg');

    $html = renderEventImage('gala');

    expect($html)
        ->toContain('loading="lazy"')
        ->not->toContain('fetchpriority');
});

it('eager-loads with high fetchpriority when loading_priority is eager', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala']);
    attachEventImage($event, 'event_header', 'header.jpg');

    $html = renderEventImage('gala', ['loading_priority' => 'eager']);

    expect($html)
        ->toContain('loading="eager"')
        ->toContain('fetchpriority="high"');
});

it('applies an aspect ratio and max width as inline styles', function () {
    Storage::fake('public');
    $event = Event::factory()->create(['slug' => 'gala']);
    attachEventImage($event, 'event_header', 'header.jpg');

    $html = renderEventImage('gala', ['aspect_ratio' => '16:9', 'max_width' => '480']);

    expect($html)->toContain('aspect-ratio:16 / 9')
        ->toContain('max-width:480px');
});
