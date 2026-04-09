<?php

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Event;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ImportExport\ContentExporter;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\SeoMetaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

// ── Phase 3: OG image storage migration ─────────────────────────────────────

it('drops the og_image_path column from pages', function () {
    expect(Schema::hasColumn('pages', 'og_image_path'))->toBeFalse();
});

it('registers the og_image media collection on Page', function () {
    $page = Page::factory()->create();
    $collections = collect($page->getRegisteredMediaCollections())->pluck('name')->all();

    expect($collections)->toContain('og_image')
        ->and($collections)->toContain('post_thumbnail')
        ->and($collections)->toContain('post_header');
});

it('registers the three image collections on Event', function () {
    $event = Event::factory()->create();
    $collections = collect($event->getRegisteredMediaCollections())->pluck('name')->all();

    expect($collections)->toContain('event_thumbnail')
        ->and($collections)->toContain('event_header')
        ->and($collections)->toContain('event_og_image');
});

it('returns the og_image media URL when a page has an attached image', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'has-og', 'status' => 'published']);
    $media = $page->addMedia(resource_path('sample-images/logos/logo-adidas.png'))
        ->preservingOriginal()
        ->toMediaCollection('og_image', 'public');

    $seo = SeoMetaGenerator::forPage($page);

    expect($seo['og_image'])->toBe($media->getUrl());
});

it('falls back to the site default when a page has no og_image and no widget images', function () {
    Storage::fake('public');
    Storage::disk('public')->put('defaults/og.png', 'fake-image-bytes');

    SiteSetting::set('site_default_og_image', 'defaults/og.png');

    $page = Page::factory()->create(['slug' => 'no-og', 'status' => 'published']);

    $seo = SeoMetaGenerator::forPage($page);

    expect($seo['og_image'])->toBe(Storage::disk('public')->url('defaults/og.png'));
});

it('returns an empty string when no og_image, no widget images, and no site default', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'truly-empty', 'status' => 'published']);

    $seo = SeoMetaGenerator::forPage($page);

    expect($seo['og_image'])->toBe('');
});

// ── Phase 3: ContentExporter / ContentImporter compatibility ────────────────

it('round-trips a page with an attached og_image media file', function () {
    Storage::fake('public');

    $page = Page::factory()->create(['slug' => 'round-trip-og', 'status' => 'published']);
    $media = $page->addMedia(resource_path('sample-images/logos/logo-adidas.png'))
        ->preservingOriginal()
        ->toMediaCollection('og_image', 'public');

    expect(Storage::disk('public')->exists($media->id . '/' . $media->file_name))->toBeTrue();

    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    // Verify the bundle's page has a media descriptor for og_image
    $exportedPage = $bundle['payload']['pages'][0];
    expect($exportedPage)->toHaveKey('media');

    $ogDescriptor = collect($exportedPage['media'])
        ->firstWhere('collection_name', 'og_image');
    expect($ogDescriptor)->not->toBeNull()
        ->and($ogDescriptor['file_name'])->toBe('logo-adidas.png');

    // Wipe the page's media (DB rows only — file stays on the fake disk)
    $page->media()->delete();

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    expect($log->hasWarnings())->toBeFalse();

    $reimported = Page::where('slug', 'round-trip-og')->first();
    $reimportedMedia = $reimported->getFirstMedia('og_image');
    expect($reimportedMedia)->not->toBeNull()
        ->and($reimportedMedia->file_name)->toBe('logo-adidas.png');
});

it('exports the bundle with the bumped format_version', function () {
    $page = Page::factory()->create(['slug' => 'version-check']);
    $bundle = app(ContentExporter::class)->exportPages([$page->id]);

    expect($bundle['format_version'])->toBe('1.1.0');
});

// ── Phase 4: Form smoke tests ───────────────────────────────────────────────

it('renders the EditPage form with the new layout without errors', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $page = Page::factory()->create([
        'type'   => 'default',
        'status' => 'draft',
    ]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->assertSuccessful();
});

it('renders the EditPost form with the new layout without errors', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $post = Page::factory()->create([
        'type'   => 'post',
        'slug'   => 'news/sample-post',
        'status' => 'draft',
    ]);

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->assertSuccessful();
});

it('renders the EditEvent form with the new layout without errors', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->id])
        ->assertSuccessful();
});

// ── Phase 7: Fullscreen toggle button ───────────────────────────────────────

it('renders the fullscreen toggle button in the admin layout', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view_any_page']);
    $this->actingAs($user);

    $this->get(route('filament.admin.resources.pages.index'))
        ->assertOk()
        ->assertSee('np-fullscreen', false);
});
