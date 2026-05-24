<?php

use App\Filament\Pages\DesignSystemPage;
use App\Filament\Pages\MediaLibraryPage;
use App\Jobs\ExportBundleJob;
use App\Jobs\ImportBundleJob;
use App\Jobs\RegenerateMediaConversionsJob;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\AssetBuildService;
use App\Services\BuildResult;
use App\Services\ColorTokenResolver;
use App\Services\ImportExport\BundleArchive;
use App\Services\ImportExport\ContentExporter;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use App\Services\ImportExport\InvalidImportBundleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    if (! \App\Models\Template::page()->where('is_default', true)->exists()) {
        \App\Models\Template::create(['name' => 'Default', 'type' => 'page', 'is_default' => true]);
    }

    // Baseline user so ContentImporter's author fallback resolves on the
    // unauthenticated (queued / hand-built bundle) import paths.
    User::factory()->create();

    // Spy build service — no external HTTP, counts the rebuild trigger.
    $this->buildSpy = new class extends AssetBuildService
    {
        public int $calls = 0;

        public function build(bool $debug = false): BuildResult
        {
            $this->calls++;

            return BuildResult::success('a.css', 'b.js', 1, 1, 1);
        }
    };
    $this->app->instance(AssetBuildService::class, $this->buildSpy);
});

function s303Author(): User
{
    $u = User::factory()->create();
    $u->givePermissionTo('update_page');

    return $u;
}

function s303PageWithLogoMedia(string $slug): array
{
    $page   = Page::factory()->create(['slug' => $slug, 'status' => 'published']);
    $logoWt = WidgetType::where('handle', 'logo')->firstOrFail();

    $widget = $page->widgets()->create([
        'widget_type_id'    => $logoWt->id,
        'label'             => 'Site Logo',
        'config'            => ['logo' => null, 'text' => 'Acme', 'link_url' => '/'],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $media = $widget->addMedia(firstSampleLogo())
        ->preservingOriginal()
        ->toMediaCollection('config_logo', 'public');

    $widget->update(['config' => ['logo' => $media->id, 'text' => 'Acme', 'link_url' => '/']]);

    return [$page, $widget, $media];
}

// ── BundleArchive primitive ─────────────────────────────────────────────────

it('round-trips an envelope + media bytes through BundleArchive build/extract', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$page] = s303PageWithLogoMedia('archive-roundtrip');
    $envelope = app(ContentExporter::class)->exportPages([$page->id]);

    $zipPath = Storage::disk('local')->path('test/bundle.zip');
    app(BundleArchive::class)->build($envelope, $zipPath);

    expect(is_file($zipPath))->toBeTrue();

    $out = app(BundleArchive::class)->extract($zipPath);

    expect($out['envelope']['format_version'])->toBe(ContentExporter::FORMAT_VERSION);
    expect($out['envelope']['media_transport'])->toBe('embedded');
    expect($out['envelope']['payload']['pages'][0]['slug'])->toBe('archive-roundtrip');

    $desc = $out['envelope']['payload']['pages'][0]['widgets'][0]['media'][0];
    expect(is_file($out['mediaRoot'] . '/' . $desc['path']))->toBeTrue();

    // caller owns cleanup
    (function () use ($out) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($out['tempDir'], FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($out['tempDir']);
    })();
});

it('rejects a zip-slip entry on extract', function () {
    $zipPath = sys_get_temp_dir() . '/slip_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('bundle.json', json_encode(['format_version' => '1.1.0', 'payload' => []]));
    $zip->addFromString('../escape.txt', 'pwned');
    $zip->close();

    expect(fn () => app(BundleArchive::class)->extract($zipPath))
        ->toThrow(InvalidImportBundleException::class);

    @unlink($zipPath);
});

it('rejects a zip exceeding the uncompressed ceiling (zip-bomb guard)', function () {
    Storage::fake('local');
    $zipPath = Storage::disk('local')->path('test/big.zip');
    app(BundleArchive::class)->build(
        ['format_version' => '1.1.0', 'payload' => []],
        $zipPath,
    );

    // bundle.json alone is well over a 10-byte ceiling.
    $tiny = new BundleArchive(maxTotalUncompressed: 10);

    expect(fn () => $tiny->extract($zipPath))
        ->toThrow(InvalidImportBundleException::class);
});

// ── Content + media: cross-system simulation ────────────────────────────────

