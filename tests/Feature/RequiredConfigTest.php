<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

// ── Migration ───────────────────────────────────────────────────────────

it('has required_config column on widget_types table', function () {
    expect(Schema::hasColumn('widget_types', 'required_config'))->toBeTrue();
});

// ── Seeder ──────────────────────────────────────────────────────────────

it('populates required_config for widgets that need it', function () {
    $expectedHandles = [
        'carousel',
        'logo_garden',
        'board_members',
        'video_embed',
        'map_embed',
        'web_form',
        'event_registration',
        'event_description',
    ];

    foreach ($expectedHandles as $handle) {
        $wt = WidgetType::where('handle', $handle)->first();
        expect($wt->required_config)->not->toBeNull("$handle should have required_config");
        expect($wt->required_config)->toHaveKeys(['keys', 'message']);
        expect($wt->required_config['keys'])->toBeArray()->not->toBeEmpty();
        expect($wt->required_config['message'])->toBeString()->not->toBeEmpty();
    }
});

it('does not set required_config for widgets that do not need it', function () {
    $noRequiredConfig = ['text_block', 'hero', 'site_header', 'site_footer', 'image'];

    foreach ($noRequiredConfig as $handle) {
        $wt = WidgetType::where('handle', $handle)->first();
        expect($wt->required_config)->toBeNull("$handle should NOT have required_config");
    }
});

it('carousel required_config checks collection_handle and image_field', function () {
    $wt = WidgetType::where('handle', 'carousel')->first();
    expect($wt->required_config['keys'])->toBe(['collection_handle', 'image_field']);
});

// ── API returns required_config ─────────────────────────────────────────

function rc_apiUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view_page', 'update_page']);

    return $user;
}

function rc_apiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

it('widget types endpoint includes required_config', function () {
    $response = $this->actingAs(rc_apiUser())
        ->getJson(rc_apiPrefix() . '/widget-types?page_type=default');

    $response->assertOk();

    $types = collect($response->json('widget_types'));

    $carousel = $types->firstWhere('handle', 'carousel');
    expect($carousel)->not->toBeNull();
    expect($carousel['required_config'])->not->toBeNull();
    expect($carousel['required_config']['keys'])->toBe(['collection_handle', 'image_field']);

    $textBlock = $types->firstWhere('handle', 'text_block');
    expect($textBlock['required_config'])->toBeNull();
});

it('widget tree response includes widget_type_required_config', function () {
    $page = Page::factory()->create(['status' => 'published']);
    $wt = WidgetType::where('handle', 'carousel')->first();

    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => 'Test Carousel',
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $response = $this->actingAs(rc_apiUser())
        ->getJson(rc_apiPrefix() . "/{$page->id}/widgets");

    $response->assertOk();

    $widget = $response->json('widgets.0');
    expect($widget)->toHaveKey('widget_type_required_config');
    expect($widget['widget_type_required_config']['keys'])->toBe(['collection_handle', 'image_field']);
});
