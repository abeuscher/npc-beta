<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\Media\MediaContentHasher;
use App\Services\Media\MediaDedupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    Storage::fake('public');
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('update_page');

    $this->page = Page::factory()->create(['slug' => 'dd-' . uniqid()]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'dd_widget_' . uniqid(),
        'label'         => 'DD Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [
            ['key' => 'logo', 'type' => 'image'],
        ],
    ]);

    $this->widget = $this->page->widgets()->create([
        'widget_type_id'    => $this->widgetType->id,
        'label'             => 'W',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
});

function ddAttach(PageWidget $owner, string $collection, string $bytes, string $name = 'a.png'): Media
{
    return $owner->addMediaFromString($bytes)
        ->usingFileName($name)
        ->toMediaCollection($collection, 'public');
}

// ── Phase 1: content_hash population ──────────────────────────────────────

it('populates content_hash on upload with the sha-256 of the stored bytes', function () {
    $media = ddAttach($this->widget, 'config_logo', 'LOGO-BYTES');

    expect($media->fresh()->content_hash)->toBe(hash('sha256', 'LOGO-BYTES'));
});

it('gives identical bytes the same content_hash', function () {
    $a = ddAttach($this->widget, 'config_logo', 'SAME');
    $b = ddAttach($this->widget, 'appearance_background_image', 'SAME');

    expect($a->fresh()->content_hash)
        ->toBe($b->fresh()->content_hash)
        ->and($a->fresh()->content_hash)->toBe(hash('sha256', 'SAME'));
});

it('backfills content_hash for rows that lack one', function () {
    $media = ddAttach($this->widget, 'config_logo', 'BACKFILL-ME');

    // Simulate a genuine pre-CAS legacy row: no hash, file at the id path
    // (where a null-hash row's path generator resolves it).
    $casPath = $media->getPathRelativeToRoot();
    Media::query()->update(['content_hash' => null]);
    Storage::disk('public')->move($casPath, $media->id . '/' . $media->file_name);

    $this->artisan('media:backfill-hashes')->assertSuccessful();

    expect($media->fresh()->content_hash)->toBe(hash('sha256', 'BACKFILL-ME'));
});

// ── Phase 2: match detection ──────────────────────────────────────────────

it('finds an identical-content match by hash', function () {
    $existing = ddAttach($this->widget, 'config_logo', 'DUPE');

    $matches = app(MediaDedupService::class)->findMatches(hash('sha256', 'DUPE'));

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['id'])->toBe($existing->id)
        ->and($matches[0]['match_type'])->toBe('identical');
});

it('collapses several identical-hash rows into one candidate with a count', function () {
    ddAttach($this->widget, 'config_logo', 'DUPE');
    ddAttach($this->widget, 'appearance_background_image', 'DUPE');

    $matches = app(MediaDedupService::class)->findMatches(hash('sha256', 'DUPE'));

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['duplicate_count'])->toBe(2);
});

it('surfaces a same-name iteration match when bytes differ', function () {
    $existing = ddAttach($this->widget, 'config_logo', 'V1', 'brand.png');

    $matches = app(MediaDedupService::class)->findMatches(hash('sha256', 'V2'), 'brand.png');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['id'])->toBe($existing->id)
        ->and($matches[0]['match_type'])->toBe('same_name');
});

it('ignores randomised hashName filenames for name matching', function () {
    $hashed = str_repeat('a', 40) . '.png';
    ddAttach($this->widget, 'config_logo', 'V1', $hashed);

    $matches = app(MediaDedupService::class)->findMatches(hash('sha256', 'V2'), $hashed);

    expect($matches)->toBeEmpty();
});

it('returns no matches for a brand-new hash', function () {
    ddAttach($this->widget, 'config_logo', 'EXISTING');

    $matches = app(MediaDedupService::class)->findMatches(hash('sha256', 'BRAND-NEW'));

    expect($matches)->toBeEmpty();
});

// ── Phase 2: endpoints ────────────────────────────────────────────────────

it('serves the dedup check endpoint with matches', function () {
    ddAttach($this->widget, 'config_logo', 'CHECK-ME');

    $this->actingAs($this->admin)
        ->postJson('/admin/api/page-builder/media-dedup-check', [
            'hash' => hash('sha256', 'CHECK-ME'),
        ])
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.match_type', 'identical');
});

it('rejects a malformed hash on the check endpoint', function () {
    $this->actingAs($this->admin)
        ->postJson('/admin/api/page-builder/media-dedup-check', ['hash' => 'not-a-hash'])
        ->assertStatus(422);
});

it('reuses another widget\'s asset for a config image, copying it to the new owner', function () {
    // The asset being reused is owned by a different widget — the real scenario.
    $other = $this->page->widgets()->create([
        'widget_type_id'    => $this->widgetType->id,
        'label'             => 'Other',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 1,
        'is_active'         => true,
    ]);
    $existing = ddAttach($other, 'config_logo', 'REUSE');
    $before = Media::count();

    $this->actingAs($this->admin)
        ->postJson("/admin/api/page-builder/widgets/{$this->widget->id}/use-existing-image", [
            'key'      => 'logo',
            'media_id' => $existing->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['media_id', 'url']);

    // A copy is made (bytes still duplicated until CAS lands) — it carries the
    // same content hash, the source survives, and the widget points at the copy.
    $copy = $this->widget->fresh()->getFirstMedia('config_logo');
    expect(Media::count())->toBe($before + 1)
        ->and($existing->fresh())->not->toBeNull()
        ->and($copy->content_hash)->toBe($existing->content_hash)
        ->and($this->widget->fresh()->config['logo'])->toBe($copy->id);
});

it('reuses an existing asset for an appearance background', function () {
    $existing = ddAttach($this->widget, 'config_logo', 'BG-REUSE');

    $this->actingAs($this->admin)
        ->postJson("/admin/api/page-builder/widgets/{$this->widget->id}/use-existing-appearance-image", [
            'media_id' => $existing->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['url']);

    $copy = $this->widget->fresh()->getFirstMedia('appearance_background_image');
    expect($copy)->not->toBeNull()
        ->and($copy->content_hash)->toBe($existing->content_hash);
});

it('blocks the reuse endpoints without update_page permission', function () {
    $existing = ddAttach($this->widget, 'config_logo', 'NOPE');
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/admin/api/page-builder/widgets/{$this->widget->id}/use-existing-image", [
            'key'      => 'logo',
            'media_id' => $existing->id,
        ])
        ->assertStatus(403);
});
