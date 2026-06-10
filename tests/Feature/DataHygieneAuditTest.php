<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\Page;
use App\Services\DataHygieneAudit;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Plant a content-addressed directory whose hash no media row references.
function plantOrphanCasDir(string $seed): string
{
    $hash = hash('sha256', 'hygiene-orphan-'.$seed);
    $dir  = 'cas/'.substr($hash, 0, 2).'/'.$hash;
    Storage::disk('public')->put($dir.'/orphan.png', 'ORPHAN-'.$seed);

    return $dir;
}

// Seed one specimen of every cruft category. Returns the planted orphan dir.
function seedAllCruftCategories(): string
{
    // (1) orphan event page — type=event with no backing Event
    Page::factory()->create(['type' => 'event', 'source' => Source::HUMAN, 'slug' => 'orphan-event-'.uniqid()]);

    // (2) scrub residue — two scrub contacts + one scrub event (3 rows)
    Contact::factory()->count(2)->create(['source' => Source::SCRUB_DATA]);
    Event::factory()->create(['source' => Source::SCRUB_DATA]);

    // (3) dead-owner media — attach to an event, then raw-delete the owner row
    //     so the media row survives but its owner is gone.
    $owner = Event::factory()->create(['source' => Source::HUMAN]);
    $owner->addMedia(UploadedFile::fake()->image('hdr.jpg', 200, 200))->toMediaCollection('event_header');
    DB::table('events')->where('id', $owner->id)->delete();

    // (4) orphan media directory — referenced by no media row
    return plantOrphanCasDir('a');
}

it('counts — a clean box reports zero in every category (the Phase-2 signal shape)', function () {
    Storage::fake('public');

    $audit = app(DataHygieneAudit::class);

    expect($audit->counts())->toBe([
        'orphan_event_pages' => 0,
        'scrub_records'      => 0,
        'orphan_media_dirs'  => 0,
        'dead_owner_media'   => 0,
    ])->and($audit->total())->toBe(0);
});

it('counts — detects each cruft category with the right aggregate', function () {
    Storage::fake('public');
    Queue::fake();

    seedAllCruftCategories();

    $counts = app(DataHygieneAudit::class)->counts();

    expect($counts['orphan_event_pages'])->toBe(1)
        ->and($counts['scrub_records'])->toBe(3)   // 2 scrub contacts + 1 scrub event
        ->and($counts['dead_owner_media'])->toBe(1)
        ->and($counts['orphan_media_dirs'])->toBe(1)
        ->and(app(DataHygieneAudit::class)->total())->toBe(6);
});

it('scrubBreakdown — splits residual scrub_data per table', function () {
    Storage::fake('public');

    Contact::factory()->count(2)->create(['source' => Source::SCRUB_DATA]);
    Event::factory()->create(['source' => Source::SCRUB_DATA]);

    $breakdown = app(DataHygieneAudit::class)->scrubBreakdown();

    expect($breakdown['contacts'])->toBe(2)
        ->and($breakdown['events'])->toBe(1)
        ->and($breakdown['donations'])->toBe(0);
});

it('is read-only — auditing detects but never deletes the cruft', function () {
    Storage::fake('public');
    Queue::fake();

    $orphanDir = seedAllCruftCategories();

    // Default run, then deep run.
    $this->artisan('app:data-hygiene')->assertSuccessful();
    $this->artisan('app:data-hygiene --deep')->assertSuccessful();

    // Everything it reported is still present afterwards.
    expect(app(DataHygieneAudit::class)->total())->toBe(6);
    Storage::disk('public')->assertExists($orphanDir.'/orphan.png');
});

it('command — a clean box prints the zero summary and the Clean message', function () {
    Storage::fake('public');

    $this->artisan('app:data-hygiene')
        ->expectsOutputToContain('Clean — no derived/cruft data detected.')
        ->assertSuccessful();
});

it('command --deep — lists the offending records, not just counts', function () {
    Storage::fake('public');
    Queue::fake();

    seedAllCruftCategories();

    $this->artisan('app:data-hygiene --deep')
        ->expectsOutputToContain('Orphan event pages:')
        ->expectsOutputToContain('Orphan media directories:')
        ->expectsOutputToContain('Dead-owner media rows:')
        ->assertSuccessful();
});
