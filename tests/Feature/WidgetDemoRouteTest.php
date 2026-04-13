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
