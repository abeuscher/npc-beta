<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Attach media to an event, then raw-delete the event row so the media row
// survives with no owner. Returns the now-dead media id.
function deadOwnerEventMedia(): string
{
    $owner = Event::factory()->create();
    $media = $owner->addMedia(UploadedFile::fake()->image('hdr.jpg', 200, 200))->toMediaCollection('event_header');
    DB::table('events')->where('id', $owner->id)->delete();

    return $media->id;
}

it('clean box is a no-op', function () {
    Storage::fake('public');

    $this->artisan('media:prune-dead-owner')
        ->expectsOutputToContain('No dead-owner media rows found.')
        ->assertSuccessful();
});

it('dry-run (the default) reports dead-owner media but deletes nothing', function () {
    Storage::fake('public');
    Queue::fake();

    $deadId = deadOwnerEventMedia();

    $this->artisan('media:prune-dead-owner')
        ->expectsOutputToContain('1 dead-owner media row would be deleted')
        ->assertSuccessful();

    expect(Media::find($deadId))->not->toBeNull();
});

it('--force deletes dead-owner media but leaves live media intact', function () {
    Storage::fake('public');
    Queue::fake();

    // Dead-owner: media whose owning event was raw-deleted out from under it.
    $deadId = deadOwnerEventMedia();

    // Live: media on an event that still exists — must survive.
    $liveEvent = Event::factory()->create();
    $liveMedia = $liveEvent->addMedia(UploadedFile::fake()->image('live.jpg', 200, 200))->toMediaCollection('event_header');

    expect(Media::count())->toBe(2);

    $this->artisan('media:prune-dead-owner --force')
        ->expectsOutputToContain('Pruned 1 dead-owner media row')
        ->assertSuccessful();

    expect(Media::find($deadId))->toBeNull()
        ->and(Media::find($liveMedia->id))->not->toBeNull()
        ->and(Media::count())->toBe(1);
});

it('does not treat a soft-deleted owner as dead (its media is retained)', function () {
    Storage::fake('public');
    Queue::fake();

    // Page soft-deletes; a soft-deleted (recoverable) owner must count as present.
    $page  = \App\Models\Page::factory()->create();
    $media = $page->addMedia(UploadedFile::fake()->image('p.jpg', 200, 200))->toMediaCollection('post_header');
    $page->delete(); // soft delete — row remains

    $this->artisan('media:prune-dead-owner --force')
        ->expectsOutputToContain('No dead-owner media rows found.')
        ->assertSuccessful();

    expect(Media::find($media->id))->not->toBeNull();
});
