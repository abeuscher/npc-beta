<?php

use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    User::factory()->create();

    if (! Template::page()->where('is_default', true)->exists()) {
        Template::create(['name' => 'Default', 'type' => 'page', 'is_default' => true]);
    }
});

function enterDemoMode(): void
{
    app()->instance('env', 'demo');
    // The `demo` role is only seeded in demo mode (session 370 gate); re-run the
    // idempotent PermissionSeeder now that the environment reports demo so the
    // role exists for /demo/enter's syncRoles(['demo']) and the assertions below.
    (new \Database\Seeders\PermissionSeeder())->run();
}

it('imports demo.json with zero importer warnings and publishes the split page', function () {
    $bundle = json_decode(
        file_get_contents(base_path('tests/Fixtures/demo.json')),
        true
    );

    $log = new ImportLog();
    app(ContentImporter::class)->import($bundle, $log);

    expect($log->hasWarnings())->toBeFalse();

    $page = Page::where('slug', 'demo')->first();
    expect($page)->not->toBeNull();
    expect($page->status)->toBe('published');

    $layout = $page->layouts()->first();
    expect($layout)->not->toBeNull();
    expect($layout->display)->toBe('grid');
    expect((int) $layout->columns)->toBe(2);

    $cells = $layout->widgets()->get();
    expect($cells)->toHaveCount(2);
    expect($cells->pluck('widget_type_id')->unique())->toHaveCount(1);
});

it('demo server: /demo/enter creates and authenticates the shared Demo User, redirecting into the admin panel', function () {
    enterDemoMode();
    expect(isDemoMode())->toBeTrue();

    $response = $this->get('/demo/enter');

    $response->assertRedirect(filament()->getPanel('admin')->getUrl());

    $this->assertAuthenticated();
    $demo = User::where('email', 'demo@demo.local')->first();
    expect($demo)->not->toBeNull();
    expect($demo->is_active)->toBeTrue();
    expect($demo->hasRole('demo'))->toBeTrue();
    expect(auth()->id())->toBe($demo->id);
});

it('demo server: a second visit reuses the one shared Demo User row', function () {
    enterDemoMode();

    $this->get('/demo/enter');
    $this->get('/demo/enter');

    expect(User::where('email', 'demo@demo.local')->count())->toBe(1);
});

it('demo server: the per-IP throttle returns 429 once the per-minute limit is exceeded', function () {
    enterDemoMode();

    // Reset the in-memory guard between calls so every request resolves
    // unauthenticated at throttle time — same-process tests otherwise cache
    // the logged-in user and split the limiter key (IP vs user id).
    $statuses = [];
    for ($i = 0; $i < 14; $i++) {
        $statuses[] = $this->get('/demo/enter')->getStatusCode();
        $this->app['auth']->forgetGuards();
    }

    // The first 10 entries succeed — the limit is 10/min, not tighter…
    expect(array_slice($statuses, 0, 10))->each->toBe(302);
    // …and the cap is enforced — a 429 appears within the window.
    expect($statuses)->toContain(429);
});

it('demo server: the login page shows a "Re-enter the demo" button pointing at demo.enter', function () {
    enterDemoMode();

    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('Re-enter the demo');
    $response->assertSee(route('demo.enter'), false);
});

it('non-demo install: the login page has no demo re-enter button', function () {
    expect(isDemoMode())->toBeFalse();

    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertDontSee('Re-enter the demo');
});

it('non-demo install: /demo/enter is inert — 404, no authentication, no Demo User row', function () {
    expect(isDemoMode())->toBeFalse();

    $this->get('/demo/enter')->assertStatus(404);

    $this->assertGuest();
    expect(User::where('email', 'demo@demo.local')->exists())->toBeFalse();
});

it('the demo role grants product-feel surfaces but denies settings/secrets/user-management and is never super_admin', function () {
    // The `demo` role is only seeded in demo mode (session 370 gate).
    enterDemoMode();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('demo');

    // Product-feel surfaces a prospect needs — granted.
    expect($user->can('view_any_page'))->toBeTrue();
    expect($user->can('create_contact'))->toBeTrue();
    expect($user->can('view_any_form_submission'))->toBeTrue();

    // Binding deny-list — never granted.
    expect($user->can('view_any_user'))->toBeFalse();
    expect($user->can('manage_mail_settings'))->toBeFalse();
    expect($user->can('manage_email_templates'))->toBeFalse();
    expect($user->can('manage_financial_settings'))->toBeFalse();
    expect($user->can('edit_theme_scss'))->toBeFalse();
    expect($user->can('manage_routing_prefixes'))->toBeFalse();
    expect($user->can('manage_cms_settings'))->toBeFalse();

    // Never the policy-bypassing role.
    expect($user->hasRole('super_admin'))->toBeFalse();
    expect($user->isSuperAdmin())->toBeFalse();
});