it('resolves widget media from the archive after the source disk is wiped', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$page, $widget, $media] = s303PageWithLogoMedia('cross-system');
    $logoName = $media->file_name;

    $envelope = app(ContentExporter::class)->exportPages([$page->id]);
    $zipPath  = Storage::disk('local')->path('test/cross.zip');
    app(BundleArchive::class)->build($envelope, $zipPath);

    // Simulate a fresh target: wipe the source disk and the widget row.
    PageWidget::forOwner($page)->delete();
    Storage::fake('public'); // empty disk — local fallback would fail

    $out = app(BundleArchive::class)->extract($zipPath);
    $log = new ImportLog();
    app(ContentImporter::class)->import($out['envelope'], $log, mediaRoot: $out['mediaRoot']);

    $reimported = PageWidget::forOwner(Page::where('slug', 'cross-system')->first())->first();
    $reMedia    = $reimported->getFirstMedia('config_logo');

    expect($log->hasWarnings())->toBeFalse();
    expect($reMedia)->not->toBeNull();
    expect($reMedia->file_name)->toBe($logoName);
    expect($reimported->config['logo'])->toBe($reMedia->id);
});

// ── JSON-only path: byte-unchanged regression ───────────────────────────────

it('keeps the JSON import path unchanged (local-disk fallback, no archive)', function () {
    Storage::fake('public');

    [$page] = s303PageWithLogoMedia('json-regression');
    $envelope = app(ContentExporter::class)->exportPages([$page->id]);

    expect($envelope['media_transport'])->toBe('reference');

    PageWidget::forOwner($page)->delete();

    // No mediaRoot → resolves against the (still-present) local public disk.
    $log = new ImportLog();
    app(ContentImporter::class)->import($envelope, $log);

    $w = PageWidget::forOwner(Page::where('slug', 'json-regression')->first())->first();
    expect($w->getFirstMedia('config_logo'))->not->toBeNull();
    expect($log->hasWarnings())->toBeFalse();
});

it('still imports a legacy bundle that has no media_transport key', function () {
    $bundle = [
        'format_version' => '1.1.0',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => ['templates' => [], 'pages' => [[
            'title' => 'Legacy', 'slug' => 'legacy-no-transport', 'type' => 'default',
            'status' => 'draft', 'widgets' => [],
        ]]],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog());

    expect(Page::where('slug', 'legacy-no-transport')->exists())->toBeTrue();
});

// ── Theme/design export-import ──────────────────────────────────────────────

it('round-trips theme/design rows through export and import', function () {
    SiteSetting::updateOrCreate(['key' => 'theme_colors'],
        ['value' => json_encode(['brand' => '#abcdef', 'link' => '#123456']), 'type' => 'json', 'group' => 'design']);

    $envelope = app(ContentExporter::class)->exportDesign();
    expect($envelope['payload']['design']['theme_colors']['brand'])->toBe('#abcdef');

    // Mutate the target away, then re-import.
    SiteSetting::updateOrCreate(['key' => 'theme_colors'],
        ['value' => json_encode(['brand' => '#000000']), 'type' => 'json', 'group' => 'design']);
    \Illuminate\Support\Facades\Cache::forget('site_setting:theme_colors');

    app(ContentImporter::class)->import($envelope, new ImportLog(), ['merge_design' => true]);

    $loaded = ColorTokenResolver::load();
    expect($loaded['brand'])->toBe('#abcdef');
    expect($loaded['link'])->toBe('#123456');
    expect($this->buildSpy->calls)->toBe(1);
});

it('deep-merges over defaults and never sweeps a key the bundle omits (295 lesson)', function () {
    // Bundle carries ONLY bg — every other token is absent.
    $bundle = [
        'format_version' => '1.1.0',
        'exported_at'    => now()->toIso8601String(),
        'payload'        => ['design' => ['theme_colors' => ['bg' => '#000000']]],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog(), ['merge_design' => true]);

    $stored = SiteSetting::get('theme_colors');

    // Present key applied.
    expect($stored['bg'])->toBe('#000000');

    // Every other tier-1 token is its concrete default — never null, never
    // removed, never blanked (the sweep that 295 forbids).
    foreach (ColorTokenResolver::TIER1 as $key) {
        expect($stored)->toHaveKey($key);
        expect($stored[$key])->toBeString()->not->toBe('');
    }
    expect($stored['brand'])->toBe(ColorTokenResolver::defaults()['brand']);
});

it('leaves design rows the bundle does not mention untouched', function () {
    SiteSetting::updateOrCreate(['key' => 'typography'],
        ['value' => json_encode(['sample_text' => 'KEEP ME']), 'type' => 'json', 'group' => 'design']);

    // Colours-only bundle.
    $bundle = [
        'format_version' => '1.1.0',
        'payload'        => ['design' => ['theme_colors' => ['brand' => '#ff0000']]],
    ];

    app(ContentImporter::class)->import($bundle, new ImportLog(), ['merge_design' => true]);

    expect(SiteSetting::get('typography')['sample_text'])->toBe('KEEP ME');
});

