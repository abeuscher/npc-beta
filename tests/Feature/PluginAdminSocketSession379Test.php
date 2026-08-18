<?php

use App\Models\User;
use App\Plugins\PluginAdminRegistry;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Plugins\SocketProbe\Filament\Pages\SocketProbePage;
use Tests\Fixtures\Plugins\SocketProbe\SocketProbeServiceProvider;
use Tests\Fixtures\Plugins\SocketProbe\SocketProbeTestCase;

uses(SocketProbeTestCase::class, RefreshDatabase::class);

// Session 379 (Plugin Architecture arc P3): the admin-shell socket, proven by
// the SocketProbe fixture plugin. SocketProbeTestCase appends the fixture
// provider to config('plugins') at bootstrap; these tests assert the socket
// mechanics of docs/plugin-contract.md surfaces 3 and 4. The mirror file
// PluginAdminSocketRemovalSession379Test proves everything here vanishes when
// the config line is absent.

beforeEach(function () {
    (new PermissionSeeder())->run();
});

function actAsSocketSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    test()->actingAs($admin);

    return $admin;
}

it('registers the fixture provider and its admin contribution', function () {
    expect(config('plugins'))->toContain(SocketProbeServiceProvider::class);
    expect(app()->providerIsLoaded(SocketProbeServiceProvider::class))->toBeTrue();

    $contributions = app(PluginAdminRegistry::class)->all();
    $probe = collect($contributions)->firstWhere('plugin', 'socket-probe');

    expect($probe)->not->toBeNull();
    expect($probe->permissions)->toBe(['use_socket_probe']);
    expect($probe->routesFile)->toEndWith('SocketProbe/routes/admin.php');
});

it('discovers the fixture page into the panel with a core nav group and sort weight', function () {
    expect(Filament::getPanel('admin')->getPages())->toContain(SocketProbePage::class);

    $coreGroups = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group) => $group->getLabel())
        ->values()
        ->all();

    expect($coreGroups)->toContain('Tools');
    expect(SocketProbePage::getNavigationGroup())->toBe('Tools');
    expect(SocketProbePage::getNavigationSort())->toBe(97);
    expect(Route::has('filament.admin.pages.socket-probe'))->toBeTrue();
});

it('registers the fixture route file inside the panel behind Authenticate', function () {
    $route = Route::getRoutes()->getByName('filament.admin.socket-probe.ping');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/socket-probe/ping');

    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain(\Filament\Http\Middleware\Authenticate::class)
        ->and($middleware)->toContain(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('redirects guests off the fixture route and serves super-admins', function () {
    $this->get(route('filament.admin.socket-probe.ping'))
        ->assertRedirect();

    actAsSocketSuperAdmin();

    $this->get(route('filament.admin.socket-probe.ping'))
        ->assertOk()
        ->assertJson(['pong' => true]);
});

it('seeds the declared permission idempotently, granted to no shipped role', function () {
    $query = Permission::where('name', 'use_socket_probe')->where('guard_name', 'web');

    expect($query->count())->toBe(1);

    (new PermissionSeeder())->run();

    expect($query->count())->toBe(1);

    $permission = $query->first();
    expect($permission->roles()->count())->toBe(0);
});

it('gates the fixture page on the declared permission — super-admin only by default', function () {
    $user = User::factory()->create();
    $user->assignRole('developer');
    $this->actingAs($user);

    expect($user->can('use_socket_probe'))->toBeFalse();
    $this->get(route('filament.admin.pages.socket-probe'))->assertForbidden();

    $admin = actAsSocketSuperAdmin();

    expect($admin->can('use_socket_probe'))->toBeTrue();
    $this->get(route('filament.admin.pages.socket-probe'))->assertOk();
});
