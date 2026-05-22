<?php

use App\Filament\Pages\MediaFinderPage;
use App\Models\EmailTemplate;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\Media\MediaFinderService;
use App\Services\Media\MediaReferenceInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    \Illuminate\Support\Facades\Storage::fake('public');
    // Conversions queue by default; faking the queue keeps Spatie from trying
    // to load the lightweight string fixtures as real images.
    \Illuminate\Support\Facades\Queue::fake();

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('manage_cms_settings');

    $this->page = Page::factory()->create(['slug' => 'mf-' . uniqid()]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'mf_widget_' . uniqid(),
        'label'         => 'MF Widget',
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

function attach(PageWidget|Page|EmailTemplate $owner, string $collection, string $bytes = 'IMG', string $name = 'a.png'): Media
{
    return $owner->addMediaFromString($bytes)
        ->usingFileName($name)
        ->toMediaCollection($collection, 'public');
}

// ── Rule A: live collection ───────────────────────────────────────────────

it('treats a media in a live widget config collection as referenced', function () {
    $media = attach($this->widget, 'config_logo');

    expect(app(MediaReferenceInventory::class)->classify($media))
        ->toBe(MediaReferenceInventory::CLASS_LIVE);

    $unused = collect(app(MediaFinderService::class)->scanUnused())->pluck('id');
    expect($unused)->not->toContain($media->id);
});

it('treats appearance_background_image as a live collection', function () {
    $media = attach($this->widget, 'appearance_background_image');

    expect(app(MediaReferenceInventory::class)->classify($media))
        ->toBe(MediaReferenceInventory::CLASS_LIVE);
});

it('treats a fixed-collection owner (page post_header) as live', function () {
    $media = attach($this->page, 'post_header');

    expect(app(MediaReferenceInventory::class)->classify($media))
        ->toBe(MediaReferenceInventory::CLASS_LIVE);
});

// ── Rule A: dead collection ───────────────────────────────────────────────

it('flags a media whose widget config field no longer exists as dead_collection', function () {
    $media = attach($this->widget, 'config_oldkey');

    expect(app(MediaReferenceInventory::class)->classify($media))
        ->toBe(MediaReferenceInventory::CLASS_DEAD_COLLECTION);

    $unused = collect(app(MediaFinderService::class)->scanUnused());
    expect($unused->firstWhere('id', $media->id))
        ->not->toBeNull()
        ->and($unused->firstWhere('id', $media->id)['classification'])
        ->toBe(MediaReferenceInventory::CLASS_DEAD_COLLECTION);
});

// ── Rule B: embedded URL keeps media alive ────────────────────────────────

it('does not flag media whose storage url is embedded in rich-text content', function () {
    // Media lives in a dead collection but is embedded by URL elsewhere.
    $media = attach($this->widget, 'config_oldkey');

    $other = $this->page->widgets()->create([
        'widget_type_id'    => $this->widgetType->id,
        'label'             => 'rich',
        'config'            => ['body' => '<p><img src="' . $media->getUrl() . '"></p>'],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 1,
        'is_active'         => true,
    ]);

    $inventory = app(MediaReferenceInventory::class);
    expect($inventory->embeddedMediaIds())->toHaveKey($media->id)
        ->and($inventory->classify($media))->toBe(MediaReferenceInventory::CLASS_LIVE);
});

it('extracts storage ids from anchor hrefs and srcset, not just img src', function () {
    $inventory = app(MediaReferenceInventory::class);

    expect($inventory->extractStorageIds('<a href="/storage/42/file.pdf">x</a>'))->toBe([42])
        ->and($inventory->extractStorageIds('<img srcset="/storage/7/a.webp 1x">'))->toBe([7])
        ->and($inventory->extractStorageIds('no media here'))->toBe([]);
});

// ── Orphan owner ──────────────────────────────────────────────────────────

it('classifies media whose owner row is gone as orphan_owner', function () {
    $media = attach($this->widget, 'config_logo');

    // Remove the owner row directly to bypass Spatie's cascade observer.
    DB::table('page_widgets')->where('id', $this->widget->id)->delete();

    expect(app(MediaReferenceInventory::class)->classify($media->fresh()))
        ->toBe(MediaReferenceInventory::CLASS_ORPHAN_OWNER);
});

// ── Duplicate scan ────────────────────────────────────────────────────────

it('clusters media with identical content', function () {
    $a = attach($this->widget, 'config_logo', 'IDENTICAL', 'one.png');
    $b = attach($this->page, 'post_header', 'IDENTICAL', 'two.png');

    $clusters = app(MediaFinderService::class)->scanDuplicates();

    $hashCluster = collect($clusters)->firstWhere('reason', 'identical_content');
    expect($hashCluster)->not->toBeNull()
        ->and($hashCluster['count'])->toBe(2)
        ->and(collect($hashCluster['members'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('clusters distinct-content media that share filename and size', function () {
    attach($this->widget, 'config_logo', 'AAAA', 'dup.png');
    attach($this->page, 'post_header', 'BBBB', 'dup.png');

    $clusters = app(MediaFinderService::class)->scanDuplicates();

    expect(collect($clusters)->firstWhere('reason', 'same_name_size'))->not->toBeNull();
});

it('marks referenced members in a duplicate cluster', function () {
    $live = attach($this->widget, 'config_logo', 'SAME', 'x.png');
    $dead = attach($this->widget, 'config_oldkey', 'SAME', 'y.png');

    $cluster = collect(app(MediaFinderService::class)->scanDuplicates())
        ->firstWhere('reason', 'identical_content');

    $members = collect($cluster['members'])->keyBy('id');
    expect($members[$live->id]['referenced'])->toBeTrue()
        ->and($members[$dead->id]['referenced'])->toBeFalse();
});

// ── Missing-file scan ─────────────────────────────────────────────────────

it('surfaces media whose file is gone from disk', function () {
    $present = attach($this->widget, 'config_logo', 'HERE', 'present.png');
    $gone = attach($this->page, 'post_header', 'GONE', 'gone.png');

    \Illuminate\Support\Facades\Storage::disk('public')->delete($gone->getPathRelativeToRoot());

    $missing = collect(app(MediaFinderService::class)->scanMissingFiles())->pluck('id');

    expect($missing)->toContain($gone->id)
        ->and($missing)->not->toContain($present->id);
});

it('flags a missing file that is still referenced as broken', function () {
    // config_logo is a live collection, so this row is referenced.
    $media = attach($this->widget, 'config_logo', 'GONE', 'broken.png');
    \Illuminate\Support\Facades\Storage::disk('public')->delete($media->getPathRelativeToRoot());

    $row = collect(app(MediaFinderService::class)->scanMissingFiles())->firstWhere('id', $media->id);

    expect($row)->not->toBeNull()
        ->and($row['referenced'])->toBeTrue();
});

it('deletes a dead record from the missing-file results', function () {
    $media = attach($this->page, 'post_header', 'GONE', 'dead.png');
    \Illuminate\Support\Facades\Storage::disk('public')->delete($media->getPathRelativeToRoot());

    $component = Livewire::actingAs($this->admin)
        ->test(MediaFinderPage::class)
        ->callAction('runMissingScan');

    expect(collect($component->get('missingResults'))->pluck('id'))->toContain($media->id);

    $component->callAction('deleteMedia', arguments: ['media' => $media->id]);

    expect(Media::find($media->id))->toBeNull()
        ->and(collect($component->get('missingResults'))->pluck('id'))->not->toContain($media->id);
});

// ── Page access + delete action ───────────────────────────────────────────

it('gates the page behind manage_cms_settings', function () {
    $stranger = User::factory()->create();

    expect(MediaFinderPage::canAccess())->toBeFalse();

    $this->actingAs($this->admin);
    expect(MediaFinderPage::canAccess())->toBeTrue();
});

it('deletes a media file through the confirmed action', function () {
    $media = attach($this->widget, 'config_oldkey');
    $id = $media->id;

    Livewire::actingAs($this->admin)
        ->test(MediaFinderPage::class)
        ->callAction('runUnusedScan')
        ->callAction('deleteMedia', arguments: ['media' => $id]);

    expect(Media::find($id))->toBeNull();
});

it('removes a deleted row from the in-memory scan results', function () {
    $media = attach($this->widget, 'config_oldkey');

    $component = Livewire::actingAs($this->admin)
        ->test(MediaFinderPage::class)
        ->callAction('runUnusedScan');

    expect(collect($component->get('unusedResults'))->pluck('id'))->toContain($media->id);

    $component->callAction('deleteMedia', arguments: ['media' => $media->id]);

    expect(collect($component->get('unusedResults'))->pluck('id'))->not->toContain($media->id);
});
