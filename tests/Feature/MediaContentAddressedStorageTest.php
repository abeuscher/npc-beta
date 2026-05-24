<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\Media\ContentAddressedPathGenerator;
use App\Services\Media\MediaReferenceInventory;
use App\Services\Media\MediaRelocator;
use App\Services\Media\InlineImageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->page = Page::factory()->create(['slug' => 'cas-' . uniqid()]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'cas_widget_' . uniqid(),
        'label'         => 'CAS Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [['key' => 'logo', 'type' => 'image']],
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

function casAttach(PageWidget $owner, string $collection, string $bytes, string $name = 'a.png'): Media
{
    return $owner->addMediaFromString($bytes)
        ->usingFileName($name)
        ->toMediaCollection($collection, 'public');
}

// ── Phase 1: content-addressed path on upload ─────────────────────────────

it('stores a new upload at its content-addressed path, not the legacy id path', function () {
    $media = casAttach($this->widget, 'config_logo', 'CAS-BYTES', 'logo.png');
    $media->refresh();

    $hash = hash('sha256', 'CAS-BYTES');
    $expected = 'cas/' . substr($hash, 0, 2) . '/' . $hash . '/logo.png';

    expect($media->getPathRelativeToRoot())->toBe($expected);
    Storage::disk('public')->assertExists($expected);
    // No file left behind at the legacy id path.
    Storage::disk('public')->assertMissing($media->id . '/logo.png');
});

it('resolves two identical uploads to the same single physical file', function () {
    $a = casAttach($this->widget, 'config_logo', 'IDENTICAL', 'x.png');
    $b = casAttach($this->widget, 'appearance_background_image', 'IDENTICAL', 'x.png');

    expect($b->getPathRelativeToRoot())->toBe($a->getPathRelativeToRoot());

    // Exactly one physical original on disk for these identical bytes.
    $matching = collect(Storage::disk('public')->allFiles())
        ->filter(fn ($p) => str_ends_with($p, '/x.png'));
    expect($matching)->toHaveCount(1);
});

it('shares bytes when a media row is copied (the 319 reuse path)', function () {
    $a = casAttach($this->widget, 'config_logo', 'REUSE-ME', 'logo.png');
    $copy = $a->copy($this->widget, 'config_logo_copy');
    $copy->refresh();

    expect($copy->content_hash)->toBe($a->content_hash);
    expect($copy->getPathRelativeToRoot())->toBe($a->getPathRelativeToRoot());
    // The copy did not leave a fresh physical write at its own legacy id path.
    Storage::disk('public')->assertMissing($copy->id . '/logo.png');
});

// ── Phase 3: refcounted deletion ──────────────────────────────────────────

it('keeps the shared file when one of several referencing rows is deleted', function () {
    $a = casAttach($this->widget, 'config_logo', 'SHARED', 'y.png');
    $b = casAttach($this->widget, 'appearance_background_image', 'SHARED', 'y.png');
    $path = $a->getPathRelativeToRoot();

    $b->delete();

    expect(Media::find($b->id))->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('removes the file only when the last referencing row is deleted', function () {
    $a = casAttach($this->widget, 'config_logo', 'LASTREF', 'z.png');
    $b = casAttach($this->widget, 'appearance_background_image', 'LASTREF', 'z.png');
    $path = $a->getPathRelativeToRoot();

    $b->delete();
    Storage::disk('public')->assertExists($path);

    $a->delete();
    Storage::disk('public')->assertMissing($path);
});

it('removes a unique file on delete (no other row shares its content)', function () {
    $a = casAttach($this->widget, 'config_logo', 'UNIQUE-BYTES', 'u.png');
    $path = $a->getPathRelativeToRoot();

    $a->delete();

    Storage::disk('public')->assertMissing($path);
});

// ── Relocation primitive (used by the one-time migration) ─────────────────

it('relocates a legacy id-path file to its content-addressed path, idempotently', function () {
    $media = casAttach($this->widget, 'config_logo', 'RELOCATE', 'r.png');
    $casPath = $media->getPathRelativeToRoot();
    $legacyPath = $media->id . '/r.png';

    // Simulate a pre-CAS placement: move the file back to the legacy id path.
    Storage::disk('public')->move($casPath, $legacyPath);
    Storage::disk('public')->assertExists($legacyPath);

    $result = app(MediaRelocator::class)->relocate($media);

    expect($result)->toBe(MediaRelocator::RESULT_MOVED);
    Storage::disk('public')->assertExists($casPath);
    Storage::disk('public')->assertMissing($legacyPath);

    // Re-running is a no-op.
    expect(app(MediaRelocator::class)->relocate($media))->toBe(MediaRelocator::RESULT_NOOP);
});

// ── Phase 2: relocation command + embed rewrite + reference survival ──────

it('relocates legacy files via the command and is idempotent', function () {
    $media = casAttach($this->widget, 'config_logo', 'CMD-RELOCATE', 'cmd.png');
    $casPath = $media->getPathRelativeToRoot();
    $legacyPath = $media->id . '/cmd.png';

    Storage::disk('public')->move($casPath, $legacyPath);

    $this->artisan('media:relocate-cas')->assertSuccessful();
    Storage::disk('public')->assertExists($casPath);
    Storage::disk('public')->assertMissing($legacyPath);

    // Re-running changes nothing.
    $this->artisan('media:relocate-cas')->assertSuccessful();
    Storage::disk('public')->assertExists($casPath);
});

it('rewrites embedded legacy /storage/{id}/ URLs to the content-addressed form', function () {
    $media = casAttach($this->widget, 'inline-images', 'INLINE-BYTES', 'inline.png');
    $hash = $media->content_hash;

    $this->widget->update([
        'config' => ['intro' => '<p><img src="/storage/' . $media->id . '/inline.png"></p>'],
    ]);

    $this->artisan('media:relocate-cas')->assertSuccessful();

    $intro = $this->widget->fresh()->config['intro'];
    $casUrl = '/storage/cas/' . substr($hash, 0, 2) . '/' . $hash . '/inline.png';

    expect($intro)->toContain($casUrl);
    expect($intro)->not->toContain('/storage/' . $media->id . '/');
});

it('keeps an embedded inline image classified live after relocation (Rule B)', function () {
    // Owned in a collection the widget does not read — only the embed keeps it alive.
    $media = casAttach($this->widget, 'inline-images', 'EMBED-LIVE', 'embed.png');
    $hash = $media->content_hash;
    $casUrl = '/storage/cas/' . substr($hash, 0, 2) . '/' . $hash . '/embed.png';

    $inventory = new MediaReferenceInventory();
    expect($inventory->classify($media))->toBe(MediaReferenceInventory::CLASS_DEAD_COLLECTION);

    $this->widget->update(['config' => ['intro' => '<img src="' . $casUrl . '">']]);

    expect((new MediaReferenceInventory())->classify($media))
        ->toBe(MediaReferenceInventory::CLASS_LIVE);
});

it('still renders an inline image by file name after relocation', function () {
    $media = casAttach($this->widget, 'inline-images', 'RENDER-ME', 'render.png');

    $html = '<p><img src="/storage/anything/render.png" alt="x"></p>';
    $rendered = InlineImageRenderer::process($html);

    expect($rendered)->toContain('cas/');
    expect($rendered)->toContain('render.png');
});

it('collapses a legacy duplicate onto an already-relocated identical file', function () {
    $a = casAttach($this->widget, 'config_logo', 'COLLAPSE', 'c.png');
    $b = casAttach($this->widget, 'appearance_background_image', 'COLLAPSE', 'c.png');
    $casPath = $a->getPathRelativeToRoot();

    // Stage b as an un-relocated legacy duplicate (a is already at the CAS path).
    $legacyB = $b->id . '/c.png';
    Storage::disk('public')->put($legacyB, 'COLLAPSE');

    $result = app(MediaRelocator::class)->relocate($b);

    expect($result)->toBe(MediaRelocator::RESULT_DEDUPED);
    Storage::disk('public')->assertMissing($legacyB);
    Storage::disk('public')->assertExists($casPath);
});
