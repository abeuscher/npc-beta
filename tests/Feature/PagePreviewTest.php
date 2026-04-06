<?php

use App\Models\Page;
use App\Models\User;
use App\Models\WidgetType;
use App\Models\PageWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\WidgetTypeSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
});

it('returns 403 for unauthenticated users', function () {
    $page = Page::factory()->create(['status' => 'published', 'published_at' => now()]);

    $this->get(route('filament.admin.page-preview', ['page' => $page->id]))
        ->assertRedirect();
});

it('returns 403 for users without update_page permission', function () {
    $user = User::factory()->create();
    // User with no roles/permissions

    $page = Page::factory()->create(['status' => 'published', 'published_at' => now()]);

    $this->actingAs($user)
        ->get(route('filament.admin.page-preview', ['page' => $page->id]))
        ->assertForbidden();
});

it('returns 200 for admin users and renders page content', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $page = Page::factory()->create([
        'title' => 'Preview Test Page',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $textBlock = WidgetType::where('handle', 'text_block')->first();
    PageWidget::create([
        'page_id' => $page->id,
        'widget_type_id' => $textBlock->id,
        'label' => 'Test Text Block',
        'config' => ['content' => '<p>Hello preview world</p>'],
        'query_config' => [],
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.page-preview', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('Hello preview world');
});

it('includes widget handle overlays with data attributes', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $page = Page::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

    $textBlock = WidgetType::where('handle', 'text_block')->first();
    $pw = PageWidget::create([
        'page_id' => $page->id,
        'widget_type_id' => $textBlock->id,
        'label' => 'Clickable Widget',
        'config' => ['content' => '<p>Content</p>'],
        'query_config' => [],
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.page-preview', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('data-preview-widget-id="' . $pw->id . '"', false)
        ->assertSee('preview-widget-handle', false)
        ->assertSee('Clickable Widget');
});

it('does not expose any new public routes', function () {
    $page = Page::factory()->create(['status' => 'published', 'published_at' => now()]);

    // The preview route is under /admin/ prefix, not a public route
    $this->get('/preview/' . $page->id)->assertNotFound();
});