it('does not trigger a CSS rebuild for a content-only bundle', function () {
    $page = Page::factory()->create(['slug' => 'no-design', 'status' => 'published']);
    $envelope = app(ContentExporter::class)->exportPages([$page->id]);

    app(ContentImporter::class)->import($envelope, new ImportLog());

    expect($this->buildSpy->calls)->toBe(0);
});

// ── Theme page action gate ──────────────────────────────────────────────────

function s303ThemeActions(): array
{
    $m = new ReflectionMethod(DesignSystemPage::class, 'getHeaderActions');
    $m->setAccessible(true);
    $out = [];
    foreach ($m->invoke(new DesignSystemPage()) as $action) {
        $out[$action->getName()] = $action;
    }

    return $out;
}

it('gates the theme export/import actions on manage_cms_settings', function () {
    $allowed = User::factory()->create();
    $allowed->givePermissionTo('manage_cms_settings');
    $this->actingAs($allowed);

    expect(DesignSystemPage::canAccess())->toBeTrue();
    $a = s303ThemeActions();
    expect($a['exportTheme']->isVisible())->toBeTrue();
    expect($a['importTheme']->isVisible())->toBeTrue();

    $denied = User::factory()->create();
    $this->actingAs($denied);

    expect(DesignSystemPage::canAccess())->toBeFalse();
    $b = s303ThemeActions();
    expect($b['exportTheme']->isVisible())->toBeFalse();
    expect($b['importTheme']->isVisible())->toBeFalse();
});

// ── Queued export job + stored artifact + notification ──────────────────────

it('builds a stored zip artifact and notifies the operator on export', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$page] = s303PageWithLogoMedia('export-job');
    $user = s303Author();

    (new ExportBundleJob('pages', [$page->id], $user->id, 'pages-1'))->handle();

    $files = Storage::disk('local')->allFiles('exports/bundles');
    expect($files)->toHaveCount(1);
    expect($files[0])->toEndWith('.zip');

    $notif = $user->fresh()->notifications()->first();
    expect($notif)->not->toBeNull();
    expect(json_encode($notif->data))->toContain('Export ready');
});

// ── Queued import job: JSON + zip ───────────────────────────────────────────

it('imports a JSON bundle through the queued job and cleans the upload', function () {
    Storage::fake('local');

    $src      = Page::factory()->create(['slug' => 'job-json', 'status' => 'published']);
    $envelope = app(ContentExporter::class)->exportPages([$src->id]);
    $src->forceDelete();

    Storage::disk('local')->put('imports/bundles/u.json', json_encode($envelope));
    $user = s303Author();

    (new ImportBundleJob('imports/bundles/u.json', $user->id))->handle();

    expect(Page::where('slug', 'job-json')->exists())->toBeTrue();
    expect(Storage::disk('local')->exists('imports/bundles/u.json'))->toBeFalse();
    expect(json_encode($user->fresh()->notifications()->first()->data))->toContain('Import complete');
});

it('imports a zip bundle archive-first through the queued job', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$page, , $media] = s303PageWithLogoMedia('job-zip');
    $logoName = $media->file_name;
    $envelope = app(ContentExporter::class)->exportPages([$page->id]);

    app(BundleArchive::class)->build($envelope, Storage::disk('local')->path('imports/bundles/u.zip'));

    PageWidget::forOwner($page)->delete();
    Storage::fake('public'); // wipe source disk

    $user = s303Author();
    (new ImportBundleJob('imports/bundles/u.zip', $user->id))->handle();

    $w = PageWidget::forOwner(Page::where('slug', 'job-zip')->first())->first();
    expect($w->getFirstMedia('config_logo')?->file_name)->toBe($logoName);
    expect(Storage::disk('local')->exists('imports/bundles/u.zip'))->toBeFalse();
});

// ── Gated download route ────────────────────────────────────────────────────

it('serves an export artifact only to update_page holders', function () {
    Storage::fake('local');
    $token = (string) \Illuminate\Support\Str::uuid();
    Storage::disk('local')->put("exports/bundles/{$token}/bundle.zip", 'ZIPBYTES');

    $url = route('filament.admin.exports.bundle.download', ['token' => $token]);

    $this->actingAs(s303Author())->get($url)->assertOk();

    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

    $bad = route('filament.admin.exports.bundle.download', ['token' => 'not-a-uuid']);
    $this->actingAs(s303Author())->get($bad)->assertNotFound();
});

