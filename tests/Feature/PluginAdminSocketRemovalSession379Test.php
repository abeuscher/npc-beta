<?php

use App\Plugins\PluginAdminRegistry;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Plugins\SocketProbe\Filament\Pages\SocketProbePage;
use Tests\Fixtures\Plugins\SocketProbe\SocketProbeServiceProvider;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 379 (Plugin Architecture arc P3): the remove-the-line guarantee,
// extended to the admin socket. This file boots the plain application — the
// SocketProbe fixture is NOT in config/plugins.php — and proves that every
// surface its enabled twin (PluginAdminSocketSession379Test) asserts present
// is absent: no contribution, no discovered page, no route, no seeded
// permission.

it('does not carry the fixture in the shipped plugin list', function () {
    expect(config('plugins'))->not->toContain(SocketProbeServiceProvider::class);
    expect(app()->providerIsLoaded(SocketProbeServiceProvider::class))->toBeFalse();
    expect(collect(app(PluginAdminRegistry::class)->all())->firstWhere('plugin', 'socket-probe'))->toBeNull();
});

it('discovers no fixture page and registers no fixture routes', function () {
    expect(Filament::getPanel('admin')->getPages())->not->toContain(SocketProbePage::class);
    expect(Route::has('filament.admin.pages.socket-probe'))->toBeFalse();
    expect(Route::has('filament.admin.socket-probe.ping'))->toBeFalse();
});

it('seeds no fixture permission', function () {
    (new PermissionSeeder())->run();

    expect(Permission::where('name', 'use_socket_probe')->exists())->toBeFalse();
});
