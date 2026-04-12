<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['view_page', 'update_page']);

    $this->page = Page::factory()->create([
        'title'  => 'Upload Test Page',
        'slug'   => 'upload-test-' . uniqid(),
        'status' => 'published',
    ]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'upload_test_widget_' . uniqid(),
        'label'         => 'Upload Test Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
    ]);

    $this->widget = PageWidget::create([
        'page_id'           => $this->page->id,
        'widget_type_id'    => $this->widgetType->id,
        'label'             => 'Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => ['background' => ['color' => '#ffffff']],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
});

it('uploads an appearance background image', function () {
    $file = UploadedFile::fake()->image('bg.jpg', 800, 600);

    $response = $this->actingAs($this->user)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file]
        );

    $response->assertOk();
    $response->assertJsonStructure(['url']);

    $this->widget->refresh();
    expect($this->widget->getFirstMedia('appearance_background_image'))->not->toBeNull();
});

it('does not mutate appearance_config on upload', function () {
    $originalConfig = $this->widget->appearance_config;
    $file = UploadedFile::fake()->image('bg.jpg', 800, 600);

    $this->actingAs($this->user)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file]
        )
        ->assertOk();

    $this->widget->refresh();
    expect($this->widget->appearance_config)->toBe($originalConfig);
});

it('replaces existing image on second upload (single-file collection)', function () {
    $file1 = UploadedFile::fake()->image('bg1.jpg', 800, 600);
    $file2 = UploadedFile::fake()->image('bg2.jpg', 800, 600);

    $this->actingAs($this->user)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file1]
        )
        ->assertOk();

    $this->actingAs($this->user)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file2]
        )
        ->assertOk();

    $this->widget->refresh();
    expect($this->widget->getMedia('appearance_background_image'))->toHaveCount(1);
});

it('deletes the appearance background image', function () {
    $file = UploadedFile::fake()->image('bg.jpg', 800, 600);

    $this->actingAs($this->user)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file]
        )
        ->assertOk();

    $this->actingAs($this->user)
        ->delete("/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image")
        ->assertOk()
        ->assertJson(['removed' => true]);

    $this->widget->refresh();
    expect($this->widget->getFirstMedia('appearance_background_image'))->toBeNull();
});

it('requires update_page permission for upload', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_page');

    $file = UploadedFile::fake()->image('bg.jpg', 800, 600);

    $this->actingAs($viewer)
        ->post(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file]
        )
        ->assertForbidden();
});

it('requires update_page permission for delete', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_page');

    $this->actingAs($viewer)
        ->delete("/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image")
        ->assertForbidden();
});

it('rejects non-image files', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->postJson(
            "/admin/api/page-builder/widgets/{$this->widget->id}/appearance-image",
            ['file' => $file]
        )
        ->assertUnprocessable();
});