// ── User repro: editor colour round-trip through the real save path ─────────

it('reverts an imported colour through the real editor save path and reload', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('manage_cms_settings');
    $this->actingAs($admin);

    Livewire::test(DesignSystemPage::class)
        ->set('colorsData.theme_colors.brand', '#aaaaaa')
        ->call('saveColors');
    expect(ColorTokenResolver::load()['brand'])->toBe('#aaaaaa');

    $bundle = app(ContentExporter::class)->exportDesign();
    expect($bundle['payload']['design']['theme_colors']['brand'])->toBe('#aaaaaa');

    Livewire::test(DesignSystemPage::class)
        ->set('colorsData.theme_colors.brand', '#bbbbbb')
        ->call('saveColors');
    expect(ColorTokenResolver::load()['brand'])->toBe('#bbbbbb');

    app(ContentImporter::class)->import($bundle, new ImportLog(), ['merge_design' => true]);
    expect(ColorTokenResolver::load()['brand'])->toBe('#aaaaaa');

    // Fresh page load must show the reverted colour.
    Livewire::test(DesignSystemPage::class)
        ->assertSet('colorsData.theme_colors.brand', '#aaaaaa');
});

// ── notifications.data is json (Filament bell Postgres query) ───────────────

it('lets Filament query the notification bell with the Postgres json operator', function () {
    $user = s303Author();

    \Filament\Notifications\Notification::make()
        ->title('Bell check')
        ->success()
        ->sendToDatabase($user);

    // The exact operator Filament's database-notifications component uses;
    // throws on a text column, works on json.
    $row = DB::table('notifications')
        ->where('notifiable_type', $user->getMorphClass())
        ->where('notifiable_id', $user->id)
        ->whereRaw("data->>'format' = ?", ['filament'])
        ->first();

    expect($row)->not->toBeNull();
});

// ── Phase 2: ID-preserving media seed ───────────────────────────────────────

/**
 * Package an envelope into a zip and extract it — called BEFORE the target is
 * wiped so the source media bytes actually travel in the archive (the
 * cross-system simulation). Returns the extracted {envelope, mediaRoot}.
 */
function s303PackageZip(array $envelope): array
{
    $zip = tempnam(sys_get_temp_dir(), 's303') . '.zip';
    app(BundleArchive::class)->build($envelope, $zip);

    return app(BundleArchive::class)->extract($zip);
}

function s303SeedMediaFromZip(array $out): ImportLog
{
    $log = new ImportLog();
    app(ContentImporter::class)->import($out['envelope'], $log, mediaRoot: $out['mediaRoot']);

    return $log;
}

