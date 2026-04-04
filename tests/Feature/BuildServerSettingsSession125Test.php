<?php

use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Widgets\DashboardIntegrationStatusWidget;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AssetBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

// ── CMS Settings page: build server fields ──

it('saves build server url to site settings', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->fillForm([
            'build_server_url' => 'http://build.example.com:8080',
        ])
        ->call('save');

    expect(SiteSetting::get('build_server_url'))->toBe('http://build.example.com:8080');
});

it('saves build server api key encrypted', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->fillForm([
            'build_server_api_key' => 'my-secret-key',
        ])
        ->call('save');

    // Check the raw DB value is encrypted (not plaintext)
    $raw = SiteSetting::where('key', 'build_server_api_key')->first();
    expect($raw->type)->toBe('encrypted');
    expect($raw->value)->not->toBe('my-secret-key');

    // Check SiteSetting::get() decrypts it correctly
    expect(SiteSetting::get('build_server_api_key'))->toBe('my-secret-key');
});

it('preserves existing api key when field left blank', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    // Pre-set an encrypted API key
    SiteSetting::create([
        'key' => 'build_server_api_key',
        'value' => Crypt::encryptString('existing-key'),
        'type' => 'encrypted',
    ]);

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->fillForm([
            'build_server_url' => 'http://build.example.com',
            'build_server_api_key' => '',
        ])
        ->call('save');

    // Key should still be the original
    expect(SiteSetting::get('build_server_api_key'))->toBe('existing-key');
});

// ── AssetBuildService: site settings fallback ──

it('prefers site settings over env config for build server url', function () {
    config(['services.build_server.url' => 'http://env-server:8080']);
    config(['services.build_server.api_key' => 'env-key']);

    SiteSetting::create([
        'key' => 'build_server_url',
        'value' => 'http://site-settings-server:9090',
    ]);
    SiteSetting::create([
        'key' => 'build_server_api_key',
        'value' => Crypt::encryptString('site-settings-key'),
        'type' => 'encrypted',
    ]);

    Http::fake([
        'site-settings-server:9090/*' => Http::response([
            'success' => true,
            'files' => [
                'css' => ['content' => base64_encode('.test{}')],
                'js' => ['content' => base64_encode('//test')],
            ],
        ]),
    ]);

    $service = app(AssetBuildService::class);
    $result = $service->build();

    // Should have called the site-settings URL, not the env one
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'site-settings-server:9090');
    });
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'env-server:8080');
    });
});

it('falls back to env config when site settings are empty', function () {
    config(['services.build_server.url' => 'http://env-server:8080']);
    config(['services.build_server.api_key' => 'env-key']);

    // No site settings set — should fall back to env

    Http::fake([
        'env-server:8080/*' => Http::response([
            'success' => true,
            'files' => [
                'css' => ['content' => base64_encode('.test{}')],
                'js' => ['content' => base64_encode('//test')],
            ],
        ]),
    ]);

    $service = app(AssetBuildService::class);
    $result = $service->build();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'env-server:8080');
    });
});

// ── Dashboard widget: build server status ──

it('returns not_configured when no build server url is set', function () {
    config(['services.build_server.url' => null]);

    $widget = new DashboardIntegrationStatusWidget();
    expect($widget->getBuildServerStatus())->toBe('not_configured');
});

it('returns connected when build server health check succeeds', function () {
    config(['services.build_server.url' => 'http://build.test:8080']);
    config(['services.build_server.api_key' => 'test-key']);

    Http::fake([
        'build.test:8080/health' => Http::response(['status' => 'ok'], 200),
    ]);

    $widget = new DashboardIntegrationStatusWidget();
    expect($widget->getBuildServerStatus())->toBe('connected');
});

it('returns unreachable when build server health check fails', function () {
    config(['services.build_server.url' => 'http://build.test:8080']);
    config(['services.build_server.api_key' => 'test-key']);

    Http::fake([
        'build.test:8080/health' => Http::response([], 500),
    ]);

    $widget = new DashboardIntegrationStatusWidget();
    expect($widget->getBuildServerStatus())->toBe('unreachable');
});

it('returns unreachable when build server is not reachable', function () {
    config(['services.build_server.url' => 'http://localhost:19999']);

    $widget = new DashboardIntegrationStatusWidget();
    expect($widget->getBuildServerStatus())->toBe('unreachable');
});
