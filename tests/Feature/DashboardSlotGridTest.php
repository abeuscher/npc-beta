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
    (new \Database\Seeders\DashboardConfigSeeder())->run();
});

it('widgets() returns the super_admin config\'s three dashboard-native widgets in sort order', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $widget = new DashboardSlotGridWidget();
    $method = (new ReflectionClass($widget))->getMethod('widgets');
    $method->setAccessible(true);
    $instances = $method->invoke($widget);

    $handles = array_map(fn ($pw) => $pw->widgetType->handle, $instances);

    expect($handles)->toBe(['memos', 'quick_actions', 'this_weeks_events']);
});

it('widgets() returns an empty array when the acting user has no role', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $widget = new DashboardSlotGridWidget();
    $method = (new ReflectionClass($widget))->getMethod('widgets');
    $method->setAccessible(true);

    expect($method->invoke($widget))->toBe([]);
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

it('renders the empty-state copy when the acting user has no dashboard config', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rendered = Livewire::test(DashboardSlotGridWidget::class)->html();

    expect($rendered)->toContain('No dashboard arrangement for your role');
});

it('dashboard page loads successfully for an authenticated super_admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();
    $response->assertSee('dashboard-slot-grid-widget', false);

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