it('preserves media id, uuid and path and advances the sequence', function () {
    Storage::fake('public');

    [, , $media] = s303PageWithLogoMedia('seed-source');
    $origId   = $media->id;
    $origUuid = $media->uuid;
    $origName = $media->file_name;
    $casPath  = $media->getPathRelativeToRoot();

    $envelope = app(ContentExporter::class)->exportMedia([$origId]);
    expect($envelope['payload']['media'][0]['id'])->toBe($origId);
    expect($envelope['payload']['media'][0]['path'])->toBe($casPath);

    // Package while the source bytes still exist, then simulate a clean target.
    $out = s303PackageZip($envelope);
    DB::table('media')->where('id', $origId)->delete();
    Storage::fake('public');

    $log = s303SeedMediaFromZip($out);

    $row = DB::table('media')->where('id', $origId)->first();
    expect($row)->not->toBeNull();
    expect($row->uuid)->toBe($origUuid);
    expect($row->file_name)->toBe($origName);
    expect(Storage::disk('public')->exists($casPath))->toBeTrue();
    expect($log->hasWarnings())->toBeFalse();

    // Sequence is past the explicit id — a fresh insert gets a higher id.
    $nextId = (int) DB::table('media')->insertGetId([
        'model_type' => 'X', 'model_id' => 1, 'collection_name' => 'c', 'name' => 'n',
        'file_name' => 'f', 'disk' => 'public', 'size' => 1,
        'manipulations' => '[]', 'custom_properties' => '[]',
        'generated_conversions' => '[]', 'responsive_images' => '[]',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect($nextId)->toBeGreaterThan($origId);
});

it('skips an identical re-push and warns on a divergent id collision', function () {
    Storage::fake('public');
    [, , $media] = s303PageWithLogoMedia('seed-collide');
    $id = $media->id;
    $envelope = app(ContentExporter::class)->exportMedia([$id]);
    $out = s303PackageZip($envelope);

    // Identical re-push: row already present and matching → idempotent skip.
    $log1 = s303SeedMediaFromZip($out);
    expect($log1->hasWarnings())->toBeFalse();
    expect(DB::table('media')->where('id', $id)->count())->toBe(1);

    // Divergent: same id, different uuid/file_name already on target.
    $divergentUuid = (string) \Illuminate\Support\Str::uuid();
    DB::table('media')->where('id', $id)->update(['uuid' => $divergentUuid, 'file_name' => 'other.png']);
    $log2 = s303SeedMediaFromZip($out);
    expect($log2->warnings())->not->toBeEmpty();
    expect($log2->warnings()[0]['message'])->toContain((string) $id);
    // No clobber — the divergent row stands.
    expect(DB::table('media')->where('id', $id)->value('uuid'))->toBe($divergentUuid);
});

it('parks media whose original owner is absent on the target', function () {
    Storage::fake('public');
    [, , $media] = s303PageWithLogoMedia('seed-orphan');
    $id = $media->id;
    $envelope = app(ContentExporter::class)->exportMedia([$id]);
    $out = s303PackageZip($envelope);

    // Wipe the owning widget + the media row → owner is now absent.
    PageWidget::query()->delete();
    DB::table('media')->where('id', $id)->delete();
    Storage::fake('public');

    $log = s303SeedMediaFromZip($out);

    expect(DB::table('media')->where('id', $id)->exists())->toBeTrue();
    $infos = collect($log->entries())->where('level', 'info')->pluck('message')->implode(' ');
    expect($infos)->toContain('parked');
});

it('queues conversion regeneration for each seeded media', function () {
    Storage::fake('public');
    Storage::fake('local');
    [, , $media] = s303PageWithLogoMedia('seed-regen');
    $id = $media->id;
    $envelope = app(ContentExporter::class)->exportMedia([$id]);
    $out = s303PackageZip($envelope);

    DB::table('media')->where('id', $id)->delete();
    Storage::fake('public');

    Queue::fake();
    app(ContentImporter::class)->import($out['envelope'], new ImportLog(), mediaRoot: $out['mediaRoot']);

    Queue::assertPushed(RegenerateMediaConversionsJob::class, fn ($job) => $job->mediaId === $id);
});

it('resolves a by-reference page bundle after a standalone media seed (end-to-end)', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$page, , $media] = s303PageWithLogoMedia('e2e-byref');
    $mediaId  = $media->id;
    $fileName = $media->file_name;
    $casPath  = $media->getPathRelativeToRoot();

    // By-reference page bundle (JSON, descriptors only) + standalone media zip.
    $pageBundle  = app(ContentExporter::class)->exportPages([$page->id]);
    $mediaBundle = app(ContentExporter::class)->exportMedia([$mediaId]);
    $out = s303PackageZip($mediaBundle); // package before the wipe

    // Clean target: drop the page tree, the media row, and the disk.
    PageWidget::forOwner($page)->delete();
    Page::where('slug', 'e2e-byref')->forceDelete();
    DB::table('media')->where('id', $mediaId)->delete();
    Storage::fake('public');

    // 1) Seed media (carries bytes) → file lands at its content-addressed path.
    s303SeedMediaFromZip($out);
    expect(Storage::disk('public')->exists($casPath))->toBeTrue();

    // 2) Import the by-reference JSON page bundle → resolves off the seed.
    $log = new ImportLog();
    app(ContentImporter::class)->import($pageBundle, $log);

    $w = PageWidget::forOwner(Page::where('slug', 'e2e-byref')->first())->first();
    expect($w->getFirstMedia('config_logo'))->not->toBeNull();
    expect($w->getFirstMedia('config_logo')->file_name)->toBe($fileName);
    expect($log->hasWarnings())->toBeFalse();
});

it('gates the media library export/import actions on update_page but keeps the page at view_any_page', function () {
    $m = new ReflectionMethod(MediaLibraryPage::class, 'getHeaderActions');
    $m->setAccessible(true);
    $actions = fn () => collect($m->invoke(new MediaLibraryPage()))
        ->keyBy(fn ($a) => $a->getName());

    // view_any_page only: page is reachable, mutating actions are hidden.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_any_page');
    $this->actingAs($viewer);
    expect(MediaLibraryPage::canAccess())->toBeTrue();
    expect($actions()['exportAllMedia']->isVisible())->toBeFalse();
    expect($actions()['importMediaBundle']->isVisible())->toBeFalse();

    // update_page: actions visible.
    $editor = s303Author();
    $this->actingAs($editor);
    expect($actions()['exportAllMedia']->isVisible())->toBeTrue();
    expect($actions()['importMediaBundle']->isVisible())->toBeTrue();
});
