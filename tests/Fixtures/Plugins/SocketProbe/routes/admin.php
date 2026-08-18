<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Socket-probe fixture routes
|--------------------------------------------------------------------------
|
| Pulled into the admin panel's route group by AdminPanelProvider via the
| plugin admin registry. Core wraps the pull in Filament's Authenticate
| middleware — this file cannot opt out of panel auth.
|
*/

Route::get('/socket-probe/ping', fn () => response()->json(['pong' => true]))
    ->name('socket-probe.ping');
