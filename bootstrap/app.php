<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\PublicDevAuth::class);

        // Perimeter security headers (session 370, Security S1). Appended to the
        // `web` group so public pages + the member portal carry the enforced CSP
        // and header baseline. The admin panel registers it separately in
        // AdminPanelProvider (its own middleware stack); the `api` group is
        // deliberately excluded so the FM /api/* contract surface stays untouched.
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);

        // Client-billing suspension gate (contract v2.6.0), public surface.
        // Prepended to the `web` group so `site_off` short-circuits the public
        // site + member portal to a 503 maintenance notice before any other work.
        // `admin_locked` is a no-op here (public + portal stay up); the admin
        // surface is gated separately in AdminPanelProvider's base stack. The
        // `api` group is deliberately untouched, so the FM /api/* endpoints stay
        // up under every state. Absent flag = none = no-op.
        $middleware->prependToGroup('web', \App\Http\Middleware\EnforceSuspensionState::class . ':' . \App\Http\Middleware\EnforceSuspensionState::SURFACE_PUBLIC);

        $middleware->validateCsrfTokens(except: [
            '/webhooks/*',
        ]);

        $middleware->alias([
            'portal.auth' => \App\Http\Middleware\PortalAuthenticate::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run')->daily()->at('01:30');
        $schedule->command('exports:clean')->daily()->at('02:00');
        $schedule->command('media-library:clean')->daily()->onOneServer()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
