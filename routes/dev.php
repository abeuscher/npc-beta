<?php

use App\Http\Controllers\Dev\WidgetDemoController;
use App\Http\Middleware\DevRoutesMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(DevRoutesMiddleware::class)->group(function () {
    Route::get('/dev/widgets/{handle}', [WidgetDemoController::class, 'show'])
        ->name('dev.widgets.show');
});
