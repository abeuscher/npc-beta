<?php

use App\Http\Middleware\DevRoutesMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders a widget demo page for a known handle in non-production', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/text_block');

    $response->assertOk();
});

it('returns 404 for an unknown widget handle', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/not-a-real-handle');

    $response->assertNotFound();
});

it('renders a preset variant demo page for a valid preset handle', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $preset = collect(app(\App\Services\WidgetRegistry::class)->find('hero')->presets())->first();

    $response = $this->get("/dev/widgets/hero/presets/{$preset['handle']}");

    $response->assertOk();
});

it('returns 404 for an unknown preset handle', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/hero/presets/not-a-real-preset');

    $response->assertNotFound();
});

it('returns 404 for the preset route when the widget is unknown', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/not-a-widget/presets/bold-statement');

    $response->assertNotFound();
});

it('renders a portal widget demo with its demoContext member authenticated', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/portal_account_dashboard');

    $response->assertOk();
    // The dashboard greets the stand-in contact and prints the portal email —
    // both only render when auth('portal')->user() resolves the seeded member.
    $response->assertSee('Jordan');
    $response->assertSee(\Database\Seeders\DemoPortalMemberSeeder::ACCOUNT_EMAIL);
});

it('renders the portal event-registrations widget against the demo event row', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $response = $this->get('/dev/widgets/portal_event_registrations');

    $response->assertOk();
    // A real registration row resolves through the authenticated member.
    $response->assertSee('Annual Community Gala');
    $response->assertSee('Registered');
});

it('forgets the demoContext member after the render, leaving the portal guard guest', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $this->get('/dev/widgets/portal_account_dashboard')->assertOk();

    expect(auth('portal')->check())->toBeFalse();
});

it('DevRoutesMiddleware aborts with 404 in the production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $middleware = new DevRoutesMiddleware();

    expect(fn () => $middleware->handle(Request::create('/dev/widgets/text_block'), fn ($r) => response('ok')))
        ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('DevRoutesMiddleware passes through in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $middleware = new DevRoutesMiddleware();

    $response = $middleware->handle(Request::create('/dev/widgets/text_block'), fn ($r) => response('ok'));

    expect($response->getContent())->toBe('ok');
});
