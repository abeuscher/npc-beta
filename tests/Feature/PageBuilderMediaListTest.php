<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
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

    $this->page = Page::factory()->create(['slug' => 'ml-' . uniqid()]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'ml_widget_' . uniqid(),
        'label'         => 'ML Widget',
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

// A genuinely decodable PNG (the WidgetType thumbnail collection runs a
// synchronous GD conversion). Varying the width yields distinct valid bytes —
// hence distinct content_hash — while the same width yields identical bytes.
function mlPng(int $width = 1): string
{
    $img = imagecreatetruecolor(max(1, $width), 1);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

function mlAttach($owner, string $collection, string $bytes, string $name = 'image.png'): Media
{
    return $owner->addMediaFromString($bytes)
        ->usingFileName($name)
        ->toMediaCollection($collection, 'public');
}

function mlViewer(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('view_page');

    return $user;
}

// ── Contract ───────────────────────────────────────────────────────────────

it('returns image media in the picker shape and excludes non-image media', function () {
    $image = mlAttach($this->widget, 'config_logo', mlPng(2), 'photo.png');
    $doc = mlAttach($this->widget, 'config_logo', 'JUST-TEXT', 'notes.txt');

    $response = $this->actingAs(mlViewer())
        ->getJson('/admin/api/page-builder/media')
        ->assertOk()
        ->assertJsonStructure(['data' => [['media_id', 'url', 'file_name', 'size', 'mime_type']], 'has_more']);

    $ids = collect($response->json('data'))->pluck('media_id');
    expect($ids)->toContain($image->id)
        ->and($ids)->not->toContain($doc->id);
});

it('dedupes the list by content_hash, showing one representative per distinct image', function () {
    // Same bytes attached twice (two rows, one physical file) collapse to one
    // entry; the representative is the most-recent (highest) id.
    $dupe = mlPng(5);
    $first = mlAttach($this->widget, 'config_logo', $dupe, 'a.png');
    $second = mlAttach($this->widget, 'appearance_background_image', $dupe, 'b.png');
    // A distinct image stays its own entry.
    $other = mlAttach($this->widget, 'config_logo', mlPng(6), 'c.png');

    $ids = collect(
        $this->actingAs(mlViewer())->getJson('/admin/api/page-builder/media')->assertOk()->json('data')
    )->pluck('media_id');

    expect($ids)->toContain($second->id)   // the representative (highest id)
        ->and($ids)->not->toContain($first->id)
        ->and($ids)->toContain($other->id)
        ->and($ids->filter(fn ($id) => in_array($id, [$first->id, $second->id], true)))->toHaveCount(1);
});

it('excludes widget-type chrome thumbnails from the browsable list', function () {
    $authorImage = mlAttach($this->widget, 'config_logo', mlPng(7), 'author.png');
    $chrome = mlAttach($this->widgetType, 'thumbnail', mlPng(8), 'thumb.png');

    $ids = collect(
        $this->actingAs(mlViewer())->getJson('/admin/api/page-builder/media')->assertOk()->json('data')
    )->pluck('media_id');

    expect($ids)->toContain($authorImage->id)
        ->and($ids)->not->toContain($chrome->id);
});

it('narrows the list by a filename search term', function () {
    $hero = mlAttach($this->widget, 'config_logo', mlPng(9), 'hero-banner.png');
    $logo = mlAttach($this->widget, 'config_logo', mlPng(10), 'company-logo.png');

    $ids = collect(
        $this->actingAs(mlViewer())
            ->getJson('/admin/api/page-builder/media?search=hero')
            ->assertOk()->json('data')
    )->pluck('media_id');

    expect($ids)->toContain($hero->id)
        ->and($ids)->not->toContain($logo->id);
});

// ── Auth ─────────────────────────────────────────────────────────────────────

it('forbids the list without view_page permission', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/admin/api/page-builder/media')
        ->assertStatus(403);
});

it('lets the demo role browse the list (no new file involved)', function () {
    $image = mlAttach($this->widget, 'config_logo', mlPng(11), 'demo.png');

    $demo = User::factory()->create(['is_active' => true]);
    $demo->assignRole('demo');

    $ids = collect(
        $this->actingAs($demo)->getJson('/admin/api/page-builder/media')->assertOk()->json('data')
    )->pluck('media_id');

    expect($ids)->toContain($image->id);
});
