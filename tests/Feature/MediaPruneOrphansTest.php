<?php

use App\Models\Page;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $page = Page::factory()->create(['slug' => 'prune-'.uniqid()]);

    $widgetType = WidgetType::create([
        'handle'        => 'prune_widget_'.uniqid(),
        'label'         => 'Prune Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [['key' => 'logo', 'type' => 'image']],
    ]);

    $this->widget = $page->widgets()->create([
        'widget_type_id'    => $widgetType->id,
        'label'             => 'W',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
});

// A live media file at its content-addressed directory, hash referenced by a row.
function pruneLiveDir(\App\Models\PageWidget $widget, string $bytes): string
{
    $media = $widget->addMediaFromString($bytes)
        ->usingFileName('live.png')
        ->toMediaCollection('config_logo', 'public');
    $media->refresh();

    return 'cas/'.substr($media->content_hash, 0, 2).'/'.$media->content_hash;
}

// A planted directory in the cas tree whose hash is referenced by no media row.
function pruneOrphanDir(string $seed): string
{
    $hash = hash('sha256', 'orphan-'.$seed);
    $dir  = 'cas/'.substr($hash, 0, 2).'/'.$hash;
    Storage::disk('public')->put($dir.'/orphan.png', 'ORPHAN-'.$seed);

    return $dir;
}

it('dry-run (the default) reports orphans but deletes nothing', function () {
    $liveDir   = pruneLiveDir($this->widget, 'LIVE-BYTES');
    $orphanDir = pruneOrphanDir('one');

    $this->artisan('media:prune-orphans')->assertSuccessful();

    Storage::disk('public')->assertExists($orphanDir.'/orphan.png');
    Storage::disk('public')->assertExists($liveDir.'/live.png');
});

it('--force deletes only orphan directories and never a live one', function () {
    $liveDir = pruneLiveDir($this->widget, 'LIVE-BYTES');
    $orphanA = pruneOrphanDir('a');
    $orphanB = pruneOrphanDir('b');

    $this->artisan('media:prune-orphans --force')->assertSuccessful();

    Storage::disk('public')->assertMissing($orphanA.'/orphan.png');
    Storage::disk('public')->assertMissing($orphanB.'/orphan.png');
    Storage::disk('public')->assertExists($liveDir.'/live.png');
});

it('is a clean no-op when the tree holds only live files', function () {
    $liveDir = pruneLiveDir($this->widget, 'ONLY-LIVE');

    $this->artisan('media:prune-orphans --force')
        ->expectsOutputToContain('No orphan media directories found.')
        ->assertSuccessful();

    Storage::disk('public')->assertExists($liveDir.'/live.png');
});
