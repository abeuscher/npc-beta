<?php

use App\Filament\Widgets\DashboardSlotGridWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('DashboardSlotGridWidget::widgets() registers the three dashboard-native widgets (memos, quick_actions, this_weeks_events)', function () {
    $widget = new DashboardSlotGridWidget();
    $method = (new ReflectionClass($widget))->getMethod('widgets');
    $method->setAccessible(true);
    $instances = $method->invoke($widget);

    expect(array_keys($instances))->toBe(['memos', 'quick_actions', 'this_weeks_events']);
});

it('the mounted Livewire widget renders the slot grid container with the three dashboard-native widgets', function () {
    (new \Database\Seeders\MemosCollectionSeeder())->run();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    DB::connection()->enableQueryLog();

    $rendered = Livewire::test(DashboardSlotGridWidget::class)->html();

    $queryCount = count(DB::connection()->getQueryLog());
    fwrite(STDERR, "\n[DashboardSlotGridTest] Livewire mount query count: {$queryCount}\n");
    DB::connection()->disableQueryLog();

    expect($rendered)
        ->toContain('np-dashboard-slot-grid')
        ->toContain('np-memos')
        ->toContain('np-quick-actions')
        ->toContain('np-this-weeks-events');
});

it('dashboard page loads successfully for an authenticated super_admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();
    // Widget content is lazily isolated by Livewire; assert the widget
    // component is referenced by its view name in the dashboard payload.
    $response->assertSee('dashboard-slot-grid-widget', false);

    // Per-widget lib bundles are emitted in the admin head so external scripts
    // load synchronously before any Livewire widget mount (Alpine init races
    // morph-inserted <script src> tags otherwise). Assert when the manifest
    // publishes lib entries.
    $manifestPath = public_path('build/widgets/manifest.json');
    if (is_readable($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        if (! empty($manifest['libs']['swiper']['js'])) {
            $response->assertSee('data-widget-lib="swiper"', false);
        }
    }
});

it('dashboard page redirects unauthenticated users', function () {
    $response = $this->get('/admin');

    $response->assertRedirect();
});
