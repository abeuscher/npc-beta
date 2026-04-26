<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sets published_at when a draft event transitions to published', function () {
    $event = Event::factory()->draft()->create([
        'starts_at' => now()->addDay(),
    ]);

    expect($event->published_at)->toBeNull();

    $event->update(['status' => 'published']);

    expect($event->published_at)->not->toBeNull()
        ->and($event->published_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('sets published_at when an event is created with status=published', function () {
    $event = Event::factory()->create([
        'status'    => 'published',
        'starts_at' => now()->addDay(),
    ]);

    expect($event->published_at)->not->toBeNull()
        ->and($event->published_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('does not override an existing published_at on update', function () {
    $existing = now()->subDays(7);

    $event = Event::factory()->create([
        'status'       => 'published',
        'starts_at'    => now()->addDay(),
        'published_at' => $existing,
    ]);

    $event->update(['title' => 'Renamed']);

    $event->refresh();
    expect($event->published_at?->timestamp)->toBe($existing->timestamp);
});

it('does not set published_at on draft events', function () {
    $event = Event::factory()->draft()->create([
        'starts_at' => now()->addDay(),
    ]);

    expect($event->published_at)->toBeNull();

    $event->update(['title' => 'Still Draft']);

    $event->refresh();
    expect($event->published_at)->toBeNull();
});
